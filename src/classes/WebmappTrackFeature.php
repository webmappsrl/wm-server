<?php 

class WebmappTrackFeature extends WebmappAbstractFeature {

	// Mapping dei meta specifici delle tracks
    // 
	protected function mappingSpecific($json_array) {
        $this->setProperty('n7webmapp_track_color',$json_array,'color');
        $this->setProperty('n7webmap_start',$json_array,'from');
        $this->setProperty('n7webmap_end',$json_array,'to');
        $this->setProperty('ref',$json_array);
        $this->setProperty('ascent',$json_array);
        $this->setProperty('descent',$json_array);
        $this->setProperty('distance',$json_array);
        $this->setProperty('duration:forward',$json_array);
        $this->setProperty('duration:backward',$json_array);
        $this->setProperty('cai_scale',$json_array);
}

    // Impostazione della geometry a partire da formato API WP
    /**
    **/
	protected function mappingGeometry($json_array) {
        // TODO: controllo esistenza coordinate
        $this->geometry=unserialize($json_array['n7webmap_geojson']);
	}
}