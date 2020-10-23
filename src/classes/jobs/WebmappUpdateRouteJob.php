<?php

class WebmappUpdateRouteJob extends WebmappAbstractJob
{
    /**
     * WebmappUpdateRouteJob constructor.
     *
     * @param $instanceUrl string containing the instance url
     * @param $params string containing an encoded JSON with the poi ID
     * @param false $verbose
     */
    public function __construct($instanceUrl, $params, $verbose = false)
    {
        parent::__construct("update_route", $instanceUrl, $params, $verbose);
    }

    protected function process()
    {
        $id = intval($this->params['id']);

        try {
            // Load poi from be
            if ($this->verbose) {
                WebmappUtils::verbose("Loading route from {$this->wp->getApiRoute($id)}");
            }
            $route = new WebmappRoute("{$this->wp->getApiRoute($id)}", '', true);
            $apiTracks = $route->getApiTracks();
            $tracks = [];


            // Make sure all the tracks are up to date
            foreach ($apiTracks as $track) {
                $currentDate = 1;
                $generatedDate = 0;
                if (file_exists("{$this->aProject->getRoot()}/geojson/{$track['ID']}.geojson")) {
                    $currentDate = strtotime($track["post_modified"]);
                    $file = json_decode(file_get_contents("{$this->aProject->getRoot()}/geojson/{$track['ID']}.geojson"), true);
                    $generatedDate = strtotime($file["properties"]["modified"]);
                }
                if ($currentDate > $generatedDate) {
                    if ($this->verbose) {
                        WebmappUtils::verbose("Updating track {$track['ID']}");
                    }
                    $params = [
                        "id" => $track["ID"]
                    ];
                    $job = new WebmappUpdateTrackJob($this->instanceUrl, json_encode($params), $this->verbose);
                    $job->run();
                }

                $tracks[] = json_decode(file_get_contents("{$this->aProject->getRoot()}/geojson/{$track['ID']}.geojson"), true);
            }

            $route->buildPropertiesAndFeaturesFromTracksGeojson($tracks);

            file_put_contents("{$this->aProject->getRoot()}/geojson/{$id}.geojson", $route->getJson());
            $json = json_decode($route->getPoiJson(), true);

            $taxonomies = isset($json["properties"]) && isset($json["properties"]["taxonomy"]) ? $json["properties"]["taxonomy"] : [];
            $this->_setTaxonomies($id, $taxonomies, "route");
        } catch (WebmappExceptionPOINoCoodinates $e) {
            throw new WebmappExceptionPOINoCoodinates("The poi with id {$id} is missing the coordinates");
        } catch (WebmappExceptionHttpRequest $e) {
            throw new WebmappExceptionHttpRequest("The instance $this->instanceUrl is unreachable or the route with id {$id} does not exists");
        } catch (Exception $e) {
            throw new WebmappException("An unknown error occurred: " . json_encode($e));
        }
    }
}