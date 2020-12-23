<?php

define("GEOMETRY_METADATA_PROPERTIES", [
    'osmid',
    'computed',
    'distance',
    'ascent',
    'descent',
    'ele:from',
    'ele:to',
    'ele:min',
    'ele:max',
    'duration:forward',
    'duration:backward',
    'bbox',
    'bbox_metric',
    'id_pois',
    "related"
]);

class WebmappUpdateTrackJob extends WebmappAbstractJob
{
    protected $skipRouteCheck;

    /**
     * WebmappUpdateTrackJob constructor.
     * @param string $instanceUrl containing the instance url
     * @param string $params containing an encoded JSON with the track ID
     * @param false $verbose
     * @param string $type the type, default "update_track"
     * @throws WebmappExceptionNoDirectory
     */
    public function __construct(string $instanceUrl, string $params, bool $verbose = false, string $type = "update_track")
    {
        parent::__construct($type, $instanceUrl, $params, $verbose);
        $this->skipRouteCheck = array_key_exists("skipRouteCheck", $this->params) ? boolval($this->params["skipRouteCheck"]) : false;
    }

    protected function process()
    {
        $id = intval($this->params['id']);
        if (is_null($id)) {
            throw new WebmappExceptionParameterError("The id must be set, null given");
            return;
        }
        $updateOsmGeometry = isset($this->params['update_geometry']) && $this->params['update_geometry'] === true;

        try {
            // Load track from be
            if ($this->verbose) {
                $this->_verbose("Loading track from {$this->wp->getApiTrack($id)}");
            }
            $track = new WebmappTrackFeature($this->wp->getApiTrack($id), false);
            if ($track->hasGeometry() &&
                ($updateOsmGeometry ||
                    !$track->hasProperty('osmid') ||
                    !file_exists("{$this->aProject->getRoot()}/geojson/{$id}.geojson"))) {
                $track = $this->_addGeometryToTrack($track);
            } else {
                if ($this->verbose && !$updateOsmGeometry && $track->hasProperty('osmid')) {
                    $this->_verbose("Skipping geometry due to job parameters");
                }
            }
            $track = $this->_setCustomProperties($track);

            if (!$updateOsmGeometry &&
                $track->hasProperty('osmid') &&
                file_exists("{$this->aProject->getRoot()}/geojson/{$id}.geojson")) {
                if ($this->verbose) {
                    $this->_verbose("Skipping geometry generation. Using geometry from {$this->aProject->getRoot()}/geojson/{$id}.geojson");
                }
                $currentGeojson = json_decode(file_get_contents("{$this->aProject->getRoot()}/geojson/{$id}.geojson"), true);
                if (isset($currentGeojson["properties"])) {
                    $currentMetadata = $currentGeojson["properties"];

                    foreach (GEOMETRY_METADATA_PROPERTIES as $key) {
                        if (array_key_exists($key, $currentMetadata)) {
                            $track->setProperty($key, $currentMetadata, $key);
                        }
                    }
                }
                if (isset($currentGeojson["geometry"])) {
                    $track->setGeometry($currentGeojson["geometry"]);
                }
            }

            $track->setProperty("modified", $this->_getPostLastModified($id, strtotime($track->getProperty("modified"))));

            if ($this->verbose) {
                $this->_verbose("Applying the mapping");
            }
            $this->_applyMapping($track, "track");
            if ($track->hasProperty('osmid')) {
                if (!$track->hasRelation())
                    $track->setRelation($track->getProperty('osmid'));
                $this->_applyMapping($track, "osm", $track->getRelation());
            }

            if ($this->verbose) {
                $this->_verbose("Writing track to {$this->aProject->getRoot()}/geojson/{$id}.geojson");
            }
            file_put_contents("{$this->aProject->getRoot()}/geojson/{$id}.geojson", $track->getJson());

            $this->_setTaxonomies("track", json_decode($track->getJson(), true));

            $this->_updateKProjects("track", $id, $track->getJson());

            $this->_updateRelatedRoutes($id);
        } catch (WebmappExceptionFeaturesNoGeometry $e) {
            throw new WebmappExceptionHttpRequest("The track {$id} is missing the geometry");
        } catch (WebmappExceptionGeoJsonBadGeomType $e) {
            throw new WebmappExceptionHttpRequest("The track {$id} has an invalid geometry type: " . $e->getMessage());
        } catch (WebmappExceptionHttpRequest $e) {
            throw new WebmappExceptionHttpRequest("The instance $this->instanceUrl is unreachable or the track with id {$id} does not exists: " . $e->getMessage());
        } catch (Exception $e) {
            throw new WebmappException("An unknown error occurred: " . json_encode($e));
        }
    }

    /**
     * Perform all the operations needed to generate the full geometry
     *
     * @param WebmappTrackFeature $track
     * @return WebmappTrackFeature
     * @throws WebmappExceptionFeaturesNoGeometry
     * @throws WebmappExceptionGeoJsonBadGeomType
     */
    protected function _addGeometryToTrack(WebmappTrackFeature $track)
    {
        if ($this->verbose) {
            $this->_verbose("Writing to postgis");
        }
        if ($track->hasGeometry()) {
            $track->writeToPostGis();
            if ($track->getGeometryType() === 'LineString') {
                if ($this->verbose) {
                    $this->_verbose("Adding 3D and computed properties");
                }
                $track->setComputedProperties2();

                // TODO: From params
                if ($this->verbose) {
                    $this->_verbose("Adding bounding box");
                }
                $track->addBBox();
                $trackPath = "{$this->aProject->getRoot()}/track";
                if (!file_exists($trackPath)) {
                    system("mkdir -p {$trackPath}");
                }

                $track = $this->_orderRelatedPoi($track);

                if ($this->verbose) {
                    $this->_verbose("Writing related poi for roadbook");
                }
                $track->writeRBRelatedPoi($trackPath);
                if ($this->verbose) {
                    $this->_verbose("Writing roadbook images");
                }
                $track->generateAllImages('', $trackPath);
                $track->generateLandscapeRBImages('', $trackPath);

                if ($this->verbose) {
                    $this->_verbose("Generating gpx");
                }
                $track->writeGPX($trackPath);
                if ($this->verbose) {
                    $this->_verbose("Generating kml");
                }
                $track->writeKML($trackPath);
            } else if ($track->getGeometryType() !== 'MultiLineString') {
                throw new WebmappExceptionGeoJsonBadGeomType("{$track->getGeometryType()} geometry type is not accepted");
            }
        } else {
            throw new WebmappExceptionFeaturesNoGeometry("Track {$track->getId()} is missing the geometry");
        }

        try {
            if ($track->getGeometryType() === 'LineString') {
                $this->_store("generate_elevation_chart_image", ["id" => $this->params["id"]]);
            } else if ($track->getGeometryType() === 'MultiLineString') {
                $this->_warning("The track is a MultiLineString. Elevation is not supported");
            }
        } catch (WebmappExceptionHoquRequest | WebmappExceptionHttpRequest $e) {
            $this->_warning("An error occurred creating a new generate_elevation_chart_image job: " . $e->getMessage());
        }

        return $track;
    }

    /**
     * Order the list of related pois based on the track geometry
     *
     * @param WebmappTrackFeature $track
     * @return WebmappTrackFeature
     */
    private function _orderRelatedPoi(WebmappTrackFeature $track)
    {
        if ($track->hasProperty("related")) {
            $related = $track->getProperty("related");
            if (isset($related) && isset($related["poi"]) && isset($related["poi"]["related"]) && is_array($related["poi"]["related"]) && count($related["poi"]["related"]) > 0) {
                $pois = [];
                $poisIds = $related["poi"]["related"];
                foreach ($poisIds as $poiId) {
                    if (!file_exists("{$this->aProject->getRoot()}/geojson/$poiId.geojson")) {
                        $poiJob = new WebmappUpdatePoiJob($this->instanceUrl, json_encode(["id" => $poiId]), $this->verbose);
                        try {
                            $poiJob->run();
                        } catch (Exception $e) {
                            $this->_warning("Related poi $poiId cannot be generated: " . $e->getMessage());
                        }
                    }
                    if (file_exists("{$this->aProject->getRoot()}/geojson/$poiId.geojson")) {
                        $poi = json_decode(file_get_contents("{$this->aProject->getRoot()}/geojson/$poiId.geojson"), true);
                        $pois[] = $poi;
                    }
                }

                $track->orderRelatedPois($pois);
            }
        }

        return $track;
    }

    /**
     * Perform all the operations needed to generate all the track metadata
     *
     * @param WebmappTrackFeature $track
     * @return WebmappTrackFeature
     */
    protected function _removeGeometryMetadata(WebmappTrackFeature $track)
    {
        foreach (GEOMETRY_METADATA_PROPERTIES as $property) {
            if ($track->hasProperty($property)) {
                $track->removeProperty($property);
            }
        }

        return $track;
    }

    /**
     * Map the custom properties in the track
     *
     * @param WebmappTrackFeature $track
     * @return WebmappTrackFeature
     */
    protected function _setCustomProperties(WebmappTrackFeature $track)
    {
        if ($this->verbose) {
            $this->_verbose("Mapping custom properties");
        }
        $track_properties = $this->_getCustomProperties("track");
        if (isset($track_properties) && is_array($track_properties)) {
            $track->mapCustomProperties($track_properties);
        }

        return $track;
    }

    /**
     * Perform the store operation for all he related routes
     *
     * @param int $id the route id
     */
    protected function _updateRelatedRoutes(int $id)
    {
        if (!$this->skipRouteCheck) {
            $ch = $this->_getCurl("{$this->instanceUrl}/wp-json/webmapp/v1/track/related_routes/{$id}");
            $routes = null;
            try {
                $routes = curl_exec($ch);
            } catch (Exception $e) {
                $this->_warning("An error occurred creating a new generate_elevation_chart_image job: " . $e->getMessage());
                return;
            }
            if (curl_getinfo($ch, CURLINFO_HTTP_CODE) >= 400) {
                $this->_warning("The api {$this->instanceUrl}/wp-json/webmapp/v1/track/related_routes/{$id} seems unreachable: " . curl_error($ch));
                curl_close($ch);
            } else {
                try {
                    curl_close($ch);

                    $routes = json_decode($routes, true);

                    if (isset($routes) && is_array($routes) && array_key_exists("related_routes", $routes)) {
                        foreach ($routes["related_routes"] as $routeId) {
                            try {
                                $this->_store("update_route", ["id" => $routeId]);
                            } catch (WebmappExceptionHttpRequest $e) {
                                $this->_warning($e->getMessage());
                            }
                        }
                    }
                } catch (WebmappExceptionHoquRequest $e) {
                    $this->_warning($e->getMessage());
                }
            }
        }
    }
}