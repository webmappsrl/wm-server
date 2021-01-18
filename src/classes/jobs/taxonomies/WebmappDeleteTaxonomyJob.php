<?php

class WebmappDeleteTaxonomyJob extends WebmappAbstractJob
{
    /**
     * WebmappDeleteTaxonomyJob constructor.
     * @param string $instanceUrl containing the instance url
     * @param string $params containing an encoded JSON with the poi ID
     * @param bool $verbose
     * @throws WebmappExceptionNoDirectory
     */
    public function __construct(string $instanceUrl, string $params, bool $verbose = false)
    {
        parent::__construct("delete_taxonomy", $instanceUrl, $params, $verbose);
    }

    protected function process()
    {
        $taxonomyType = null;
        $items = [];
        foreach (TAXONOMY_TYPES as $taxType) {
            $jsonUrl = "{$this->aProject->getRoot()}/taxonomies/{$taxType}.json";
            if (file_exists($jsonUrl)) {
                $this->_lockFile($jsonUrl);
                $this->_verbose("Checking $taxType file $jsonUrl");
                $json = json_decode(file_get_contents($jsonUrl), true);

                if (is_array($json) && array_key_exists($this->id, $json)) {
                    $this->_verbose("Taxonomy found in $taxType file");
                    $taxonomyType = $taxType;

                    $ch = $this->_getCurl($this->wp->getApiUrl() . "/{$taxonomyType}/{$this->id}");
                    curl_exec($ch);

                    if (curl_getinfo($ch, CURLINFO_HTTP_CODE) < 400) {
                        throw new WebmappExceptionTaxonomyStillExists("The taxonomy seems to be still public. Deletion stopped to prevent data loss");
                    }

                    if (isset($json[$this->id]["items"]) && is_array($json[$this->id]["items"]) && count($json[$this->id]["items"]) > 0)
                        $items = $json[$this->id]["items"];
                    unset($json[$this->id]);

                    $this->_verbose("Deleting taxonomy $this->id from $jsonUrl");
                    file_put_contents($jsonUrl, json_encode($json));
                    $this->_unlockFile($jsonUrl);
                    break;
                }
                $this->_unlockFile($jsonUrl);
            }
        }

        $collectionUrl = "{$this->aProject->getRoot()}/taxonomies/{$this->id}.geojson";
        if (file_exists($collectionUrl)) {
            $this->_lockFile($collectionUrl);
            $this->_verbose("Deleting feature collection file {$collectionUrl}");
            unlink($collectionUrl);
            $this->_unlockFile($collectionUrl);
        }

        if ($taxonomyType) {
            if (is_array($items) && count($items) > 0) {
                $this->_verbose("Deleting taxonomy from features");

                $this->_deleteTaxonomyFromFeatures($this->id, $taxonomyType, $items);
            }
        }
    }

    /**
     * Remove the given taxonomy from the given features files
     *
     * @param string $id the taxonomy id
     * @param string $taxonomyType
     * @param array $features
     */
    private function _deleteTaxonomyFromFeatures(string $id, string $taxonomyType, array $features)
    {
        if (is_array($features) && count($features) > 0) {
            foreach ($features as $postType => $featuresIds) {
                if (is_array($featuresIds) && count($featuresIds) > 0) {
                    foreach ($featuresIds as $featureId) {
                        $jsonUrl = "{$this->aProject->getRoot()}/geojson/{$featureId}.geojson";
                        $this->_deleteTaxonomyFromGeojson($jsonUrl, $id, $taxonomyType, [$featureId]);
                    }
                }
            }

            if (isset($features["route"]) && is_array($features["route"]) && count($features["route"]) > 0) {
                $jsonUrl = "{$this->aProject->getRoot()}/geojson/route_index.geojson";
                $this->_deleteTaxonomyFromGeojson($jsonUrl, $id, $taxonomyType, $features["route"]);
                $jsonUrl = "{$this->aProject->getRoot()}/geojson/full_geometry_route_index.geojson";
                $this->_deleteTaxonomyFromGeojson($jsonUrl, $id, $taxonomyType, $features["route"]);

                foreach ($this->kProjects as $kProject) {
                    $jsonUrl = "{$kProject->getRoot()}/routes/route_index.geojson";
                    if (file_exists($jsonUrl)) {
                        $this->_deleteTaxonomyFromGeojson($jsonUrl, $id, $taxonomyType, $features["route"]);
                    }
                    $jsonUrl = "{$kProject->getRoot()}/routes/full_geometry_route_index.geojson";
                    if (file_exists($jsonUrl)) {
                        $this->_deleteTaxonomyFromGeojson($jsonUrl, $id, $taxonomyType, $features["route"]);
                    }
                }
            }
        }
    }

    /**
     * Remove the given taxonomy from the given features in the given geojson
     * @param string $url
     * @param string $taxonomyId
     * @param string $taxonomyType
     * @param array $featuresIds
     */
    private function _deleteTaxonomyFromGeojson(string $url, string $taxonomyId, string $taxonomyType, array $featuresIds)
    {
        if (file_exists($url)) {
            $this->_verbose("Checking file {$url}");
            $this->_lockFile($url);
            $json = json_decode(file_get_contents($url), true);
            if (isset($json["type"]) &&
                ($json["type"] === "Feature" ||
                    $json["type"] === "FeatureCollection")) {
                if (isset($json["properties"]["taxonomy"][$taxonomyType]) &&
                    is_array($json["properties"]["taxonomy"][$taxonomyType]) &&
                    in_array($taxonomyId, $json["properties"]["taxonomy"][$taxonomyType])) {
                    $taxonomies = $json["properties"]["taxonomy"][$taxonomyType];
                    $keys = array_keys($taxonomies, $taxonomyId);
                    foreach ($keys as $key) {
                        unset($taxonomies[$key]);
                    }
                    $taxonomies = array_values(array_unique($taxonomies));
                    $json["properties"]["taxonomy"][$taxonomyType] = $taxonomies;

                    if (count($json["properties"]["taxonomy"][$taxonomyType]) === 0)
                        unset($json["properties"]["taxonomy"][$taxonomyType]);
                }
                if ($json["type"] === "FeatureCollection" &&
                    isset($json["features"]) &&
                    is_array($json["features"]) &&
                    count($json["features"]) > 0) {
                    $this->_verbose("File is a feature collection. Searching and updating features " . json_encode($featuresIds));
                    foreach ($json["features"] as $key => $feature) {
                        if (isset($feature["properties"]["id"]) &&
                            in_array($feature["properties"]["id"], $featuresIds) &&
                            isset($feature["properties"]["taxonomy"][$taxonomyType]) &&
                            is_array($feature["properties"]["taxonomy"][$taxonomyType]) &&
                            count($feature["properties"]["taxonomy"][$taxonomyType]) > 0) {
                            $taxonomies = $feature["properties"]["taxonomy"][$taxonomyType];
                            $keys = array_keys($taxonomies, $taxonomyId);
                            if (count($keys) > 0)
                                $this->_verbose("  Removing taxonomy from feature {$feature["properties"]["id"]}");

                            foreach ($keys as $key) {
                                unset($taxonomies[$key]);
                            }
                            $taxonomies = array_values(array_unique($taxonomies));
                            $json["features"][$key]["properties"]["taxonomy"][$taxonomyType] = $taxonomies;

                            if (count($json["features"][$key]["properties"]["taxonomy"][$taxonomyType]) === 0)
                                unset($json["features"][$key]["properties"]["taxonomy"][$taxonomyType]);
                            if (count($json["features"][$key]["properties"]["taxonomy"]) === 0) {
                                $this->_verbose("    The feature {$feature["properties"]["id"]} has no more taxonomies. Removing from file {$url}");
                                unset($json["features"][$key]);
                                $json["features"] = array_values($json["features"]);
                            }
                        }
                    }
                }
            }

            $this->_verbose("Check complete. Overwriting file {$url}");
            file_put_contents($url, json_encode($json));
            $this->_unlockFile($url);
        }
    }
}