<?php

use PHPUnit\Framework\TestCase;

class WebmappDeletePoiJobTest extends TestCase
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

    function testDeletionStopWhenPoiPublic()
    {
        $aEndpoint = "./data/a";
        $kEndpoint = "./data/k";
        $instanceUrl = "http://elm.be.webmapp.it";
        $instanceName = "elm.be.webmapp.it";
        $id = 1459;

        $this->_createProjectStructure($aEndpoint, $kEndpoint, $instanceName);

        $params = "{\"id\":{$id}}";
        $job = new WebmappDeletePoiJob($instanceUrl, $params, false);
        try {
            $job->run();
        } catch (WebmappExceptionFeatureStillExists $exception) {
            $this->assertTrue(true);
            // Fake assertion since the poi 1459 should be public and available and
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

        $this->_createProjectStructure($aEndpoint, $kEndpoint, $instanceName);

        $params = "{\"id\":{$id}}";
        $job = new WebmappDeletePoiJob($instanceUrl, $params, false);
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
                    "type" => "Point",
                    "coordinates" => [0, 0]
                ]
            ];
            $webmappCategory = [
                "100" => [
                    "id" => 100,
                    "items" => [
                        "poi" => [$id]
                    ]
                ]
            ];
            $theme = [
                "200" => [
                    "id" => 200,
                    "items" => [
                        "poi" => [$id, 1000]
                    ]
                ]
            ];
            $themeCollection = [
                "type" => "FeatureCollection",
                "features" => [
                    $geojson
                ]
            ];

            file_put_contents($geojsonUrl, json_encode($geojson));
            file_put_contents($webmappCategoryUrl, json_encode($webmappCategory));
            file_put_contents($themeUrl, json_encode($theme));
            file_put_contents($themeCollectionUrl, json_encode($themeCollection));

            $job->run();
        } catch (Exception $e) {
            $this->fail($e->getMessage());
        }

        $this->assertTrue(!file_exists($geojsonUrl));

        $this->assertTrue(file_exists($webmappCategoryUrl));
        $file = json_decode(file_get_contents($webmappCategoryUrl), true);
        $this->assertIsArray($file);
        $this->assertSame(count($file), 0);

        $this->assertTrue(file_exists($themeUrl));
        $file = json_decode(file_get_contents($themeUrl), true);
        $this->assertIsArray($file);
        $this->assertSame(count($file), 1);

        $this->assertTrue(!file_exists($themeCollectionUrl));
    }
}