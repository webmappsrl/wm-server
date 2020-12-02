<?php

use PHPUnit\Framework\TestCase;

class WebmappDeleteTrackJobTest extends TestCase
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

        $cmd = "rm {$a}/{$instanceName}/geojson/* &>/dev/null";
        system($cmd);
        $cmd = "rm {$a}/{$instanceName}/taxonomies/* &>/dev/null";
        system($cmd);

        if (!$conf) $conf = [];

        file_put_contents("{$a}/{$instanceName}/server/server.conf", json_encode($conf));
    }

    function testDeletionStopWhenTrackPublic()
    {
        $aEndpoint = "./data/a";
        $kEndpoint = "./data/k";
        $instanceUrl = "http://elm.be.webmapp.it";
        $instanceName = "elm.be.webmapp.it";
        $id = 2036;

        $this->_createProjectStructure($aEndpoint, $kEndpoint, $instanceName);

        $params = "{\"id\":{$id}}";
        $job = new WebmappDeleteTrackJob($instanceUrl, $params, false);
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
        $id = 0; // This is a non existing poi for sure - delete should perform as expected
        $geojsonUrl = "{$aEndpoint}/{$instanceName}/geojson/{$id}.geojson";
        $webmappCategoryUrl = "{$aEndpoint}/{$instanceName}/taxonomies/webmapp_category.json";
        $themeUrl = "{$aEndpoint}/{$instanceName}/taxonomies/theme.json";
        $themeCollectionUrl = "{$aEndpoint}/{$instanceName}/taxonomies/200.geojson";
        $whereUrl = "{$aEndpoint}/{$instanceName}/taxonomies/where.json";

        $this->_createProjectStructure($aEndpoint, $kEndpoint, $instanceName);

        $params = "{\"id\":{$id}}";
        $job = new WebmappDeleteTrackJob($instanceUrl, $params, false);
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
                        "track" => [$id]
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
                        "track" => [$id, 10000]
                    ]
                ],
                "400" => [
                    "id" => 400,
                    "items" => [
                        "poi" => [10000],
                        "track" => [$id]
                    ]
                ]
            ];

            file_put_contents($geojsonUrl, json_encode($geojson));
            file_put_contents($webmappCategoryUrl, json_encode($webmappCategory));
            file_put_contents($themeUrl, json_encode($theme));
            file_put_contents($themeCollectionUrl, json_encode($themeCollection));
            file_put_contents($whereUrl, json_encode($where));

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
        $this->assertArrayHasKey("track", $file["300"]["items"]);
        $this->assertSame(count($file["300"]["items"]["track"]), 1);
        $this->assertArrayHasKey("400", $file);
        $this->assertIsArray($file["400"]);
        $this->assertArrayHasKey("items", $file["400"]);
        $this->assertIsArray($file["400"]["items"]);
        $this->assertArrayHasKey("poi", $file["400"]["items"]);
        $this->assertSame(count($file["400"]["items"]["poi"]), 1);
        $this->assertFalse(array_key_exists("track", $file["400"]["items"]));
    }
}