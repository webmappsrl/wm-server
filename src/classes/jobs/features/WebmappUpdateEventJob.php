<?php

class WebmappUpdateEventJob extends WebmappAbstractJob {
    /**
     * WebmappUpdatePoiJob constructor.
     *
     * @param string $instanceUrl containing the instance url
     * @param string $params      containing an encoded JSON with the poi ID
     * @param false  $verbose
     *
     * @throws WebmappExceptionNoDirectory
     * @throws WebmappExceptionParameterError
     * @throws WebmappExceptionParameterMandatory
     */
    public function __construct(string $instanceUrl, string $params, $verbose = false) {
        parent::__construct("update_event", $instanceUrl, $params, $verbose);
    }

    /**
     * @throws WebmappExceptionHttpRequest
     * @throws WebmappExceptionHoquRequest
     */
    protected function process() {
        $this->_verbose("Loading event from {$this->wp->getApiEvent($this->id)}");
        $poi = new WebmappPoiFeature($this->wp->getApiEvent($this->id));
        $poi = $this->_setCustomProperties($poi);
        $poi->setEventProperties();
        $poi->addProperty("modified", WebmappUtils::formatDate($this->_getPostLastModified($this->id, strtotime($poi->getProperty("modified")))));
        $poi->addProperty("post_type", "event");

        // Write geojson
        $geojsonUrl = "{$this->aProject->getRoot()}/geojson/{$this->id}.geojson";
        $this->_lockFile($geojsonUrl);
        $this->_verbose("Writing event to $geojsonUrl...");
        file_put_contents($geojsonUrl, $poi->getJson());
        $this->_unlockFile($geojsonUrl);

        $this->_setTaxonomies("event", json_decode($poi->getJson(), true));

        $this->_checkAudios(json_decode($poi->getJson(), true));
    }

    /**
     * Map the custom properties in the poi
     *
     * @param WebmappPoiFeature $poi
     *
     * @return WebmappPoiFeature
     */
    protected function _setCustomProperties(WebmappPoiFeature $poi) {
        $this->_verbose("Mapping custom properties");
        $properties = $this->_getCustomProperties("poi");
        if (isset($properties) && is_array($properties))
            $poi->mapCustomProperties($properties);

        return $poi;
    }
}
