<?php 

class WebmappPoiFeature extends WebmappAbstractFeature {

	// Mapping dei meta specifici dei punti
	// http://dev.be.webmapp.local/wp-json/wp/v2/poi/38
	protected function mappingSpecific($json_array) {
		$this->setProperty('addr:street',$json_array);
		$this->setProperty('addr:housenumber',$json_array);
		$this->setProperty('addr:postcode',$json_array);
		$this->setProperty('addr:city',$json_array);
	    $this->setProperty('contact:phone',$json_array);
	    $this->setProperty('contact:email',$json_array);
	    $this->setProperty('opening_hours',$json_array);
	    $this->setProperty('capacity',$json_array);
        // Gestione dell'address
        if (isset($json_array['address'])) {
            $this->setProperty('address',$json_array);
        }
        else if ((isset($json_array['addr:street']) && (!empty($json_array['addr:street'])))
               &&(isset($json_array['addr:city']) && (!empty($json_array['addr:city']))) ) {
            $num = '';
            if (isset($json_array['addr:housenumber'])) {
                $num = $json_array['addr:housenumber'];
            }
          $address = $json_array['addr:street'].', '.$num.' '.$json_array['addr:city'];
          $this->properties['address'] = $address;
        }
	}

    // Impostazione della geometry a partire da formato API WP
    /**
     {
        "coordinates": [
            10.441684,
            43.762954999999998
        ],
        "type": "Point"
    } 
    **/

	protected function mappingGeometry($json_array) {
        // TODO: controllo esistenza coordinate
        $lng = $json_array['n7webmap_coord']['lng'];
        $lat = $json_array['n7webmap_coord']['lat'];
        $this->geometry['type'] = 'Point' ;
        $this->geometry['coordinates']=array((float) $lng, (float) $lat);
	}

    public function setGeometry($lng,$lat) {
        $this->geometry['type'] = 'Point' ;
        $this->geometry['coordinates']=array((float) $lng, (float) $lat);        
    }

}