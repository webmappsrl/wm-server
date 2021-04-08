<?php

require_once 'helpers/WebmappTestHelpers.php';

use PHPUnit\Framework\TestCase;

class WebmappGenerateAudioJobTest extends TestCase {
    public function __construct() {
        $this->setOutputCallback(function () {
        });
        parent::__construct();
    }

    function testAudioJobWithoutGeojson() {
        $aEndpoint = "./data/a";
        $kEndpoint = "./data/k";
        $instanceUrl = "http://elm.be.webmapp.it";
        $instanceName = "elm.be.webmapp.it";
        $id = 1459;
        $lang = "en";

        WebmappHelpers::createProjectStructure($aEndpoint, $kEndpoint, $instanceName);

        $params = "{\"id\":{$id},\"lang\":\"{$lang}\"}";
        $job = new WebmappGenerateAudioJob($instanceUrl, $params, false);
        try {
            $job->run();
            $this->fail("The job should have failed since there should be no file to generate the audio from");
        } catch (Exception $e) {
            $this->assertFalse(file_exists("{$aEndpoint}/{$instanceName}/media/audios/{$id}_{$lang}.mp3"));
        }
    }

    function testAudioJobFileCreation() {
        $aEndpoint = "./data/a";
        $kEndpoint = "./data/k";
        $instanceUrl = "http://elm.be.webmapp.it";
        $instanceName = "elm.be.webmapp.it";
        $id = 1459;
        $lang = "en";

        WebmappHelpers::createProjectStructure($aEndpoint, $kEndpoint, $instanceName);

        $params = "{\"id\":{$id}}";
        $job = new WebmappUpdatePoiJob($instanceUrl, $params, false);
        try {
            $job->run();
        } catch (Exception $e) {
            $this->fail($e->getMessage());
        }

        $this->assertTrue(file_exists("{$aEndpoint}/{$instanceName}/geojson/{$id}.geojson"));

        $params = "{\"id\":{$id},\"lang\":\"{$lang}\"}";
        $job = new WebmappGenerateAudioJob($instanceUrl, $params, false);
        try {
            $job->run();
        } catch (Exception $e) {
            $this->fail($e->getMessage());
        }

        $this->assertTrue(file_exists("{$aEndpoint}/{$instanceName}/media/audios/{$id}_{$lang}.mp3"));
    }
}
