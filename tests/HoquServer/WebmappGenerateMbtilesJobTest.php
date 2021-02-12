<?php

require_once 'helpers/WebmappTestHelpers.php';

use PHPUnit\Framework\TestCase;

class WebmappGenerateMbtilesJobTest extends TestCase
{
    public function __construct()
    {
//        $this->setOutputCallback(function () {
//        });
        parent::__construct();
    }

    function testTriggerErrorWhenDirectoryMissing()
    {
        $aEndpoint = "./data/a";
        $kEndpoint = "./data/k";
        $instanceUrl = "http://elm.be.webmapp.it";
        $instanceName = "elm.be.webmapp.it";
        $instanceCode = "elm";
        $id = 2036;

        WebmappHelpers::createProjectStructure($aEndpoint, $kEndpoint, $instanceName, null, $instanceCode);

        $params = "{\"id\":{$id}}";
        $job = new WebmappGenerateMbtilesJob($instanceUrl, $params, false);
        try {
            $job->run();
        } catch (Exception $e) {
            $this->assertTrue(true);
            return;
        }
        $this->fail("It should trigger an error but apparently it works. Some dark magic happened and it is not good");
    }
}