<?php

class WebmappUpdateTaxonomyJob extends WebmappAbstractJob
{
    /**
     * WebmappUpdateTaxonomyJob constructor.
     * @param string $instanceUrl containing the instance url
     * @param string $params containing an encoded JSON with the poi ID
     * @param bool $verbose
     * @throws WebmappExceptionNoDirectory
     */
    public function __construct(string $instanceUrl, string $params, bool $verbose = false)
    {
        parent::__construct("update_taxonomy", $instanceUrl, $params, $verbose);
    }

    protected function process()
    {
        $taxonomyType = null;
        $taxonomyMetadata = null;
        foreach (TAXONOMY_TYPES as $taxType) {
            $jsonUrl = "{$this->aProject->getRoot()}/taxonomies/{$taxType}.json";
            if (file_exists($jsonUrl)) {
                $this->_verbose("Checking $taxType file $jsonUrl");
                $json = json_decode(file_get_contents($jsonUrl), true);

                if (is_array($json) && array_key_exists($this->id, $json)) {
                    $this->_verbose("Taxonomy found in $taxType file. Updating metadata");
                    $taxonomyType = $taxType;
                    $currentItems = is_array($json[$this->id]["items"]) ? $json[$this->id]["items"] : [];
                    if (count($currentItems) > 0) {
                        $taxonomyMetadata = $this->_getTaxonomy($taxonomyType, $this->id);
                        $newTaxonomy = $taxonomyMetadata;
                        if ($newTaxonomy) {
                            $newTaxonomy["items"] = $currentItems;
                            $json[$this->id] = $this->_cleanTaxonomy($newTaxonomy);
                        }
                    } else
                        unset($json[$this->id]);

                    $this->_verbose("Updating file $jsonUrl");
                    file_put_contents($jsonUrl, json_encode($json));
                    break;
                }
            }
        }

        if ($taxonomyType && $taxonomyMetadata) {
            $collectionUrl = "{$this->aProject->getRoot()}/taxonomies/{$this->id}.geojson";
            if (file_exists($collectionUrl)) {
                $this->_verbose("Updating feature collection file {$collectionUrl}");
                $json = json_decode(file_get_contents($collectionUrl), true);
                if (is_array($json) && array_key_exists("properties", $json)) {
                    $newMetadata = $this->_cleanTaxonomy($taxonomyMetadata);
                    $newMetadata["count"] = is_array($json["features"]) ? count($json["features"]) : 0;
                    $json["properties"] = $newMetadata;
                    file_put_contents($collectionUrl, json_encode($json));
                }
            }

            $this->_updateKTaxonomy($this->id, $taxonomyType, $taxonomyMetadata);
        } else
            $this->_verbose("The taxonomy with id {$this->id} has no related features. There is no need to update it");
    }

    /**
     * Update the taxonomy in the k projects
     *
     * @param string $id
     * @param string $taxonomyType
     * @param array $taxonomyMetadata
     */
    private function _updateKTaxonomy(string $id, string $taxonomyType, array $taxonomyMetadata)
    {
        foreach ($this->kProjects as $kProject) {
            $jsonUrl = "{$kProject->getRoot()}/taxonomies/{$taxonomyType}.json";
            if (file_exists($jsonUrl)) {
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
            }
        }
    }
}