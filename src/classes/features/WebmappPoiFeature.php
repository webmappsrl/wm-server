<?php 

/*
// Esempio di MAPPING per la creazione di un POI
$j=array();

// GENERAL
$j['id']=$ja[''];
$j['title']['rendered']=$ja[''];
$j['content']['rendered']=$ja[''];
$j['color']=$ja[''];
$j['icon']=$ja[''];
$j['noDetails']=$ja[''];
$j['noInteractions']=$ja[''];

// TODO RELATED POI

// IMAGES
$j['n7webmap_media_gallery']=$j[''];

// SPECIFIC POI
$j['addr:street']=$ja[''];
$j['addr:housenumber']=$ja[''];
$j['addr:postcode']=$ja[''];
$j['addr:city']=$ja[''];
$j['contact:phone']=$ja[''];
$j['contact:email']=$ja[''];
$j['opening_hours']=$ja[''];
$j['capacity']=$ja[''];
$j['address']=$ja[''];

// GEOMETRY
$j['n7webmap_coord']['lng']=$ja[''];
$j['n7webmap_coord']['lat']=$ja[''];

// Creazione del POI
$poi = new WebmappPoiFeature($j);
*/

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

        if (!array_key_exists('n7webmap_coord', $json_array) ) {
            print_r($json_array);
            throw new Exception("INVALID POI", 1);
        }

        if (!is_array($json_array['n7webmap_coord']) ) {
            print_r($json_array);
            throw new Exception("INVALID POI", 1);
        }

        if (!array_key_exists('lng', $json_array['n7webmap_coord']) ) {
            print_r($json_array);
            throw new Exception("INVALID POI", 1);
        }

        $lng = $json_array['n7webmap_coord']['lng'];
        $lat = $json_array['n7webmap_coord']['lat'];
        $this->geometry['type'] = 'Point' ;
        $this->geometry['coordinates']=array((float) $lng, (float) $lat);
	}

    public function setGeometry($lng,$lat) {
        $this->geometry['type'] = 'Point' ;
        $this->geometry['coordinates']=array((float) $lng, (float) $lat);        
    }

    public function getLatMax(){ return $this->geometry['coordinates'][1];}
    public function getLatMin(){ return $this->geometry['coordinates'][1];}
    public function getLngMax(){ return $this->geometry['coordinates'][0];}
    public function getLngMin(){ return $this->geometry['coordinates'][0];}

}