<?php // WebmappLayer.php

class WebmappLayer {
	private $features = array();
	private $name;
	// TODO: togliere $path dalle properties
	private $path;
	private $label;
	private $icon = 'wm-icon-generic';
	private $color = '#FF3812';
	private $showByDefault = true ;
	// Array associativo che contiene le traduzioni dei label del layer
	private $languages = array();

	public function __construct($name,$path='') {
		// TODO: check parameter
		$this->name = $name;
		$this->path = $path;
	}

	public function setLabel($label) {
		$this->label=$label;
	}

	public function addFeature($feature) {
		// TODO: check feature typeof Webmapp*Feature
        array_push($this->features, $feature);
	}

	public function getShowByDefault() {
		return $this->showByDefault;
	}

	public function getIcon() {
		return $this->icon;
	}

	public function getColor() {
		return $this->color;
	}

	public function getLabel() {
		return $this->label;
	}

	public function getName() {
		return $this->name;
	}

	public function getFeatures() {
		return $this->features;
	}

	public function getLanguages() {
		return $this->languages;
	}

	public function translateLabel($lang,$label) {
        $this->languages[$lang] = $label;
	}

    public function loadMetaFromUrl($url) {
    	// TODO: leggi API alla WP e poi setta label, icon e color
    	$meta = json_decode(file_get_contents($url),TRUE);
    	if (isset($meta['icon'])) {
    		$this->icon=$meta['icon'];
    	}
    	if (isset($meta['name'])) {
    		$this->label=$meta['name'];
    	}
    	if (isset($meta['color'])) {
    		$this->color=$meta['color'];
    	}
    	if (isset($meta['show_by_default']) && $meta['show_by_default'] == false) {
    		$this->showByDefault=false;
    	}

    	// Gestione delle lingue
    	// http://vn.be.webmapp.it/wp-json/wp/v2/webmapp_category/33
    	// TODO: recuperare le lingue da altro parametro
    	$langs = array('it','en');

    	foreach ($langs as $lang) {
    		if(preg_match('/\?/', $url) ) {
    			$url_lang = $url . '&lang=' . $lang ;
    		}
    		else {
    			$url_lang = $url . '?lang=' . $lang ;
    		}
    		$meta = json_decode(file_get_contents($url_lang),TRUE);
    		if (is_array($meta) && isset($meta['name'])) {
    			$this->languages[$lang]=$meta['name'];
    		}
    	}
    }

    public function getGeoJson($lang='') {
       $json["type"] ='FeatureCollection';
       if (count($this->features) > 0 ) {
       	$features = array();
       	foreach ($this->features as $feature) {
       		$features[] = $feature->getArrayJson($lang);
       	}
       	$json["features"]=$features;
       }
       return json_encode($json);
    }

    // Il Path viene costruito in base alla lingua
	public function write($path='',$lang='') {
		if($path=='') {
	      $path=$this->path;	
		} 
		if ($lang!='') {
			if (!file_exists($path.'/languages')) {
				mkdir ($path.'/languages');
			}
		  $path = $path .'/languages/'.$lang;
		  if (!file_exists($path)) {
		  	mkdir($path);
		  }	
		} 
		$fname = $path.'/'.$this->name.'.geojson';
		file_put_contents($fname, $this->getGeoJson($lang));
	}


}