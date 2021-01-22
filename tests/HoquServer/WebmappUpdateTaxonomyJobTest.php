<?php

require_once 'helpers/WebmappTestHelpers.php';

use PHPUnit\Framework\TestCase;

class WebmappUpdateTaxonomyJobTest extends TestCase
{
    public function __construct()
    {
        $this->setOutputCallback(function () {
        });
        parent::__construct();
    }

    function testCreationSkippedWhenNotPresent()
    {
        $aEndpoint = "./data/a";
        $kEndpoint = "./data/k";
        $instanceUrl = "http://elm.be.webmapp.it";
        $instanceName = "elm.be.webmapp.it";
        $id = 161;

        WebmappHelpers::createProjectStructure($aEndpoint, $kEndpoint, $instanceName);
        $params = "{\"id\":{$id}}";
        $job = new WebmappUpdateTaxonomyJob($instanceUrl, $params, false);
        try {
            $job->run();
        } catch (Exception $e) {
            $this->fail($e->getMessage());
        }

        $this->assertFalse(file_exists("$aEndpoint/$instanceName/taxonomies/webmapp_category.json"));
        $this->assertFalse(file_exists("$aEndpoint/$instanceName/taxonomies/activity.json"));
        $this->assertFalse(file_exists("$aEndpoint/$instanceName/taxonomies/theme.json"));
        $this->assertFalse(file_exists("$aEndpoint/$instanceName/taxonomies/when.json"));
        $this->assertFalse(file_exists("$aEndpoint/$instanceName/taxonomies/where.json"));
        $this->assertFalse(file_exists("$aEndpoint/$instanceName/taxonomies/who.json"));
        $this->assertFalse(file_exists("$aEndpoint/$instanceName/taxonomies/$id.geojson"));
    }

    function testTaxonomyNotPublic()
    {
        $aEndpoint = "./data/a";
        $kEndpoint = "./data/k";
        $instanceUrl = "http://elm.be.webmapp.it";
        $instanceName = "elm.be.webmapp.it";
        $id = 0;

        WebmappHelpers::createProjectStructure($aEndpoint, $kEndpoint, $instanceName);
        $params = "{\"id\":{$id}}";
        $job = new WebmappUpdateTaxonomyJob($instanceUrl, $params, false);
        try {
            $job->run();
        } catch (Exception $e) {
            $this->fail($e->getMessage());
        }

        $this->assertFalse(file_exists("$aEndpoint/$instanceName/taxonomies/webmapp_category.json"));
        $this->assertFalse(file_exists("$aEndpoint/$instanceName/taxonomies/activity.json"));
        $this->assertFalse(file_exists("$aEndpoint/$instanceName/taxonomies/theme.json"));
        $this->assertFalse(file_exists("$aEndpoint/$instanceName/taxonomies/when.json"));
        $this->assertFalse(file_exists("$aEndpoint/$instanceName/taxonomies/where.json"));
        $this->assertFalse(file_exists("$aEndpoint/$instanceName/taxonomies/who.json"));
        $this->assertFalse(file_exists("$aEndpoint/$instanceName/taxonomies/$id.geojson"));
    }

    function testFileUpdate()
    {
        $aEndpoint = "./data/a";
        $kEndpoint = "./data/k";
        $instanceUrl = "http://elm.be.webmapp.it";
        $instanceName = "elm.be.webmapp.it";
        $instanceCode = "elm";
        $conf = [
            "multimap" => true
        ];
        $id = 161;

        WebmappHelpers::createProjectStructure($aEndpoint, $kEndpoint, $instanceName, null, $instanceCode, $conf);
        $params = "{\"id\":{$id}}";
        $job = new WebmappUpdateTaxonomyJob($instanceUrl, $params, false);
        try {
            $fakeWC = [
                $id => [
                    "id" => "WrongTest",
                    "items" => [
                        "route" => [2056]
                    ]
                ]
            ];
            file_put_contents("$aEndpoint/$instanceName/taxonomies/webmapp_category.json", json_encode($fakeWC));
            $fakeFeatureCollection = [
                "type" => "FeatureCollection",
                "features" => [
                    2056
                ],
                "properties" => [
                    "id" => "WrongTest",
                    "count" => 0
                ]
            ];
            file_put_contents("$aEndpoint/$instanceName/taxonomies/$id.geojson", json_encode($fakeFeatureCollection));
            $fakeKWC = [
                $id => [
                    "id" => "WrongTest",
                    "items" => [
                        "route" => [2056]
                    ]
                ]
            ];
            file_put_contents("$kEndpoint/$instanceCode/taxonomies/webmapp_category.json", json_encode($fakeKWC));
            $job->run();
        } catch (Exception $e) {
            $this->fail($e->getMessage());
        }

        $this->assertTrue(file_exists("$aEndpoint/$instanceName/taxonomies/webmapp_category.json"));
        $json = json_decode(file_get_contents("$aEndpoint/$instanceName/taxonomies/webmapp_category.json"), true);
        $this->assertIsArray($json);
        $this->assertArrayHasKey($id, $json);
        $this->assertIsArray($json[$id]);
        $this->assertArrayHasKey("id", $json[$id]);
        $this->assertSame($json[$id]["id"], $id);
        $this->assertArrayHasKey("items", $json[$id]);
        $this->assertIsArray($json[$id]["items"]);
        $this->assertArrayHasKey("route", $json[$id]["items"]);
        $this->assertIsArray($json[$id]["items"]["route"]);
        $this->assertSame(count($json[$id]["items"]["route"]), 1);

        $this->assertFalse(file_exists("$aEndpoint/$instanceName/taxonomies/activity.json"));
        $this->assertFalse(file_exists("$aEndpoint/$instanceName/taxonomies/theme.json"));
        $this->assertFalse(file_exists("$aEndpoint/$instanceName/taxonomies/when.json"));
        $this->assertFalse(file_exists("$aEndpoint/$instanceName/taxonomies/where.json"));
        $this->assertFalse(file_exists("$aEndpoint/$instanceName/taxonomies/who.json"));

        $this->assertTrue(file_exists("$aEndpoint/$instanceName/taxonomies/$id.geojson"));
        $json = json_decode(file_get_contents("$aEndpoint/$instanceName/taxonomies/$id.geojson"), true);
        $this->assertIsArray($json);
        $this->assertArrayHasKey("features", $json);
        $this->assertIsArray($json["features"]);
        $this->assertSame(count($json["features"]), 1);
        $this->assertArrayHasKey("type", $json);
        $this->assertSame($json["type"], "FeatureCollection");
        $this->assertArrayHasKey("properties", $json);
        $this->assertIsArray($json["properties"]);
        $this->assertArrayHasKey("id", $json["properties"]);
        $this->assertSame($json["properties"]["id"], $id);
        $this->assertArrayHasKey("count", $json["properties"]);
        $this->assertSame(count($json["features"]), $json["properties"]["count"]);

        $this->assertTrue(file_exists("$kEndpoint/$instanceCode/taxonomies/webmapp_category.json"));
    }

    function testCleanEmptyTaxonomy()
    {
        $aEndpoint = "./data/a";
        $kEndpoint = "./data/k";
        $instanceUrl = "http://elm.be.webmapp.it";
        $instanceName = "elm.be.webmapp.it";
        $instanceCode = "elm";
        $conf = [
            "multimap" => true
        ];
        $id = 161;
        $emptyTaxonomyId = 10000;

        $fcUrl = "$aEndpoint/$instanceName/taxonomies/$id.geojson";
        $wcUrl = "$aEndpoint/$instanceName/taxonomies/webmapp_category.json";
        $kWcUrl = "$kEndpoint/$instanceCode/taxonomies/webmapp_category.json";
        $emptyFcUrl = "$aEndpoint/$instanceName/taxonomies/$emptyTaxonomyId.geojson";

        WebmappHelpers::createProjectStructure($aEndpoint, $kEndpoint, $instanceName, null, $instanceCode, $conf);
        $params = "{\"id\":{$id}}";
        $job = new WebmappUpdateTaxonomyJob($instanceUrl, $params, false);
        try {
            $fakeWC = [
                $id => [
                    "id" => "WrongTest",
                    "items" => [
                        "route" => [2056]
                    ]
                ],
                $emptyTaxonomyId => [
                    "id" => "WrongTest",
                    "items" => [
                        "route" => []
                    ]
                ]
            ];
            file_put_contents($wcUrl, json_encode($fakeWC));
            $fakeFeatureCollection = [
                "type" => "FeatureCollection",
                "features" => [
                    2056
                ],
                "properties" => [
                    "id" => "WrongTest",
                    "count" => 0
                ]
            ];
            file_put_contents($fcUrl, json_encode($fakeFeatureCollection));
            $fakeFeatureCollection = [
                "type" => "FeatureCollection",
                "features" => [
                    2056
                ],
                "properties" => [
                    "id" => "WrongTest",
                    "count" => 0
                ]
            ];
            file_put_contents($emptyFcUrl, json_encode($fakeFeatureCollection));
            $fakeKWC = [
                $id => [
                    "id" => "WrongTest",
                    "items" => [
                        "route" => [2056]
                    ]
                ],
                $emptyTaxonomyId => [
                    "id" => "WrongTest",
                    "items" => [
                        "route" => []
                    ]
                ]
            ];
            file_put_contents($kWcUrl, json_encode($fakeKWC));
            $job->run();
        } catch (Exception $e) {
            $this->fail($e->getMessage());
        }

        $this->assertTrue(file_exists($wcUrl));
        $json = json_decode(file_get_contents($wcUrl), true);
        $this->assertIsArray($json);
        $this->assertFalse(array_key_exists($emptyTaxonomyId, $json));

        $this->assertFalse(file_exists("$aEndpoint/$instanceName/taxonomies/activity.json"));
        $this->assertFalse(file_exists("$aEndpoint/$instanceName/taxonomies/theme.json"));
        $this->assertFalse(file_exists("$aEndpoint/$instanceName/taxonomies/when.json"));
        $this->assertFalse(file_exists("$aEndpoint/$instanceName/taxonomies/where.json"));
        $this->assertFalse(file_exists("$aEndpoint/$instanceName/taxonomies/who.json"));

        $this->assertFalse(file_exists($emptyFcUrl));
        $this->assertTrue(file_exists($fcUrl));

        $this->assertTrue(file_exists($kWcUrl));
        $json = json_decode(file_get_contents($kWcUrl), true);
        $this->assertIsArray($json);
        $this->assertTrue(array_key_exists($id, $json));
        $this->assertFalse(array_key_exists($emptyTaxonomyId, $json));
    }
}