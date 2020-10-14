<?php

require_once "WebmappAbstractJob.php";
require_once __DIR__ . "/../utils/WebmappExceptions.php";
require_once __DIR__ . "/../utils/WebmappUtils.php";
require_once __DIR__ . "/../utils/WebmappWP.php";
require_once __DIR__ . "/../features/WebmappAbstractFeature.php";
require_once __DIR__ . "/../features/WebmappPoiFeature.php";

class WebmappUpdatePoiJob extends WebmappAbstractJob
{
    public function __construct($instanceUrl, $params, $verbose)
    {
        parent::__construct("update_poi", $instanceUrl, $params, $verbose);
    }

    protected function process()
    {
        if ($this->verbose) {
            $this->verbose("Running process...");
        }

//        $aBase = "/var/www/html/a.webmapp.it/{$this->instanceName}";
        $aBase = "/Users/dvdpzzt/Downloads";// /{$this->instanceName}";

        try {
            // Load poi from be
            $poi = new WebmappPoiFeature("$this->instanceUrl/wp-json/wp/v2/poi/{$this->params['id']}");

            // Write geojson
            file_put_contents("{$aBase}/geojson/{$this->params['id']}.geojson", $poi->getJson());

//            $this->wp->loadTaxonomy('webmapp_category');
//            $this->wp->addItemToTaxonomies();
//            echo json_encode($this->wp->getTaxonomies());
//            echo json_encode($this->wp->getCategoriesArray());
//            $this->wp->writeTaxonomies($this->tax_path);
        } catch (WebmappExceptionPOINoCoodinates $e) {
            $this->error("The poi with id {$this->params['id']} is missing the coordinates");
        } catch (WebmappExceptionHttpRequest $e) {
            $this->error("The instance $this->instanceUrl is unreachable or the poi with id {$this->params['id']} does not exists");
        } catch (Exception $e) {
            $this->error("An unknown error occurred: " . json_encode($e));
        }

        if ($this->verbose) {
            $this->verbose("Process completed");
        }
    }
}

$a = new WebmappUpdatePoiJob("http://elm.be.webmapp.it", "{\"id\":\"1459\"}", true);
$a->run();