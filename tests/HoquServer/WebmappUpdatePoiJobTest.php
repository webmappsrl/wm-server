<?php

require_once 'helpers/WebmappTestHelpers.php';

use PHPUnit\Framework\TestCase;

class WebmappUpdatePoiJobTest extends TestCase
{
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
        $id = 1459;

        WebmappHelpers::createProjectStructure($aEndpoint, $kEndpoint, $instanceName);

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

        WebmappHelpers::createProjectStructure($aEndpoint, $kEndpoint, $instanceName);

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

        WebmappHelpers::createProjectStructure($aEndpoint, $kEndpoint, $instanceName, $conf);

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

    function testLastModified()
    {
        $aEndpoint = "./data/a";
        $kEndpoint = "./data/k";
        $instanceUrl = "http://elm.be.webmapp.it";
        $instanceName = "elm.be.webmapp.it";
        $id = 1459;

        WebmappHelpers::createProjectStructure($aEndpoint, $kEndpoint, $instanceName);

        $params = "{\"id\":{$id}}";
        $job = new WebmappUpdatePoiJob($instanceUrl, $params, false);
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
}