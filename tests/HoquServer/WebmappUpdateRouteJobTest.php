<?php

use PHPUnit\Framework\TestCase;

class WebmappUpdateRouteJobTest extends TestCase
{
    private function _createProjectStructure($a, $k, $instanceName, $instanceCode)
    {
        global $wm_config;
        $wm_config["endpoint"] = [
            "a" => $a,
            "k" => $k
        ];
        $wm_config["a_k_instances"] = [
            "elm.be.webmapp.it" => [
                "elm"
            ]
        ];

        if (!file_exists("{$a}/{$instanceName}/geojson")) {
            $cmd = "mkdir -p {$a}/{$instanceName}/geojson";
            system($cmd);
        }
        if (!file_exists("{$a}/{$instanceName}/taxonomies")) {
            $cmd = "mkdir -p {$a}/{$instanceName}/taxonomies";
            system($cmd);
        }

        if (!file_exists("{$k}/{$instanceCode}/geojson")) {
            $cmd = "mkdir -p {$k}/{$instanceCode}/geojson";
            system($cmd);
        }
        if (!file_exists("{$k}/{$instanceCode}/taxonomies")) {
            $cmd = "mkdir -p {$k}/{$instanceCode}/taxonomies";
            system($cmd);
        }
        if (!file_exists("{$k}/{$instanceCode}/routes")) {
            $cmd = "mkdir -p {$k}/{$instanceCode}/routes";
            system($cmd);
        }
        if (!file_exists("{$k}/{$instanceCode}/server")) {
            $cmd = "mkdir -p {$k}/{$instanceCode}/server";
            system($cmd);
        }
        if (!file_exists("{$k}/{$instanceCode}/server/server.conf")) {
            $conf = [
                "multimap" => true,
                "routesFilter" => [2056]
            ];

            file_put_contents("{$k}/{$instanceCode}/server/server.conf", json_encode($conf));
        }

        $cmd = "rm {$a}/{$instanceName}/geojson/*";
        system($cmd);
        $cmd = "rm {$a}/{$instanceName}/taxonomies/*";
        system($cmd);
        $cmd = "rm {$k}/{$instanceCode}/geojson/*";
        system($cmd);
        $cmd = "rm {$k}/{$instanceCode}/taxonomies/*";
        system($cmd);
        $cmd = "rm -r {$k}/{$instanceCode}/routes/*";
        system($cmd);
    }

    function testFileCreation()
    {
        $aEndpoint = "./data/a";
        $kEndpoint = "./data/k";
        $instanceUrl = "http://elm.be.webmapp.it";
        $instanceName = "elm.be.webmapp.it";
        $instanceCode = "elm";
        $id = 2056;

        $this->_createProjectStructure($aEndpoint, $kEndpoint, $instanceName, $instanceCode);

        $params = "{\"id\":{$id}}";
        $job = new WebmappUpdateRouteJob($instanceUrl, $params, false);

        try {
            $job->run();
        } catch (Exception $e) {
            $this->fail($e->getMessage());
        }

        $this->assertTrue(file_exists("{$aEndpoint}/{$instanceName}/geojson/{$id}.geojson"));
        $this->assertTrue(file_exists("{$aEndpoint}/{$instanceName}/geojson/route_index.geojson"));
        $this->assertTrue(file_exists("{$aEndpoint}/{$instanceName}/geojson/full_geometry_route_index.geojson"));
        $this->assertTrue(file_exists("{$aEndpoint}/{$instanceName}/taxonomies/activity.json"));
        $this->assertTrue(file_exists("{$aEndpoint}/{$instanceName}/taxonomies/theme.json"));
        $this->assertTrue(file_exists("{$aEndpoint}/{$instanceName}/taxonomies/webmapp_category.json"));
        $this->assertTrue(file_exists("{$aEndpoint}/{$instanceName}/taxonomies/when.json"));
        $this->assertTrue(file_exists("{$aEndpoint}/{$instanceName}/taxonomies/where.json"));
        $this->assertTrue(file_exists("{$aEndpoint}/{$instanceName}/taxonomies/who.json"));

        $this->assertTrue(file_exists("{$kEndpoint}/{$instanceCode}/geojson/{$id}.geojson"));
//        $this->assertTrue(file_exists("{$kEndpoint}/{$instanceCode}/geojson/route_index.geojson"));
//        $this->assertTrue(file_exists("{$kEndpoint}/{$instanceCode}/geojson/full_geometry_route_index.geojson"));
        $this->assertTrue(file_exists("{$kEndpoint}/{$instanceCode}/taxonomies/activity.json"));
        $this->assertTrue(file_exists("{$kEndpoint}/{$instanceCode}/taxonomies/theme.json"));
        $this->assertTrue(file_exists("{$kEndpoint}/{$instanceCode}/taxonomies/webmapp_category.json"));
        $this->assertTrue(file_exists("{$kEndpoint}/{$instanceCode}/taxonomies/when.json"));
        $this->assertTrue(file_exists("{$kEndpoint}/{$instanceCode}/taxonomies/where.json"));
        $this->assertTrue(file_exists("{$kEndpoint}/{$instanceCode}/taxonomies/who.json"));

        $this->assertTrue(file_exists("{$kEndpoint}/{$instanceCode}/routes/{$id}"));
        $this->assertTrue(file_exists("{$kEndpoint}/{$instanceCode}/routes/{$id}/map.json"));
        $this->assertTrue(file_exists("{$kEndpoint}/{$instanceCode}/routes/{$id}/taxonomies"));
        $this->assertTrue(file_exists("{$kEndpoint}/{$instanceCode}/routes/{$id}/taxonomies/activity.json"));
        $this->assertTrue(file_exists("{$kEndpoint}/{$instanceCode}/routes/{$id}/taxonomies/webmapp_category.json"));

        $file = json_decode(file_get_contents("{$aEndpoint}/{$instanceName}/geojson/{$id}.geojson"), true);
        $this->assertIsArray($file);
        $this->assertArrayHasKey("type", $file);
        $this->assertSame($file["type"], "FeatureCollection");
        $this->assertArrayHasKey("features", $file);
        $this->assertIsArray($file["features"]);
        $this->assertTrue(count($file["features"]) > 0);

        $file = json_decode(file_get_contents("{$aEndpoint}/{$instanceName}/geojson/route_index.geojson"), true);
        $this->assertIsArray($file);
        $this->assertArrayHasKey("type", $file);
        $this->assertSame($file["type"], "FeatureCollection");
        $this->assertArrayHasKey("features", $file);
        $this->assertIsArray($file["features"]);
        $this->assertTrue(count($file["features"]) > 0);
        $route = $file["features"][0];
        $this->assertIsArray($route);
        $this->assertArrayHasKey("type", $route);
        $this->assertSame($route["type"], "Feature");
        $this->assertArrayHasKey("geometry", $route);
        $this->assertIsArray($route["geometry"]);
        $this->assertArrayHasKey("type", $route["geometry"]);
        $this->assertSame($route["geometry"]["type"], "Point");
        $this->assertArrayHasKey("coordinates", $route["geometry"]);
        $this->assertIsArray($route["geometry"]["coordinates"]);
        $this->assertTrue(count($route["geometry"]["coordinates"]) >= 2 && count($route["geometry"]["coordinates"]) <= 3);
        $this->assertArrayHasKey("properties", $route);
        $this->assertIsArray($route["properties"]);
        $this->assertArrayHasKey("id", $route["properties"]);
        $this->assertSame($route["properties"]["id"], $id);

        $file = json_decode(file_get_contents("{$aEndpoint}/{$instanceName}/geojson/full_geometry_route_index.geojson"), true);
        $this->assertIsArray($file);
        $this->assertArrayHasKey("type", $file);
        $this->assertSame($file["type"], "FeatureCollection");
        $this->assertArrayHasKey("features", $file);
        $this->assertIsArray($file["features"]);
        $this->assertTrue(count($file["features"]) > 0);
        $route = $file["features"][0];
        $this->assertIsArray($route);
        $this->assertArrayHasKey("type", $route);
        $this->assertSame($route["type"], "Feature");
        $this->assertArrayHasKey("geometry", $route);
        $this->assertIsArray($route["geometry"]);
        $this->assertArrayHasKey("type", $route["geometry"]);
        $this->assertTrue($route["geometry"]["type"] === "LineString" || $route["geometry"]["type"] === "MultiLineString");
        $this->assertArrayHasKey("coordinates", $route["geometry"]);
        $this->assertIsArray($route["geometry"]["coordinates"]);
        $this->assertTrue(count($route["geometry"]["coordinates"]) > 1);
        $this->assertArrayHasKey("properties", $route);
        $this->assertIsArray($route["properties"]);
        $this->assertArrayHasKey("id", $route["properties"]);
        $this->assertSame($route["properties"]["id"], $id);
    }
}