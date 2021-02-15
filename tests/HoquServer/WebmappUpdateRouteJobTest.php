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

    function testLastModified()
    {
        $aEndpoint = "./data/a";
        $kEndpoint = "./data/k";
        $instanceUrl = "http://elm.be.webmapp.it";
        $instanceName = "elm.be.webmapp.it";
        $id = 2056;

        WebmappHelpers::createProjectStructure($aEndpoint, $kEndpoint, $instanceName);

        $params = "{\"id\":{$id}}";
        $job = new WebmappUpdateRouteJob($instanceUrl, $params, false);
        try {
            $job->run();
        } catch (Exception $e) {
            $this->fail($e->getMessage());
        }

        $geojsonUrl = "{$aEndpoint}/{$instanceName}/geojson/{$id}.geojson";
        $this->assertTrue(file_exists($geojsonUrl));
        $file = json_decode(file_get_contents($geojsonUrl), true);

        $this->assertArrayHasKey("modified", $file["properties"]);
        $this->assertTrue(isset($file["properties"]["modified"]));

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');
        curl_setopt($ch, CURLOPT_HTTPHEADER, []);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, "{$instanceUrl}/wp-json/webmapp/v1/feature/last_modified/{$id}");
        $result = curl_exec($ch);
        curl_close($ch);

        $lastModifiedApi = json_decode($result, true);
        $lastModifiedApi = WebmappUtils::formatDate(strtotime($lastModifiedApi["last_modified"]));

        $this->assertSame($file["properties"]["modified"], $lastModifiedApi);
    }

    function testKTaxonomiesUpdate()
    {
        $aEndpoint = "./data/a";
        $kEndpoint = "./data/k";
        $instanceUrl = "http://elm.be.webmapp.it";
        $instanceName = "elm.be.webmapp.it";
        $instanceCode = "elm";
        $instanceCode2 = "elm2";
        $id = 2056;

        WebmappHelpers::createProjectStructure($aEndpoint, $kEndpoint, $instanceName, null, [$instanceCode, $instanceCode2], [["multimap" => true], ["multimap" => false]]);

        $params = "{\"id\":{$id}}";
        $job = new WebmappUpdateRouteJob($instanceUrl, $params, false);
        try {
            $job->run();
        } catch (Exception $e) {
            $this->fail($e->getMessage());
        }

        $this->assertTrue(file_exists("{$kEndpoint}/{$instanceCode}/taxonomies/webmapp_category.json"));
        $file = json_decode(file_get_contents("{$kEndpoint}/{$instanceCode}/taxonomies/webmapp_category.json"), true);
        $this->assertIsArray($file);

        $this->assertTrue(file_exists("{$kEndpoint}/{$instanceCode}/taxonomies/activity.json"));
        $file = json_decode(file_get_contents("{$kEndpoint}/{$instanceCode}/taxonomies/activity.json"), true);
        $this->assertIsArray($file);
        $this->assertArrayHasKey(127, $file);
        $this->assertIsArray($file[127]);
        $this->assertArrayHasKey("items", $file[127]);
        $this->assertIsArray($file[127]["items"]);
        $this->assertCount(1, $file[127]["items"]);
        $this->assertArrayHasKey("route", $file[127]["items"]);
        $this->assertCount(1, $file[127]["items"]["route"]);
        $this->assertSame($file[127]["items"]["route"][0], $id);

        $this->assertTrue(file_exists("{$kEndpoint}/{$instanceCode}/taxonomies/theme.json"));
        $file = json_decode(file_get_contents("{$kEndpoint}/{$instanceCode}/taxonomies/theme.json"), true);
        $this->assertIsArray($file);
        $this->assertArrayHasKey(384, $file);
        $this->assertIsArray($file[384]);
        $this->assertArrayHasKey("items", $file[384]);
        $this->assertIsArray($file[384]["items"]);
        $this->assertCount(1, $file[384]["items"]);
        $this->assertArrayHasKey("route", $file[384]["items"]);
        $this->assertCount(1, $file[384]["items"]["route"]);
        $this->assertSame($file[384]["items"]["route"][0], $id);

        $this->assertTrue(file_exists("{$kEndpoint}/{$instanceCode}/taxonomies/when.json"));
        $file = json_decode(file_get_contents("{$kEndpoint}/{$instanceCode}/taxonomies/when.json"), true);
        $this->assertIsArray($file);
        $this->assertArrayHasKey(190, $file);
        $this->assertIsArray($file[190]);
        $this->assertArrayHasKey("items", $file[190]);
        $this->assertIsArray($file[190]["items"]);
        $this->assertCount(1, $file[190]["items"]);
        $this->assertArrayHasKey("route", $file[190]["items"]);
        $this->assertCount(1, $file[190]["items"]["route"]);
        $this->assertSame($file[190]["items"]["route"][0], $id);
        $this->assertArrayHasKey(191, $file);
        $this->assertIsArray($file[191]);
        $this->assertArrayHasKey("items", $file[191]);
        $this->assertIsArray($file[191]["items"]);
        $this->assertCount(1, $file[191]["items"]);
        $this->assertArrayHasKey("route", $file[191]["items"]);
        $this->assertCount(1, $file[191]["items"]["route"]);
        $this->assertSame($file[191]["items"]["route"][0], $id);

        $this->assertTrue(file_exists("{$kEndpoint}/{$instanceCode}/taxonomies/where.json"));
        $file = json_decode(file_get_contents("{$kEndpoint}/{$instanceCode}/taxonomies/where.json"), true);
        $this->assertIsArray($file);
        $this->assertTrue(file_exists("{$kEndpoint}/{$instanceCode}/taxonomies/who.json"));
        $file = json_decode(file_get_contents("{$kEndpoint}/{$instanceCode}/taxonomies/who.json"), true);
        $this->assertIsArray($file);

        $this->assertFalse(file_exists("{$kEndpoint}/{$instanceCode2}/taxonomies/webmapp_category.json"));
        $this->assertFalse(file_exists("{$kEndpoint}/{$instanceCode2}/taxonomies/activity.json"));
        $this->assertFalse(file_exists("{$kEndpoint}/{$instanceCode2}/taxonomies/theme.json"));
        $this->assertFalse(file_exists("{$kEndpoint}/{$instanceCode2}/taxonomies/when.json"));
        $this->assertFalse(file_exists("{$kEndpoint}/{$instanceCode2}/taxonomies/where.json"));
        $this->assertFalse(file_exists("{$kEndpoint}/{$instanceCode2}/taxonomies/who.json"));
    }
}