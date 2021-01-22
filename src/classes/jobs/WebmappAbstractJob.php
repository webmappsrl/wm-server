<?php

define("TAXONOMY_TYPES", ["webmapp_category", "activity", "theme", "when", "where", "who"]);

abstract class WebmappAbstractJob
{
    protected $name; // Job name
    protected $instanceUrl; // Job instance url
    protected $instanceName; // Instance name
    protected $instanceCode; // Instance code
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
     * @param string $name
     * @param string $instanceUrl
     * @param string $params
     * @param bool $verbose
     * @throws WebmappExceptionNoDirectory
     * @throws WebmappExceptionParameterMandatory
     * @throws WebmappExceptionParameterError
     */
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

        if (strpos($this->instanceName, "/") >= 0) {
            $this->instanceName = explode("/", $this->instanceName)[0];
            $this->instanceUrl = str_replace("http(s)?://(.*)", "http$1://{$this->instanceName}", $this->instanceUrl);
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

    public function run()
    {
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
     * Return the current API value of the given taxonomy
     *
     * @param string $taxonomyType
     * @param string $id
     * @return mixed|void|null
     */
    protected function _getTaxonomy(string $taxonomyType, string $id)
    {
        $taxonomy = null;
        if (isset($this->cachedTaxonomies[$id])) {
            $taxonomy = $this->cachedTaxonomies[$id];
        } else {
            try {
                $taxonomy = WebmappUtils::getJsonFromApi("{$this->instanceUrl}/wp-json/wp/v2/{$taxonomyType}/{$id}");
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
     * @param array $json the json of the feature
     */
    protected function _setTaxonomies(string $postType, array $json)
    {
        $id = isset($json["properties"]) && isset($json["properties"]["id"]) ? $json["properties"]["id"] : null;
        if (!isset($id)) {
            return;
        }
        $taxonomies = isset($json["properties"]) && isset($json["properties"]["taxonomy"]) ? $json["properties"]["taxonomy"] : [];

        $this->_verbose("Taxonomies: " . json_encode($taxonomies));
        $this->_verbose("Checking taxonomies...");
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
                    $postType => [$id],
                ];
                $taxonomy = $this->_getTaxonomy($taxTypeId, $taxId);

                // Enrich the current taxonomy array
                if (array_key_exists($taxId, $taxonomyJson)) {
                    if (!isset($taxonomy))
                        $taxonomy = $taxonomyJson[$taxId];
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
                    in_array($id, $taxonomy["items"][$postType])
                ) {
                    $this->_verbose("Removing post from $taxId");
                    $keys = array_keys($taxonomyJson[$taxId]["items"][$postType], $id);
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
                        unset($taxonomyJson[$taxId]);
                        $cleanedIds[] = $taxId;
                    } else
                        $taxonomyJson[$taxId] = $tax;
                }
            }

            $this->_verbose("Writing $taxTypeId to {$taxonomyUrl}");
            file_put_contents($taxonomyUrl, json_encode($taxonomyJson));
            $this->_unlockFile($taxonomyUrl);

            foreach ($taxArray as $taxId) {
                $this->_verbose("Checking {$taxTypeId} {$taxId} taxonomy term feature collection");
                $geojsonUrl = "{$this->aProject->getRoot()}/taxonomies/{$taxId}.geojson";
                $taxonomyGeojson = [
                    "type" => "FeatureCollection",
                    "features" => [],
                    "properties" => $taxonomyJson[$taxId]
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
                    if (isset($taxonomyGeojson["features"][$key]["properties"]["id"]) && strval($taxonomyGeojson["features"][$key]["properties"]["id"]) === strval($id))
                        $found = true;
                    else
                        $key++;
                }

                if ($found)
                    $taxonomyGeojson["features"][$key] = $json;
                else
                    $taxonomyGeojson["features"][] = $json;

                $taxonomyGeojson["features"] = array_values($taxonomyGeojson["features"]);

                $this->_lockFile($geojsonUrl);
                $this->_verbose("Writing {$taxTypeId} {$taxId} taxonomy term feature collection to {$geojsonUrl}");
                file_put_contents($geojsonUrl, json_encode($taxonomyGeojson));
                $this->_unlockFile($geojsonUrl);
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
                            if (isset($taxonomyGeojson["features"][$key]["properties"]["id"]) && strval($taxonomyGeojson["features"][$key]["properties"]["id"]) === strval($id))
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
     * Clean the unneeded values of the given taxonomy
     *
     * @param array $taxonomy the taxonomy to clean
     * @return array the resulted cleaned taxonomy
     */
    protected function _cleanTaxonomy(array $taxonomy): array
    {
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
     * @return null | array with the property mapping
     */
    protected function _getCustomProperties(string $type): ?array
    {
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

    protected function _lockFile(string $url)
    {
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

    protected function _unlockFile($url)
    {
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
    private function _getMapping(): ?array
    {
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
     * @param string $mappingKey
     * @param object|null $relation
     * @param array|null $oldGeojson
     */
    protected function _applyMapping(WebmappAbstractFeature $feature, string $mappingKey, object $relation = null, array $oldGeojson = null)
    {
        $mapping = $this->_getMapping();
        if (isset($mapping) && is_array($mapping) && array_key_exists($mappingKey, $mapping) && is_array($mapping[$mappingKey])) {
            foreach ($mapping[$mappingKey] as $key => $mappingArray) {
                $value = "";
                if ($mappingKey === "osm" && isset($relation)) {
                    if (is_array($mappingArray)) {
                        foreach ($mappingArray as $item) {
                            if (is_string($item) && substr($item, 0, 1) === "$") {
                                if ($mappingKey === "osm" && isset($relation) && $relation->hasTag(substr($item, 1)))
                                    $value .= strval($relation->getTag(substr($item, 1)));
                                elseif ($feature->hasProperty(substr($item, 1)))
                                    $value .= strval($feature->getProperty(substr($item, 1)));
                            } else
                                $value .= strval($item);
                        }
                    } else $value = strval($mappingArray);
                } elseif (isset($oldGeojson[$mappingKey]))
                    $value = $oldGeojson[$mappingKey];

                $feature->addProperty($key, $value);
            }
        }
    }

    /**
     * Add the given json feature in the specified file
     *
     * @param string $url the file url
     * @param int $id the feature id
     * @param array|null $json the feature array. If null the route will be deleted from the file
     */
    protected function _updateRouteIndex(string $url, int $id, array $json = null)
    {
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
     * @param int $id the post id
     * @param int|null $defaultValue the default last modified value
     * @return false|int|void
     */
    protected function _getPostLastModified(int $id, int $defaultValue = null)
    {
        $lastModified = isset($defaultValue) ? $defaultValue : strtotime("now");
        $apiUrl = "{$this->instanceUrl}/wp-json/webmapp/v1/feature/last_modified/{$id}";
        $ch = $this->_getCurl($apiUrl);
        $modified = null;
        try {
            $modified = curl_exec($ch);
        } catch (Exception $e) {
            $this->_warning("An error occurred getting last modified date for track {$id}: " . $e->getMessage());
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
     * @return array|mixed the config
     */
    protected function _getConfig(string $projectRoot): array
    {
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
     * @param string $job the job name
     * @param array $params the array of params
     *
     * @throws WebmappExceptionHoquRequest for any problem with HOQU (missing params)
     * @throws WebmappExceptionHttpRequest for any problem with connection
     */
    protected function _store(string $job, array $params)
    {
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
            throw new WebmappExceptionHttpRequest("Unable to perform a Store operation ({$this->instanceUrl}, {$job}, " . json_encode($params) . "). HOQU is unreachable");
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

    private function _logHeader(): string
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
