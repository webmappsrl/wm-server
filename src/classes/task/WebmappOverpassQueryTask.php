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
                $ways = [];
                $nodes = [];
                $id = 1;
                foreach ($json["elements"] as $item) {
                    if (is_array($item)) {
                        if (isset($item["type"]) && $item["type"] === "way") {
                            if (array_key_exists("nodes", $item))
                                $ways[] = $item;
                        } elseif (array_key_exists("lat", $item) && array_key_exists("lon", $item)) {
                            $geometry = [
                                "type" => "Point",
                                "coordinates" => [
                                    floatval($item["lon"]),
                                    floatval($item["lat"])
                                ]
                            ];

                            if (isset($item["tags"]) && is_array($item["tags"])) {
                                $feature = $this->getFeature($item, $geometry, $id);
                                if (isset($feature["properties"]["name"])) {
                                    $geojson["features"][] = $feature;
                                    $id++;
                                }
                            }
                            if (isset($item["id"]))
                                $nodes[$item["id"]] = $geometry;
                        }
                    }
                }

                foreach ($ways as $way) {
                    $geometry = $this->getPoint($way, $nodes);
                    if (isset($geometry)) {
                        $feature = $this->getFeature($way, $geometry, $id);
                        if (isset($feature["properties"]["name"])) {
                            $geojson["features"][] = $feature;
                            $id++;
                        }
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
     * Return a geojson geometry point given a way and a set of nodes
     *
     * @param array $way the way
     * @param array $nodes the nodes
     * @return array|null
     */
    private function getPoint(array $way, array $nodes): ?array
    {
        $sumLon = 0;
        $sumLat = 0;
        $count = 0;
        $nodesDone = [];

        $ids = $way["nodes"];

        if (is_array($ids)) {
            foreach ($ids as $id) {
                if (isset($nodes[$id]) && !in_array($id, $nodesDone)) {
                    $sumLon += $nodes[$id]["coordinates"][0];
                    $sumLat += $nodes[$id]["coordinates"][1];
                    $count++;
                    $nodesDone[] = $id;
                }
            }
        }

        return $count > 0 ? [
            "type" => "Point",
            "coordinates" => [
                $sumLon / $count,
                $sumLat / $count
            ]
        ] : null;
    }

    /**
     * Return a feature from a overpass element
     *
     * @param array $item the overpass item
     * @param array $geometry the geometry
     * @param int $id the item id
     * @return array
     */
    private function getFeature(array $item, array $geometry, int $id): array
    {
        $feature = [
            "type" => "Feature",
            "geometry" => $geometry,
            "properties" => [
                "id" => $id,
                "osm_type" => isset($item["type"]) ? $item["type"] : "node"
            ]
        ];

        if (isset($item["tags"]["name"]))
            $feature["properties"]["name"] = strval($item["tags"]["name"]);

        if (isset($item["tags"]["ele"]) && !is_nan(floatval($item["tags"]["ele"]))) {
            $feature["geometry"]["coordinates"][] = floatval($item["tags"]["ele"]);
            $feature["properties"]["ele"] = floatval($item["tags"]["ele"]);
        }

        if (isset($item['id']))
            $feature["properties"]["osmid"] = $item["id"];

        $propertiesToMap = ["ref" => "ref", "operator" => "operator"];

        foreach ($propertiesToMap as $key => $property) {
            if (array_key_exists($property, $item["tags"]))
                $feature["properties"][$key] = $item["tags"][$property];
        }

        if (is_array($this->_mapping)) {
            foreach ($this->_mapping as $key => $mappingArray) {
                $value = "";
                if (is_array($mappingArray)) {
                    foreach ($mappingArray as $val) {
                        if (is_string($val) && substr($val, 0, 1) === "$") {
                            if (array_key_exists(substr($val, 1), $feature["properties"]))
                                $value .= strval($feature["properties"][substr($val, 1)]);
                        } else
                            $value .= strval($val);
                    }
                } else $value = strval($mappingArray);

                if (substr($key, 0, 9) === "default::") {
                    $keyToCheck = substr($key, 9);
                    if (!isset($feature["properties"][$keyToCheck]) || empty($feature["properties"][$keyToCheck]))
                        $feature["properties"][$keyToCheck] = trim($value);
                } else
                    $feature["properties"][$key] = trim($value);
            }
        }

        if (isset($item["tags"]["wikimedia_commons"])) {
            $filename = $item["tags"]["wikimedia_commons"];
            $url = null;
            try {
                $url = "https://commons.wikimedia.org/w/api.php?action=query&titles=" .
                    rawurlencode($filename) .
                    "&format=json&prop=imageinfo&iiprop=url&iilimit=1&iiurlwidth=600";
                echo "Fetching wikimedia img url from $url...";
                $apiJson = json_decode(file_get_contents($url), true);
                if (isset($apiJson) &&
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
                            isset($apiJson["query"]["pages"][$key]["imageinfo"][$imageKey]["thumburl"]))
                            $url = $apiJson["query"]["pages"][$key]["imageinfo"][$imageKey]["thumburl"];
                        else if (isset($apiJson["query"]["pages"][$key]["imageinfo"][$imageKey]) &&
                            isset($apiJson["query"]["pages"][$key]["imageinfo"][$imageKey]["url"]))
                            $url = $apiJson["query"]["pages"][$key]["imageinfo"][$imageKey]["url"];
                    }
                }
                echo " OK\n";
            } catch (Exception $e) {
                echo "\nError getting wikimedia file: {$e->getMessage()}\n";
            }

            if (isset($url)) {
                if (!isset($feature["properties"]["image"]))
                    $feature["properties"]["image"] = $url;
                else {
                    if (!isset($feature["properties"]["imageGallery"]))
                        $feature["properties"]["imageGallery"] = [];

                    $feature["properties"]["imageGallery"][] = [
                        "id" => $url,
                        "src" => $url,
                        "caption" => ""
                    ];
                }
            }
        }

        return $feature;
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
        if (curl_getinfo($ch, CURLINFO_HTTP_CODE) != 200)
            throw new WebmappException("An error " . curl_getinfo($ch, CURLINFO_HTTP_CODE) . " occurred while calling {$url}: " . curl_error($ch));

        $result = json_decode($result, true);
        return $result;
    }
}