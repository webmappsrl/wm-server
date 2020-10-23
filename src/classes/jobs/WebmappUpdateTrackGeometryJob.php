<?php

class WebmappUpdateTrackGeometryJob extends WebmappUpdateTrackJob
{
    /**
     * WebmappUpdateTrackGeometryJob constructor.
     * @param $instanceUrl string containing the instance url
     * @param $params string containing an encoded JSON with the track ID
     * @param false $verbose
     */
    public function __construct($instanceUrl, $params, $verbose = false)
    {
        parent::__construct($instanceUrl, $params, $verbose, "update_track_geometry");
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
            $track = $this->_addGeometryToTrack($track);
            $json = json_decode($track->getJson(), true);

            if (file_exists("{$this->aProject->getRoot()}/geojson/{$id}.geojson")) {
                if ($this->verbose) {
                    WebmappUtils::verbose("Using metadata from {$this->aProject->getRoot()}/geojson/{$id}.geojson");
                }
                $currentMetadata = json_decode(file_get_contents("{$this->aProject->getRoot()}/geojson/{$id}.geojson"), true)["properties"];

                foreach (GEOMETRY_METADATA_PROPERTIES as $property) {
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

            $this->_updateKProjects("track", $id, json_encode($json));
        } catch (WebmappExceptionFeaturesNoGeometry $e) {
            throw new WebmappExceptionHttpRequest("The track {$id} is missing the geometry");
        } catch (WebmappExceptionGeoJsonBadGeomType $e) {
            throw new WebmappExceptionHttpRequest("The track {$id} Has a wrong geometry type: " . $e->getMessage());
        } catch (WebmappExceptionHttpRequest $e) {
            throw new WebmappExceptionHttpRequest("The instance $this->instanceUrl is unreachable or the track with id {$id} does not exists");
        } catch (Exception $e) {
            throw new WebmappException("An unknown error occurred: " . json_encode($e));
        }
    }
}