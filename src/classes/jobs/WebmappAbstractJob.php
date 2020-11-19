<?php

abstract class WebmappAbstractJob
{
    protected $name; // Job name
    protected $params; // Job params
    protected $instanceUrl; // Job instance url
    protected $instanceName; // Instance name
    protected $instanceCode; // Instance code
    protected $verbose; // Verbose option
    protected $wp; // WordPress backend
    protected $aProject; // Project root
    protected $kProjects; // Projects root of the various possible K
    protected $storeToken; // Token to create other jobs
    protected $hoquBaseUrl; // Token to create other jobs
    protected $cachedTaxonomies; // An array of already downloaded taxonomies

    public function __construct(string $name, string $instanceUrl, string $params, bool $verbose)
    {
        declare(ticks=1);
        $this->verbose = $verbose;
        $this->name = $name;

        if (substr($instanceUrl, 0, 4) == "http") {
            $this->instanceUrl = $instanceUrl;
            $this->instanceName = str_replace("http://", "", str_replace("https://", "", $instanceUrl));
        } else {
            $this->instanceUrl = "http://" . $instanceUrl;
            $this->instanceName = $instanceUrl;
        }

        global $wm_config;

        $this->aProject = new WebmappProjectStructure(
            isset($wm_config["endpoint"]) && isset($wm_config["endpoint"]["a"])
                ? "{$wm_config["endpoint"]["a"]}/{$this->instanceName}"
                : "/var/www/html/a.webmapp.it/{$this->instanceName}");

        if (!file_exists("{$this->aProject->getRoot()}") ||
            !file_exists("{$this->aProject->getRoot()}/geojson") ||
            !file_exists("{$this->aProject->getRoot()}/taxonomies")) {
            throw new WebmappExceptionNoDirectory("The a project for the instance {$this->instanceName} is missing or on a different server");
        }

        $this->kProjects = [];
        $kBaseUrl = isset($wm_config["endpoint"]) && isset($wm_config["endpoint"]["k"])
            ? "{$wm_config["endpoint"]["k"]}"
            : "/var/www/html/k.webmapp.it";
        if (isset($wm_config["a_k_instances"]) && is_array($wm_config["a_k_instances"]) && isset($wm_config["a_k_instances"][$this->instanceName])) {
            foreach ($wm_config["a_k_instances"][$this->instanceName] as $kName) {
                $this->kProjects[] = new WebmappProjectStructure("{$kBaseUrl}/$kName");
            }
        }

        try {
            $this->params = json_decode($params, true);
        } catch (Exception $e) {
            $this->params = array();
        }

        if (isset($wm_config["hoqu"]["url"])) {
            $this->hoquBaseUrl = $wm_config["hoqu"]["url"];
        }
        if (isset($wm_config["hoqu"]["store_token"])) {
            $this->storeToken = $wm_config["hoqu"]["store_token"];
        }

        $this->wp = new WebmappWP($this->instanceUrl);

        if ($this->verbose) {
            $this->_verbose("Instantiating $name job with");
            $this->_verbose("  instanceName: $this->instanceName");
            $this->_verbose("  instanceUrl: $this->instanceUrl");
            $this->_verbose("  params: " . json_encode($this->params));
        }

        $this->cachedTaxonomies = [];
    }

    public function run()
    {
        $startTime = round(microtime(true) * 1000);
        if (isset($this->params["id"])) {
            $this->_title("Starting generation of {$this->params['id']}");
        } else {
            $this->_title("Starting");
        }
        if ($this->verbose) {
            $this->_verbose("start time: $startTime");
        }
        $this->process();
        $endTime = round(microtime(true) * 1000);
        $duration = ($endTime - $startTime) / 1000;
        if ($this->verbose) {
            $this->_verbose("end time: $endTime");
        }
        if (isset($this->params["id"])) {
            $this->_success("Completed generation of {$this->params['id']} in {$duration} seconds");
        } else {
            $this->_success("Completed in {$duration} seconds");
        }
    }

    abstract protected function process();

    /**
     * Set the taxonomies for the post id
     *
     * @param string $postType the post type for the id
     * @param array $json the json of the feature
     */
    protected function _setTaxonomies(string $postType, array $json)
    {
        $id = isset($json["properties"]) && isset($json["properties"]["id"]) ? $json["properties"]["id"] : null;
        if (!isset($id)) {
            return;
        }
        $taxonomies = isset($json["properties"]) && isset($json["properties"]["taxonomy"]) ? $json["properties"]["taxonomy"] : [];
        $taxonomyTypes = ["webmapp_category", "activity", "theme", "when", "where", "who"];

        if ($this->verbose) {
            $this->_verbose("Taxonomies: " . json_encode($taxonomies));
            $this->_verbose("Checking taxonomies...");
        }
        foreach ($taxonomyTypes as $taxTypeId) {
            $taxonomyJson = null;
            if (file_exists("{$this->aProject->getRoot()}/taxonomies/{$taxTypeId}.json")) {
                $taxonomyJson = file_get_contents("{$this->aProject->getRoot()}/taxonomies/{$taxTypeId}.json");
            }
            if ($taxonomyJson) {
                $taxonomyJson = json_decode($taxonomyJson, true);
            }
            $taxArray = array_key_exists($taxTypeId, $taxonomies) ? $taxonomies[$taxTypeId] : [];
            if (!$taxonomyJson) {
                $taxonomyJson = [];
            }

            // Add post to its taxonomies
            foreach ($taxArray as $taxId) {
                $taxonomy = null;
                $items = [
                    $postType => [$id],
                ];
                if (isset($this->cachedTaxonomies[$taxId])) {
                    $taxonomy = $this->cachedTaxonomies[$taxId];
                } else {
                    try {
                        $taxonomy = WebmappUtils::getJsonFromApi("{$this->instanceUrl}/wp-json/wp/v2/{$taxTypeId}/{$taxId}");
                        $this->cachedTaxonomies[$taxId] = $taxonomy; // Cache downloaded taxonomies
                    } catch (WebmappExceptionHttpRequest $e) {
                        $this->_warning("Taxonomy {$taxId} is not available from {$this->instanceUrl}/wp-json/wp/v2/{$taxTypeId}/{$taxId}. Skipping");
                    }
                }

                // Enrich the current taxonomy array
                if (array_key_exists($taxId, $taxonomyJson)) {
                    if (!isset($taxonomy)) {
                        $taxonomy = $taxonomyJson[$taxId];
                    }
                    $items = array_key_exists("items", $taxonomyJson[$taxId]) ? $taxonomyJson[$taxId]["items"] : [];
                    $postTypeArray = array_key_exists($postType, $items) && is_array($items[$postType]) ? $items[$postType] : [];

                    $postTypeArray[] = $id;
                    $postTypeArray = array_values(array_unique($postTypeArray));

                    $items[$postType] = $postTypeArray;
                }

                if (isset($taxonomy)) {
                    $taxonomy["items"] = $items;

                    $taxonomy = $this->_cleanTaxonomy($taxonomy);
                    $taxonomyJson[$taxId] = $taxonomy;

                    if ($this->verbose) {
                        $this->_verbose("Checking {$taxTypeId} {$taxId} taxonomy term feature collection");
                    }
                    $geojsonUrl = "{$this->aProject->getRoot()}/taxonomies/{$taxId}.geojson";
                    $taxonomyGeojson = [
                        "type" => "FeatureCollection",
                        "features" => [],
                        "properties" => $taxonomy
                    ];
                    if (file_exists($geojsonUrl)) {
                        $file = json_decode(file_get_contents($geojsonUrl), true);
                        if (isset($file["features"]) && is_array($file["features"])) {
                            $taxonomyGeojson["features"] = $file["features"];
                        }
                    }

                    $found = false;
                    $key = 0;

                    while (!$found && $key < count($taxonomyGeojson["features"])) {
                        if (isset($taxonomyGeojson["features"][$key]["properties"]["id"]) && strval($taxonomyGeojson["features"][$key]["properties"]["id"]) === strval($id)) {
                            $found = true;
                        } else {
                            $key++;
                        }
                    }

                    if ($found) {
                        $taxonomyGeojson["features"][$key] = $json;
                    } else {
                        $taxonomyGeojson["features"][] = $json;
                    }

                    if ($this->verbose) {
                        $this->_verbose("Writing {$taxTypeId} {$taxId} taxonomy term feature collection to {$geojsonUrl}");
                    }
                    file_put_contents($geojsonUrl, json_encode($taxonomyGeojson));
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

                if (isset($taxonomyJson[$taxId])) {
                    $tax = $taxonomyJson[$taxId];
                    $tax = $this->_cleanTaxonomy($tax);
                    $taxonomyJson[$taxId] = $tax;
                }

                if (!in_array($taxId, $taxArray)) {
                    $geojsonUrl = "{$this->aProject->getRoot()}/taxonomies/{$taxId}.geojson";
                    if (file_exists($geojsonUrl)) {
                        if ($this->verbose) {
                            $this->_verbose("Checking {$taxTypeId} {$taxId} taxonomy term feature collection");
                        }
                        $taxonomyGeojson = json_decode(file_get_contents($geojsonUrl), true);
                        $found = false;
                        $key = 0;
                        while (!$found && $key < count($taxonomyGeojson["features"])) {
                            if (isset($taxonomyGeojson["features"][$key]["properties"]["id"]) && strval($taxonomyGeojson["features"][$key]["properties"]["id"]) === strval($id)) {
                                $found = true;
                            } else {
                                $key++;
                            }
                        }

                        if ($found) {
                            if ($this->verbose) {
                                $this->_verbose("Cleaning {$taxTypeId} {$taxId} taxonomy term feature collection");
                            }
                            unset($taxonomyGeojson["features"][$key]);
                            if (count($taxonomyGeojson["features"]) === 0) {
                                unlink($geojsonUrl);
                            } else {
                                file_put_contents($geojsonUrl, json_encode($taxonomyGeojson));
                            }
                        }
                    }
                }
            }

            if ($this->verbose) {
                $this->_verbose("Writing $taxTypeId to {$this->aProject->getRoot()}/taxonomies/{$taxTypeId}.json");
            }
            file_put_contents("{$this->aProject->getRoot()}/taxonomies/{$taxTypeId}.json", json_encode($taxonomyJson));
        }
    }

    /**
     * Clean the unneeded values of the given taxonomy
     *
     * @param array $taxonomy the taxonomy to clean
     * @return array the resulted cleaned taxonomy
     */
    private function _cleanTaxonomy(array $taxonomy)
    {
        if (isset($taxonomy['featured_image']) && is_array($taxonomy['featured_image'])) {
            if (isset($taxonomy['featured_image']['sizes']['large'])) {
                $taxonomy['image'] = $taxonomy['featured_image']['sizes']['large'];
            } else if (isset($taxonomy['featured_image']['sizes']['medium_large'])) {
                $taxonomy['image'] = $taxonomy['featured_image']['sizes']['medium_large'];
            } else if (isset($taxonomy['featured_image']['sizes']['medium'])) {
                $taxonomy['image'] = $taxonomy['featured_image']['sizes']['medium'];
            }
        } else if (isset($taxonomy["acf"]['featured_image']) && is_array($taxonomy["acf"]['featured_image'])) {
            if (isset($taxonomy["acf"]['featured_image']['sizes']['large'])) {
                $taxonomy['image'] = $taxonomy["acf"]['featured_image']['sizes']['large'];
            } else if (isset($taxonomy["acf"]['featured_image']['sizes']['medium_large'])) {
                $taxonomy['image'] = $taxonomy["acf"]['featured_image']['sizes']['medium_large'];
            } else if (isset($taxonomy["acf"]['featured_image']['sizes']['medium'])) {
                $taxonomy['image'] = $taxonomy["acf"]['featured_image']['sizes']['medium'];
            }
        }

        $count = 0;
        if (isset($taxonomy["items"])) {
            foreach ($taxonomy["items"] as $postTypeIds) {
                $count += count($postTypeIds);
            }
        }
        $taxonomy["count"] = $count;

        unset($taxonomy["acf"]);
        unset($taxonomy["featured_image"]);

        $floatProperties = ["min_size", "max_size", "icon_size", "min_visible_zoom", "min_size_zoom", "icon_zoom"];
        $intProperties = [];
        $stringProperties = [];

        foreach ($taxonomy as $key => $value) {
            if (is_null($value) || (is_string($value) && empty($value)) || $key === '_links') {
                unset($taxonomy[$key]);
            } else {
                if (in_array($key, $floatProperties)) {
                    $taxonomy[$key] = floatval($taxonomy[$key]);
                } else if (in_array($key, $intProperties)) {
                    $taxonomy[$key] = intval($taxonomy[$key]);
                } else if (in_array($key, $stringProperties)) {
                    $taxonomy[$key] = strval($taxonomy[$key]);
                }
            }
        }

        return $taxonomy;
    }

    /**
     * Write the file in the k projects if needed
     *
     * @param string $postType the post type
     * @param int $id the post id
     * @param string $json the content to write
     */
    protected function _updateKProjects(string $postType, int $id, string $json)
    {
        if (count($this->kProjects) > 0) {
            if ($this->verbose) {
                $this->_verbose("Adding geojson to K projects...");
            }
            if (in_array($postType, ["poi", "track", "route"])) {
                foreach ($this->kProjects as $kProject) {
                    if ($this->verbose) {
                        $this->_verbose("  {$kProject->getRoot()}");
                    }
                    if (file_exists("{$kProject->getRoot()}/server/server.conf")) {
                        $conf = json_decode(file_get_contents("{$kProject->getRoot()}/server/server.conf"), true);
//                        if (isset($conf["multimap"]) && $conf["multimap"] === true) {
                        file_put_contents("{$kProject->getRoot()}/geojson/{$id}.geojson", $json);
//                        }
                    }
                }
            }
        }
    }

    /**
     * Perform a store operation to hoqu
     *
     * @param string $job the job name
     * @param array $params the array of params
     *
     * @throws WebmappExceptionHoquRequest for any problem with HOQU (missing params)
     * @throws WebmappExceptionHttpRequest for any problem with connection
     */
    protected function _store(string $job, array $params)
    {
        if ($this->verbose) {
            $this->_verbose("Performing new Store operation to HOQU");
        }

        if (!$this->hoquBaseUrl || !$this->storeToken) {
            throw new WebmappExceptionHoquRequest("Unable to perform a Store operation ({$this->instanceUrl}, {$job}, " . json_encode($params) . "). HOQU url or a store token are missing in the configuration");
        }
        $headers = [
            "Accept: application/json",
            "Authorization: Bearer {$this->storeToken}",
            "Content-Type:application/json",
        ];

        $payload = [
            "instance" => $this->instanceUrl,
            "job" => $job,
            "parameters" => $params,
        ];

        $url = "{$this->hoquBaseUrl}/api/store";

        if ($this->verbose) {
            $this->_verbose("Initializing POST curl using:");
            $this->_verbose("  url: {$url}");
            $this->_verbose("  payload: " . json_encode($payload));
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_exec($ch);

        if (curl_getinfo($ch, CURLINFO_HTTP_CODE) !== 201) {
            curl_close($ch);
            throw new WebmappExceptionHttpRequest("Unable to perform a Store operation ({$this->instanceUrl}, {$job}, " . json_encode($params) . "). HOQU url or a store token are missing in the configuration");
        }
        curl_close($ch);
    }

    /**
     * Prepare curl for a put request
     *
     * @param string $url the request url
     * @param array|null $headers the headers - optional
     * @return false|resource
     */
    protected function _getCurl(string $url, array $headers = null)
    {
        if (!isset($headers)) {
            $headers = [];
        }

        if ($this->verbose) {
            $this->_verbose("Initializing GET curl using:");
            $this->_verbose("  url: {$url}");
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        return $ch;
    }

    private function _logHeader()
    {
        return date("Y-m-d H:i:s") . " - {$this->name} JOB | ";
    }

    protected function _title($message)
    {
        WebmappUtils::title($this->_logHeader() . $message);
    }

    protected function _verbose($message)
    {
        WebmappUtils::verbose($this->_logHeader() . $message);
    }

    protected function _success($message)
    {
        WebmappUtils::success($this->_logHeader() . $message);
    }

    protected function _message($message)
    {
        WebmappUtils::message($this->_logHeader() . $message);
    }

    protected function _warning($message)
    {
        WebmappUtils::warning($this->_logHeader() . $message);
    }

    protected function _error($message)
    {
        WebmappUtils::error($this->_logHeader() . $message);
    }
}
