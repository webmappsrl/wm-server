<?php

require_once 'helpers/WebmappTestHelpers.php';

use PHPUnit\Framework\TestCase;

class WebmappUpdateRouteJobTest extends TestCase
{
    public $kConf = [
        "multimap" => true,
        "filters" => [
            "routes_id" => [10]
        ]
    ];

    public function __construct()
    {
        $this->setOutputCallback(function () {
        });
        parent::__construct();
    }

    function testFileCreation()
    {
        $aEndpoint = "./data/a";
        $kEndpoint = "./data/k";
        $instanceUrl = "http://elm.be.webmapp.it";
        $instanceName = "elm.be.webmapp.it";
        $instanceCode = "elm";
        $id = 2056;

        unset($this->kConf["filters"]);

        WebmappHelpers::createProjectStructure($aEndpoint, $kEndpoint, $instanceName, null, $instanceCode, $this->kConf);

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

    function testKRoutesIdFilterSetButNotPresent()
    {
        $aEndpoint = "./data/a";
        $kEndpoint = "./data/k";
        $instanceUrl = "http://elm.be.webmapp.it";
        $instanceName = "elm.be.webmapp.it";
        $instanceCode = "elm";
        $id = 2056;

        $this->kConf["filters"] = [
            "routes_id" => [2057]
        ];

        WebmappHelpers::createProjectStructure($aEndpoint, $kEndpoint, $instanceName, null, $instanceCode, $this->kConf);

        $params = "{\"id\":{$id}}";
        $job = new WebmappUpdateRouteJob($instanceUrl, $params, false);

        try {
            $job->run();
        } catch (Exception $e) {
            $this->fail($e->getMessage());
        }

        $this->assertFalse(file_exists("{$kEndpoint}/{$instanceCode}/routes/{$id}"));

        $file = json_decode(file_get_contents("{$aEndpoint}/{$instanceName}/geojson/route_index.geojson"), true);
        $this->assertIsArray($file);
        $this->assertArrayHasKey("type", $file);
        $this->assertSame($file["type"], "FeatureCollection");
        $this->assertArrayHasKey("features", $file);
        $this->assertIsArray($file["features"]);
        $this->assertCount(1, $file["features"]);
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
        $this->assertCount(1, $file["features"]);
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

        $file = json_decode(file_get_contents("{$kEndpoint}/{$instanceCode}/routes/route_index.geojson"), true);
        $this->assertIsArray($file);
        $this->assertArrayHasKey("type", $file);
        $this->assertSame($file["type"], "FeatureCollection");
        $this->assertArrayHasKey("features", $file);
        $this->assertIsArray($file["features"]);
        $this->assertCount(0, $file["features"]);

        $file = json_decode(file_get_contents("{$kEndpoint}/{$instanceCode}/routes/full_geometry_route_index.geojson"), true);
        $this->assertIsArray($file);
        $this->assertArrayHasKey("type", $file);
        $this->assertSame($file["type"], "FeatureCollection");
        $this->assertArrayHasKey("features", $file);
        $this->assertIsArray($file["features"]);
        $this->assertCount(0, $file["features"]);
    }

    function testKRoutesIdFilterSet()
    {
        $aEndpoint = "./data/a";
        $kEndpoint = "./data/k";
        $instanceUrl = "http://elm.be.webmapp.it";
        $instanceName = "elm.be.webmapp.it";
        $instanceCode = "elm";
        $id = 2056;

        $this->kConf["filters"] = [
            "routes_id" => [2056]
        ];

        WebmappHelpers::createProjectStructure($aEndpoint, $kEndpoint, $instanceName, null, $instanceCode, $this->kConf);

        $params = "{\"id\":{$id}}";
        $job = new WebmappUpdateRouteJob($instanceUrl, $params, false);

        try {
            $job->run();
        } catch (Exception $e) {
            $this->fail($e->getMessage());
        }

        $this->assertTrue(file_exists("{$kEndpoint}/{$instanceCode}/routes/{$id}"));
        $this->assertTrue(file_exists("{$kEndpoint}/{$instanceCode}/routes/{$id}/map.json"));
        $this->assertTrue(file_exists("{$kEndpoint}/{$instanceCode}/routes/{$id}/taxonomies"));
        $this->assertTrue(file_exists("{$kEndpoint}/{$instanceCode}/routes/{$id}/taxonomies/activity.json"));
        $this->assertTrue(file_exists("{$kEndpoint}/{$instanceCode}/routes/{$id}/taxonomies/webmapp_category.json"));

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

        $file = json_decode(file_get_contents("{$kEndpoint}/{$instanceCode}/routes/route_index.geojson"), true);
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

        $file = json_decode(file_get_contents("{$kEndpoint}/{$instanceCode}/routes/full_geometry_route_index.geojson"), true);
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

    function testKRoutesTaxonomyFilterSetButNotPresent()
    {
        $aEndpoint = "./data/a";
        $kEndpoint = "./data/k";
        $instanceUrl = "http://elm.be.webmapp.it";
        $instanceName = "elm.be.webmapp.it";
        $instanceCode = "elm";
        $id = 2056;

        $this->kConf["filters"] = [
            "routes_taxonomy" => [123123123]
        ];

        WebmappHelpers::createProjectStructure($aEndpoint, $kEndpoint, $instanceName, null, $instanceCode, $this->kConf);

        $params = "{\"id\":{$id}}";
        $job = new WebmappUpdateRouteJob($instanceUrl, $params, false);

        try {
            $job->run();
        } catch (Exception $e) {
            $this->fail($e->getMessage());
        }

        $this->assertFalse(file_exists("{$kEndpoint}/{$instanceCode}/routes/{$id}"));

        $file = json_decode(file_get_contents("{$aEndpoint}/{$instanceName}/geojson/route_index.geojson"), true);
        $this->assertIsArray($file);
        $this->assertArrayHasKey("type", $file);
        $this->assertSame($file["type"], "FeatureCollection");
        $this->assertArrayHasKey("features", $file);
        $this->assertIsArray($file["features"]);
        $this->assertCount(1, $file["features"]);
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
        $this->assertCount(1, $file["features"]);
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

        $file = json_decode(file_get_contents("{$kEndpoint}/{$instanceCode}/routes/route_index.geojson"), true);
        $this->assertIsArray($file);
        $this->assertArrayHasKey("type", $file);
        $this->assertSame($file["type"], "FeatureCollection");
        $this->assertArrayHasKey("features", $file);
        $this->assertIsArray($file["features"]);
        $this->assertCount(0, $file["features"]);

        $file = json_decode(file_get_contents("{$kEndpoint}/{$instanceCode}/routes/full_geometry_route_index.geojson"), true);
        $this->assertIsArray($file);
        $this->assertArrayHasKey("type", $file);
        $this->assertSame($file["type"], "FeatureCollection");
        $this->assertArrayHasKey("features", $file);
        $this->assertIsArray($file["features"]);
        $this->assertCount(0, $file["features"]);
    }

    function testKRoutesTaxonomyFilterSet()
    {
        $aEndpoint = "./data/a";
        $kEndpoint = "./data/k";
        $instanceUrl = "http://elm.be.webmapp.it";
        $instanceName = "elm.be.webmapp.it";
        $instanceCode = "elm";
        $id = 2056;

        $this->kConf["filters"] = [
            "routes_taxonomy" => [127]
        ];

        WebmappHelpers::createProjectStructure($aEndpoint, $kEndpoint, $instanceName, null, $instanceCode, $this->kConf);

        $params = "{\"id\":{$id}}";
        $job = new WebmappUpdateRouteJob($instanceUrl, $params, false);

        try {
            $job->run();
        } catch (Exception $e) {
            $this->fail($e->getMessage());
        }

        $this->assertTrue(file_exists("{$kEndpoint}/{$instanceCode}/routes/{$id}"));
        $this->assertTrue(file_exists("{$kEndpoint}/{$instanceCode}/routes/{$id}/map.json"));
        $this->assertTrue(file_exists("{$kEndpoint}/{$instanceCode}/routes/{$id}/taxonomies"));
        $this->assertTrue(file_exists("{$kEndpoint}/{$instanceCode}/routes/{$id}/taxonomies/activity.json"));
        $this->assertTrue(file_exists("{$kEndpoint}/{$instanceCode}/routes/{$id}/taxonomies/webmapp_category.json"));

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

        $file = json_decode(file_get_contents("{$kEndpoint}/{$instanceCode}/routes/route_index.geojson"), true);
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

        $file = json_decode(file_get_contents("{$kEndpoint}/{$instanceCode}/routes/full_geometry_route_index.geojson"), true);
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