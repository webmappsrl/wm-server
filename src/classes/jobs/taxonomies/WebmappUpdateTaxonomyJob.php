<?php

class WebmappUpdateTaxonomyJob extends WebmappAbstractJob {
    /**
     * WebmappUpdateTaxonomyJob constructor.
     *
     * @param string $instanceUrl containing the instance url
     * @param string $params      containing an encoded JSON with the poi ID
     * @param bool   $verbose
     *
     * @throws WebmappExceptionNoDirectory
     * @throws WebmappExceptionParameterError
     * @throws WebmappExceptionParameterMandatory
     */
    public function __construct(string $instanceUrl, string $params, bool $verbose = false) {
        parent::__construct("update_taxonomy", $instanceUrl, $params, $verbose);
    }

    protected function process() {
        $taxonomyType = null;
        $taxonomyMetadata = null;
        $validIds = [];
        foreach (TAXONOMY_TYPES as $taxType) {
            $cleanedIds = [];
            $jsonUrl = "{$this->aProject->getRoot()}/taxonomies/{$taxType}.json";
            if (file_exists($jsonUrl)) {
                $this->_lockFile($jsonUrl);
                $this->_verbose("Checking $taxType file $jsonUrl");
                $json = json_decode(file_get_contents($jsonUrl), true);

                if (is_array($json)) {
                    if (array_key_exists($this->id, $json)) {
                        $this->_verbose("Taxonomy found in $taxType file. Updating metadata");
                        $taxonomyType = $taxType;
                        $currentItems = is_array($json[$this->id]["items"]) ? $json[$this->id]["items"] : [];
                        $isParent = $this->_isParentTaxonomy($this->id, $json);
                        if (count($currentItems) > 0 || $isParent) {
                            $taxonomyMetadata = $this->_getTaxonomy($taxonomyType, $this->id);
                            $newTaxonomy = $taxonomyMetadata;
                            if ($newTaxonomy) {
                                $newTaxonomy["items"] = $currentItems;
                                $json[$this->id] = $this->_cleanTaxonomy($newTaxonomy);
                            }
                        } else
                            unset($json[$this->id]);
                    }

                    $this->_verbose("Cleaning empty taxonomies in $jsonUrl");
                    foreach ($json as $id => $taxonomy) {
                        $clean = true;
                        if (isset($taxonomy["items"]) && is_array($taxonomy["items"]) && count($taxonomy["items"]) > 0) {
                            foreach ($taxonomy["items"] as $postTypeArray) {
                                if (isset($postTypeArray) && is_array($postTypeArray) && count($postTypeArray)) {
                                    $clean = false;
                                    break;
                                }
                            }
                        }
                        if ($clean) {
                            $isParent = $this->_isParentTaxonomy($id, $json);

                            if (!$isParent) {
                                unset($json[$id]);
                                $cleanedIds[] = $id;
                            }
                        }
                    }

                    $this->_verbose("Updating file $jsonUrl");
                    file_put_contents($jsonUrl, json_encode($json));
                }

                $this->_unlockFile($jsonUrl);

                $this->_verbose("Cleaning empty taxonomies in $taxType");
                if (count($cleanedIds) > 0) {
                    foreach ($cleanedIds as $taxId) {
                        $geojsonUrl = "{$this->aProject->getRoot()}/taxonomies/{$taxId}.geojson";
                        if (file_exists($geojsonUrl)) {
                            $this->_lockFile($geojsonUrl);
                            $this->_verbose("Cleaning {$taxType} {$taxId} taxonomy term feature collection since it is empty");
                            unlink($geojsonUrl);
                            $this->_unlockFile($geojsonUrl);
                        }
                    }
                }

                array_push($validIds, ...array_keys($json));

                //                if (is_array($json) && array_key_exists($this->id, $json))
                //                    break;
            }
        }

        $this->_cleanKTaxonomies($validIds);

        $this->_warning($taxonomyType);

        if ($taxonomyType && $taxonomyMetadata) {
            $collectionUrl = "{$this->aProject->getRoot()}/taxonomies/{$this->id}.geojson";
            $json = null;
            if (file_exists($collectionUrl)) {
                $this->_lockFile($collectionUrl);
                $json = json_decode(file_get_contents($collectionUrl), true);
            } else
                $json = [
                    "type" => "FeatureCollection",
                    "features" => [],
                    "properties" => []
                ];

            $this->_verbose("Updating feature collection file {$collectionUrl}");
            if (is_array($json) && array_key_exists("properties", $json)) {
                $newMetadata = $this->_cleanTaxonomy($taxonomyMetadata);
                $newMetadata["count"] = is_array($json["features"]) ? count($json["features"]) : 0;
                $json["properties"] = $newMetadata;
                file_put_contents($collectionUrl, json_encode($json));
            }
            $this->_unlockFile($collectionUrl);

            $this->_updateKTaxonomy($this->id, $taxonomyType, $taxonomyMetadata);
        } else
            $this->_verbose("The taxonomy with id {$this->id} has no related features. There is no need to update it");
    }

    /**
     * Update the taxonomy in the k projects
     *
     * @param string $id
     * @param string $taxonomyType
     * @param array  $taxonomyMetadata
     */
    private function _updateKTaxonomy(string $id, string $taxonomyType, array $taxonomyMetadata) {
        foreach ($this->kProjects as $kProject) {
            $config = $this->_getConfig($kProject->getRoot());
            if (isset($config) && is_array($config) && isset($config["multimap"]) && $config["multimap"] === true) {
                $jsonUrl = "{$kProject->getRoot()}/taxonomies/{$taxonomyType}.json";
                if (file_exists($jsonUrl)) {
                    $this->_lockFile($jsonUrl);
                    $json = json_decode(file_get_contents($jsonUrl), true);
                    if (array_key_exists($id, $json)) {
                        $currentItems = is_array($json[$id]["items"]) ? $json[$id]["items"] : [];
                        if (count($currentItems) > 0) {
                            $newTaxonomy = $taxonomyMetadata;
                            if ($newTaxonomy) {
                                $newTaxonomy["items"] = $currentItems;
                                $json[$id] = $this->_cleanTaxonomy($newTaxonomy);
                            }
                        } else
                            unset($json[$id]);

                        $this->_verbose("Updating file $jsonUrl");
                        file_put_contents($jsonUrl, json_encode($json));
                    }
                    $this->_unlockFile($jsonUrl);
                }
            }
        }
    }

    /**
     * Remove the unused taxonomies from the multimap k projects
     *
     * @param array $validIds
     */
    private function _cleanKTaxonomies(array $validIds = []) {
        foreach ($this->kProjects as $kProject) {
            $config = $this->_getConfig($kProject->getRoot());
            if (isset($config) && is_array($config) && isset($config["multimap"]) && $config["multimap"] === true) {
                $this->_verbose("Cleaning taxonomies in {$kProject->getRoot()}");
                foreach (TAXONOMY_TYPES as $taxonomyType) {
                    $jsonUrl = "{$kProject->getRoot()}/taxonomies/{$taxonomyType}.json";
                    if (file_exists($jsonUrl)) {
                        $this->_verbose("Checking $taxonomyType in $jsonUrl");
                        $this->_lockFile($jsonUrl);
                        $json = json_decode(file_get_contents($jsonUrl), true);
                        $updated = false;
                        foreach ($json as $taxonomyId => $taxonomy) {
                            $this->_warning($taxonomyId);
                            if (!in_array($taxonomyId, $validIds)) {
                                unset($json[$taxonomyId]);
                                $updated = true;
                            }
                        }
                        if ($updated) {
                            $this->_verbose("Updating file $jsonUrl");
                            file_put_contents($jsonUrl, json_encode($json));
                        }
                        $this->_unlockFile($jsonUrl);
                    }
                }
            }
        }
    }
}
