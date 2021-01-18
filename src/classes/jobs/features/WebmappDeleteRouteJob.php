<?php

class WebmappDeleteRouteJob extends WebmappAbstractJob
{
    /**
     * WebmappDeleteRouteJob constructor.
     * @param string $instanceUrl containing the instance url
     * @param string $params containing an encoded JSON with the route ID
     * @param bool $verbose
     * @throws WebmappExceptionNoDirectory
     * @throws WebmappExceptionParameterError
     * @throws WebmappExceptionParameterMandatory
     */
    public function __construct(string $instanceUrl, string $params, bool $verbose = false)
    {
        parent::__construct("delete_route", $instanceUrl, $params, $verbose);
    }

    /**
     * @throws WebmappExceptionFeatureStillExists
     */
    protected function process()
    {
        $this->_verbose("Checking if route is available from {$this->wp->getApiRoute($this->id)}");
        $ch = $this->_getCurl($this->wp->getApiRoute($this->id));
        curl_exec($ch);

        if (curl_getinfo($ch, CURLINFO_HTTP_CODE) < 400)
            throw new WebmappExceptionFeatureStillExists("The route seems to be still public. Deletion stopped to prevent data loss");
        else {
            $this->_verbose("Check complete. Starting clean");

            // Delete the geojson
            $geojsonUrl = "{$this->aProject->getRoot()}/geojson/{$this->id}.geojson";
            if (file_exists($geojsonUrl)) {
                $this->_lockFile($geojsonUrl);
                $this->_verbose("Removing {$geojsonUrl}");
                unlink($geojsonUrl);
                $this->_unlockFile($geojsonUrl);
            }

            // Delete id from the taxonomies
            $this->_setTaxonomies('route', [
                "properties" => [
                    "id" => $this->id,
                    "taxonomies" => []
                ]
            ]);

            // Delete the id from the route_indexes
            $routeIndexUrl = "{$this->aProject->getRoot()}/geojson/route_index.geojson";
            $this->_updateRouteIndex($routeIndexUrl, $this->id);
            $routeIndexUrl = "{$this->aProject->getRoot()}/geojson/full_geometry_route_index.geojson";
            $this->_updateRouteIndex($routeIndexUrl, $this->id);

            // Delete the route from the routes directory
            if (count($this->kProjects) > 0) {
                $this->_verbose("Deleting from k projects");
                foreach ($this->kProjects as $kProject) {
                    $this->_verbose("  {$kProject->getRoot()}");
                    $routeIndexUrl = "{$kProject->getRoot()}/routes/route_index.geojson";
                    if (file_exists($routeIndexUrl)) {
                        $this->_updateRouteIndex(
                            $routeIndexUrl,
                            $this->id
                        );
                    }
                    $routeIndexUrl = "{$kProject->getRoot()}/routes/full_geometry_route_index.geojson";
                    if (file_exists($routeIndexUrl)) {
                        $this->_updateRouteIndex(
                            $routeIndexUrl,
                            $this->id
                        );
                    }

                    $routeFolderUrl = "{$kProject->getRoot()}/routes/{$this->id}";
                    if (file_exists($routeFolderUrl))
                        $this->_removeDirectory($routeFolderUrl);

                    $this->_removeKTaxonomies($kProject, $this->id);
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
    private function _removeDirectory(string $url): bool
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
        $postType = 'route';

        $this->_verbose("Checking taxonomies in {$kProject->getRoot()}...");
        $conf = $this->_getConfig($kProject->getRoot());
        if (isset($conf["multimap"]) && $conf["multimap"] === true) {
            foreach (TAXONOMY_TYPES as $taxTypeId) {
                $kJson = null;
                $taxonomyUrl = "{$kProject->getRoot()}/taxonomies/{$taxTypeId}.json";
                if (file_exists($taxonomyUrl)) {
                    $this->_lockFile($taxonomyUrl);
                    $kJson = json_decode(file_get_contents($taxonomyUrl), true);
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
                        if (count($taxonomy["items"][$postType]) == 0)
                            unset($kJson[$taxId]["items"][$postType]);
                        else
                            $kJson[$taxId]["items"][$postType] = array_values($kJson[$taxId]["items"][$postType]);
                        if (count($taxonomy["items"]) == 0)
                            unset($kJson[$taxId][$taxId]);
                    }
                }

                $this->_lockFile($taxonomyUrl);
                $this->_verbose("Writing $taxTypeId to $taxonomyUrl");
                file_put_contents($taxonomyUrl, json_encode($kJson));
                $this->_unlockFile($taxonomyUrl);
            }
        } else
            $this->_verbose("Skipping {$kProject->getRoot()} since is not a multimap project...");
    }
}