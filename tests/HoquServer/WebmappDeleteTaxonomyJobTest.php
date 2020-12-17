<?php

require_once 'helpers/WebmappTestHelpers.php';

use PHPUnit\Framework\TestCase;

class WebmappDeleteTaxonomyJobTest extends TestCase
{
    public function __construct()
    {
        $this->setOutputCallback(function () {
        });
        parent::__construct();
    }

    function testDeletionCompleteWhenNotGenerated()
    {
        $aEndpoint = "./data/a";
        $kEndpoint = "./data/k";
        $instanceUrl = "http://elm.be.webmapp.it";
        $instanceName = "elm.be.webmapp.it";
        $id = 161;

        WebmappHelpers::createProjectStructure($aEndpoint, $kEndpoint, $instanceName);

        $params = "{\"id\":{$id}}";
        $job = new WebmappDeleteTaxonomyJob($instanceUrl, $params, false);
        try {
            $job->run();
        } catch (Exception $e) {
            $this->fail($e->getMessage());
        }

        // The simple fact that complete means is correct since the taxonomy should
        // have never been generated in first place - deletion should delete nothing
        $this->assertTrue(true);
    }

    function testDeletionStopWhenPublicAndAlreadyGenerated()
    {
        $aEndpoint = "./data/a";
        $kEndpoint = "./data/k";
        $instanceUrl = "http://elm.be.webmapp.it";
        $instanceName = "elm.be.webmapp.it";
        $id = 127;

        WebmappHelpers::createProjectStructure($aEndpoint, $kEndpoint, $instanceName);

        $params = "{\"id\":{$id}}";
        $job = new WebmappDeleteTaxonomyJob($instanceUrl, $params, false);
        try {
            $activityUrl = "{$aEndpoint}/{$instanceName}/taxonomies/activity.json";
            $activity = [
                $id => []
            ];
            file_put_contents($activityUrl, json_encode($activity));

            $job->run();
        } catch (WebmappExceptionTaxonomyStillExists $exception) {
            $this->assertTrue(true);
            // Fake assertion since the taxonomy 161 should be public and available and
            // the job should not perform any action
            return;
        } catch (Exception $e) {
            $this->fail($e->getMessage());
        }

        $this->fail("The job should trigger an error since the taxonomy should be public");
    }

    function testFileDeletionWhenGenerated()
    {
        $aEndpoint = "./data/a";
        $kEndpoint = "./data/k";
        $instanceUrl = "http://elm.be.webmapp.it";
        $instanceName = "elm.be.webmapp.it";
        $instanceCode = "elm";
        $id = 0; // This is a non existing taxonomy for sure - delete should perform as expected
        $taxonomyType = "activity";
        $poiId = 100;
        $poiUrl = "$aEndpoint/$instanceName/geojson/$poiId.geojson";
        $trackId = 200;
        $trackUrl = "$aEndpoint/$instanceName/geojson/$trackId.geojson";
        $routeId = 300;
        $routeUrl = "$aEndpoint/$instanceName/geojson/$routeId.geojson";
        $activityUrl = "$aEndpoint/$instanceName/taxonomies/activity.json";
        $activityCollectionUrl = "$aEndpoint/$instanceName/taxonomies/$id.geojson";
        $themeUrl = "$aEndpoint/$instanceName/taxonomies/theme.json";
        $wcUrl = "$aEndpoint/$instanceName/taxonomies/webmapp_category.json";
        $whenUrl = "$aEndpoint/$instanceName/taxonomies/when.json";
        $whereUrl = "$aEndpoint/$instanceName/taxonomies/where.json";
        $whoUrl = "$aEndpoint/$instanceName/taxonomies/who.json";
        $routeIndexUrl = "$aEndpoint/$instanceName/geojson/route_index.geojson";
        $fullRouteIndexUrl = "$aEndpoint/$instanceName/geojson/full_geometry_route_index.geojson";
        $kRouteIndexUrl = "$kEndpoint/$instanceCode/routes/route_index.geojson";
        $kFullRouteIndexUrl = "$kEndpoint/$instanceCode/routes/full_geometry_route_index.geojson";

        WebmappHelpers::createProjectStructure($aEndpoint, $kEndpoint, $instanceName, null, $instanceCode, ["multimap" => true]);

        $activity = [
            $id => [
                "id" => $id,
                "items" => [
                    "poi" => [150],
                    "track" => [$trackId],
                    "route" => [$routeId]
                ]
            ]
        ];
        $poi = [
            "type" => "Feature",
            "geometry" => [
                "type" => "Point",
                "coordinated" => [0, 0]
            ],
            "properties" => [
                "id" => $poiId,
                "taxonomy" => [
                    $taxonomyType => [8]
                ]
            ]
        ];
        $track = [
            "type" => "Feature",
            "geometry" => [
                "type" => "LineString",
                "coordinated" => [[0, 0], [0, 0]]
            ],
            "properties" => [
                "id" => $trackId,
                "taxonomy" => [
                    $taxonomyType => [$id, 2],
                    "theme" => [3]
                ]
            ]
        ];
        $route = [
            "type" => "Feature",
            "geometry" => [
                "type" => "LineString",
                "coordinated" => [[0, 0], [0, 0]]
            ],
            "properties" => [
                "id" => $routeId,
                "taxonomy" => [
                    $taxonomyType => [$id],
                    "theme" => [4]
                ]
            ]
        ];
        $routeIndex = [
            "type" => "FeatureCollection",
            "features" => [$route]
        ];
        $fullRouteIndex = [
            "type" => "FeatureCollection",
            "features" => [$route]
        ];
        $activityCollection = [
            "type" => "FeatureCollection",
            "features" => [$route, $track],
            "properties" => [
                "id" => $id,
                "items" => [
                    "poi" => [150],
                    "track" => [$trackId],
                    "route" => [$routeId]
                ]
            ]
        ];

        file_put_contents($poiUrl, json_encode($poi));
        file_put_contents($trackUrl, json_encode($track));
        file_put_contents($routeUrl, json_encode($route));
        file_put_contents($routeIndexUrl, json_encode($routeIndex));
        file_put_contents($fullRouteIndexUrl, json_encode($fullRouteIndex));
        file_put_contents($kRouteIndexUrl, json_encode($routeIndex));
        file_put_contents($kFullRouteIndexUrl, json_encode($fullRouteIndex));
        file_put_contents($wcUrl, json_encode([]));
        file_put_contents($activityUrl, json_encode($activity));
        file_put_contents($themeUrl, json_encode([]));
        file_put_contents($whenUrl, json_encode([]));
        file_put_contents($whereUrl, json_encode([]));
        file_put_contents($whoUrl, json_encode([]));
        file_put_contents($activityCollectionUrl, json_encode($activityCollection));

        $params = "{\"id\":{$id}}";
        try {
            $job = new WebmappDeleteTaxonomyJob($instanceUrl, $params, false);
            $job->run();
        } catch (Exception $e) {
            $this->fail($e->getMessage());
        }

        $this->assertTrue(file_exists($poiUrl));
        $json = json_decode(file_get_contents($poiUrl), true);
        $this->assertTrue(isset($json["properties"]["taxonomy"]));
        $this->assertSame(json_encode($json["properties"]["taxonomy"]), json_encode($poi["properties"]["taxonomy"]));
        $this->assertTrue(file_exists($trackUrl));
        $json = json_decode(file_get_contents($trackUrl), true);
        $this->assertTrue(isset($json["properties"]["taxonomy"][$taxonomyType]));
        $this->assertTrue(isset($json["properties"]["taxonomy"]["theme"]));
        $this->assertCount(1, $json["properties"]["taxonomy"][$taxonomyType]);
        $this->assertSame($json["properties"]["taxonomy"][$taxonomyType][0], $track["properties"]["taxonomy"][$taxonomyType][1]);
        $this->assertSame(json_encode($json["properties"]["taxonomy"]["theme"]), json_encode($track["properties"]["taxonomy"]["theme"]));
        $this->assertTrue(file_exists($routeUrl));
        $json = json_decode(file_get_contents($routeUrl), true);
        $this->assertFalse(isset($json["properties"]["taxonomy"][$taxonomyType]));
        $this->assertTrue(isset($json["properties"]["taxonomy"]["theme"]));
        $this->assertSame(json_encode($json["properties"]["taxonomy"]["theme"]), json_encode($route["properties"]["taxonomy"]["theme"]));

        $this->assertTrue(file_exists($wcUrl));
        $json = json_decode(file_get_contents($wcUrl), true);
        $this->assertCount(0, $json);

        $this->assertTrue(file_exists($activityUrl));
        $json = json_decode(file_get_contents($activityUrl), true);
        $this->assertCount(0, $json);

        $this->assertTrue(file_exists($themeUrl));
        $json = json_decode(file_get_contents($themeUrl), true);
        $this->assertCount(0, $json);

        $this->assertTrue(file_exists($whenUrl));
        $json = json_decode(file_get_contents($whenUrl), true);
        $this->assertCount(0, $json);

        $this->assertTrue(file_exists($whereUrl));
        $json = json_decode(file_get_contents($whereUrl), true);
        $this->assertCount(0, $json);

        $this->assertTrue(file_exists($whoUrl));
        $json = json_decode(file_get_contents($whoUrl), true);
        $this->assertCount(0, $json);

        $this->assertTrue(file_exists($routeIndexUrl));
        $json = json_decode(file_get_contents($routeIndexUrl), true);
        $this->assertSame($json["type"], "FeatureCollection");
        $this->assertIsArray($json["features"]);
        $this->assertCount(1, $json["features"]);
        $this->assertTrue(isset($json["features"][0]["properties"]["taxonomy"]));
        $this->assertFalse(isset($json["features"][0]["properties"]["taxonomy"][$taxonomyType]));
        $this->assertTrue(isset($json["features"][0]["properties"]["taxonomy"]["theme"]));
        $this->assertSame(json_encode($json["features"][0]["properties"]["taxonomy"]["theme"]), json_encode($route["properties"]["taxonomy"]["theme"]));

        $this->assertTrue(file_exists($fullRouteIndexUrl));
        $json = json_decode(file_get_contents($fullRouteIndexUrl), true);
        $this->assertSame($json["type"], "FeatureCollection");
        $this->assertIsArray($json["features"]);
        $this->assertCount(1, $json["features"]);
        $this->assertTrue(isset($json["features"][0]["properties"]["taxonomy"]));
        $this->assertFalse(isset($json["features"][0]["properties"]["taxonomy"][$taxonomyType]));
        $this->assertTrue(isset($json["features"][0]["properties"]["taxonomy"]["theme"]));
        $this->assertSame(json_encode($json["features"][0]["properties"]["taxonomy"]["theme"]), json_encode($route["properties"]["taxonomy"]["theme"]));

        $this->assertTrue(file_exists($kRouteIndexUrl));
        $json = json_decode(file_get_contents($kRouteIndexUrl), true);
        $this->assertSame($json["type"], "FeatureCollection");
        $this->assertIsArray($json["features"]);
        $this->assertCount(1, $json["features"]);
        $this->assertTrue(isset($json["features"][0]["properties"]["taxonomy"]));
        $this->assertFalse(isset($json["features"][0]["properties"]["taxonomy"][$taxonomyType]));
        $this->assertTrue(isset($json["features"][0]["properties"]["taxonomy"]["theme"]));
        $this->assertSame(json_encode($json["features"][0]["properties"]["taxonomy"]["theme"]), json_encode($route["properties"]["taxonomy"]["theme"]));

        $this->assertTrue(file_exists($kFullRouteIndexUrl));
        $json = json_decode(file_get_contents($kFullRouteIndexUrl), true);
        $this->assertSame($json["type"], "FeatureCollection");
        $this->assertIsArray($json["features"]);
        $this->assertCount(1, $json["features"]);
        $this->assertTrue(isset($json["features"][0]["properties"]["taxonomy"]));
        $this->assertFalse(isset($json["features"][0]["properties"]["taxonomy"][$taxonomyType]));
        $this->assertTrue(isset($json["features"][0]["properties"]["taxonomy"]["theme"]));
        $this->assertSame(json_encode($json["features"][0]["properties"]["taxonomy"]["theme"]), json_encode($route["properties"]["taxonomy"]["theme"]));

        $this->assertFalse(file_exists($activityCollectionUrl));
    }
}