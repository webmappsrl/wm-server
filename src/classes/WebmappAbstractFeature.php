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

    // Array per la gestione delle traduzioni
    protected $languages = array();

    // WP URL
    private $wp_url = '';

    // Il costruttore prende in ingresso un array che rispecchia le API di WP
    // della singola feature oppure direttamente l'URL di un singolo POI
	public function __construct ($array_or_url) {
		if (!is_array($array_or_url)) {
			// E' Un URL quindi leggo API WP e converto in array
			$json_array = json_decode(file_get_contents($array_or_url),true);
            $this->wp_url = $array_or_url;

            if (isset($json_array['wpml_translations']) && 
                is_array($json_array['wpml_translations']) &&
                count($json_array['wpml_translations'])>0) {
                foreach($json_array['wpml_translations'] as $t ) {
                    $lang = $t['locale'];
                    $lang = preg_replace('|_.*$|', '', $lang);
                    $id = $t['id'];
                    $lang_url = preg_replace('|\d+$|', $id, $array_or_url);
                    $json_t = json_decode(file_get_contents($lang_url),true);
                    // TODO: estendere oltre a name e description (variabile globale?)
                    $this->translate($lang,'name',$json_t['title']['rendered']);
                    $this->translate($lang,'description',$json_t['content']['rendered']);
                }
            }
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

    // Simple Getters
    public function getWPUrl() {
        return $this->wp_url;
    }

    // Setters
    public function setImage($url) {
        $this->properties['image']=$url;
    }

    private function translate($lang,$key,$val) {
        $this->languages[$lang][$key]=$val;
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
        $this->setPropertyBool('noDetails',$json_array);
        $this->setPropertyBool('noInteraction',$json_array);
        $this->setProperty('related_pois',$json_array);

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

    protected function setPropertyBool($key,$json_array,$key_map='') {
        if ($key_map=='') $key_map = $key;
        $val = false;
        if (isset($json_array[$key]) && !is_null($json_array[$key])) {
            $json_val = $json_array[$key];
            if($json_val==true or $json_val=='true' or $json_val=='1') $val=true;
        }
        $this->properties[$key_map] = $val;
    }

    public function addProperty($key,$val) {
        $this->properties[$key]=$val;
    }

    public function map($a) {
        foreach ($a as $key => $val) {
            $this->addProperty($key,$val);
        }
    }

    public function setDescription ($val) {
        $this->addProperty('description',$val);
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
    
    // Lat e Lng Max  e MIN (usate per il BB)
    abstract public function getLatMax();
    abstract public function getLatMin();
    abstract public function getLngMax();
    abstract public function getLngMin();

    // ARRAY pronto per essere convertito in json
    // ['bounds']['southWest']array(lat,lng)
    // ['bounds']['northEast']array(lat,lng)
    // ['center']array(lat,lng)
    // NON FARLA ASTRATTA MA IN FUNZIONE DELLE PRECEDENTI
    public function getBB() {
        $bb = array();
        $bb['bounds']['southWest']=array($this->getLatMin(),$this->getLngMin());
        $bb['bounds']['northEast']=array($this->getLatMax(),$this->getLngMax());
        $bb['center']['lat']=($this->getLatMin()+$this->getLatMax())/2;
        $bb['center']['lng']=($this->getLngMin()+$this->getLngMax())/2;
        return $bb;
    }

    public function getArrayJson($lang='') {

        $meta = $this->properties;
        
        // manage translations
        if ($lang!='') {
            if(array_key_exists($lang, $this->languages)) {
                $t = $this->languages[$lang];
                foreach($t as $key=>$value) {
                    if (isset($meta[$key])) {
                        $meta[$key]=$value;
                    }
                }
            }
        }

        $json = array();
        $json['type']='Feature';
        $json['properties'] = $meta;
        $json['geometry']=$this->geometry;
        return $json;
    }
    public function getJson($lang='') {
    	return json_encode($this->getArrayJson($lang));
    }


}


/** Esempio di classe concreta che estende la classe astratta
class WebmappPoiFeature extends WebmappAbstractFeature {
	protected function mappingSpecific($json_array) {}
	protected function mappingGeometry($json_array) {}
}
**/
