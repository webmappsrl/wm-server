<?php

class WebmappUpdatePoiJob extends WebmappAbstractJob
{
    /**
     * WebmappUpdatePoiJob constructor.
     * @param $instanceUrl string containing the instance url
     * @param $params string containing an encoded JSON with the poi ID
     * @param false $verbose
     */
    public function __construct($instanceUrl, $params, $verbose = false)
    {
        parent::__construct("update_poi", $instanceUrl, $params, $verbose);
    }

    protected function process()
    {
        if ($this->verbose) {
            WebmappUtils::verbose("Running process...");
        }

        $aBase = "{$this->aProject->getRoot()}";
        $id = intval($this->params['id']);

        try {
            // Load poi from be
            if ($this->verbose) {
                WebmappUtils::verbose("Loading poi from {$this->instanceUrl}/wp-json/wp/v2/poi/{$id}...");
            }
            $poi = new WebmappPoiFeature("$this->instanceUrl/wp-json/wp/v2/poi/{$id}");
            $json = json_decode($poi->getJson(), true);

            // Write geojson
            if ($this->verbose) {
                WebmappUtils::verbose("Writing poi to {$aBase}/geojson/{$id}.geojson...");
            }
            file_put_contents("{$aBase}/geojson/{$id}.geojson", $poi->getJson());

            // Get taxonomies object from geojson
            $taxonomies = isset($json["properties"]) && isset($json["properties"]["taxonomy"]) ? $json["properties"]["taxonomy"] : [];
            $taxonomyTypes = ["webmapp_category", "activity", "theme", "when", "where", "who"];

            if ($this->verbose) {
                WebmappUtils::verbose("Checking taxonomies...");
            }
            foreach ($taxonomyTypes as $taxTypeId) {
                $taxonomyJson = null;
                if (file_exists("{$aBase}/taxonomies/{$taxTypeId}.json")) {
                    $taxonomyJson = file_get_contents("{$aBase}/taxonomies/{$taxTypeId}.json");
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
                        $postTypeArray = array_key_exists("poi", $items) ? $items["poi"] : [];

                        if (!in_array($id, $postTypeArray)) {
                            $postTypeArray[] = $id;
                        }

                        $items["poi"] = $postTypeArray;
                        $taxonomy["items"] = $items;
                    } // Taxonomy does not exists - download and add it
                    else {
                        $taxonomy = WebmappUtils::getJsonFromApi("{$this->instanceUrl}/wp-json/wp/v2/{$taxTypeId}/{$taxId}");
                        $taxonomy["items"] = [
                            "poi" => [$id]
                        ];
                    }
                    $taxonomyJson[$taxId] = $taxonomy;
                }

                // Remove poi from its not taxonomies
                foreach ($taxonomyJson as $taxId => $taxonomy) {
                    if (
                        !in_array($taxId, $taxArray) &&
                        array_key_exists("items", $taxonomy) &&
                        array_key_exists("poi", $taxonomy["items"]) &&
                        is_array($taxonomy["items"]["poi"]) &&
                        in_array($id, $taxonomy["items"]["poi"])
                    ) {
                        $keys = array_keys($taxonomy["items"]["poi"], $id);
                        foreach ($keys as $key) {
                            unset($taxonomy["items"]["poi"][$key]);
                        }
                        if (count($taxonomy["items"]["poi"]) == 0) {
                            unset($taxonomy["items"]["poi"]);
                        }
                        if (count($taxonomy["items"]) == 0) {
                            unset($taxonomyJson[$taxId]);
                        } else {
                            $taxonomyJson[$taxId] = $taxonomy;
                        }
                    }
                }
                file_put_contents("{$aBase}/taxonomies/{$taxTypeId}.json", json_encode($taxonomyJson));
            }
        } catch (WebmappExceptionPOINoCoodinates $e) {
            throw new WebmappExceptionPOINoCoodinates("The poi with id {$id} is missing the coordinates");
        } catch (WebmappExceptionHttpRequest $e) {
            throw new WebmappExceptionHttpRequest("The instance $this->instanceUrl is unreachable or the poi with id {$id} does not exists");
        } catch (Exception $e) {
            throw new WebmappException("An unknown error occurred: " . json_encode($e));
        }

        if ($this->verbose) {
            WebmappUtils::verbose("Process completed");
        }
    }
}