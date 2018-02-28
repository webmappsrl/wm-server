<?php // WebmappLayer.php

class WebmappLayer {
	private $features = array();
	private $name;
	// TODO: togliere $path dalle properties
	private $path;
	private $label;
	private $icon = 'wm-icon-generic';
	private $color = '#FF3812';
	private $alert = false;
	private $showByDefault = true ;
	// Array associativo che contiene le traduzioni dei label del layer
	private $languages = array();

	public function __construct($name,$path='') {
		// TODO: check parameter
		$this->name = $name;
		$this->path = $path;
	}

	public function setLabel($v) {
		$this->label=$v;
	}

	public function setColor($v) {
		$this->color=$v;
	}

	public function setIcon($v) {
		$this->icon=$v;
	}

	public function setAlert($v) {
		$this->alert=$v;
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

	public function getAlert() {
		return $this->alert;
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
    	if (isset($meta['alert']) && $meta['alert'] == true) {
    		$this->alert=true;
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

    // Restituisce un array con il BB
	public function getBB() {
		$bb = array();
		if(count($this->features)>0){
			$first = true;
			foreach ($this->features as $f) {
				if ($first) {
					$latMin = $f->getLatMin();
					$latMax = $f->getLatMax();
					$lngMin = $f->getLngMin();
					$lngMax = $f->getLngMax();
					$first=false;
				}
				else {
					if($f->getLatMin()<$latMin) $latMin=$f->getLatMin();
					if($f->getLatMax()>$latMax) $latMax=$f->getLatMax();
					if($f->getLngMin()<$lngMin) $lngMin=$f->getLngMin();
					if($f->getLngMax()>$lngMax) $lngMax=$f->getLngMax();
				}
			}
			$bb['bounds']['southWest']=array($latMin,$lngMin);
			$bb['bounds']['northEast']=array($latMax,$lngMax);
			$bb['center']['lat']=($latMin+$latMax)/2;
			$bb['center']['lng']=($lngMin+$lngMax)/2;
		}
		return $bb;
	}


}