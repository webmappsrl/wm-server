<?php

class WebmappOverpassQueryTask extends WebmappAbstractTask
{
    private $_path;
    private $_query;
    private $_layerName;
    private $_mapping;

    public function check()
    {
        if (!array_key_exists('query', $this->options)) {
            throw new WebmappExceptionParameterMandatory("Missing mandatory parameter: 'query'", 1);
        }

        if (!array_key_exists('layer_name', $this->options)) {
            throw new WebmappExceptionParameterMandatory("Missing mandatory parameter: 'layer_name'", 1);
        }

        $this->_query = $this->options["query"];
        $this->_layerName = $this->options["layer_name"];
        $this->_path = $this->project_structure->getRoot();

        if (array_key_exists('mapping', $this->options) && is_array($this->options["mapping"])) {
            $this->_mapping = $this->options["mapping"];
        }

        if (!preg_match("/^(\[out:json\])?\[timeout:[0-9]{1,3}\](\[out:json\])?;/", $this->_query)) {
            $this->_query = "[out:json][timeout:25];" . $this->_query;
        }

        if (!preg_match("/out body;(\n|\\n|\s)?>;(\n|\\n|\s)?out skel qt;(\n|\\n\s)?$/", $this->_query)) {
            $this->_query = $this->_query . "out body; >; out skel qt;";
        }

        return true;
    }

    public function process()
    {
        try {
            $json = $this->getOverpassJson();

            $geojson = [
                "type" => "FeatureCollection",
                "features" => []
            ];

            if (array_key_exists("elements", $json) && is_array($json["elements"])) {
                $id = 1;
                foreach ($json["elements"] as $item) {
                    if (is_array($item) &&
                        array_key_exists("lat", $item) &&
                        array_key_exists("lon", $item) &&
                        array_key_exists("tags", $item) &&
                        is_array($item["tags"]) &&
                        array_key_exists("name", $item["tags"])) {
                        $feature = [
                            "type" => "Feature",
                            "geometry" => [
                                "type" => "Point",
                                "coordinates" => [
                                    floatval($item["lon"]),
                                    floatval($item["lat"])
                                ]
                            ],
                            "properties" => [
                                "id" => $id,
                                "name" => strval($item["tags"]["name"])
                            ]
                        ];

                        $propertiesToMap = ["ref" => "ref", "operator" => "operator"];

                        foreach ($propertiesToMap as $key => $property) {
                            if (array_key_exists($property, $item["tags"])) {
                                $feature["properties"][$key] = $item["tags"][$property];
                            }
                        }

                        if (is_array($this->_mapping)) {
                            foreach ($this->_mapping as $key => $mappingArray) {
                                $value = "";
                                if (is_array($mappingArray)) {
                                    foreach ($mappingArray as $item) {
                                        if (is_string($item) && substr($item, 0, 1) === "$") {
                                            if (array_key_exists(substr($item, 1), $feature["properties"])) {
                                                $value .= strval($feature["properties"][substr($item, 1)]);
                                            }
                                        } else {
                                            $value .= strval($item);
                                        }
                                    }
                                } else $value = strval($mappingArray);

                                $feature["properties"][$key] = $value;
                            }
                        }

                        if (isset($item["tags"]["wikimedia_commons"])) {
                            $filename = $item["tags"]["wikimedia_commons"];
                            $url = null;
                            try {
                                $apiJson = json_decode(file_get_contents("https://commons.wikimedia.org/w/api.php?action=query&titles={$filename}&format=json&prop=imageinfo&iiprop=url&iilimit=1"), true);
                                if (isset($apiJson) &&
                                    is_array($apiJson) &&
                                    isset($apiJson["query"]) &&
                                    isset($apiJson["query"]["pages"]) &&
                                    is_array($apiJson["query"]["pages"]) &&
                                    count($apiJson["query"]["pages"]) > 0
                                ) {
                                    $key = array_key_first($apiJson["query"]["pages"]);
                                    if (isset($apiJson["query"]["pages"][$key]) &&
                                        isset($apiJson["query"]["pages"][$key]["imageinfo"]) &&
                                        is_array($apiJson["query"]["pages"][$key]["imageinfo"]) &&
                                        count($apiJson["query"]["pages"][$key]["imageinfo"]) > 0) {
                                        $imageKey = array_key_first($apiJson["query"]["pages"][$key]["imageinfo"]);
                                        if (isset($apiJson["query"]["pages"][$key]["imageinfo"][$imageKey]) &&
                                            isset($apiJson["query"]["pages"][$key]["imageinfo"][$imageKey]["url"])) {
                                            $url = $apiJson["query"]["pages"][$key]["imageinfo"][$imageKey]["url"];
                                        }
                                    }
                                }
                            } catch (Exception $e) {
                                echo "Error getting wikimedia file: {$e->getMessage()}\n";
                            }

                            if (isset($url)) {
                                if (!isset($feature["properties"]["image"])) {
                                    $feature["properties"]["image"] = $url;
                                } else {
                                    if (!isset($feature["properties"]["imageGallery"])) {
                                        $feature["properties"]["imageGallery"] = [];
                                    }
                                    $feature["properties"]["imageGallery"][] = [
                                        "id" => $url,
                                        "src" => $url,
                                        "caption" => ""
                                    ];
                                }
                            }
                        }

                        $geojson["features"][] = $feature;

                        $id++;
                    }
                }
            }

            if (count($geojson["features"]) > 0) {
                $fileUrl = "{$this->_path}/geojson/{$this->_layerName}.geojson";
                file_put_contents($fileUrl, json_encode($geojson));
            }

            return true;
        } catch (Exception $e) {
            echo $e->getMessage() . "\n";
        }
    }

    /**
     * Execute the given overpass query
     *
     * @return array
     * @throws WebmappException if any error occurs during the request
     */
    private function getOverpassJson()
    {
        $url = "https://overpass-api.de/api/interpreter";
        $headers = [
            "Content-Type: application/x-www-form-urlencoded"
        ];

        $payload = [
            "data" => $this->_query
        ];

        $payloadString = "";

        foreach ($payload as $key => $value) {
            if (is_string($value) && strlen($value) > 0) {
                if (strlen($payloadString) > 0) $payloadString .= "&";
                $payloadString .= "{$key}=" . urlencode($value);
            }
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payloadString);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        if (curl_getinfo($ch, CURLINFO_HTTP_CODE) != 200) {
            throw new WebmappException("An error " . curl_getinfo($ch, CURLINFO_HTTP_CODE) . " occurred while calling {$url}: " . curl_error($ch));
        }
        $result = json_decode($result, true);
        return $result;
    }
}