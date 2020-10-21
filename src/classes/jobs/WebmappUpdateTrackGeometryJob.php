<?php

class WebmappUpdateTrackGeometryJob extends WebmappAbstractJob
{
    /**
     * WebmappUpdateTrackMetadataJob constructor.
     * @param $instanceUrl string containing the instance url
     * @param $params string containing an encoded JSON with the track ID
     * @param false $verbose
     */
    public function __construct($instanceUrl, $params, $verbose = false)
    {
        parent::__construct("update_track_geometry", $instanceUrl, $params, $verbose);
    }

    protected function process()
    {
        if ($this->verbose) {
            WebmappUtils::verbose("Running process...");
        }

        $id = intval($this->params['id']);

        try {
            // Load track from be
            if ($this->verbose) {
                WebmappUtils::verbose("Loading track from {$this->instanceUrl}/wp-json/wp/v2/track/{$id}");
            }
            $track = new WebmappTrackFeature("$this->instanceUrl/wp-json/wp/v2/track/{$id}");

            if ($this->verbose) {
                WebmappUtils::verbose("Writing to postgis");
            }
            $track->writeToPostGis();
            if ($track->getGeometryType() == 'LineString') {
                try {
//                if ($this->verbose) {
//                    WebmappUtils::verbose("Adding 3D");
//                }
//                $track->add3D();
                    if ($this->verbose) {
                        WebmappUtils::verbose("Adding 3D and computed properties");
                    }
                    $track->setComputedProperties2();

                    // TODO: From params
//                $track_properties = $this->getCustomProperties("track");
//                if (isset($track_properties) && is_array($track_properties)) {
//                    $track->mapCustomProperties($track_properties);
//                }
                    if ($this->verbose) {
                        WebmappUtils::verbose("Adding bounding box");
                    }
                    $track->addBBox();
//                if ($this->verbose) {
//                    WebmappUtils::verbose("Generating track images");
//                }
//                $track->generateAllImages('', $this->track_path);
                } catch (WebmappExceptionFeaturesNoGeometry $e) {
                    WebmappUtils::warning($e->getMessage());
                } catch (WebmappExceptionGeoJsonBadGeomType $e) {
                    WebmappUtils::warning($e->getMessage());
                }
            }

            $json = json_decode($track->getJson(), true);

            if (file_exists("{$this->aProject->getRoot()}/geojson/{$id}.geojson")) {
                if ($this->verbose) {
                    WebmappUtils::verbose("Using metadata from {$this->aProject->getRoot()}/geojson/{$id}.geojson");
                }
                $currentMetadata = json_decode(file_get_contents("{$this->aProject->getRoot()}/geojson/{$id}.geojson"), true)["properties"];
                $geometryMetadataProperties = ['computed', 'distance', 'ascent', 'descent', 'ele:from', 'ele:to', 'ele:min', 'ele:max', 'bbox', 'bbox_metric'];

                foreach ($geometryMetadataProperties as $property) {
                    if (array_key_exists($property, $json["properties"])) {
                        $currentMetadata[$property] = $json["properties"][$property];
                    } else if (array_key_exists($property, $currentMetadata)) {
                        unset($currentMetadata[$property]);
                    }
                }

                $json["properties"] = $currentMetadata;
            } else {
                if ($this->verbose) {
                    WebmappUtils::verbose("Using default metadata");
                }
                $json = json_decode($track->getJson(), true);

                if ($this->verbose) {
                    WebmappUtils::verbose("Generating taxonomies as it is the first geojson generation");
                }
                $taxonomies = isset($json["properties"]) && isset($json["properties"]["taxonomy"]) ? $json["properties"]["taxonomy"] : [];
                $this->_setTaxonomies($id, $taxonomies, "track");
            }

            if ($this->verbose) {
                WebmappUtils::verbose("Writing track to {$this->aProject->getRoot()}/geojson/{$id}.geojson");
            }
            file_put_contents("{$this->aProject->getRoot()}/geojson/{$id}.geojson", json_encode($json));
        } catch (WebmappExceptionHttpRequest $e) {
            throw new WebmappExceptionHttpRequest("The instance $this->instanceUrl is unreachable or the poi with id {$id} does not exists");
        } catch (Exception $e) {
            throw new WebmappException("An unknown error occurred: " . json_encode($e));
        }

        if ($this->verbose) {
            WebmappUtils::verbose("Process completed");
        }
    }
}