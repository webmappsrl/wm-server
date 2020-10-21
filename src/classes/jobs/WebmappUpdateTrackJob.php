<?php

define("GEOMETRY_METADATA_PROPERTIES", ['computed', 'distance', 'ascent', 'descent', 'ele:from', 'ele:to', 'ele:min', 'ele:max', 'bbox', 'bbox_metric']);

class WebmappUpdateTrackJob extends WebmappAbstractJob
{
    /**
     * WebmappUpdateTrackJob constructor.
     * @param string $instanceUrl containing the instance url
     * @param string $params containing an encoded JSON with the track ID
     * @param false $verbose
     * @param string $type the type, default "update_track"
     */
    public function __construct($instanceUrl, $params, $verbose = false, $type = "update_track")
    {
        parent::__construct($type, $instanceUrl, $params, $verbose);
    }

    protected function process()
    {
        $id = intval($this->params['id']);

        try {
            // Load track from be
            if ($this->verbose) {
                WebmappUtils::verbose("Loading track from {$this->instanceUrl}/wp-json/wp/v2/track/{$id}");
            }
            $track = new WebmappTrackFeature("$this->instanceUrl/wp-json/wp/v2/track/{$id}");
            $track = $this->_addMetadataToTrack($track);
            $track = $this->_addGeometryToTrack($track);

            if ($this->verbose) {
                WebmappUtils::verbose("Writing track to {$this->aProject->getRoot()}/geojson/{$id}.geojson");
            }
            file_put_contents("{$this->aProject->getRoot()}/geojson/{$id}.geojson", $track->getJson());

            $taxonomies = $track->hasProperty("taxonomy") && is_array($track->getProperty("taxonomy")) ? $track->getProperty("taxonomy") : [];
            $this->_setTaxonomies($id, $taxonomies, "track");
        } catch (WebmappExceptionFeaturesNoGeometry $e) {
            throw new WebmappExceptionHttpRequest("The track {$id} is missing the geometry");
        } catch (WebmappExceptionGeoJsonBadGeomType $e) {
            throw new WebmappExceptionHttpRequest("The track {$id} Has a wrong geometry type: " . $e->getMessage());
        } catch (WebmappExceptionHttpRequest $e) {
            throw new WebmappExceptionHttpRequest("The instance $this->instanceUrl is unreachable or the poi with id {$id} does not exists");
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
            if ($track->getGeometryType() == 'LineString') {
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
            }
        } else {
            throw new WebmappExceptionFeaturesNoGeometry("Track {$track->getId()} is missing the geometry");
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
}