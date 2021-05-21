<?php

define("TAXONOMY_TYPES", ["webmapp_category", "activity", "theme", "when", "where", "who"]);

abstract class WebmappAbstractJob {
    protected $name; // Job name
    protected $instanceUrl; // Job instance url
    protected $instanceName; // Instance name
    protected $params; // Job params
    protected $id; // The job id
    protected $verbose; // Verbose option
    protected $wp; // WordPress backend
    protected $aProject; // Project root
    protected $kProjects; // Projects root of the various possible K
    protected $storeToken; // Token to create other jobs
    protected $hoquBaseUrl; // Token to create other jobs
    protected $cachedTaxonomies; // An array of already downloaded taxonomies
    private $lockedFile;
    private $lockedFileUrl;

    /**
     * WebmappAbstractJob constructor.
     *
     * @param string $name
     * @param string $instanceUrl
     * @param string $params
     * @param bool   $verbose
     *
     * @throws WebmappExceptionNoDirectory
     * @throws WebmappExceptionParameterMandatory
     * @throws WebmappExceptionParameterError
     */
    public function __construct(string $name, string $instanceUrl, string $params, bool $verbose) {
        declare(ticks = 1);
        $this->verbose = $verbose;
        $this->name = $name;

        if (substr($instanceUrl, 0, 4) == "http") {
            $this->instanceUrl = $instanceUrl;
            $this->instanceName = str_replace("http://", "", str_replace("https://", "", $instanceUrl));
        } else {
            $this->instanceUrl = "http://" . $instanceUrl;
            $this->instanceName = $instanceUrl;
        }

        if (strpos($this->instanceName, "/") >= 0) {
            $this->instanceName = explode("/", $this->instanceName)[0];
            $this->instanceUrl = str_replace("http(s)?://(.*)", "http$1://{$this->instanceName}", $this->instanceUrl);
        }

        global $wm_config;

        $aName = isset($wm_config["a_k_instances"][$this->instanceName]["a"]) ? $wm_config["a_k_instances"][$this->instanceName]["a"] : $this->instanceName;
        $this->aProject = new WebmappProjectStructure(
            isset($wm_config["endpoint"]) && isset($wm_config["endpoint"]["a"])
                ? "{$wm_config["endpoint"]["a"]}/{$aName}"
                : "/var/www/html/a.webmapp.it/{$aName}");

        if (!file_exists("{$this->aProject->getRoot()}") ||
            !file_exists("{$this->aProject->getRoot()}/geojson") ||
            !file_exists("{$this->aProject->getRoot()}/taxonomies"))
            throw new WebmappExceptionNoDirectory("The a project for the instance {$this->instanceName} is missing or on a different server");

        $this->kProjects = [];
        $kBaseUrl = isset($wm_config["endpoint"]) && isset($wm_config["endpoint"]["k"])
            ? "{$wm_config["endpoint"]["k"]}"
            : "/var/www/html/k.webmapp.it";
        if (isset($wm_config["a_k_instances"][$this->instanceName]["k"]) && is_array($wm_config["a_k_instances"][$this->instanceName]["k"])) {
            foreach ($wm_config["a_k_instances"][$this->instanceName]["k"] as $kName) {
                $this->kProjects[] = new WebmappProjectStructure("{$kBaseUrl}/$kName");
            }
        }

        try {
            $this->params = json_decode($params, true);
        } catch (Exception $e) {
            $this->params = array();
        }

        if (isset($this->params["id"]) && !is_nan(intval($this->params["id"]))) {
            $this->id = intval($this->params["id"]);
            unset ($this->params["id"]);
        } else {
            if (!isset($this->params["id"]))
                throw new WebmappExceptionParameterMandatory("The parameter 'id' is required");
            elseif (is_nan(intval($this->params["id"])))
                throw new WebmappExceptionParameterError("Invalid parameter 'id': " . $this->params["id"] . " must be a number");
            else
                throw new WebmappExceptionParameterError("Invalid parameter 'id': " . $this->params["id"]);
        }

        if (isset($wm_config["hoqu"]["url"])) {
            $this->hoquBaseUrl = $wm_config["hoqu"]["url"];
        }
        if (isset($wm_config["hoqu"]["store_token"])) {
            $this->storeToken = $wm_config["hoqu"]["store_token"];
        }

        $this->wp = new WebmappWP($this->instanceUrl);

        $this->_verbose("Instantiating $name job with");
        $this->_verbose("  instanceName: $this->instanceName");
        $this->_verbose("  instanceUrl: $this->instanceUrl");
        $this->_verbose("  id: " . $this->id);
        $this->_verbose("  params: " . json_encode($this->params));

        $this->cachedTaxonomies = [];
    }

    /**
     * @throws Exception
     */
    public function run() {
        $startTime = round(microtime(true) * 1000);
        $this->_title(isset($this->id) ? "Starting generation of {$this->id}" : "Starting");
        $this->_verbose("start time: $startTime");
        try {
            $this->process();
        } catch (Exception $e) {
            if (!is_null($this->lockedFileUrl))
                $this->_unlockFile($this->lockedFileUrl);
            throw $e;
        }
        $endTime = round(microtime(true) * 1000);
        $duration = ($endTime - $startTime) / 1000;
        $this->_verbose("end time: $endTime");
        $this->_success(isset($this->id) ? "Completed generation of {$this->id} in {$duration} seconds" : "Completed in {$duration} seconds");
    }

    abstract protected function process();

    /**
     * Check if a feature has some audio that needs to be generated and push the related hoqu jobs
     *
     * @param array $json the feature
     *
     * @throws WebmappExceptionHoquRequest
     * @throws WebmappExceptionHttpRequest
     */
    protected function _checkAudios(array $json) {
        $config = $this->_getConfig($this->aProject->getRoot());

        if (isset($config['generate_audios']) && !!$config['generate_audios']) {
            $this->_verbose("Checking audios that need to be generated");
            $languages = [];

            if (isset($json['properties']['translations']) && is_array($json['properties']['translations'])) {
                foreach ($json['properties']['translations'] as $lang => $translation) {
                    if (isset($translation['description']) &&
                        !empty($translation['description']) &&
                        !isset($translation['audio']))
                        $languages[] = $lang;
                }
            }

            if (isset($json['properties']['locale']) &&
                isset($json['properties']['description']) &&
                !empty($json['properties']['description']) &&
                !isset($json['properties']['audio'])) {
                $languages[] = $json['properties']['locale'];
            }

            if (count($languages) > 0) {
                $this->_verbose("There are " . count($languages) . " audios to be generated: " . implode(', ', $languages));
                $this->_verbose("Creating the hoqu jobs");

                foreach ($languages as $lang) {
                    $this->_store('generate_audio', [
                        'id' => $this->id,
                        'lang' => $lang
                    ]);
                }
            } else
                $this->_verbose("There are no audio to be generated");
        }
    }

    /**
     * Return the current API value of the given taxonomy
     *
     * @param string $taxonomyType
     * @param string $id
     *
     * @return mixed|void|null
     */
    protected function _getTaxonomy(string $taxonomyType, string $id) {
        $taxonomy = null;
        if (isset($this->cachedTaxonomies[$id]))
            $taxonomy = $this->cachedTaxonomies[$id];
        else {
            try {
                $url = $taxonomyType === "event"
                    ? "{$this->instanceUrl}/wp-json/tribe/events/v1/categories/{$id}"
                    : "{$this->instanceUrl}/wp-json/wp/v2/{$taxonomyType}/{$id}";
                $taxonomy = WebmappUtils::getJsonFromApi($url);
                if ($taxonomyType === "event") {
                    $propertiesToMap = ["icon", "color"];
                    foreach ($propertiesToMap as $property) {
                        if (isset($taxonomy["acf"][$property]))
                            $taxonomy[$property] = $taxonomy["acf"][$property];
                    }
                }

                if (isset($taxonomy['line_dash_repeater'])
                    && is_array($taxonomy['line_dash_repeater'])
                    && count($taxonomy['line_dash_repeater']) > 0) {
                    $lineDash = [];
                    foreach ($taxonomy['line_dash_repeater'] as $item) {
                        $lineDash[] = floatval($item['line_dash']);
                    }
                    $taxonomy['line_dash'] = $lineDash;
                }

                if (isset($taxonomy["wpml_current_locale"])) {
                    $taxonomy["locale"] = preg_replace('|_.*$|', '', $taxonomy["wpml_current_locale"]);
                    unset($taxonomy["wpml_current_locale"]);
                }

                if (isset($taxonomy['yoast_head']))
                    unset($taxonomy["yoast_head"]);

                if (isset($taxonomy["wpml_translations"])) {
                    if (is_array($taxonomy['wpml_translations']) && count($taxonomy['wpml_translations']) > 0) {
                        $translations = [];
                        foreach ($taxonomy['wpml_translations'] as $item) {
                            $locale = preg_replace('|_.*$|', '', $item['locale']);
                            $val = array();
                            $val['id'] = $item['id'];
                            $val['name'] = $item['name'];
                            $val['source'] = $item['source'];
                            try {
                                $ja = WebmappUtils::getJsonFromApi($val['source']);
                                if (isset($ja['name']))
                                    $val['name'] = $ja['name'];
                                if (isset($ja['html_description']))
                                    $val['description'] = $ja['html_description'];

                                $translations[$locale] = $val;
                            } catch (WebmappExceptionHttpRequest $e) {
                                WebmappUtils::warning("The taxonomy {$locale} language is not available at the url {$val['source']}. This could be due to the translation being in a draft state. HttpError: " . $e->getMessage());
                            }
                        }
                        $taxonomy["translations"] = $translations;
                    }
                    unset($taxonomy["wpml_translations"]);
                }

                $this->cachedTaxonomies[$id] = $taxonomy; // Cache downloaded taxonomies
            } catch (WebmappExceptionHttpRequest $e) {
                $this->_warning("Taxonomy {$id} is not available from {$this->instanceUrl}/wp-json/wp/v2/{$taxonomyType}/{$id}. Skipping");
            }
        }

        return $taxonomy;
    }

    /**
     * Set the taxonomies for the post id
     *
     * @param string $postType the post type for the id
     * @param array  $json     the json of the feature
     */
    protected function _setTaxonomies(string $postType, array $json) {
        $isEvent = false;
        if ($postType === "event") {
            $postType = "poi";
            $isEvent = true;
        }

        $taxonomies = isset($json["properties"]) && isset($json["properties"]["taxonomy"]) ? $json["properties"]["taxonomy"] : [];

        $this->_verbose("Taxonomies: " . json_encode($taxonomies));
        $this->_verbose("Downloading taxonomies...");
        foreach (TAXONOMY_TYPES as $taxTypeId) {
            $taxArray = array_key_exists($taxTypeId, $taxonomies) ? $taxonomies[$taxTypeId] : [];
            foreach ($taxArray as $taxId) {
                if (!is_null($taxId))
                    //                $this->_getTaxonomy($isEvent ? "event" : $taxTypeId, $taxId);
                    $this->_getTaxonomy($taxTypeId, $taxId);
            }
        }

        $this->_setATaxonomies($postType, $json);

        $this->_verbose("Checking K taxonomies...");
        foreach ($this->kProjects as $kProject) {
            $this->_setKTaxonomies($postType, $json, $kProject);
        }
    }

    /**
     * Set the taxonomies for the post id in the A project
     *
     * @param string $postType the post type for the id
     * @param array  $json     the json of the feature
     */
    private function _setATaxonomies(string $postType, array $json) {
        $this->_verbose("Checking taxonomies in the A project {$this->aProject->getRoot()}...");
        $taxonomies = isset($json["properties"]) && isset($json["properties"]["taxonomy"]) ? $json["properties"]["taxonomy"] : [];
        foreach (TAXONOMY_TYPES as $taxTypeId) {
            $taxonomyJson = null;
            $idsToCheck = [];
            $cleanedIds = [];
            $taxonomyUrl = "{$this->aProject->getRoot()}/taxonomies/{$taxTypeId}.json";
            if (file_exists($taxonomyUrl)) {
                $this->_lockFile($taxonomyUrl);
                $taxonomyJson = file_get_contents($taxonomyUrl);
                if ($taxonomyJson)
                    $taxonomyJson = json_decode($taxonomyJson, true);
            }

            $taxArray = array_key_exists($taxTypeId, $taxonomies) ? $taxonomies[$taxTypeId] : [];
            if (!$taxonomyJson)
                $taxonomyJson = [];

            // Add post to its taxonomies
            foreach ($taxArray as $taxId) {
                $taxonomy = null;
                $items = [
                    $postType => [$this->id],
                ];
                $taxonomy = $this->cachedTaxonomies[$taxId];

                // Enrich the current taxonomy array
                if (array_key_exists($taxId, $taxonomyJson)) {
                    if (!isset($taxonomy)) {
                        $taxonomy = $taxonomyJson[$taxId];
                        $this->cachedTaxonomies[$taxId] = $taxonomy;
                    }
                    $items = array_key_exists("items", $taxonomyJson[$taxId]) ? $taxonomyJson[$taxId]["items"] : [];
                    $postTypeArray = array_key_exists($postType, $items) && is_array($items[$postType]) ? $items[$postType] : [];

                    $postTypeArray[] = $this->id;
                    $postTypeArray = array_values(array_unique($postTypeArray));

                    $items[$postType] = $postTypeArray;
                }

                if (isset($taxonomy)) {
                    $taxonomy["items"] = $items;

                    $taxonomy = $this->_cleanTaxonomy($taxonomy);
                    $taxonomyJson[$taxId] = $taxonomy;
                }
            }

            // Remove post from its not taxonomies and clean empty taxonomies
            foreach ($taxonomyJson as $taxId => $taxonomy) {
                $idsToCheck[] = $taxId;
                if (
                    !in_array($taxId, $taxArray) &&
                    array_key_exists("items", $taxonomy) &&
                    array_key_exists($postType, $taxonomy["items"]) &&
                    is_array($taxonomy["items"][$postType]) &&
                    in_array($this->id, $taxonomy["items"][$postType])
                ) {
                    $this->_verbose("Removing post from $taxId");
                    $keys = array_keys($taxonomyJson[$taxId]["items"][$postType], $this->id);
                    foreach ($keys as $key) {
                        unset($taxonomyJson[$taxId]["items"][$postType][$key]);
                    }

                    if (count($taxonomyJson[$taxId]["items"][$postType]) == 0)
                        unset($taxonomyJson[$taxId]["items"][$postType]);
                    else
                        $taxonomyJson[$taxId]["items"][$postType] = array_values($taxonomyJson[$taxId]["items"][$postType]);
                }

                if (isset($taxonomyJson[$taxId])) {
                    $tax = $taxonomyJson[$taxId];
                    $tax = $this->_cleanTaxonomy($tax);
                    $clean = true;
                    if (isset($taxonomyJson[$taxId]["items"]) && count($taxonomyJson[$taxId]["items"]) > 0) {
                        foreach ($taxonomyJson[$taxId]["items"] as $postTypeArray) {
                            if (is_array($postTypeArray) && count($postTypeArray) > 0) {
                                $clean = false;
                                break;
                            }
                        }
                    }

                    if ($clean) {
                        $isParent = $this->_isParentTaxonomy($taxId, $taxonomyJson);

                        if (!$isParent) {
                            unset($taxonomyJson[$taxId]);
                            $cleanedIds[] = $taxId;
                        }
                    } else
                        $taxonomyJson[$taxId] = $tax;
                }
            }

            $taxonomyJson = $this->_checkParentsTaxonomies($taxTypeId, $taxonomyJson, $taxArray);

            $this->_verbose("Writing $taxTypeId to {$taxonomyUrl}");
            file_put_contents($taxonomyUrl, json_encode($taxonomyJson));
            $this->_unlockFile($taxonomyUrl);

            foreach ($taxArray as $taxId) {
                $this->_setTaxonomyFeatureCollection($taxTypeId, $taxId, $taxonomyJson, $json);
            }

            $this->_verbose("Cleaning empty taxonomies of $taxTypeId");
            if (count($cleanedIds) > 0) {
                foreach ($cleanedIds as $taxId) {
                    $geojsonUrl = "{$this->aProject->getRoot()}/taxonomies/{$taxId}.geojson";
                    if (file_exists($geojsonUrl)) {
                        $this->_lockFile($geojsonUrl);
                        $this->_verbose("Cleaning {$taxTypeId} {$taxId} taxonomy term feature collection since it is empty");
                        unlink($geojsonUrl);
                        $this->_unlockFile($geojsonUrl);
                    }
                }
            }

            // Remove post from its not taxonomies in the collections
            foreach ($idsToCheck as $taxId) {
                if (!in_array($taxId, $taxArray)) {
                    $geojsonUrl = "{$this->aProject->getRoot()}/taxonomies/{$taxId}.geojson";
                    if (file_exists($geojsonUrl)) {
                        $this->_lockFile($geojsonUrl);
                        $this->_verbose("Checking {$taxTypeId} {$taxId} taxonomy term feature collection");
                        $taxonomyGeojson = json_decode(file_get_contents($geojsonUrl), true);
                        $found = false;
                        $key = 0;
                        while (!$found && $key < count($taxonomyGeojson["features"])) {
                            if (isset($taxonomyGeojson["features"][$key]["properties"]["id"])
                                && strval($taxonomyGeojson["features"][$key]["properties"]["id"]) === strval($this->id))
                                $found = true;
                            else
                                $key++;
                        }

                        if ($found) {
                            $this->_verbose("Cleaning {$taxTypeId} {$taxId} taxonomy term feature collection");
                            unset($taxonomyGeojson["features"][$key]);
                            $taxonomyGeojson["features"] = array_values($taxonomyGeojson["features"]);
                            if (count($taxonomyGeojson["features"]) === 0)
                                unlink($geojsonUrl);
                            else
                                file_put_contents($geojsonUrl, json_encode($taxonomyGeojson));
                        }
                        $this->_unlockFile($geojsonUrl);
                    }
                }
            }
        }
    }

    /**
     * Return true if the given id is a parent taxonomy
     *
     * @param string $id   the taxonomy id
     * @param array  $json the taxonomies json
     *
     * @return bool
     */
    protected function _isParentTaxonomy(string $id, array $json): bool {
        return in_array($id, $this->_getParentsTaxonomiesIds($json));
    }

    /**
     * Return the parents ids of the taxonomies in the given json
     *
     * @param array      $json the json
     * @param array|null $ids  the taxonomies ids to get the parents of
     *
     * @return array
     */
    private function _getParentsTaxonomiesIds(array $json, array $ids = null): array {
        $parents = [];
        if (is_null($ids)) {
            $ids = array_keys($json);
        }

        foreach ($ids as $taxId) {
            if (isset($json[$taxId]["parent"]) && intval($json[$taxId]["parent"]) > 0)
                $parents[] = $json[$taxId]["parent"];
        }

        return array_values(array_unique($parents));
    }

    /**
     * Set the parents taxonomies of the given taxonomies id in the given json
     *
     * @param string     $taxonomyType
     * @param array      $json
     * @param array|null $ids
     *
     * @return array
     */
    private function _checkParentsTaxonomies(string $taxonomyType, array $json, array $ids = null): array {
        $this->_verbose("Checking taxonomies parents");
        $parents = $this->_getParentsTaxonomiesIds($json, $ids);

        foreach ($parents as $parentId) {
            if (!isset($json[$parentId])) {
                try {
                    $taxonomy = $this->_getTaxonomy($taxonomyType, $parentId);
                    $taxonomy = $this->_cleanTaxonomy($taxonomy);
                    $json[$parentId] = $taxonomy;
                    $this->_store("update_taxonomy", ["id" => $parentId]);
                } catch (Exception $e) {
                    WebmappUtils::warning("An error occurred updating the parent taxonomy {$parentId}: " . $e->getMessage());
                }
            }
        }

        return $json;
    }

    /**
     * Set the feature collection of the given taxonomy adding the given feature
     *
     * @param string     $type
     * @param int        $id
     * @param array      $taxonomyJson
     * @param array|null $feature
     */
    private function _setTaxonomyFeatureCollection(string $type, int $id, array $taxonomyJson, array $feature = null) {
        $this->_verbose("Checking {$type} {$id} taxonomy term feature collection");
        $geojsonUrl = "{$this->aProject->getRoot()}/taxonomies/{$id}.geojson";
        $taxonomyGeojson = [
            "type" => "FeatureCollection",
            "features" => [],
            "properties" => $taxonomyJson[$id]
        ];
        if (file_exists($geojsonUrl)) {
            $this->_lockFile($geojsonUrl);
            $file = json_decode(file_get_contents($geojsonUrl), true);
            if (isset($file["features"]) && is_array($file["features"]))
                $taxonomyGeojson["features"] = $file["features"];
        }

        $found = false;
        $key = 0;

        while (!$found && $key < count($taxonomyGeojson["features"])) {
            if (isset($taxonomyGeojson["features"][$key]["properties"]["id"])
                && strval($taxonomyGeojson["features"][$key]["properties"]["id"]) === strval($this->id))
                $found = true;
            else
                $key++;
        }

        if (isset($feature)) {
            if ($found)
                $taxonomyGeojson["features"][$key] = $feature;
            else
                $taxonomyGeojson["features"][] = $feature;
        }

        $taxonomyGeojson["features"] = array_values($taxonomyGeojson["features"]);

        $this->_lockFile($geojsonUrl);
        $this->_verbose("Writing {$type} {$id} taxonomy term feature collection to {$geojsonUrl}");
        file_put_contents($geojsonUrl, json_encode($taxonomyGeojson));
        $this->_unlockFile($geojsonUrl);
    }

    /**
     * Set the taxonomies in the k project if needed
     *
     * @param string                  $postType the post type
     * @param array                   $json     the json
     * @param WebmappProjectStructure $kProject the project
     */
    private function _setKTaxonomies(string $postType, array $json, WebmappProjectStructure $kProject) {
        $this->_verbose("Checking taxonomies for {$kProject->getRoot()}");
        $config = $this->_getConfig($kProject->getRoot());
        if (isset($config["multimap"]) && !!$config["multimap"]) {
            $taxonomies = isset($json["properties"]) && isset($json["properties"]["taxonomy"]) ? $json["properties"]["taxonomy"] : [];
            foreach (TAXONOMY_TYPES as $taxTypeId) {
                $aJson = null;
                $aJsonUrl = "{$this->aProject->getRoot()}/taxonomies/{$taxTypeId}.json";
                $kJson = null;
                $kJsonUrl = "{$kProject->getRoot()}/taxonomies/{$taxTypeId}.json";
                if (file_exists($aJsonUrl))
                    $aJson = json_decode(file_get_contents($aJsonUrl), true);
                if (file_exists($kJsonUrl)) {
                    $this->_lockFile($kJsonUrl);
                    $kJson = json_decode(file_get_contents($kJsonUrl), true);
                }

                if (!isset($aJson)) {
                    $this->_warning("The file {$aJsonUrl} is missing and should exists. Skipping the k {$taxTypeId} generation");

                    if (!$kJson) {
                        $this->_lockFile($kJsonUrl);
                        file_put_contents($kJsonUrl, json_encode([]));
                    }
                } else {
                    if (!$kJson) $kJson = [];
                    $taxArray = array_key_exists($taxTypeId, $taxonomies) ? $taxonomies[$taxTypeId] : [];
                    // Add post to its taxonomies
                    foreach ($taxArray as $taxId) {
                        $taxonomy = null;
                        $items = [];
                        if ($postType === "route")
                            $items[$postType] = [$this->id];

                        if (!isset($aJson[$taxId]))
                            $this->_warning("The taxonomy json file {$aJsonUrl} is missing the {$taxId} taxonomy.");
                        else {
                            $taxonomy = $aJson[$taxId];
                            if (isset($kJson[$taxId]["items"])) {
                                $items = $kJson[$taxId]["items"];
                                foreach ($items as $postTypeKey => $value) {
                                    if ($postTypeKey !== "route")
                                        unset($items[$postTypeKey]);
                                }

                                if ($postType === "route" && !isset($items[$postType])) {
                                    $items[$postType] = [];
                                    $items[$postType][] = $this->id;
                                    $items[$postType] = array_values(array_unique($items[$postType]));
                                }
                            }
                            $taxonomy["items"] = $items;
                            $kJson[$taxId] = $taxonomy;
                        }
                    }

                    // Remove post from its not taxonomies
                    foreach ($kJson as $taxId => $taxonomy) {
                        $items = isset($taxonomy["items"]) ? $taxonomy["items"] : [];
                        foreach ($items as $postTypeKey => $value) {
                            if ($postTypeKey !== "route")
                                unset($items[$postTypeKey]);
                        }

                        if (
                            $postType === "route" &&
                            !in_array($taxId, $taxArray) &&
                            isset($taxonomy["items"][$postType]) &&
                            is_array($taxonomy["items"][$postType]) &&
                            in_array($this->id, $taxonomy["items"][$postType])
                        ) {
                            $keys = array_keys($taxonomy["items"][$postType], $this->id);
                            foreach ($keys as $key) {
                                unset($kJson[$taxId]["items"][$postType][$key]);
                            }
                            if (count($taxonomy["items"][$postType]) == 0)
                                unset($kJson[$taxId]["items"][$postType]);
                            else
                                $kJson[$taxId]["items"][$postType] = array_values($kJson[$taxId]["items"][$postType]);
                        }
                    }

                    $this->_lockFile($kJsonUrl);
                    $this->_verbose("Writing $taxTypeId to $kJsonUrl");
                    file_put_contents($kJsonUrl, json_encode($kJson));
                }

                $this->_unlockFile($kJsonUrl);
            }
        } else
            $this->_verbose("Single map project found. No action needed");
    }

    /**
     * Clean the unneeded values of the given taxonomy
     *
     * @param array $taxonomy the taxonomy to clean
     *
     * @return array the resulted cleaned taxonomy
     */
    protected function _cleanTaxonomy(array $taxonomy): array {
        if (isset($taxonomy["featured_image"]) && is_array($taxonomy["featured_image"])) {
            if (isset($taxonomy["featured_image"]["sizes"]["large"])) {
                $taxonomy["image"] = $taxonomy["featured_image"]["sizes"]["large"];
            } else if (isset($taxonomy["featured_image"]["sizes"]["medium_large"])) {
                $taxonomy["image"] = $taxonomy["featured_image"]["sizes"]["medium_large"];
            } else if (isset($taxonomy["featured_image"]["sizes"]["medium"])) {
                $taxonomy["image"] = $taxonomy["featured_image"]["sizes"]["medium"];
            }
        } else if (isset($taxonomy["acf"]["featured_image"]) && is_array($taxonomy["acf"]["featured_image"])) {
            if (isset($taxonomy["acf"]["featured_image"]["sizes"]["large"])) {
                $taxonomy["image"] = $taxonomy["acf"]["featured_image"]["sizes"]["large"];
            } else if (isset($taxonomy["acf"]["featured_image"]["sizes"]["medium_large"])) {
                $taxonomy["image"] = $taxonomy["acf"]["featured_image"]["sizes"]["medium_large"];
            } else if (isset($taxonomy["acf"]["featured_image"]["sizes"]["medium"])) {
                $taxonomy["image"] = $taxonomy["acf"]["featured_image"]["sizes"]["medium"];
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
        $intProperties = ["zindex"];
        $stringProperties = [];

        foreach ($taxonomy as $key => $value) {
            if (is_null($value) || (is_string($value) && empty($value)) || $key === "_links") {
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
     * Return an associative array with the key as the property in the json and the value the property to map it in
     *
     * @param string $type the geometry type
     *
     * @return null | array with the property mapping
     */
    protected function _getCustomProperties(string $type): ?array {
        if ($type !== "poi" && $type !== "track" && $type !== "route")
            return null;

        $config = $this->_getConfig($this->aProject->getRoot());
        if (!isset($config["custom_mapping"]))
            return null;

        $properties = array();
        $custom_mapping = $config["custom_mapping"];

        $this->_verbose("  Custom mapping: " . json_encode($custom_mapping));

        // Map the global properties
        foreach ($custom_mapping as $key => $property) {
            if ($key !== "poi" && $key !== "track" && $key !== "route") {
                if (is_numeric($key)) {
                    $properties[$property] = $property;
                } else {
                    $properties[$key] = $property;
                }
            }
        }

        // Map the properties specific for the geometry type
        if (array_key_exists($type, $custom_mapping)) {
            foreach ($custom_mapping[$type] as $key => $property) {
                if (is_numeric($key)) {
                    $properties[$property] = $property;
                } else {
                    $properties[$key] = $property;
                }
            }
        }

        return $properties;
    }

    protected function _lockFile(string $url) {
        if (is_null($this->lockedFileUrl) && is_null($this->lockedFile)) {
            $this->_verbose("Locking file $url");
            $this->lockedFileUrl = $url;
            if (file_exists($this->lockedFileUrl)) {
                $this->lockedFile = fopen($this->lockedFileUrl, "rw+");
                if (flock($this->lockedFile, LOCK_EX))
                    $this->_verbose("Locked successfully");
            }
        } elseif ($this->lockedFileUrl === $url && !is_null($this->lockedFile))
            $this->_verbose("File $url already locked");
        else
            $this->_warning("Trying to lock {$url} while there is already {$this->lockedFileUrl} locked");
    }

    protected function _unlockFile($url) {
        if ($this->lockedFileUrl === $url) {
            $this->_verbose("Unlocking file $url");
            if ($this->lockedFile) {
                flock($this->lockedFile, LOCK_UN);
                fclose($this->lockedFile);
            }
            $this->lockedFileUrl = null;
            $this->lockedFile = null;
        } elseif (is_null($this->lockedFileUrl))
            $this->_verbose("No file to unlock");
        elseif (!is_null($this->lockedFileUrl))
            $this->_warning("Trying to unlock {$url} while {$this->lockedFileUrl} is the locked file");
    }

    /**
     * Return an array with the mapping
     *
     * @return null | array with the property mapping
     */
    private function _getMapping(): ?array {
        $config = $this->_getConfig($this->aProject->getRoot());
        if (!isset($config["mapping"]))
            return null;

        $mapping = $config["mapping"];
        $this->_verbose("  Mapping: " . json_encode($mapping));

        return $mapping;
    }

    /**
     * Apply the mapping from the configuration file for the specified type
     *
     * @param WebmappAbstractFeature $feature
     * @param string                 $mappingKey
     * @param object|null            $relation
     * @param array|null             $oldGeojson
     */
    protected function _applyMapping(WebmappAbstractFeature $feature, string $mappingKey, object $relation = null, array $oldProperties = null) {
        $mapping = $this->_getMapping();
        if (isset($mapping) && is_array($mapping) && array_key_exists($mappingKey, $mapping) && is_array($mapping[$mappingKey])) {
            foreach ($mapping[$mappingKey] as $key => $mappingArray) {
                $value = "";
                if ($mappingKey !== "osm" || isset($relation)) {
                    if (is_array($mappingArray)) {
                        $values = [];
                        foreach ($mappingArray as $item) {
                            $strippedKey = strval($item);
                            if (substr($strippedKey, 0, 1) === "?")
                                $strippedKey = substr($strippedKey, 1);
                            if (substr($strippedKey, strlen($strippedKey) - 1, 1) === "?")
                                $strippedKey = substr($strippedKey, 0, strlen($strippedKey) - 1);

                            if (is_string($strippedKey) && substr($strippedKey, 0, 1) === "$") {
                                if ($mappingKey === "osm" && isset($relation) && $relation->hasTag(substr($strippedKey, 1)))
                                    $values[] = strval($relation->getTag(substr($strippedKey, 1)));
                                elseif ($mappingKey !== "osm" && $feature->hasProperty(substr($strippedKey, 1)))
                                    $values[] = strval($feature->getProperty(substr($strippedKey, 1)));
                                else
                                    $values[] = "";
                            } else
                                $values[] = strval($strippedKey);
                        }

                        foreach ($values as $i => $currentValue) {
                            $currentKey = $mappingArray[$i];
                            $previous = $i > 0 ? $values[$i - 1] : true;
                            $next = $i < count($values) - 1 ? $values[$i + 1] : true;

                            if (isset($currentValue)
                                && !(
                                    (substr($currentKey, 0, 1) === "?"
                                        && empty($previous))
                                    || (substr($currentKey, strlen($currentKey) - 1, 1) === "?"
                                        && empty($next))
                                )
                            )
                                $value .= $currentValue;
                        }
                    } else $value = strval($mappingArray);
                } elseif (isset($oldProperties[$key]))
                    $value = $oldProperties[$key];

                if (isset($value) && !empty($value))
                    $feature->addProperty($key, $value);
            }
        }
    }

    /**
     * Add the given json feature in the specified file
     *
     * @param string     $url  the file url
     * @param int        $id   the feature id
     * @param array|null $json the feature array. If null the route will be deleted from the file
     */
    protected function _updateRouteIndex(string $url, int $id, array $json = null) {
        $file = [
            "type" => "FeatureCollection",
            "features" => []
        ];
        $this->_lockFile($url);
        $this->_verbose("Updating route index from {$url}");
        if (file_exists($url)) {
            $file = json_decode(file_get_contents($url), true);
            if (is_null($file)) {
                $file = [
                    "type" => "FeatureCollection",
                    "features" => []
                ];
            }
            $done = false;
            if (isset($file["features"]) && is_array($file["features"])) {
                foreach ($file["features"] as $key => $feature) {
                    if (strval($feature["properties"]["id"]) === strval($id)) {
                        if (!is_null($json)) {
                            $file["features"][$key] = $json;
                        } else {
                            unset($file["features"][$key]);
                            $file["features"] = array_values($file["features"]);
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
        $this->_unlockFile($url);
    }

    /**
     * Return the last modified date for the given post
     *
     * @param int      $id           the post id
     * @param int|null $defaultValue the default last modified value
     *
     * @return false|int|void
     */
    protected function _getPostLastModified(int $id, int $defaultValue = null) {
        $lastModified = isset($defaultValue) ? $defaultValue : strtotime("now");
        $apiUrl = "{$this->instanceUrl}/wp-json/webmapp/v1/feature/last_modified/{$id}";
        $ch = $this->_getCurl($apiUrl);
        $modified = null;
        try {
            $modified = curl_exec($ch);
        } catch (Exception $e) {
            $this->_warning("An error occurred getting last modified date for post {$id}: " . $e->getMessage());

            return $defaultValue;
        }
        if (curl_getinfo($ch, CURLINFO_HTTP_CODE) != 200) {
            $this->_warning("The api {$apiUrl} seems unreachable: " . curl_error($ch));
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

    /**
     * Return the config of the given root project directory
     *
     * @param string $projectRoot the root directory of the project
     *
     * @return array|mixed the config
     */
    protected function _getConfig(string $projectRoot): array {
        $config = [];
        try {
            $configUrl = "{$projectRoot}/server/server.conf";
            if (file_exists($configUrl))
                $config = json_decode(file_get_contents($configUrl), true);
        } catch (Exception $e) {
        }
        if (is_null($config) || !is_array($config) || empty($config)) $config = [];

        return $config;
    }

    /**
     * Perform a store operation to hoqu
     *
     * @param string $job    the job name
     * @param array  $params the array of params
     *
     * @throws WebmappExceptionHoquRequest for any problem with HOQU (missing params)
     * @throws WebmappExceptionHttpRequest for any problem with connection
     */
    protected function _store(string $job, array $params) {
        $this->_verbose("Performing new Store operation to HOQU");

        if (!$this->hoquBaseUrl || !$this->storeToken) {
            throw new WebmappExceptionHoquRequest("Unable to perform a Store operation ({$this->instanceUrl}, {$job}, " . json_encode($params) . "). HOQU url or a store token are missing in the configuration");
        }
        $headers = [
            "Accept: application/json",
            "Authorization: Bearer {$this->storeToken}",
            "Content-Type:application/json",
        ];

        $payload = [
            "instance" => $this->instanceName,
            "job" => $job,
            "parameters" => $params,
        ];

        $url = "{$this->hoquBaseUrl}/api/store";

        $this->_verbose("Initializing POST curl using:");
        $this->_verbose("  url: {$url}");
        $this->_verbose("  payload: " . json_encode($payload));

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13");
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_exec($ch);

        if (curl_getinfo($ch, CURLINFO_HTTP_CODE) !== 201) {
            curl_close($ch);
            throw new WebmappExceptionHoquRequest("Unable to perform a Store operation ({$this->instanceUrl}, {$job}, " . json_encode($params) . "). HOQU is unreachable");
        }
        curl_close($ch);
    }

    /**
     * Prepare curl for a put request
     *
     * @param string     $url     the request url
     * @param array|null $headers the headers - optional
     *
     * @return false|resource
     */
    protected function _getCurl(string $url, array $headers = null) {
        if (!isset($headers)) {
            $headers = [];
        }

        $this->_verbose("Initializing GET curl using:");
        $this->_verbose("  url: {$url}");

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13");
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        return $ch;
    }

    private function _logHeader(): string {
        return date("Y-m-d H:i:s") . " - {$this->name} JOB | ";
    }

    protected function _title($message) {
        WebmappUtils::title($this->_logHeader() . $message);
    }

    protected function _verbose($message) {
        WebmappUtils::verbose($this->_logHeader() . $message);
    }

    protected function _success($message) {
        WebmappUtils::success($this->_logHeader() . $message);
    }

    protected function _message($message) {
        WebmappUtils::message($this->_logHeader() . $message);
    }

    protected function _warning($message) {
        WebmappUtils::warning($this->_logHeader() . $message);
    }

    protected function _error($message) {
        WebmappUtils::error($this->_logHeader() . $message);
    }
}
