<?php

class WebmappUpdateTrackMetadataJob extends WebmappUpdateTrackJob
{
    /**
     * WebmappUpdateTrackMetadataJob constructor.
     * @param $instanceUrl string containing the instance url
     * @param $params string containing an encoded JSON with the track ID
     * @param false $verbose
     */
    public function __construct($instanceUrl, $params, $verbose = false)
    {
        parent::__construct($instanceUrl, $params, $verbose, "update_track_metadata");
    }

    protected function process()
    {
        $id = intval($this->params['id']);

        try {
            // Load track from be
            if ($this->verbose) {
                $this->_verbose("Loading track from {$this->wp->getApiTrack($id)}");
            }
            $track = new WebmappTrackFeature($this->wp->getApiTrack($id));
            $track = $this->_removeGeometryMetadata($track);

            $metadataJson = $track->getProperties();

            $json = null;
            // Merge current geometry computed properties
            if (file_exists("{$this->aProject->getRoot()}/geojson/{$id}.geojson")) {
                if ($this->verbose) {
                    $this->_verbose("Using geometry from {$this->aProject->getRoot()}/geojson/{$id}.geojson");
                }
                $currentGeojson = json_decode(file_get_contents("{$this->aProject->getRoot()}/geojson/{$id}.geojson"), true);
                if (isset($currentGeojson["properties"])) {
                    $currentMetadata = $currentGeojson["properties"];

                    foreach (GEOMETRY_METADATA_PROPERTIES as $key) {
                        if (array_key_exists($key, $currentMetadata)) {
                            $metadataJson[$key] = $currentMetadata[$key];
                        }
                    }
                }

                $currentGeojson["properties"] = $metadataJson;
                $json = $currentGeojson;
            } else {
                if ($this->verbose) {
                    $this->_verbose("Using default geometry");
                }
                $json = json_decode($track->getJson(), true);
            }

            if ($this->verbose) {
                $this->_verbose("Writing track to {$this->aProject->getRoot()}/geojson/{$id}.geojson...");
            }
            file_put_contents("{$this->aProject->getRoot()}/geojson/{$id}.geojson", json_encode($json));

            $this->_setTaxonomies("track", $json);
            $this->_updateKProjects("track", $id, $track->getJson());

            $this->_updateRelatedRoutes($id);
        } catch (WebmappExceptionHttpRequest $e) {
            throw new WebmappExceptionHttpRequest("The instance $this->instanceUrl is unreachable or the track with id {$id} does not exists");
        } catch (Exception $e) {
            throw new WebmappException("An unknown error occurred: " . json_encode($e));
        }
    }
}