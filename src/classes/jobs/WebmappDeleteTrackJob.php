<?php

class WebmappDeleteTrackJob extends WebmappAbstractJob
{
    /**
     * WebmappDeleteTrackJob constructor.
     * @param string $instanceUrl containing the instance url
     * @param string $params containing an encoded JSON with the track ID
     * @param bool $verbose
     * @throws WebmappExceptionNoDirectory
     */
    public function __construct(string $instanceUrl, string $params, bool $verbose = false)
    {
        parent::__construct("delete_track", $instanceUrl, $params, $verbose);
    }

    protected function process()
    {
        $id = intval($this->params['id']);
        if ($this->verbose) {
            $this->_verbose("Checking if track is available from {$this->wp->getApiTrack($id)}");
        }
        $ch = $this->_getCurl($this->wp->getApiTrack($id));
        curl_exec($ch);

        if (curl_getinfo($ch, CURLINFO_HTTP_CODE) < 400)
            throw new WebmappExceptionFeatureStillExists("The track seems to be still public. Deletion stopped to prevent data loss");
        else {
            if ($this->verbose) {
                $this->_verbose("Check complete. Starting clean");
            }

            // Delete the geojson
            $geojsonUrl = "{$this->aProject->getRoot()}/geojson/{$id}.geojson";
            if (file_exists($geojsonUrl)) {
                if ($this->verbose) {
                    $this->_verbose("Removing {$geojsonUrl}");
                }
                unlink($geojsonUrl);
            }

            // Delete id from the taxonomies
            $this->_setTaxonomies('track', [
                "properties" => [
                    "id" => $id,
                    "taxonomies" => []
                ]
            ]);
        }
    }
}