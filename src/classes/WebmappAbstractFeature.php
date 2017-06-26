<?php
abstract class WebmappAbstractFeature {

	// Array geometria della feature
	protected $geometry;

	// Array associativo con la sezione properties
	protected $properties;

    // Array del json restituito dalle API
    protected $json_array;

    // Il costruttore prende in ingresso un array che rispecchia le API di WP
    // della singola feature oppure direttamente l'URL di un singolo POI
    // Esempio locale singolo POI: http://dev.be.webmapp.local/wp-json/wp/v2/poi/38
    // Esempio locale singola TRACK: http://dev.be.webmapp.local/wp-json/wp/v2/track/35
	public function __construct ($array_or_url) {
		if (!is_array($array_or_url)) {
			// E' Un URL quindi leggo API WP e converto in array
			$json_array = json_decode(file_get_contents($array_or_url),true);
		}
		else {
			// TODO: implementare la lettura dell'array diretta
			throw new Exception("Lettura diretta array ancora anon implementato", 1);
			$json_array = $array_or_url;
		}

        $this->json_array = $json_array;

        // TODO: non passare $json_array ma usare la proprietà
		$this->mappingStandard($json_array);
		$this->mappingSpecific($json_array);
		$this->mappingGeometry($json_array);
	}
	
	// Costruisce la parte di array $properties comune a tutte le features (name, description, id)
    private function mappingStandard($json_array) {
    	$this->setProperty('id',$json_array);
    	$this->setProperty('rendered',$json_array['title'],'name');
    	$this->setProperty('rendered',$json_array['content'],'description');
    	$this->setProperty('color',$json_array);
    	$this->setProperty('icon',$json_array);
    	$this->setProperty('noDetails',$json_array);

    	// Gestione delle immagini
    	// TODO: migliorare la gestione unificando il nome per POI e track
    	if (isset($json_array['n7webmap_media_gallery'])) {
    		$this->mappingImage($json_array['n7webmap_media_gallery']);
    	}
    	if (isset($json_array['n7webmap_track_media_gallery'])) {
    		$this->mappingImage($json_array['n7webmap_track_media_gallery']);
    	}
    }

    protected function setProperty($key,$json_array,$key_map='') {
    	if (isset($json_array[$key]) && !is_null($json_array[$key])) {
    		if($key_map=='') $key_map = $key;
    		$this->properties[$key_map] = $json_array[$key] ;
    	}
    	// TODO: gestire un ELSE con eccezione per eventuali parametri obbligatori
    }

    // Mapping della gallery e della imagine di base
    private function mappingImage($gallery) {
        if (is_array($gallery) && count($gallery)>0) {
            $images = array();
            foreach ($gallery as $item ) {
                // TODO: usare una grandezza standard
                $images[]=array('src'=>$item['url']);
            }
            $this->properties['imageGallery']=$images;
            $this->properties['image']=$images[0]['src'];
        }    	
    }

    // Mapping delle proprietà specifiche di una feature esclusa la geometria
    abstract protected function mappingSpecific($json_array);

    // Mapping della geometry 
    abstract protected function mappingGeometry($json_array);
    
    // Restituisce la stringa del singolo elemento FEATURE
    /** Esempio di stringa del singolo elemento del geoJson
    {
    "type": "Feature",
    "properties": {
        "description": "",
        "id": 566,
        "name": "117-01",
        "image": "http://dev.be.webmapp.it/wp-content/uploads/2017/05/dolomites-550349_960_720.jpg",
        "imageGallery": [
            {
                "src": "http://dev.be.webmapp.it/wp-content/uploads/2017/05/dolomites-550349_960_720.jpg"
            },
            {
                "src": "http://dev.be.webmapp.it/wp-content/uploads/2017/05/mountain-1077939_960_720.jpg"
            },
            {
                "src": "http://dev.be.webmapp.it/wp-content/uploads/2017/03/Pisa-lungarno03.jpg"
            }
        ]
    },
    "geometry": {
        "coordinates": [
            10.441684,
            43.762954999999998
        ],
        "type": "Point"
    }
}
    **/
    public function getArrayJson() {

        $json = array();
        $json['type']='Feature';
        $json['properties'] = $this->properties;
        $json['geometry']=$this->geometry;
        return $json;
    }
    public function getJson() {
    	return json_encode($this->getArrayJson());
    }

}


/** Esempio di classe concreta che estende la classe astratta
class WebmappPoiFeature extends WebmappAbstractFeature {
	protected function mappingSpecific($json_array) {}
	protected function mappingGeometry($json_array) {}
}
**/
