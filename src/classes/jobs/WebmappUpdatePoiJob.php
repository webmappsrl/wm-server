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
                $this->_verbose("Loading poi from {$this->wp->getApiPoi($id)}");
            }
            $poi = new WebmappPoiFeature($this->wp->getApiPoi($id));
            $poi = $this->_setCustomProperties($poi);

            // Write geojson
            if ($this->verbose) {
                $this->_verbose("Writing poi to {$this->aProject->getRoot()}/geojson/{$id}.geojson...");
            }
            file_put_contents("{$this->aProject->getRoot()}/geojson/{$id}.geojson", $poi->getJson());

            $this->_setTaxonomies("poi", json_decode($poi->getJson(), true));

            $this->_updateKProjects("poi", $id, $poi->getJson());
        } catch (WebmappExceptionPOINoCoodinates $e) {
            throw new WebmappExceptionPOINoCoodinates("The poi with id {$id} is missing the coordinates");
        } catch (WebmappExceptionHttpRequest $e) {
            throw new WebmappExceptionHttpRequest("The instance $this->instanceUrl is unreachable or the poi with id {$id} does not exists");
        }
    }

    /**
     * Map the custom properties in the track
     *
     * @param WebmappPoiFeature $poi
     * @return WebmappPoiFeature
     */
    protected function _setCustomProperties(WebmappPoiFeature $poi)
    {
        if ($this->verbose) {
            $this->_verbose("Mapping custom properties");
        }
        $track_properties = $this->_getCustomProperties("poi");
        if (isset($track_properties) && is_array($track_properties)) {
            $poi->mapCustomProperties($track_properties);
        }

        return $poi;
    }
}