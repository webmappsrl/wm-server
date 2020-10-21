<?php

abstract class WebmappAbstractJob
{
    protected $name; // Job name
    protected $params; // Job params
    protected $instanceUrl; // Job instance url
    protected $instanceName; // Instance name
    protected $verbose; // Verbose option
    protected $wp; // WordPress backend
    protected $aProject; // Project root

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

        try {
            $this->params = json_decode($params, TRUE);
        } catch (Exception $e) {
            $this->params = array();
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
            WebmappUtils::title("[{$this->name} JOB] Starting");
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
        if ($this->verbose) {
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
}
