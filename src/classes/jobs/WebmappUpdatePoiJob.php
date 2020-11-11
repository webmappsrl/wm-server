<?php

class WebmappUpdatePoiJob extends WebmappAbstractJob
{
    /**
     * WebmappUpdatePoiJob constructor.
     * @param $instanceUrl string containing the instance url
     * @param $params string containing an encoded JSON with the poi ID
     * @param false $verbose
     */
    public function __construct($instanceUrl, $params, $verbose = false)
    {
        parent::__construct("update_poi", $instanceUrl, $params, $verbose);
    }

    protected function process()
    {
        $id = intval($this->params['id']);

        try {
            // Load poi from be
            if ($this->verbose) {
                WebmappUtils::verbose("Loading poi from {$this->wp->getApiPoi($id)}");
            }
            $poi = new WebmappPoiFeature($this->wp->getApiPoi($id));
            $json = json_decode($poi->getJson(), true);

            // Write geojson
            if ($this->verbose) {
                WebmappUtils::verbose("Writing poi to {$this->aProject->getRoot()}/geojson/{$id}.geojson...");
            }
            file_put_contents("{$this->aProject->getRoot()}/geojson/{$id}.geojson", $poi->getJson());

            $this->_setTaxonomies("poi", json_decode($poi->getJson(), true));

            $this->_updateKProjects("poi", $id, $poi->getJson());
        } catch (WebmappExceptionPOINoCoodinates $e) {
            throw new WebmappExceptionPOINoCoodinates("The poi with id {$id} is missing the coordinates");
        } catch (WebmappExceptionHttpRequest $e) {
            throw new WebmappExceptionHttpRequest("The instance $this->instanceUrl is unreachable or the poi with id {$id} does not exists");
        } catch (Exception $e) {
            throw new WebmappException("An unknown error occurred: " . json_encode($e));
        }
    }
}