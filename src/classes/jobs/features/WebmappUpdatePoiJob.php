<?php

class WebmappUpdatePoiJob extends WebmappAbstractJob
{
    /**
     * WebmappUpdatePoiJob constructor.
     * @param string $instanceUrl containing the instance url
     * @param string $params containing an encoded JSON with the poi ID
     * @param false $verbose
     * @throws WebmappExceptionNoDirectory
     * @throws WebmappExceptionParameterError
     * @throws WebmappExceptionParameterMandatory
     */
    public function __construct(string $instanceUrl, string $params, $verbose = false)
    {
        parent::__construct("update_poi", $instanceUrl, $params, $verbose);
    }

    /**
     * @throws WebmappExceptionHttpRequest
     */
    protected function process()
    {
        $this->_verbose("Loading poi from {$this->wp->getApiPoi($this->id)}");
        $poi = new WebmappPoiFeature($this->wp->getApiPoi($this->id));
        $poi = $this->_setCustomProperties($poi);

        $poi->setProperty("modified", $this->_getPostLastModified($this->id, strtotime($poi->getProperty("modified"))));

        // Write geojson
        $geojsonUrl = "{$this->aProject->getRoot()}/geojson/{$this->id}.geojson";
        $this->_lockFile($geojsonUrl);
        $this->_verbose("Writing poi to $geojsonUrl...");
        file_put_contents($geojsonUrl, $poi->getJson());
        $this->_unlockFile($geojsonUrl);

        $this->_setTaxonomies("poi", json_decode($poi->getJson(), true));

        $this->_updateKProjects("poi", $this->id, $poi->getJson());
    }

    /**
     * Map the custom properties in the poi
     *
     * @param WebmappPoiFeature $poi
     * @return WebmappPoiFeature
     */
    protected function _setCustomProperties(WebmappPoiFeature $poi)
    {
        $this->_verbose("Mapping custom properties");
        $properties = $this->_getCustomProperties("poi");
        if (isset($properties) && is_array($properties)) {
            $poi->mapCustomProperties($properties);
        }

        return $poi;
    }
}