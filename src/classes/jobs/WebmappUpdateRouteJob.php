<?php

class WebmappUpdateRouteJob extends WebmappAbstractJob
{
    /**
     * WebmappUpdateRouteJob constructor.
     *
     * @param string $instanceUrl containing the instance url
     * @param string $params containing an encoded JSON with the poi ID
     * @param false $verbose
     */
    public function __construct(string $instanceUrl, string $params, bool $verbose = false)
    {
        parent::__construct("update_route", $instanceUrl, $params, $verbose);
    }

    protected function process()
    {
        $id = intval($this->params['id']);

        // Load poi from be
        if ($this->verbose) {
            $this->_verbose("Loading route from {$this->wp->getApiRoute($id)}");
        }
        $route = new WebmappRoute("{$this->wp->getApiRoute($id)}", '', true);
        $apiTracks = $route->getApiTracks();
        $tracks = [];

        // Make sure all the tracks are up to date
        if (is_array($apiTracks) && count($apiTracks) > 0) {
            foreach ($apiTracks as $track) {
                $currentDate = 1;
                $generatedDate = 0;
                if (file_exists("{$this->aProject->getRoot()}/geojson/{$track['ID']}.geojson")) {
                    $currentDate = $this->_getPostLastModified($track["ID"], strtotime($track["post_modified"]));
                    $file = json_decode(file_get_contents("{$this->aProject->getRoot()}/geojson/{$track['ID']}.geojson"), true);
                    $generatedDate = strtotime($file["properties"]["modified"]);
                }
                if ($currentDate > $generatedDate) {
                    if ($this->verbose) {
                        $this->_verbose("Updating track {$track['ID']}");
                    }
                    $params = [
                        "id" => $track["ID"],
                        "skipRouteCheck" => true
                    ];
                    $job = new WebmappUpdateTrackJob($this->instanceUrl, json_encode($params), $this->verbose);
                    $job->run();
                }

                $tracks[] = json_decode(file_get_contents("{$this->aProject->getRoot()}/geojson/{$track['ID']}.geojson"), true);
            }
        }

        $route->buildPropertiesAndFeaturesFromTracksGeojson($tracks);
        $route = $this->_setCustomProperties($route);
        $route->setProperty("modified", $this->_getPostLastModified($id, strtotime($route->getProperty("modified"))));

        file_put_contents("{$this->aProject->getRoot()}/geojson/{$id}.geojson", $route->getJson());
        $json = json_decode($route->getPoiJson(), true);

        // Route index handling
        $this->_updateRouteIndex(
            "{$this->aProject->getRoot()}/geojson/route_index.geojson",
            $id,
            $json
        );
        $this->_updateRouteIndex(
            "{$this->aProject->getRoot()}/geojson/full_geometry_route_index.geojson",
            $id,
            json_decode($route->getTrackJson(), true)
        );

        $this->_updateKProjects('route', $id, $route->getJson());
        $this->_updateKRoutes($id, $route);
        $taxonomies = isset($json["properties"]) && isset($json["properties"]["taxonomy"]) ? $json["properties"]["taxonomy"] : [];
        $this->_setTaxonomies("route", $json);
        $this->_setKTaxonomies($id, $taxonomies);
    }

    /**
     * Add the given json feature in the specified file
     *
     * @param string $url the file url
     * @param int $id the feature id
     * @param array|null $json the feature array. If null the route will be deleted from the file
     */
    private function _updateRouteIndex(string $url, int $id, array $json = null)
    {
        $file = null;
        if (file_exists($url)) {
            $file = json_decode(file_get_contents($url), true);
            $done = false;
            if (isset($file["features"]) && is_array($file["features"])) {
                foreach ($file["features"] as $key => $feature) {
                    if (strval($feature["properties"]["id"]) === strval($id)) {
                        if (!is_null($json)) {
                            $file["features"][$key] = $json;
                        } else {
                            unset($file["features"][$key]);
                        }
                        $done = true;
                        break;
                    }
                }

                if (!$done && !is_null($json)) {
                    $file["features"][] = $json;
                }

                $file["features"] = array_values($file["features"]);
            }
        } elseif (!is_null($json)) {
            $file = [
                "type" => "FeatureCollection",
                "features" => array_values([$json])
            ];
        }

        file_put_contents($url, json_encode($file));
    }

    /**
     * Map the custom properties in the route
     *
     * @param WebmappRoute $route
     * @return WebmappRoute
     */
    protected function _setCustomProperties(WebmappRoute $route)
    {
        if ($this->verbose) {
            $this->_verbose("Mapping custom properties");
        }
        $track_properties = $this->_getCustomProperties("track");
        if (isset($track_properties) && is_array($track_properties)) {
            $route->mapCustomProperties($track_properties);
        }

        return $route;
    }

    /**
     * Update the K roots
     *
     * @param int $id the route id
     * @param WebmappRoute $route the route
     * @throws WebmappExceptionHoquRequest
     * @throws WebmappExceptionHttpRequest
     */
    private function _updateKRoutes(int $id, WebmappRoute $route)
    {
        if (count($this->kProjects) > 0) {
            if ($this->verbose) {
                $this->_verbose("Updating K projects...");
            }
            foreach ($this->kProjects as $kProject) {
                if ($this->verbose) {
                    $this->_verbose("  {$kProject->getRoot()}");
                }
                if (file_exists("{$kProject->getRoot()}/server/server.conf")) {
                    $conf = json_decode(file_get_contents("{$kProject->getRoot()}/server/server.conf"), true);
                    if (isset($conf["multimap"]) && $conf["multimap"] === true) {
                        if ($this->verbose) {
                            $this->_verbose("Updating route index files in single map k project");
                        }
                        if (!isset($conf["routesFilter"]) || !is_array($conf["routesFilter"]) || in_array($id, $conf["routesFilter"])) {
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
    }

    /**
     * Create or update the k route directory where the map.mbtiles will be
     *
     * @param WebmappProjectStructure $kProject the k root project
     * @param int $id the route id
     * @param WebmappRoute $route the route
     * @throws WebmappExceptionHoquRequest
     * @throws WebmappExceptionHttpRequest
     */
    private function _updateKRouteDirectory(WebmappProjectStructure $kProject, int $id, WebmappRoute $route)
    {
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
                if (file_exists("{$kProject->getRoot()}/geojson/{$poiId}.geojson")) {
                    $features[] = json_decode(file_get_contents("{$kProject->getRoot()}/geojson/{$poiId}.geojson"), true);
                }
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

            file_put_contents("{$kProject->getRoot()}/routes/$id/taxonomies/webmapp_category.json", json_encode($webmapp_categories));
            file_put_contents("{$kProject->getRoot()}/routes/$id/taxonomies/activity.json", json_encode($activities));

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

            // Update map.json verifying if routes need to be updated
            if (file_exists("{$kProject->getRoot()}/routes/$id/map.json")) {
                $mapJson = json_decode(file_get_contents("{$kProject->getRoot()}/routes/$id/map.json"), true);

                if (json_encode($mapJson["bbox"]) === json_encode($bbox)) {
                    $write = false;
                }
            }

            if ($write) {
                file_put_contents("{$kProject->getRoot()}/routes/$id/map.json", json_encode($newMapJson));

                $this->_store("generate_mbtiles", [
                    "id" => $this->params["id"]
                ]);
            }
        }
    }

    /**
     * Set the taxonomies in the k projects
     *
     * @param int $id the route id
     * @param array $taxonomies the taxonomies array
     */
    private function _setKTaxonomies(int $id, array $taxonomies)
    {
        $taxonomyTypes = ["webmapp_category", "activity", "theme", "when", "where", "who"];
        $postType = 'route';

        if ($this->verbose) {
            $this->_verbose("Checking K taxonomies...");
        }
        foreach ($this->kProjects as $kProject) {
            if ($this->verbose) {
                $this->_verbose("  {$kProject->getRoot()}");
            }
            if (file_exists("{$kProject->getRoot()}/server/server.conf")) {
                $conf = json_decode(file_get_contents("{$kProject->getRoot()}/server/server.conf"), true);
                if (isset($conf["multimap"]) && $conf["multimap"] === true) {
                    foreach ($taxonomyTypes as $taxTypeId) {
                        $kJson = null;
                        $aJson = null;
                        if (file_exists("{$this->aProject->getRoot()}/taxonomies/{$taxTypeId}.json")) {
                            $aJson = json_decode(file_get_contents("{$this->aProject->getRoot()}/taxonomies/{$taxTypeId}.json"), true);
                        }
                        if (file_exists("{$kProject->getRoot()}/taxonomies/{$taxTypeId}.json")) {
                            $kJson = json_decode(file_get_contents("{$kProject->getRoot()}/taxonomies/{$taxTypeId}.json"), true);
                        }

                        if (!isset($aJson)) {
                            $this->_warning("The file {$this->aProject->getRoot()}/taxonomies/{$taxTypeId}.json is missing and should exists. Skipping the k {$taxTypeId} generation");

                            if (!$kJson) {
                                file_put_contents("{$kProject->getRoot()}/taxonomies/{$taxTypeId}.json", json_encode([]));
                            }
                        } else {
                            if (!$kJson) $kJson = [];
                            $taxArray = array_key_exists($taxTypeId, $taxonomies) ? $taxonomies[$taxTypeId] : [];
                            // Add post to its taxonomies
                            foreach ($taxArray as $taxId) {
                                $taxonomy = null;
                                $items = [
                                    $postType => [$id]
                                ];
                                if (!isset($aJson[$taxId])) {
                                    $this->_warning("The taxonomy json file {$this->aProject->getRoot()}/taxonomies/{$taxTypeId}.json is missing the {$taxId} taxonomy.");
                                } else {
                                    $taxonomy = $aJson[$taxId];
                                    if (isset($kJson[$taxId]["items"])) {
                                        $items = $kJson[$taxId]["items"];
                                        if (!isset($items[$postType])) {
                                            $items[$postType] = [];
                                        }
                                        foreach ($items as $postTypeKey => $value) {
                                            if ($postTypeKey !== $postType) {
                                                unset($items[$postTypeKey]);
                                            }
                                        }
                                        $items[$postType][] = $id;
                                        $items[$postType] = array_values(array_unique($items[$postType]));
                                    }
                                    $taxonomy["items"] = $items;
                                    $kJson[$taxId] = $taxonomy;
                                }
                            }

                            // Remove post from its not taxonomies
                            foreach ($kJson as $taxId => $taxonomy) {
                                if (
                                    !in_array($taxId, $taxArray) &&
                                    array_key_exists("items", $taxonomy) &&
                                    array_key_exists($postType, $taxonomy["items"]) &&
                                    is_array($taxonomy["items"][$postType]) &&
                                    in_array($id, $taxonomy["items"][$postType])
                                ) {
                                    $keys = array_keys($taxonomy["items"][$postType], $id);
                                    foreach ($keys as $key) {
                                        unset($kJson[$taxId]["items"][$postType][$key]);
                                    }
                                    if (count($taxonomy["items"][$postType]) == 0) {
                                        unset($kJson[$taxId]["items"][$postType]);
                                    } else {
                                        $kJson[$taxId]["items"][$postType] = array_values($kJson[$taxId]["items"][$postType]);
                                    }
                                    if (count($taxonomy["items"]) == 0) {
                                        unset($kJson[$taxId][$taxId]);
                                    }
                                }
                            }

                            if ($this->verbose) {
                                $this->_verbose("Writing $taxTypeId to {$kProject->getRoot()}/taxonomies/{$taxTypeId}.json");
                            }
                            file_put_contents("{$kProject->getRoot()}/taxonomies/{$taxTypeId}.json", json_encode($kJson));
                        }
                    }
                }
            }
        }
    }
}