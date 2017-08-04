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
    SERIALIZED: a:2:{s:4:"type";s:10:"LineString";s:11:"coordinates";a:42:{i:0;a:2:{i:0;d:5.0802517309784996;i:1;d:52.019237307
        JSON: { "type" : "LineString" ,
                "coordinates": [
                       [ 11.238753551237847, 43.55744054805567],
              }
    **/
	protected function mappingGeometry($json_array) {
        // TODO: controllo esistenza coordinate
        $this->geometry=unserialize($json_array['n7webmap_geojson']);
	}

    // Restituisce un array di oggetti WebmappPoiFeature con i relatedPoi
    public function getRelatedPois() {
        $pois = array();
        if (isset($this->json_array['n7webmap_related_poi']) && 
            is_array($this->json_array['n7webmap_related_poi']) &&
            count($this->json_array['n7webmap_related_poi'])>0) {
            foreach ($this->json_array['n7webmap_related_poi'] as $poi) {
                $guid = $poi['guid'];
                $id = $poi['ID'];
                // http://dev.be.webmapp.it/poi/bar-pasticceria-lilli/
                $code = str_replace('http://', '', $guid);
                $code = preg_replace('|\..*|', '', $code);
                $poi_url = "http://$code.be.webmapp.it/wp-json/wp/v2/poi/$id";
                $pois[] = new WebmappPoiFeature ($poi_url);
            }
        }
        return $pois;
    }
}