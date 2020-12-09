<?php

class WebmappDeleteRouteJob extends WebmappAbstractJob
{
    /**
     * WebmappDeleteRouteJob constructor.
     * @param string $instanceUrl containing the instance url
     * @param string $params containing an encoded JSON with the route ID
     * @param bool $verbose
     * @throws WebmappExceptionNoDirectory
     */
    public function __construct(string $instanceUrl, string $params, bool $verbose = false)
    {
        parent::__construct("delete_route", $instanceUrl, $params, $verbose);
    }

    protected function process()
    {
        $id = intval($this->params['id']);
        if (is_null($id)) {
            throw new WebmappExceptionParameterError("The id must be set, null given");
            return;
        }
        if ($this->verbose) {
            $this->_verbose("Checking if route is available from {$this->wp->getApiRoute($id)}");
        }
        $ch = $this->_getCurl($this->wp->getApiRoute($id));
        curl_exec($ch);

        if (curl_getinfo($ch, CURLINFO_HTTP_CODE) < 400)
            throw new WebmappExceptionFeatureStillExists("The route seems to be still public. Deletion stopped to prevent data loss");
        else {
            if ($this->verbose) {
                $this->_verbose("Check complete. Starting clean");
            }

            // Delete the geojson
            $geojsonUrl = "{$this->aProject->getRoot()}/geojson/{$id}.geojson";
            if (file_exists($geojsonUrl)) {
                if ($this->verbose) {
                    $this->_verbose("Removing {$geojsonUrl}");
                }
                unlink($geojsonUrl);
            }

            // Delete id from the taxonomies
            $this->_setTaxonomies('route', [
                "properties" => [
                    "id" => $id,
                    "taxonomies" => []
                ]
            ]);

            // Delete the id from the route_indexes
            $routeIndexUrl = "{$this->aProject->getRoot()}/geojson/route_index.geojson";
            $this->_updateRouteIndex($routeIndexUrl, $id);
            $routeIndexUrl = "{$this->aProject->getRoot()}/geojson/full_geometry_route_index.geojson";
            $this->_updateRouteIndex($routeIndexUrl, $id);

            // Delete the route from the routes directory
            if (count($this->kProjects) > 0) {
                if ($this->verbose) {
                    $this->_verbose("Deleting from k projects");
                }
                foreach ($this->kProjects as $kProject) {
                    if ($this->verbose) {
                        $this->_verbose("  {$kProject->getRoot()}");
                    }
                    $routeIndexUrl = "{$kProject->getRoot()}/routes/route_index.geojson";
                    if (file_exists($routeIndexUrl)) {
                        $this->_updateRouteIndex(
                            $routeIndexUrl,
                            $id
                        );
                    }
                    $routeIndexUrl = "{$kProject->getRoot()}/routes/full_geometry_route_index.geojson";
                    if (file_exists($routeIndexUrl)) {
                        $this->_updateRouteIndex(
                            $routeIndexUrl,
                            $id
                        );
                    }

                    $routeFolderUrl = "{$kProject->getRoot()}/routes/{$id}";
                    if (file_exists($routeFolderUrl))
                        $this->_removeDirectory($routeFolderUrl);

                    $this->_removeKTaxonomies($kProject, $id);
                }
            }
        }
    }

    /**
     * Remove the given directory. Symlinks are unlinked and files removed
     *
     * @param string $url the directory url
     * @return bool
     */
    private function _removeDirectory(string $url)
    {
        $dir_handle = null;
        if (is_dir($url))
            $dir_handle = opendir($url);
        if (!$dir_handle)
            return false;
        while ($file = readdir($dir_handle)) {
            if ($file != "." && $file != "..") {
                if (!is_dir($url . "/" . $file) || is_link($url . "/" . $file))
                    unlink($url . "/" . $file);
                else
                    $this->_removeDirectory($url . '/' . $file);
            }
        }
        closedir($dir_handle);
        rmdir($url);
        return true;
    }

    /**
     * Remove the route from the taxonomies in the k projects
     *
     * @param WebmappProjectStructure $kProject
     * @param int $id the route id
     */
    private function _removeKTaxonomies(WebmappProjectStructure $kProject, int $id)
    {
        $taxonomyTypes = ["webmapp_category", "activity", "theme", "when", "where", "who"];
        $postType = 'route';

        if ($this->verbose) {
            $this->_verbose("Checking taxonomies in {$kProject->getRoot()}...");
        }
        if (file_exists("{$kProject->getRoot()}/server/server.conf")) {
            $conf = json_decode(file_get_contents("{$kProject->getRoot()}/server/server.conf"), true);
            if (isset($conf["multimap"]) && $conf["multimap"] === true) {
                foreach ($taxonomyTypes as $taxTypeId) {
                    $kJson = null;
                    if (file_exists("{$kProject->getRoot()}/taxonomies/{$taxTypeId}.json")) {
                        $kJson = json_decode(file_get_contents("{$kProject->getRoot()}/taxonomies/{$taxTypeId}.json"), true);
                    }

                    if (!$kJson) $kJson = [];

                    // Remove post from its not taxonomies
                    foreach ($kJson as $taxId => $taxonomy) {
                        if (
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
            } else {
                if ($this->verbose) {
                    $this->_verbose("Skipping {$kProject->getRoot()} since is not a multimap project...");
                }
            }
        } else {
            if ($this->verbose) {
                $this->_verbose("Skipping {$kProject->getRoot()} since is not a multimap project...");
            }
        }
    }
}