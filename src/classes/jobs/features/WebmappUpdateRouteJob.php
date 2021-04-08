<?php

class WebmappUpdateRouteJob extends WebmappAbstractJob {
    /**
     * WebmappUpdateRouteJob constructor.
     *
     * @param string $instanceUrl containing the instance url
     * @param string $params      containing an encoded JSON with the poi ID
     * @param false  $verbose
     *
     * @throws WebmappExceptionNoDirectory
     * @throws WebmappExceptionParameterError
     * @throws WebmappExceptionParameterMandatory
     */
    public function __construct(string $instanceUrl, string $params, bool $verbose = false) {
        parent::__construct("update_route", $instanceUrl, $params, $verbose);
    }

    /**
     * @throws WebmappExceptionHoquRequest
     * @throws WebmappExceptionHttpRequest
     * @throws WebmappExceptionNoDirectory
     * @throws WebmappExceptionParameterError
     * @throws WebmappExceptionParameterMandatory
     */
    protected function process() {
        // Load poi from be
        $this->_verbose("Loading route from {$this->wp->getApiRoute($this->id)}");
        $route = new WebmappRoute("{$this->wp->getApiRoute($this->id)}", '', true);
        $apiTracks = $route->getApiTracks();
        $tracks = [];

        // Make sure all the tracks are up to date
        if (is_array($apiTracks) && count($apiTracks) > 0) {
            foreach ($apiTracks as $track) {
                $currentDate = 1;
                $generatedDate = 0;
                $id = isset($track["ID"]) ? $track["ID"] : strval($track);
                $lastModified = isset($track["post_modified"]) ? strtotime($track["post_modified"]) : strtotime("01-01-2000");
                if (file_exists("{$this->aProject->getRoot()}/geojson/{$id}.geojson")) {
                    $currentDate = $this->_getPostLastModified($id, $lastModified);
                    $file = json_decode(file_get_contents("{$this->aProject->getRoot()}/geojson/{$id}.geojson"), true);
                    $generatedDate = strtotime($file["properties"]["modified"]);
                }
                if ($currentDate > $generatedDate) {
                    $this->_verbose("Updating track {$id}");
                    $params = [
                        "id" => $id,
                        "skipRouteCheck" => true
                    ];
                    $job = new WebmappUpdateTrackJob($this->instanceUrl, json_encode($params), $this->verbose);
                    $job->run();
                }

                $tracks[] = json_decode(file_get_contents("{$this->aProject->getRoot()}/geojson/{$id}.geojson"), true);
            }
        }

        $route->buildPropertiesAndFeaturesFromTracksGeojson($tracks);
        $route = $this->_setCustomProperties($route);
        $route->setProperty("modified", WebmappUtils::formatDate($this->_getPostLastModified($this->id, strtotime($route->getProperty("modified")))));

        file_put_contents("{$this->aProject->getRoot()}/geojson/{$this->id}.geojson", $route->getJson());
        $json = json_decode($route->getPoiJson(), true);

        // Route index handling
        $this->_updateRouteIndex(
            "{$this->aProject->getRoot()}/geojson/route_index.geojson",
            $this->id,
            $json
        );
        $this->_updateRouteIndex(
            "{$this->aProject->getRoot()}/geojson/full_geometry_route_index.geojson",
            $this->id,
            json_decode($route->getTrackJson(), true)
        );

        $this->_updateKRoutes($this->id, $route);
        $this->_setTaxonomies("route", $json);

        $this->_checkAudios(json_decode($route->getPoiJson(), true));
    }

    /**
     * Map the custom properties in the route
     *
     * @param WebmappRoute $route
     *
     * @return WebmappRoute
     */
    protected function _setCustomProperties(WebmappRoute $route): WebmappRoute {
        $this->_verbose("Mapping custom properties");
        $track_properties = $this->_getCustomProperties("track");
        if (isset($track_properties) && is_array($track_properties)) {
            $route->mapCustomProperties($track_properties);
        }

        return $route;
    }

    private function _filterKRoute(array $config, WebmappRoute $route): bool {
        $id = $route->getId();
        $result = false;
        if (isset($config["filters"]) && is_array($config["filters"])) {
            if (isset($config["filters"]["routes_id"]) || isset($config["filters"]["routes_taxonomy"])) {
                if (isset($config["filters"]["routes_id"])
                    && is_array($config["filters"]["routes_id"])
                    && in_array($id, $config["filters"]["routes_id"]))
                    $result = true;
                elseif (isset($config["filters"]["routes_taxonomy"])
                    && is_array($config["filters"]["routes_taxonomy"])) {
                    $taxonomies = $route->getTaxonomies();
                    $taxArray = [];
                    foreach ($taxonomies as $ids) {
                        array_push($taxArray, ...$ids);
                    }
                    $taxArray = array_values(array_unique($taxArray));

                    foreach ($taxArray as $taxId) {
                        if (in_array($taxId, $config["filters"]["routes_taxonomy"])) {
                            $result = true;
                            break;
                        }
                    }
                } elseif (
                    isset($config["filters"]["routes_id"])
                    && !is_array($config["filters"]["routes_id"])
                    && isset($config["filters"]["routes_taxonomy"])
                    && !is_array($config["filters"]["routes_taxonomy"]))
                    $result = true;
            } else
                $result = true;
        } else
            $result = true;

        return $result;
    }

    /**
     * Update the K roots
     *
     * @param int          $id    the route id
     * @param WebmappRoute $route the route
     *
     * @throws WebmappExceptionHoquRequest
     * @throws WebmappExceptionHttpRequest
     */
    private function _updateKRoutes(int $id, WebmappRoute $route) {
        if (count($this->kProjects) > 0) {
            $this->_verbose("Updating K projects...");
            foreach ($this->kProjects as $kProject) {
                $this->_verbose("  {$kProject->getRoot()}");
                $conf = $this->_getConfig($kProject->getRoot());
                if (isset($conf["multimap"]) && $conf["multimap"] === true) {
                    $this->_verbose("Updating route index files in single map k project");
                    if ($this->_filterKRoute($conf, $route)) {
                        $this->_updateRouteIndex(
                            "{$kProject->getRoot()}/routes/route_index.geojson",
                            $id,
                            json_decode($route->getPoiJson(), true)
                        );
                        $this->_updateRouteIndex(
                            "{$kProject->getRoot()}/routes/full_geometry_route_index.geojson",
                            $id,
                            json_decode($route->getTrackJson(), true)
                        );
                        $this->_updateKRouteDirectory($kProject, $id, $route);
                    } else {
                        $this->_updateRouteIndex(
                            "{$kProject->getRoot()}/routes/route_index.geojson",
                            $id
                        );
                        $this->_updateRouteIndex(
                            "{$kProject->getRoot()}/routes/full_geometry_route_index.geojson",
                            $id
                        );
                    }
                }
            }
        }
    }

    /**
     * Create or update the k route directory where the map.mbtiles will be
     *
     * @param WebmappProjectStructure $kProject the k root project
     * @param int                     $id       the route id
     * @param WebmappRoute            $route    the route
     *
     * @throws WebmappExceptionHoquRequest
     * @throws WebmappExceptionHttpRequest
     */
    private function _updateKRouteDirectory(WebmappProjectStructure $kProject, int $id, WebmappRoute $route) {
        if (file_exists("{$kProject->getRoot()}/routes/")) {
            if (!file_exists("{$kProject->getRoot()}/routes/$id/taxonomies")) {
                mkdir("{$kProject->getRoot()}/routes/$id/taxonomies", 0777, true);
            }

            $poisIds = [];
            $features = $route->getGeojsonTracks();
            $activities = [];
            $webmapp_categories = [];
            $bbox = null;
            $bbox_metric = null;

            foreach ($features as $track) {
                if (isset($track["properties"]["related"]["poi"]["related"]) && is_array($track["properties"]["related"]["poi"]["related"])) {
                    $poisIds = array_merge($poisIds, $track["properties"]["related"]["poi"]["related"]);
                }
                if (isset($track["properties"]["bbox"])) {
                    $trackBbox = explode(',', $track["properties"]["bbox"]);
                    foreach ($trackBbox as $key => $value) {
                        $trackBbox[$key] = floatval($value);
                    }
                    if (isset($bbox)) {
                        $bbox[0] = min($trackBbox[0], $bbox[0]);
                        $bbox[1] = min($trackBbox[1], $bbox[1]);
                        $bbox[2] = max($trackBbox[2], $bbox[2]);
                        $bbox[3] = max($trackBbox[3], $bbox[3]);
                    } else {
                        $bbox = $trackBbox;
                    }
                }
                if (isset($track["properties"]["bbox_metric"])) {
                    $trackBbox = explode(',', $track["properties"]["bbox_metric"]);
                    foreach ($trackBbox as $key => $value) {
                        $trackBbox[$key] = floatval($value);
                    }
                    if (isset($bbox_metric)) {
                        $bbox_metric[0] = min($trackBbox[0], $bbox_metric[0]);
                        $bbox_metric[1] = min($trackBbox[1], $bbox_metric[1]);
                        $bbox_metric[2] = max($trackBbox[2], $bbox_metric[2]);
                        $bbox_metric[3] = max($trackBbox[3], $bbox_metric[3]);
                    } else {
                        $bbox_metric = $trackBbox;
                    }
                }
            }

            $poisIds = array_unique($poisIds);
            $route->setProperty("bbox", $bbox);
            $route->setProperty("bbox_metric", $bbox_metric);

            foreach ($poisIds as $poiId) {
                if (file_exists("{$kProject->getRoot()}/geojson/{$poiId}.geojson"))
                    $features[] = json_decode(file_get_contents("{$kProject->getRoot()}/geojson/{$poiId}.geojson"), true);
            }

            foreach ($features as $feature) {
                if (isset($feature["properties"]["taxonomy"]["webmapp_category"]) &&
                    is_array($feature["properties"]["taxonomy"]["webmapp_category"]) &&
                    count($feature["properties"]["taxonomy"]["webmapp_category"]) > 0) {
                    foreach ($feature["properties"]["taxonomy"]["webmapp_category"] as $wcId) {
                        if (!isset($webmapp_categories[$wcId])) {
                            $webmapp_categories[$wcId] = [
                                "items" => [
                                    "poi" => []
                                ]
                            ];
                        }

                        $webmapp_categories[$wcId]["items"]["poi"][] = $feature["properties"]["id"];
                    }
                }
                if (isset($feature["properties"]["taxonomy"]["activity"]) &&
                    is_array($feature["properties"]["taxonomy"]["activity"]) &&
                    count($feature["properties"]["taxonomy"]["activity"]) > 0) {
                    foreach ($feature["properties"]["taxonomy"]["activity"] as $wcId) {
                        if (!isset($activities[$wcId])) {
                            $activities[$wcId] = [
                                "items" => [
                                    "track" => []
                                ]
                            ];
                        }

                        $activities[$wcId]["items"]["track"][] = $feature["properties"]["id"];
                    }
                }
            }

            $webmappCategoryUrl = "{$kProject->getRoot()}/routes/$id/taxonomies/webmapp_category.json";
            $this->_lockFile($webmappCategoryUrl);
            file_put_contents($webmappCategoryUrl, json_encode($webmapp_categories));
            $this->_unlockFile($webmappCategoryUrl);
            $activityUrl = "{$kProject->getRoot()}/routes/$id/taxonomies/activity.json";
            $this->_lockFile($activityUrl);
            file_put_contents($activityUrl, json_encode($activities));
            $this->_unlockFile($activityUrl);

            $newMapJson = [
                "maxZoom" => 16,
                "minZoom" => 8,
                "defZoom" => 9,
                "center" => [
                    ($bbox[0] + $bbox[2]) / 2,
                    ($bbox[1] + $bbox[3]) / 2
                ],
                "bbox" => $bbox
            ];
            $write = true;

            $mapJsonUrl = "{$kProject->getRoot()}/routes/$id/map.json";
            // Update map.json verifying if routes need to be updated
            if (file_exists($mapJsonUrl)) {
                $this->_lockFile($mapJsonUrl);
                $mapJson = json_decode(file_get_contents($mapJsonUrl), true);

                if (json_encode($mapJson["bbox"]) === json_encode($bbox))
                    $write = false;
            }

            if ($write) {
                file_put_contents($mapJsonUrl, json_encode($newMapJson));

                $this->_store("generate_mbtiles", [
                    "id" => $this->id
                ]);
            }
            $this->_unlockFile($mapJsonUrl);
        }
    }
}
