<?php

class WebmappOverpassQueryTask extends WebmappAbstractTask
{
    private $_path;
    private $_query;
    private $_layerName;

    public function check()
    {
        // Controllo parametro code http://[code].be.webmapp.it
        if (!array_key_exists('url_or_code', $this->options)) {
            throw new WebmappExceptionParameterMandatory("Missing mandatory parameter: 'url_or_code'", 1);
        }

        if (!array_key_exists('query', $this->options)) {
            throw new WebmappExceptionParameterMandatory("Missing mandatory parameter: 'query'", 1);
        }

        if (!array_key_exists('layer_name', $this->options)) {
            throw new WebmappExceptionParameterMandatory("Missing mandatory parameter: 'layer_name'", 1);
        }

        $this->_query = $this->options["query"];
        $this->_layerName = $this->options["layer_name"];
        $this->_path = $this->project_structure->getRoot();

        return true;
    }

    public function process()
    {
        try {
            $json = $this->getOverpassJson($this->_query);

            $geojson = [
                "type" => "FeatureCollection",
                "features" => []
            ];

            if (array_key_exists("elements", $json) && is_array($json["elements"])) {
                $id = 0;
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
     * @param $query string the query
     * @return array
     * @throws WebmappException if any error occurs during the request
     */
    private function getOverpassJson($query)
    {
        $url = "https://overpass-api.de/api/interpreter";
        $headers = [
            "Content-Type: application/x-www-form-urlencoded"
        ];

        $payload = [
            "data" => $query
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
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $result = curl_exec($ch);
        if (curl_getinfo($ch, CURLINFO_HTTP_CODE) != 200) {
            throw new WebmappException("An error " . curl_getinfo($ch, CURLINFO_HTTP_CODE) . " occurred while calling {$url}: " . curl_error($ch));
        }
        $result = json_decode($result, true);
        return $result;
    }
}
