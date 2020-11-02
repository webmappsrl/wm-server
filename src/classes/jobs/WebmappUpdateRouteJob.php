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

        try {
            // Load poi from be
            if ($this->verbose) {
                WebmappUtils::verbose("Loading route from {$this->wp->getApiRoute($id)}");
            }
            $route = new WebmappRoute("{$this->wp->getApiRoute($id)}", '', true);
            $apiTracks = $route->getApiTracks();
            $tracks = [];
        } catch (WebmappExceptionHttpRequest $e) {
            throw new WebmappExceptionHttpRequest("The instance $this->instanceUrl is unreachable or the route with id {$id} does not exists");
        } catch (Exception $e) {
            throw new WebmappException("An unknown error occurred: " . json_encode($e));
        }

        // Make sure all the tracks are up to date
        if (is_array($apiTracks) && count($apiTracks) > 0) {
            foreach ($apiTracks as $track) {
                $currentDate = 1;
                $generatedDate = 0;
                if (file_exists("{$this->aProject->getRoot()}/geojson/{$track['ID']}.geojson")) {
                    $currentDate = $this->_getTrackLastModified($track["ID"], strtotime($track["post_modified"]));
                    $file = json_decode(file_get_contents("{$this->aProject->getRoot()}/geojson/{$track['ID']}.geojson"), true);
                    $generatedDate = strtotime($file["properties"]["modified"]);
                }
                if ($currentDate > $generatedDate) {
                    if ($this->verbose) {
                        WebmappUtils::verbose("Updating track {$track['ID']}");
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

        try {
            $route->buildPropertiesAndFeaturesFromTracksGeojson($tracks);

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
            $this->_updateKRoots($id, $route);
            $taxonomies = isset($json["properties"]) && isset($json["properties"]["taxonomy"]) ? $json["properties"]["taxonomy"] : [];
            $this->_setTaxonomies($id, $taxonomies, "route");
            $this->_setKTaxonomies($id, $taxonomies);
        } catch (WebmappExceptionHttpRequest $e) {
            throw new WebmappExceptionHttpRequest("The instance $this->instanceUrl is unreachable or the route with id {$id} does not exists");
        } catch (WebmappExceptionHoquRequest $e) {
            throw new WebmappExceptionHttpRequest($e->getMessage());
        } catch (Exception $e) {
            throw new WebmappException("An unknown error occurred: " . json_encode($e));
        }
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
            $added = false;
            if (isset($file["features"]) && is_array($file["features"])) {
                foreach ($file["features"] as $key => $feature) {
                    if (strval($feature["properties"]["id"]) === strval($id)) {
                        if (!is_null($json)) {
                            $file["features"][$key] = $json;
                        } else {
                            unset($file["features"][$key]);
                        }
                        $added = true;
                        break;
                    }
                }

                if (!$added && !is_null($json)) {
                    $file["features"][] = $json;
                }

                $features = [];

                foreach ($file["features"] as $key => $value) {
                    $features[] = $value;
                }

                $file["features"] = $features;
            }
        }

        if (is_null($file) && !is_null($json)) {
            $file = [
                "type" => "FeatureCollection",
                "features" => [
                    $json
                ]
            ];
        }

        file_put_contents($url, json_encode($file));
    }

    /**
     * Update the K roots
     *
     * @param int $id the route id
     * @param WebmappRoute $route the route
     * @throws WebmappExceptionHoquRequest
     * @throws WebmappExceptionHttpRequest
     */
    private function _updateKRoots(int $id, WebmappRoute $route)
    {
        if (count($this->kProjects) > 0) {
            if ($this->verbose) {
                WebmappUtils::verbose("Updating K projects...");
            }
            foreach ($this->kProjects as $kProject) {
                if ($this->verbose) {
                    WebmappUtils::verbose("  {$kProject->getRoot()}");
                }
                if (file_exists("{$kProject->getRoot()}/server/server.conf")) {
                    $conf = json_decode(file_get_contents("{$kProject->getRoot()}/server/server.conf"), true);
                    if (isset($conf["multimap"]) && $conf["multimap"] === true) {
                        if ($this->verbose) {
                            WebmappUtils::verbose("Updating route index files in single map k project");
                        }
                        if (!isset($conf["routesFilter"]) || !is_array($conf["routesFilter"]) || in_array($id, $conf["routesFilter"])) {
                            $this->_updateRouteIndex(
                                "{$kProject->getRoot()}/geojson/route_index.geojson",
                                $id,
                                json_decode($route->getPoiJson(), true)
                            );
                            $this->_updateRouteIndex(
                                "{$kProject->getRoot()}/geojson/full_geometry_route_index.geojson",
                                $id,
                                json_decode($route->getTrackJson(), true)
                            );
                            $this->_updateKRouteDirectory($kProject, $id, $route);
                        } else {
                            $this->_updateRouteIndex("{$kProject->getRoot()}/geojson/route_index.geojson", $id);
                            $this->_updateRouteIndex("{$kProject->getRoot()}/geojson/full_geometry_route_index.geojson", $id);
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

    private function _setKTaxonomies(int $id, array $taxonomies)
    {
        $taxonomyTypes = ["webmapp_category", "activity", "theme", "when", "where", "who"];
        $postType = 'route';

        if ($this->verbose) {
            WebmappUtils::verbose("Checking K taxonomies...");
        }
        foreach ($this->kProjects as $kProject) {
            if ($this->verbose) {
                WebmappUtils::verbose("  {$kProject->getRoot()}");
            }
            if (file_exists("{$kProject->getRoot()}/server/server.conf")) {
                $conf = json_decode(file_get_contents("{$kProject->getRoot()}/server/server.conf"), true);
                if (isset($conf["multimap"]) && $conf["multimap"] === true) {
                    foreach ($taxonomyTypes as $taxTypeId) {
                        $taxonomyJson = null;
                        if (file_exists("{$kProject->getRoot()}/taxonomies/{$taxTypeId}.json")) {
                            $taxonomyJson = file_get_contents("{$kProject->getRoot()}/taxonomies/{$taxTypeId}.json");
                        }
                        if ($taxonomyJson) {
                            $taxonomyJson = json_decode($taxonomyJson, true);
                        }
                        $taxArray = array_key_exists($taxTypeId, $taxonomies) ? $taxonomies[$taxTypeId] : [];
                        if (!$taxonomyJson) $taxonomyJson = [];
                        // Add post to its taxonomies
                        foreach ($taxArray as $taxId) {
                            $taxonomy = null;
                            $items = [
                                $postType => [$id]
                            ];
                            if (isset($this->taxonomies[$taxId])) {
                                $taxonomy = $this->taxonomies[$taxId];
                            } else {
                                try {
                                    $taxonomy = WebmappUtils::getJsonFromApi("{$this->instanceUrl}/wp-json/wp/v2/{$taxTypeId}/{$taxId}");
                                    $this->taxonomies[$taxId] = $taxonomy;
                                } catch (WebmappExceptionHttpRequest $e) {
                                    WebmappUtils::warning("Taxonomy {$taxId} is not available from {$this->instanceUrl}/wp-json/wp/v2/{$taxTypeId}/{$taxId}. Skipping");
                                }
                            }

                            // Enrich the current taxonomy array
                            if (array_key_exists($taxId, $taxonomyJson)) {
                                if (!isset($taxonomy)) {
                                    $taxonomy = $taxonomyJson[$taxId];
                                }
                                $items = array_key_exists("items", $taxonomyJson[$taxId]) ? $taxonomyJson[$taxId]["items"] : [];
                                $postTypeArray = array_key_exists($postType, $items) && is_array($items[$postType]) ? $items[$postType] : [];

                                if (!in_array($id, $postTypeArray)) {
                                    $postTypeArray[] = $id;
                                }

                                $items[$postType] = $postTypeArray;
                            }

                            if (isset($taxonomy)) {
                                $taxonomy["items"] = $items;
                                $taxonomyJson[$taxId] = $taxonomy;
                            }
                        }

                        // Remove post from its not taxonomies
                        foreach ($taxonomyJson as $taxId => $taxonomy) {
                            if (
                                !in_array($taxId, $taxArray) &&
                                array_key_exists("items", $taxonomy) &&
                                array_key_exists($postType, $taxonomy["items"]) &&
                                is_array($taxonomy["items"][$postType]) &&
                                in_array($id, $taxonomy["items"][$postType])
                            ) {
                                $keys = array_keys($taxonomy["items"][$postType], $id);
                                foreach ($keys as $key) {
                                    unset($taxonomy["items"][$postType][$key]);
                                }
                                if (count($taxonomy["items"][$postType]) == 0) {
                                    unset($taxonomy["items"][$postType]);
                                }
                                if (count($taxonomy["items"]) == 0) {
                                    unset($taxonomyJson[$taxId]);
                                } else {
                                    $taxonomyJson[$taxId] = $taxonomy;
                                }
                            }
                        }

                        if ($this->verbose) {
                            WebmappUtils::verbose("Writing $taxTypeId to {$kProject->getRoot()}/taxonomies/{$taxTypeId}.json");
                        }
                        file_put_contents("{$kProject->getRoot()}/taxonomies/{$taxTypeId}.json", json_encode($taxonomyJson));
                    }
                }
            }
        }
    }

    /**
     * Return the last modified date for the given track
     *
     * @param int $id the track id
     * @param int|null $defaultValue the default last modified value
     * @return false|int|void
     */
    private function _getTrackLastModified(int $id, int $defaultValue = null)
    {
        $lastModified = isset($defaultValue) ? $defaultValue : strtotime("now");
        $ch = $this->_getCurl("{$this->instanceUrl}/wp-json/webmapp/v1/feature/last_modified/{$id}");
        $modified = null;
        try {
            $modified = curl_exec($ch);
        } catch (Exception $e) {
            WebmappUtils::warning("An error occurred getting last modified date for track {$id}: " . $e->getMessage());
            return;
        }
        if (curl_getinfo($ch, CURLINFO_HTTP_CODE) != 200) {
            WebmappUtils::warning("The api {$this->instanceUrl}/wp-json/webmapp/v1/feature/last_modified/{$id} seems unreachable: " . curl_error($ch));
            curl_close($ch);
        } else {
            curl_close($ch);

            $modified = json_decode($modified, true);

            if (isset($modified) && is_array($modified) && array_key_exists("last_modified", $modified) && is_string($modified["last_modified"])) {
                $lastModified = strtotime($modified["last_modified"]);
            }
        }

        return $lastModified;
    }
}