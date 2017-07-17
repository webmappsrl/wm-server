<?php
abstract class WebmappAbstractFeature {

	// Array geometria della feature
	protected $geometry;

	// Array associativo con la sezione properties
	protected $properties;

    // Array del json restituito dalle API
    protected $json_array;

    // Array degli ID delle categorie webmapp
    protected $webmapp_category_ids = array();

    // Il costruttore prende in ingresso un array che rispecchia le API di WP
    // della singola feature oppure direttamente l'URL di un singolo POI
	public function __construct ($array_or_url) {
		if (!is_array($array_or_url)) {
			// E' Un URL quindi leggo API WP e converto in array
			$json_array = json_decode(file_get_contents($array_or_url),true);
		}
        else {
            $json_array = $array_or_url;
        }

        $this->json_array = $json_array;

        // TODO: non passare $json_array ma usare la proprietà
		$this->mappingStandard($json_array);
		$this->mappingSpecific($json_array);
		$this->mappingGeometry($json_array);
	}

    // Setters
    public function setImage($url) {
        $this->properties['image']=$url;
    }

    // Restituisce l'array con l'id WP delle categorie
    public function getWebmappCategoryIds() {
        return $this->webmapp_category_ids;
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

        // Gestione delle categorie WEBMAPP
        // http://dev.be.webmapp.it/wp-json/wp/v2/poi/610
        // http://dev.be.webmapp.it/wp-json/wp/v2/track/580
        if (isset($this->json_array['webmapp_category']) &&
            is_array($this->json_array['webmapp_category']) &&
            count($this->json_array['webmapp_category'])>0) {
            $this->webmapp_category_ids = $json_array['webmapp_category'];
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
                //$images[]=array('src'=>$item['url']);
                $images[]=array('src'=>$item['sizes']['medium_large']);
            }
            $this->properties['imageGallery']=$images;
            $this->setImage($images[0]['src']);
        }    	
    }

    // Mapping delle proprietà specifiche di una feature esclusa la geometria
    abstract protected function mappingSpecific($json_array);

    // Mapping della geometry 
    abstract protected function mappingGeometry($json_array);
    
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
