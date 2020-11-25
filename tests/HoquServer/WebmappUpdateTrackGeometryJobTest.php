<?php

use PHPUnit\Framework\TestCase;

class WebmappUpdateTrackGeometryJobTest extends TestCase
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
        $job = new WebmappUpdateTrackGeometryJob($instanceUrl, $params, false);
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
        foreach ($file["geometry"]["coordinates"] as $coordinate) {
            $this->assertIsArray($coordinate);
            $this->assertTrue(count($coordinate) == 3);
        }
        $this->assertArrayHasKey("properties", $file);
        $this->assertIsArray($file["properties"]);
        $this->assertArrayHasKey("id", $file["properties"]);
        $this->assertSame($file["properties"]["id"], $id);
        $this->assertArrayHasKey("name", $file["properties"]);
        $this->assertSame($file["properties"]["name"], "Test track OSMID");

        $this->assertTrue(file_exists("{$aEndpoint}/{$instanceName}/taxonomies/webmapp_category.json"));
        $file = json_decode(file_get_contents("{$aEndpoint}/{$instanceName}/taxonomies/webmapp_category.json"), true);
        $this->assertIsArray($file);
//        $this->assertSame(count($file), 0); No more needed since it could generate pois also

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

        // Check gpx and kml creation
        $this->assertTrue(file_exists("{$aEndpoint}/{$instanceName}/track/{$id}.gpx"));
        $this->assertTrue(filesize("{$aEndpoint}/{$instanceName}/track/{$id}.gpx") > 0);
        $this->assertTrue(file_exists("{$aEndpoint}/{$instanceName}/track/{$id}.kml"));
        $this->assertTrue(filesize("{$aEndpoint}/{$instanceName}/track/{$id}.kml") > 0);
    }

    function testFileUpdate()
    {
        $aEndpoint = "./data/a";
        $kEndpoint = "./data/k";
        $instanceUrl = "http://elm.be.webmapp.it";
        $instanceName = "elm.be.webmapp.it";
        $id = 2036;
        $testName = "Test track OSMID - Test";
        $testAscent = 0;
        $testFirstCoordinates = [];
        $testGeometryType = '';

        $this->_createProjectStructure($aEndpoint, $kEndpoint, $instanceName);

        $params = "{\"id\":{$id}}";
        $job = new WebmappUpdateTrackGeometryJob($instanceUrl, $params, false);
        try {
            $job->run();

            // Simulate a change of taxonomies - this task should not overwrite
            // the taxonomies but only generate them if absent
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
            // this job should change everything back except from the name
            $file = json_decode(file_get_contents("{$aEndpoint}/{$instanceName}/geojson/{$id}.geojson"), true);
            $file["properties"]["name"] = $testName;
            $testAscent = $file["properties"]["ascent"];
            $file["properties"]["ascent"] = 100000;
            $testFirstCoordinates = $file["geometry"]["coordinates"][0];
            $file["geometry"]["coordinates"][0] = [0, 0];
            $testGeometryType = $file["geometry"]["type"];
            $file["geometry"]["type"] = "Point";
            file_put_contents("{$aEndpoint}/{$instanceName}/geojson/{$id}.geojson", json_encode($file));

            // Forge gpx and kml re-creation
            unlink("{$aEndpoint}/{$instanceName}/track/{$id}.gpx");
            unlink("{$aEndpoint}/{$instanceName}/track/{$id}.kml");

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
        $this->assertSame($file["properties"]["name"], $testName); // Has not changed since the manual change
        $this->assertArrayHasKey("ascent", $file["properties"]);
        $this->assertSame($file["properties"]["ascent"], $testAscent); // Has changed back since the manual change

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
        $this->assertSame(count($file), 0); // The fake taxonomy should be removed
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

        // Check gpx and kml creation
        $this->assertTrue(file_exists("{$aEndpoint}/{$instanceName}/track/{$id}.gpx"));
        $this->assertTrue(filesize("{$aEndpoint}/{$instanceName}/track/{$id}.gpx") > 0);
        $this->assertTrue(file_exists("{$aEndpoint}/{$instanceName}/track/{$id}.kml"));
        $this->assertTrue(filesize("{$aEndpoint}/{$instanceName}/track/{$id}.kml") > 0);
    }

    function testRelatedPoiOrder()
    {
        $aEndpoint = "./data/a";
        $kEndpoint = "./data/k";
        $instanceUrl = "http://elm.be.webmapp.it";
        $instanceName = "elm.be.webmapp.it";
        $id = 2141;

        $this->_createProjectStructure($aEndpoint, $kEndpoint, $instanceName);

        $params = "{\"id\":{$id}}";
        $job = new WebmappUpdateTrackGeometryJob($instanceUrl, $params, false);
        try {
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
        $this->assertSame(intval($file["properties"]["related"]["poi"]["related"][0]), 2150);
        $this->assertSame(intval($file["properties"]["related"]["poi"]["related"][1]), 2144);
        $this->assertSame(intval($file["properties"]["related"]["poi"]["related"][2]), 2145);
    }

    function testDurations()
    {
        $aEndpoint = "./data/a";
        $kEndpoint = "./data/k";
        $instanceUrl = "http://elm.be.webmapp.it";
        $instanceName = "elm.be.webmapp.it";
        $id = 2161;

        $this->_createProjectStructure($aEndpoint, $kEndpoint, $instanceName);

        $params = "{\"id\":{$id}}";
        $job = new WebmappUpdateTrackGeometryJob($instanceUrl, $params, false);
        try {
            $job->run();
        } catch (Exception $e) {
            $this->fail($e->getMessage());
        }

        $this->assertTrue(file_exists("{$aEndpoint}/{$instanceName}/geojson/{$id}.geojson"));
        $file = json_decode(file_get_contents("{$aEndpoint}/{$instanceName}/geojson/{$id}.geojson"), true);

        $this->assertArrayHasKey("duration:forward", $file["properties"]);
        $this->assertSame($file["properties"]["duration:forward"], "03:00");
        $this->assertArrayHasKey("duration:backward", $file["properties"]);
        $this->assertSame($file["properties"]["duration:backward"], "03:00");
    }
}