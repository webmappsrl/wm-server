<?php 

class WebmappTrackFeature extends WebmappAbstractFeature {

	// Mapping dei meta specifici dei punti
	// http://dev.be.webmapp.local/wp-json/wp/v2/poi/38
	protected function mappingSpecific($json_array) {
		//$this->setProperty('addr:street',$json_array);
	}

    // Impostazione della geometry a partire da formato API WP
    /**
    **/

	protected function mappingGeometry($json_array) {
        // TODO: controllo esistenza coordinate
	}

}