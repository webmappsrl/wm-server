<?php 

class WebmappTrackFeature extends WebmappAbstractFeature {

    private $lngMin;
    private $lngMax;
    private $latMin;
    private $latMax;
    private $bb_computed = false;

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
        // ADD id_pois
        $json_array['id_pois']=$this->getRelatedPoisId();
        $this->setProperty('id_pois',$json_array);
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
        if (isset($json_array['n7webmap_geojson'])) {
           $this->geometry=unserialize($json_array['n7webmap_geojson']);            
        }
	}


    private function computeBB() {
        $coords = $this->geometry['coordinates'];
        $first = true;
        foreach ($coords as $coord) {
            $lng = $coord[0];
            $lat = $coord[1];
            if($first) {
                $this->lngMin = $lng;
                $this->lngMax = $lng;
                $this->latMin = $lat;
                $this->latMax = $lat;
                $first = false;
            }
            else {
                if ($lng<$this->lngMin) $this->lngMin = $lng;
                if ($lng>$this->lngMax) $this->lngMax = $lng;
                if ($lat<$this->latMin) $this->latMin = $lat;
                if ($lat>$this->latMax) $this->latMax = $lat;       
            }
        }
        $this->bb_computed = true;
    }

    public function getLatMax(){if(!$this->bb_computed) $this->computeBB(); return $this->latMax;}
    public function getLatMin(){if(!$this->bb_computed) $this->computeBB(); return $this->latMin;}
    public function getLngMax(){if(!$this->bb_computed) $this->computeBB(); return $this->lngMax;}
    public function getLngMin(){if(!$this->bb_computed) $this->computeBB(); return $this->lngMin;}

    public function writeToPostGis() {
        // PER TRACK
        // ogr2ogr -update -f 'PostgreSQL' PG:'dbname=webmapptest user=webmapp host=46.101.124.52' '/root/api.webmapp.it/j/pf.j.webmapp.it/geojson/track/1452.geojson' -nln track_tmp
        if(file_exists($this->getGeoJsonPath())) {
            $geojson=$this->getGeoJsonPath();
            $cmd="ogr2ogr -append -select id -f 'PostgreSQL' PG:'dbname=webmapptest user=webmapp host=46.101.124.52' '$geojson' -nln track_tmp";
            system($cmd);
        }
    }

        public function addRelated($distance=5000,$limit=100) {
        if($limit>0) {
            $limit = " LIMIT $limit";
        } else {
            $limit='';
        }
        $id = $this->properties['id'];
        $q = "SELECT poi_tmp.id as id, track_tmp.id as tid, ST_Distance(poi_tmp.wkb_geometry, ST_Transform(track_tmp.wkb_geometry,3857)) as distance
              FROM  poi_tmp, track_tmp
              WHERE track_tmp.id=$id AND ST_Distance(poi_tmp.wkb_geometry, ST_Transform(track_tmp.wkb_geometry,3857)) < $distance
              ORDER BY distance
              $limit ;";
        $this->addRelatedPoi($q);
    }

}