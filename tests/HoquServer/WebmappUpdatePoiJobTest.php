<?php

use PHPUnit\Framework\TestCase;

class WebmappUpdatePoiJobTest extends TestCase
{
    private function _createProjectStructure($a, $k, $instanceName, array $conf = null)
    {
        $this->setOutputCallback(function () {
        });
        global $wm_config;
        $wm_config["endpoint"] = [
            "a" => $a,
            "k" => $k
        ];

        if (!file_exists("{$a}/{$instanceName}/geojson")) {
            $cmd = "mkdir -p {$a}/{$instanceName}/geojson";
            system($cmd);
        }
        if (!file_exists("{$a}/{$instanceName}/taxonomies")) {
            $cmd = "mkdir -p {$a}/{$instanceName}/taxonomies";
            system($cmd);
        }

        $cmd = "rm {$a}/{$instanceName}/geojson/*";
        system($cmd);
        $cmd = "rm {$a}/{$instanceName}/taxonomies/*";
        system($cmd);

        if (!$conf) $conf = [];

        file_put_contents("{$a}/{$instanceName}/server/server.conf", json_encode($conf));
    }

    function testFileCreation()
    {
        $aEndpoint = "./data/a";
        $kEndpoint = "./data/k";
        $instanceUrl = "http://elm.be.webmapp.it";
        $instanceName = "elm.be.webmapp.it";
        $id = 1459;

        $this->_createProjectStructure($aEndpoint, $kEndpoint, $instanceName);

        $params = "{\"id\":{$id}}";
        $job = new WebmappUpdatePoiJob($instanceUrl, $params, false);
        try {
            $job->run();
        } catch (Exception $e) {
            $this->fail($e->getMessage());
        }

        $this->assertTrue(file_exists("{$aEndpoint}/{$instanceName}/geojson/{$id}.geojson"));
        $file = json_decode(file_get_contents("{$aEndpoint}/{$instanceName}/geojson/{$id}.geojson"), true);

        $this->assertIsArray($file);
        $this->assertArrayHasKey("type", $file);
        $this->assertSame($file["type"], "Feature");
        $this->assertArrayHasKey("geometry", $file);
        $this->assertIsArray($file["geometry"]);
        $this->assertArrayHasKey("type", $file["geometry"]);
        $this->assertSame($file["geometry"]["type"], "Point");
        $this->assertArrayHasKey("coordinates", $file["geometry"]);
        $this->assertIsArray($file["geometry"]["coordinates"]);
        $this->assertTrue(count($file["geometry"]["coordinates"]) >= 2);
        $this->assertArrayHasKey("properties", $file);
        $this->assertIsArray($file["properties"]);
        $this->assertArrayHasKey("id", $file["properties"]);
        $this->assertSame($file["properties"]["id"], $id);
        $this->assertArrayHasKey("name", $file["properties"]);
        $this->assertSame($file["properties"]["name"], "Sveti martin church");

        $this->assertTrue(file_exists("{$aEndpoint}/{$instanceName}/taxonomies/webmapp_category.json"));
        $file = json_decode(file_get_contents("{$aEndpoint}/{$instanceName}/taxonomies/webmapp_category.json"), true);
        $this->assertIsArray($file);
        $this->assertArrayHasKey(161, $file);
        $this->assertIsArray($file[161]);
        $this->assertArrayHasKey("items", $file[161]);
        $this->assertIsArray($file[161]["items"]);
        $this->assertArrayHasKey("poi", $file[161]["items"]);
        $this->assertIsArray($file[161]["items"]["poi"]);
        $this->assertContains($id, $file[161]["items"]["poi"]);

        $this->assertTrue(file_exists("{$aEndpoint}/{$instanceName}/taxonomies/activity.json"));
        $file = json_decode(file_get_contents("{$aEndpoint}/{$instanceName}/taxonomies/activity.json"), true);
        $this->assertIsArray($file);
        $this->assertSame(count($file), 0);
        $this->assertTrue(file_exists("{$aEndpoint}/{$instanceName}/taxonomies/theme.json"));
        $file = json_decode(file_get_contents("{$aEndpoint}/{$instanceName}/taxonomies/theme.json"), true);
        $this->assertIsArray($file);
        $this->assertSame(count($file), 0);
        $this->assertTrue(file_exists("{$aEndpoint}/{$instanceName}/taxonomies/when.json"));
        $file = json_decode(file_get_contents("{$aEndpoint}/{$instanceName}/taxonomies/when.json"), true);
        $this->assertIsArray($file);
        $this->assertSame(count($file), 0);
        $this->assertTrue(file_exists("{$aEndpoint}/{$instanceName}/taxonomies/where.json"));
        $file = json_decode(file_get_contents("{$aEndpoint}/{$instanceName}/taxonomies/where.json"), true);
        $this->assertIsArray($file);
        $this->assertSame(count($file), 0);
        $this->assertTrue(file_exists("{$aEndpoint}/{$instanceName}/taxonomies/who.json"));
        $file = json_decode(file_get_contents("{$aEndpoint}/{$instanceName}/taxonomies/who.json"), true);
        $this->assertIsArray($file);
        $this->assertSame(count($file), 0);
    }

    function testFileUpdate()
    {
        $aEndpoint = "./data/a";
        $kEndpoint = "./data/k";
        $instanceUrl = "http://elm.be.webmapp.it";
        $instanceName = "elm.be.webmapp.it";
        $id = 1459;

        $this->_createProjectStructure($aEndpoint, $kEndpoint, $instanceName);

        $params = "{\"id\":{$id}}";
        $job = new WebmappUpdatePoiJob($instanceUrl, $params, false);
        try {
            $job->run();

            // Simulate a change of taxonomies
            $file = [
                100 => [
                    "items" => [
                        "poi" => [
                            $id
                        ]
                    ]
                ]
            ];
            file_put_contents("{$aEndpoint}/{$instanceName}/taxonomies/theme.json", json_encode($file));

            // Run this twice to force the files overwrite
            $job->run();
        } catch (Exception $e) {
            $this->fail($e->getMessage());
        }

        $this->assertTrue(file_exists("{$aEndpoint}/{$instanceName}/geojson/{$id}.geojson"));
        $file = json_decode(file_get_contents("{$aEndpoint}/{$instanceName}/geojson/{$id}.geojson"), true);

        $this->assertIsArray($file);
        $this->assertArrayHasKey("type", $file);
        $this->assertSame($file["type"], "Feature");
        $this->assertArrayHasKey("geometry", $file);
        $this->assertIsArray($file["geometry"]);
        $this->assertArrayHasKey("type", $file["geometry"]);
        $this->assertSame($file["geometry"]["type"], "Point");
        $this->assertArrayHasKey("coordinates", $file["geometry"]);
        $this->assertIsArray($file["geometry"]["coordinates"]);
        $this->assertTrue(count($file["geometry"]["coordinates"]) >= 2);
        $this->assertArrayHasKey("properties", $file);
        $this->assertIsArray($file["properties"]);
        $this->assertArrayHasKey("id", $file["properties"]);
        $this->assertSame($file["properties"]["id"], $id);
        $this->assertArrayHasKey("name", $file["properties"]);
        $this->assertSame($file["properties"]["name"], "Sveti martin church");

        $this->assertTrue(file_exists("{$aEndpoint}/{$instanceName}/taxonomies/webmapp_category.json"));
        $file = json_decode(file_get_contents("{$aEndpoint}/{$instanceName}/taxonomies/webmapp_category.json"), true);
        $this->assertIsArray($file);
        $this->assertArrayHasKey(161, $file);
        $this->assertIsArray($file[161]);
        $this->assertArrayHasKey("items", $file[161]);
        $this->assertIsArray($file[161]["items"]);
        $this->assertArrayHasKey("poi", $file[161]["items"]);
        $this->assertIsArray($file[161]["items"]["poi"]);
        $this->assertContains($id, $file[161]["items"]["poi"]);

        $this->assertTrue(file_exists("{$aEndpoint}/{$instanceName}/taxonomies/activity.json"));
        $file = json_decode(file_get_contents("{$aEndpoint}/{$instanceName}/taxonomies/activity.json"), true);
        $this->assertIsArray($file);
        $this->assertSame(count($file), 0);
        $this->assertTrue(file_exists("{$aEndpoint}/{$instanceName}/taxonomies/theme.json"));
        $file = json_decode(file_get_contents("{$aEndpoint}/{$instanceName}/taxonomies/theme.json"), true);
        $this->assertIsArray($file);
        $this->assertSame(count($file), 0);
        $this->assertTrue(file_exists("{$aEndpoint}/{$instanceName}/taxonomies/when.json"));
        $file = json_decode(file_get_contents("{$aEndpoint}/{$instanceName}/taxonomies/when.json"), true);
        $this->assertIsArray($file);
        $this->assertSame(count($file), 0);
        $this->assertTrue(file_exists("{$aEndpoint}/{$instanceName}/taxonomies/where.json"));
        $file = json_decode(file_get_contents("{$aEndpoint}/{$instanceName}/taxonomies/where.json"), true);
        $this->assertIsArray($file);
        $this->assertSame(count($file), 0);
        $this->assertTrue(file_exists("{$aEndpoint}/{$instanceName}/taxonomies/who.json"));
        $file = json_decode(file_get_contents("{$aEndpoint}/{$instanceName}/taxonomies/who.json"), true);
        $this->assertIsArray($file);
        $this->assertSame(count($file), 0);
    }

    function testCustomMapping()
    {
        $aEndpoint = "./data/a";
        $kEndpoint = "./data/k";
        $instanceUrl = "http://elm.be.webmapp.it";
        $instanceName = "elm.be.webmapp.it";
        $id = 2167;
        $conf = [
            "custom_mapping" => [
                "poi" => [
                    "test_custom_field" => "test_custom_mapping"
                ]
            ]
        ];

        $this->_createProjectStructure($aEndpoint, $kEndpoint, $instanceName, $conf);

        $params = "{\"id\":{$id}}";
        $job = new WebmappUpdatePoiJob($instanceUrl, $params, false);
        try {
            $job->run();
        } catch (Exception $e) {
            $this->fail($e->getMessage());
        }

        $this->assertTrue(file_exists("{$aEndpoint}/{$instanceName}/geojson/{$id}.geojson"));
        $file = json_decode(file_get_contents("{$aEndpoint}/{$instanceName}/geojson/{$id}.geojson"), true);

        $this->assertIsArray($file);
        $this->assertArrayHasKey("properties", $file);
        $this->assertIsArray($file["properties"]);
        $this->assertArrayHasKey("test_custom_mapping", $file["properties"]);
        $this->assertSame($file["properties"]["test_custom_mapping"], "test_value");
    }
}