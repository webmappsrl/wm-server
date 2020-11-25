<?php

use PHPUnit\Framework\TestCase;

class WebmappUpdateTrackMetadataJobTest extends TestCase
{
    private function _createProjectStructure($a, $k, $instanceName)
    {
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
    }

    function testFileCreation()
    {
        $aEndpoint = "./data/a";
        $kEndpoint = "./data/k";
        $instanceUrl = "http://elm.be.webmapp.it";
        $instanceName = "elm.be.webmapp.it";
        $id = 2036;

        $this->_createProjectStructure($aEndpoint, $kEndpoint, $instanceName);

        $params = "{\"id\":{$id}}";
        $job = new WebmappUpdateTrackMetadataJob($instanceUrl, $params, false);
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
        $this->assertSame($file["geometry"]["type"], "LineString");
        $this->assertArrayHasKey("coordinates", $file["geometry"]);
        $this->assertIsArray($file["geometry"]["coordinates"]);
        $this->assertTrue(count($file["geometry"]["coordinates"]) > 0);
        $this->assertArrayHasKey("properties", $file);
        $this->assertIsArray($file["properties"]);
        $this->assertArrayHasKey("id", $file["properties"]);
        $this->assertSame($file["properties"]["id"], $id);
        $this->assertArrayHasKey("name", $file["properties"]);
        $this->assertSame($file["properties"]["name"], "Test track OSMID");

        $this->assertTrue(file_exists("{$aEndpoint}/{$instanceName}/taxonomies/webmapp_category.json"));
        $file = json_decode(file_get_contents("{$aEndpoint}/{$instanceName}/taxonomies/webmapp_category.json"), true);
        $this->assertIsArray($file);
        $this->assertSame(count($file), 0);
        $this->assertTrue(file_exists("{$aEndpoint}/{$instanceName}/taxonomies/activity.json"));
        $file = json_decode(file_get_contents("{$aEndpoint}/{$instanceName}/taxonomies/activity.json"), true);
        $this->assertIsArray($file);
        $this->assertArrayHasKey(127, $file);
        $this->assertIsArray($file[127]);
        $this->assertArrayHasKey("items", $file[127]);
        $this->assertIsArray($file[127]["items"]);
        $this->assertArrayHasKey("track", $file[127]["items"]);
        $this->assertIsArray($file[127]["items"]["track"]);
        $this->assertContains($id, $file[127]["items"]["track"]);
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
        $id = 2036;
        $testName = '';
        $testAscent = 100000;
        $testFirstCoordinates = [0, 0, 0];
        $testGeometryType = 'MultiLineString';

        $this->_createProjectStructure($aEndpoint, $kEndpoint, $instanceName);

        $params = "{\"id\":{$id}}";
        $job = new WebmappUpdateTrackMetadataJob($instanceUrl, $params, false);
        try {
            $job->run();

            // Simulate a change of taxonomies - this task should overwrite
            // the taxonomies
            $file = [
                100 => [
                    "items" => [
                        "track" => [
                            $id
                        ]
                    ]
                ]
            ];
            file_put_contents("{$aEndpoint}/{$instanceName}/taxonomies/theme.json", json_encode($file));

            // Simulate a wrong set of data - name, ascent, first coordinates and geometry type
            // this job should change back only the name
            $file = json_decode(file_get_contents("{$aEndpoint}/{$instanceName}/geojson/{$id}.geojson"), true);
            $testName = $file["properties"]["name"];
            $file["properties"]["name"] = "Test track OSMID - Test";
            $file["properties"]["ascent"] = $testAscent;
            $file["geometry"]["coordinates"][0] = $testFirstCoordinates;
            $file["geometry"]["type"] = $testGeometryType;
            file_put_contents("{$aEndpoint}/{$instanceName}/geojson/{$id}.geojson", json_encode($file));

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
        $this->assertSame($file["geometry"]["type"], $testGeometryType);
        $this->assertArrayHasKey("coordinates", $file["geometry"]);
        $this->assertIsArray($file["geometry"]["coordinates"]);
        $this->assertTrue(count($file["geometry"]["coordinates"]) > 1);
        $this->assertTrue(json_encode($file["geometry"]["coordinates"][0]) == json_encode($testFirstCoordinates));
        $this->assertArrayHasKey("properties", $file);
        $this->assertIsArray($file["properties"]);
        $this->assertArrayHasKey("id", $file["properties"]);
        $this->assertSame($file["properties"]["id"], $id);
        $this->assertArrayHasKey("name", $file["properties"]);
        $this->assertSame($file["properties"]["name"], $testName); // Has changed back since the manual change
        $this->assertArrayHasKey("ascent", $file["properties"]);
        $this->assertSame($file["properties"]["ascent"], $testAscent); // Has not changed since the manual change

        $this->assertTrue(file_exists("{$aEndpoint}/{$instanceName}/taxonomies/activity.json"));
        $file = json_decode(file_get_contents("{$aEndpoint}/{$instanceName}/taxonomies/activity.json"), true);
        $this->assertIsArray($file);
        $this->assertArrayHasKey(127, $file);
        $this->assertIsArray($file[127]);
        $this->assertArrayHasKey("items", $file[127]);
        $this->assertIsArray($file[127]["items"]);
        $this->assertArrayHasKey("track", $file[127]["items"]);
        $this->assertIsArray($file[127]["items"]["track"]);
        $this->assertContains($id, $file[127]["items"]["track"]);
        $this->assertTrue(file_exists("{$aEndpoint}/{$instanceName}/taxonomies/theme.json"));
        $file = json_decode(file_get_contents("{$aEndpoint}/{$instanceName}/taxonomies/theme.json"), true);
        $this->assertIsArray($file);
        $this->assertSame(count($file), 0); // The fake taxonomy should have been removed
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

    function testRelatedPoiOrder()
    {
        $aEndpoint = "./data/a";
        $kEndpoint = "./data/k";
        $instanceUrl = "http://elm.be.webmapp.it";
        $instanceName = "elm.be.webmapp.it";
        $id = 2141;
        $wrongOrder = [2144, 2145, 2150]; // right order: 2150, 2144, 2145

        $this->_createProjectStructure($aEndpoint, $kEndpoint, $instanceName);

        $params = "{\"id\":{$id}}";
        $job = new WebmappUpdateTrackMetadataJob($instanceUrl, $params, false);
        try {
            $job->run();
            // Simulate a change on the related poi order taxonomies - this task should not overwrite the order
            $file = json_decode(file_get_contents("{$aEndpoint}/{$instanceName}/geojson/{$id}.geojson"), true);
            $file["properties"]["related"]["poi"]["related"] = $wrongOrder;
            file_put_contents("{$aEndpoint}/{$instanceName}/geojson/{$id}.geojson", json_encode($file));

            // Run this twice to force the files overwrite
            $job->run();
        } catch (Exception $e) {
            $this->fail($e->getMessage());
        }

        $this->assertTrue(file_exists("{$aEndpoint}/{$instanceName}/geojson/{$id}.geojson"));
        $file = json_decode(file_get_contents("{$aEndpoint}/{$instanceName}/geojson/{$id}.geojson"), true);

        $this->assertArrayHasKey("related", $file["properties"]);
        $this->assertIsArray($file["properties"]["related"]);
        $this->assertArrayHasKey("poi", $file["properties"]["related"]);
        $this->assertIsArray($file["properties"]["related"]["poi"]);
        $this->assertArrayHasKey("related", $file["properties"]["related"]["poi"]);
        $this->assertIsArray($file["properties"]["related"]["poi"]["related"]);
        $this->assertSame(count($file["properties"]["related"]["poi"]["related"]), 3);
        $this->assertSame(intval($file["properties"]["related"]["poi"]["related"][0]), $wrongOrder[0]);
        $this->assertSame(intval($file["properties"]["related"]["poi"]["related"][1]), $wrongOrder[1]);
        $this->assertSame(intval($file["properties"]["related"]["poi"]["related"][2]), $wrongOrder[2]);
    }

    function testDurations()
    {
        $aEndpoint = "./data/a";
        $kEndpoint = "./data/k";
        $instanceUrl = "http://elm.be.webmapp.it";
        $instanceName = "elm.be.webmapp.it";
        $id = 2161;
        $fakeDuration = "1000:00";

        $this->_createProjectStructure($aEndpoint, $kEndpoint, $instanceName);

        $params = "{\"id\":{$id}}";
        $job = new WebmappUpdateTrackMetadataJob($instanceUrl, $params, false);
        try {
            $job->run();
            // Simulate a change on the related poi order taxonomies - this task should not overwrite the order
            $file = json_decode(file_get_contents("{$aEndpoint}/{$instanceName}/geojson/{$id}.geojson"), true);
            $file["properties"]["duration:forward"] = $fakeDuration;
            $file["properties"]["duration:backward"] = $fakeDuration;
            file_put_contents("{$aEndpoint}/{$instanceName}/geojson/{$id}.geojson", json_encode($file));

            // Run this twice to force the files overwrite
            $job->run();
        } catch (Exception $e) {
            $this->fail($e->getMessage());
        }

        $this->assertTrue(file_exists("{$aEndpoint}/{$instanceName}/geojson/{$id}.geojson"));
        $file = json_decode(file_get_contents("{$aEndpoint}/{$instanceName}/geojson/{$id}.geojson"), true);

        $this->assertArrayHasKey("duration:forward", $file["properties"]);
        $this->assertSame($file["properties"]["duration:forward"], $fakeDuration);
        $this->assertArrayHasKey("duration:backward", $file["properties"]);
        $this->assertSame($file["properties"]["duration:backward"], $fakeDuration);
    }
}