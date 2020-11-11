<?php

define("GEOMETRY_METADATA_PROPERTIES", ['osmid', 'computed', 'distance', 'ascent', 'descent', 'ele:from', 'ele:to', 'ele:min', 'ele:max', 'bbox', 'bbox_metric']);

class WebmappUpdateTrackJob extends WebmappAbstractJob
{
    protected $skipRouteCheck;

    /**
     * WebmappUpdateTrackJob constructor.
     * @param string $instanceUrl containing the instance url
     * @param string $params containing an encoded JSON with the track ID
     * @param false $verbose
     * @param string $type the type, default "update_track"
     */
    public function __construct(string $instanceUrl, string $params, bool $verbose = false, string $type = "update_track")
    {
        parent::__construct($type, $instanceUrl, $params, $verbose);
        $this->skipRouteCheck = array_key_exists("skipRouteCheck", $this->params) ? boolval($this->params["skipRouteCheck"]) : false;
    }

    protected function process()
    {
        $id = intval($this->params['id']);

        try {
            // Load track from be
            if ($this->verbose) {
                WebmappUtils::verbose("Loading track from {$this->wp->getApiTrack($id)}");
            }
            $track = new WebmappTrackFeature($this->wp->getApiTrack($id));
            $track = $this->_addMetadataToTrack($track);
            $track = $this->_addGeometryToTrack($track);

            if ($this->verbose) {
                WebmappUtils::verbose("Writing track to {$this->aProject->getRoot()}/geojson/{$id}.geojson");
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
            throw new WebmappExceptionHttpRequest("The instance $this->instanceUrl is unreachable or the track with id {$id} does not exists");
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
            WebmappUtils::verbose("Writing to postgis");
        }
        if ($track->hasGeometry()) {
            $track->writeToPostGis();
            if ($track->getGeometryType() === 'LineString') {
                if ($this->verbose) {
                    WebmappUtils::verbose("Adding 3D and computed properties");
                }
                $track->setComputedProperties2();

                // TODO: From params
//            $track_properties = $this->getCustomProperties("track");
//            if (isset($track_properties) && is_array($track_properties)) {
//                $track->mapCustomProperties($track_properties);
//            }
                if ($this->verbose) {
                    WebmappUtils::verbose("Adding bounding box");
                }
                $track->addBBox();
//                if ($this->verbose) {
//                    WebmappUtils::verbose("Generating track images");
//                }
//                $track->generateAllImages('', $this->track_path);
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
                WebmappUtils::warning("The track is a MultiLineString. Elevation is not supported");
            }
        } catch (WebmappExceptionHoquRequest|WebmappExceptionHttpRequest $e) {
            WebmappUtils::warning("An error occurred creating a new generate_elevation_chart_image job: " . $e->getMessage());
        }

        return $track;
    }

    /**
     * Perform all the operations needed to generate all the track metadata
     *
     * @param WebmappTrackFeature $track
     * @return WebmappTrackFeature
     */
    protected function _addMetadataToTrack(WebmappTrackFeature $track)
    {
        foreach (GEOMETRY_METADATA_PROPERTIES as $property) {
            if ($track->hasProperty($property)) {
                $track->removeProperty($property);
            }
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
                WebmappUtils::warning("An error occurred creating a new generate_elevation_chart_image job: " . $e->getMessage());
                return;
            }
            if (curl_getinfo($ch, CURLINFO_HTTP_CODE) >= 400) {
                WebmappUtils::warning("The api {$this->instanceUrl}/wp-json/webmapp/v1/track/related_routes/{$id} seems unreachable: " . curl_error($ch));
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
                                WebmappUtils::warning($e->getMessage());
                            }
                        }
                    }
                } catch (WebmappExceptionHoquRequest $e) {
                    WebmappUtils::warning($e->getMessage());
                }
            }
        }
    }
}