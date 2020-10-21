<?php

class WebmappUpdateTrackMetadataJob extends WebmappAbstractJob
{
    /**
     * WebmappUpdateTrackMetadataJob constructor.
     * @param $instanceUrl string containing the instance url
     * @param $params string containing an encoded JSON with the track ID
     * @param false $verbose
     */
    public function __construct($instanceUrl, $params, $verbose = false)
    {
        parent::__construct("update_track_metadata", $instanceUrl, $params, $verbose);
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
                WebmappUtils::verbose("Loading track from {$this->instanceUrl}/wp-json/wp/v2/track/{$id}...");
            }
            $track = new WebmappTrackFeature("$this->instanceUrl/wp-json/wp/v2/track/{$id}");
            $metadataJson = json_decode($track->getJson(), true)["properties"];
            $geometryMetadataProperties = ['computed', 'distance', 'ascent', 'descent', 'ele:from', 'ele:to', 'ele:min', 'ele:max', 'bbox', 'bbox_metric'];
            foreach ($geometryMetadataProperties as $property) {
                if (isset($metadataJson[$property])) {
                    unset($metadataJson[$property]);
                }
            }

            $json = null;
            // Merge current geometry computed properties
            if (file_exists("{$this->aProject->getRoot()}/geojson/{$id}.geojson")) {
                if ($this->verbose) {
                    WebmappUtils::verbose("Using geometry from {$this->aProject->getRoot()}/geojson/{$id}.geojson");
                }
                $currentGeojson = json_decode(file_get_contents("{$this->aProject->getRoot()}/geojson/{$id}.geojson"), true);
                if (isset($currentGeojson["properties"])) {
                    $currentMetadata = $currentGeojson["properties"];

                    foreach ($geometryMetadataProperties as $key) {
                        if (array_key_exists($key, $currentMetadata)) {
                            $metadataJson[$key] = $currentMetadata[$key];
                        }
                    }
                }

                $currentGeojson["properties"] = $metadataJson;
                $json = $currentGeojson;
            } else {
                if ($this->verbose) {
                    WebmappUtils::verbose("Using default geometry");
                }
                $json = json_decode($track->getJson(), true);
            }

            if ($this->verbose) {
                WebmappUtils::verbose("Writing track to {$this->aProject->getRoot()}/geojson/{$id}.geojson...");
            }
            file_put_contents("{$this->aProject->getRoot()}/geojson/{$id}.geojson", json_encode($json));
            
            $taxonomies = isset($json["properties"]) && isset($json["properties"]["taxonomy"]) ? $json["properties"]["taxonomy"] : [];
            $this->_setTaxonomies($id, $taxonomies, "track");
        } catch (WebmappExceptionPOINoCoodinates $e) {
            throw new WebmappExceptionPOINoCoodinates("The poi with id {$id} is missing the coordinates");
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