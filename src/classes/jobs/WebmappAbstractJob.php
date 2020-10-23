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

    public function __construct($name, $instanceUrl, $params, $verbose)
    {
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
            $this->params = json_decode($params, TRUE);
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
            WebmappUtils::verbose("Instantiating $name job with");
            WebmappUtils::verbose("  instanceName: $this->instanceName");
            WebmappUtils::verbose("  instanceUrl: $this->instanceUrl");
            WebmappUtils::verbose("  params: " . json_encode($this->params));
        }
    }

    public function run()
    {
        $startTime = round(microtime(true) * 1000);
        if ($this->verbose) {
            if (isset($this->params["id"])) {
                WebmappUtils::title("[{$this->name} JOB] Starting generation of {$this->params['id']}");
            } else {
                WebmappUtils::title("[{$this->name} JOB] Starting");
            }
        }
        if ($this->verbose) {
            WebmappUtils::verbose("start time: $startTime");
        }
        $this->process();
        $endTime = round(microtime(true) * 1000);
        $duration = ($endTime - $startTime) / 1000;
        if ($this->verbose) {
            WebmappUtils::verbose("end time: $endTime");
        }
        if (isset($this->params["id"])) {
            WebmappUtils::success("[{$this->name} JOB] Completed generation of {$this->params['id']} in {$duration} seconds");
        } else {
            WebmappUtils::success("[{$this->name} JOB] Completed in {$duration} seconds");
        }
    }

    abstract protected function process();

    /**
     * Set the taxonomies for the post id
     *
     * @param $id number the post id
     * @param $taxonomies array the taxonomies array
     * @param $postType string the post type for the id
     */
    protected function _setTaxonomies($id, $taxonomies, $postType = "poi")
    {
        $taxonomyTypes = ["webmapp_category", "activity", "theme", "when", "where", "who"];

        if ($this->verbose) {
            WebmappUtils::verbose("Taxonomies: " . json_encode($taxonomies));
        }

        if ($this->verbose) {
            WebmappUtils::verbose("Checking taxonomies...");
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
            if (!$taxonomyJson) $taxonomyJson = [];
            // Add poi to its taxonomies
            foreach ($taxArray as $taxId) {
                $taxonomy = null;
                // Taxonomy already exists - check if the poi id is present and eventually add it
                if (array_key_exists($taxId, $taxonomyJson)) {
                    $taxonomy = $taxonomyJson[$taxId];
                    $items = array_key_exists("items", $taxonomy) ? $taxonomy["items"] : [];
                    $postTypeArray = array_key_exists($postType, $items) && is_array($items[$postType]) ? $items[$postType] : [];

                    if (!in_array($id, $postTypeArray)) {
                        $postTypeArray[] = $id;
                    }

                    $items[$postType] = $postTypeArray;
                    $taxonomy["items"] = $items;
                } // Taxonomy does not exists - download and add it
                else {
                    $taxonomy = null;
                    try {
                        $taxonomy = WebmappUtils::getJsonFromApi("{$this->instanceUrl}/wp-json/wp/v2/{$taxTypeId}/{$taxId}");
                        $taxonomy["items"] = [
                            $postType => [$id]
                        ];
                    } catch (WebmappExceptionHttpRequest $e) {
                        WebmappUtils::warning("Taxonomy {$taxId} is not available from {$this->instanceUrl}/wp-json/wp/v2/{$taxTypeId}/{$taxId}. Skipping");
                    }
                }
                if (isset($taxonomy)) {
                    $taxonomyJson[$taxId] = $taxonomy;
                }
            }

            // Remove poi from its not taxonomies
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
                WebmappUtils::verbose("Writing $taxTypeId to {$this->aProject->getRoot()}/taxonomies/{$taxTypeId}.json");
            }
            file_put_contents("{$this->aProject->getRoot()}/taxonomies/{$taxTypeId}.json", json_encode($taxonomyJson));
        }
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
                WebmappUtils::verbose("Updating k projects...");
            }
            if (in_array($postType, ["poi", "track"])) {
                foreach ($this->kProjects as $kProject) {
                    if ($this->verbose) {
                        WebmappUtils::verbose("  {$kProject->getRoot()}");
                    }
                    if (file_exists("{$kProject->getRoot()}/server/server.conf")) {
                        $conf = json_decode(file_get_contents("{$kProject->getRoot()}/server/server.conf"), true);
                        if (isset($conf["multimap"]) && $conf["multimap"] === true) {
                            file_put_contents("{$kProject->getRoot()}/geojson/{$id}.geojson", $json);
                        }
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
     * @throws WebmappExceptionHoquRequest for any problem with HOQU (connection/missing params)
     */
    protected function _store(string $job, array $params)
    {
        if ($this->verbose) {
            WebmappUtils::verbose("Performing new Store operation to HOQU");
        }

        if (!$this->hoquBaseUrl || !$this->storeToken) {
            throw new WebmappExceptionHoquRequest("Unable to perform a Store operation ({$this->instanceUrl}, {$job}, " . json_encode($params) . "). HOQU url or a store token are missing in the configuration");
        }
        $headers = [
            "Accept: application/json",
            "Authorization: Bearer {$this->storeToken}",
            "Content-Type:application/json"
        ];

        $payload = [
            "instance" => $this->instanceUrl,
            "job" => $job,
            "parameters" => $params
        ];

        $url = "{$this->hoquBaseUrl}/api/store";

        if ($this->verbose) {
            WebmappUtils::verbose("Initializing POST curl using:");
            WebmappUtils::verbose("  url: {$url}");
            WebmappUtils::verbose("  payload: " . json_encode($payload));
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_exec($ch);

        if (curl_getinfo($ch, CURLINFO_HTTP_CODE) !== 201) {
            curl_close($ch);
            throw new WebmappExceptionHoquRequest("Unable to perform a Store operation ({$this->instanceUrl}, {$job}, " . json_encode($params) . "). HOQU url or a store token are missing in the configuration");
        }
        curl_close($ch);
    }
}
