<?php

require_once 'helpers/WebmappTestHelpers.php';

use PHPUnit\Framework\TestCase;

class WebmappDeleteRouteJobTest extends TestCase
{
    public $kConf = [
        "multimap" => true,
        "routesFilter" => [2056]
    ];

    public function __construct()
    {
        $this->setOutputCallback(function () {
        });
        parent::__construct();
    }

    function testDeletionStopWhenPublic()
    {
        $aEndpoint = "./data/a";
        $kEndpoint = "./data/k";
        $instanceUrl = "http://elm.be.webmapp.it";
        $instanceName = "elm.be.webmapp.it";
        $instanceCode = "elm";
        $id = 2056;

        WebmappHelpers::createProjectStructure($aEndpoint, $kEndpoint, $instanceName, null, $instanceCode, $this->kConf);

        $params = "{\"id\":{$id}}";
        $job = new WebmappDeleteRouteJob($instanceUrl, $params, false);
        try {
            $job->run();
        } catch (WebmappExceptionFeatureStillExists $exception) {
            $this->assertTrue(true);
            // Fake assertion since the track 2036 should be public and available and
            // the job should not perform any action
            return;
        } catch (Exception $e) {
            $this->fail($e->getMessage());
        }

        $this->fail("The job should trigger an error since the poi should be public");
    }

    function testFileDeletion()
    {
        $aEndpoint = "./data/a";
        $kEndpoint = "./data/k";
        $instanceUrl = "http://elm.be.webmapp.it";
        $instanceName = "elm.be.webmapp.it";
        $instanceCode = "elm";
        $id = 0; // This is a non existing route for sure - delete should perform as expected
        $geojsonUrl = "{$aEndpoint}/{$instanceName}/geojson/{$id}.geojson";
        $webmappCategoryUrl = "{$aEndpoint}/{$instanceName}/taxonomies/webmapp_category.json";
        $themeUrl = "{$aEndpoint}/{$instanceName}/taxonomies/theme.json";
        $themeCollectionUrl = "{$aEndpoint}/{$instanceName}/taxonomies/200.geojson";
        $whereUrl = "{$aEndpoint}/{$instanceName}/taxonomies/where.json";
        $mapJsonUrl = "{$kEndpoint}/{$instanceCode}/routes/{$id}/map.json";
        $mapMbtilesUrl = "{$kEndpoint}/{$instanceCode}/routes/{$id}/map.mbtiles";
        $wcKRoutesUrl = "{$kEndpoint}/{$instanceCode}/routes/{$id}/webmapp_category.json";
        $activityKRoutesUrl = "{$kEndpoint}/{$instanceCode}/routes/{$id}/activity.json";
        $activityKUrl = "{$kEndpoint}/{$instanceCode}/taxonomies/activity.json";

        WebmappHelpers::createProjectStructure($aEndpoint, $kEndpoint, $instanceName, null, $instanceCode, $this->kConf);

        $params = "{\"id\":{$id}}";
        $job = new WebmappDeleteRouteJob($instanceUrl, $params, false);
        try {
            // Simulate the file existence - geojson and taxonomies
            $geojson = [
                "properties" => [
                    "id" => $id,
                    "name" => "Fake name",
                    "description" => "Fake description",
                    "taxonomies" => [
                        "webmapp_category" => ["100"],
                        "theme" => ["200"]
                    ]
                ],
                "type" => "Feature",
                "geometry" => [
                    "type" => "LineString",
                    "coordinates" => [[0, 0], [1, 1], [2, 2], [3, 3], [4, 4]]
                ]
            ];
            $webmappCategory = [
                "100" => [
                    "id" => 100,
                    "items" => [
                        "poi" => ["120"]
                    ]
                ]
            ];
            $theme = [
                "200" => [
                    "id" => 200,
                    "items" => [
                        "route" => [$id]
                    ]
                ]
            ];
            $themeCollection = [
                "type" => "FeatureCollection",
                "features" => [
                    $geojson
                ]
            ];
            $where = [
                "300" => [
                    "id" => 300,
                    "items" => [
                        "route" => [$id, 10000]
                    ]
                ],
                "400" => [
                    "id" => 400,
                    "items" => [
                        "poi" => [10000],
                        "route" => [$id]
                    ]
                ]
            ];
            $activityK = [
                "1000" => [
                    "id" => 1000,
                    "items" => [
                        "route" => [$id]
                    ]
                ]
            ];

            file_put_contents($geojsonUrl, json_encode($geojson));
            file_put_contents($webmappCategoryUrl, json_encode($webmappCategory));
            file_put_contents($themeUrl, json_encode($theme));
            file_put_contents($themeCollectionUrl, json_encode($themeCollection));
            file_put_contents($whereUrl, json_encode($where));

            $cmd = "mkdir -p {$kEndpoint}/{$instanceCode}/routes/{$id}/taxonomies";
            system($cmd);
            file_put_contents($mapJsonUrl, json_encode([]));
            file_put_contents($mapMbtilesUrl, json_encode([]));
            file_put_contents($activityKRoutesUrl, json_encode([]));
            file_put_contents($wcKRoutesUrl, json_encode([]));
            file_put_contents($activityKUrl, json_encode($activityK));

            $job->run();
        } catch (Exception $e) {
            $this->fail($e->getMessage());
        }

        $this->assertFalse(file_exists($geojsonUrl));

        $this->assertTrue(file_exists($webmappCategoryUrl));
        $file = json_decode(file_get_contents($webmappCategoryUrl), true);
        $this->assertIsArray($file);
        $this->assertSame(count($file), 1);

        $this->assertTrue(file_exists($themeUrl));
        $file = json_decode(file_get_contents($themeUrl), true);
        $this->assertIsArray($file);
        $this->assertSame(count($file), 0);

        $this->assertFalse(file_exists($themeCollectionUrl));

        $this->assertTrue(file_exists($whereUrl));
        $file = json_decode(file_get_contents($whereUrl), true);
        $this->assertIsArray($file);
        $this->assertSame(count($file), 2);
        $this->assertArrayHasKey("300", $file);
        $this->assertIsArray($file["300"]);
        $this->assertArrayHasKey("items", $file["300"]);
        $this->assertIsArray($file["300"]["items"]);
        $this->assertArrayHasKey("route", $file["300"]["items"]);
        $this->assertSame(count($file["300"]["items"]["route"]), 1);
        $this->assertArrayHasKey("400", $file);
        $this->assertIsArray($file["400"]);
        $this->assertArrayHasKey("items", $file["400"]);
        $this->assertIsArray($file["400"]["items"]);
        $this->assertArrayHasKey("poi", $file["400"]["items"]);
        $this->assertSame(count($file["400"]["items"]["poi"]), 1);
        $this->assertFalse(array_key_exists("route", $file["400"]["items"]));

        $this->assertFalse(file_exists($mapJsonUrl));
        $this->assertFalse(file_exists($mapMbtilesUrl));
        $this->assertFalse(file_exists($wcKRoutesUrl));
        $this->assertFalse(file_exists($activityKRoutesUrl));
        $this->assertTrue(file_exists($activityKUrl));
        $file = json_decode(file_get_contents($activityKUrl), true);
        $this->assertIsArray($file);
        $this->assertSame(count($file), 1);
        $this->assertArrayHasKey("1000", $file);
        $this->assertIsArray($file["1000"]);
        $this->assertArrayHasKey("items", $file["1000"]);
        $this->assertIsArray($file["1000"]["items"]);
        $this->assertArrayHasKey("route", $file["1000"]["items"]);
        $this->assertIsArray($file["1000"]["items"]["route"]);
        $this->assertSame(count($file["1000"]["items"]["route"]), 0);
    }
}