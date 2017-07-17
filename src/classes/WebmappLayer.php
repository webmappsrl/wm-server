<?php // WebmappLayer.php

class WebmappLayer {
	private $features = array();
	private $name;
	// TODO: togliere $path dalle properties
	private $path;
	private $label;
	private $icon = 'wm-icon-generic';
	private $color = '#FF3812';

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
    }

    public function getGeoJson() {
       $json["type"] ='FeatureCollection';
       if (count($this->features) > 0 ) {
       	$features = array();
       	foreach ($this->features as $feature) {
       		$features[] = $feature->getArrayJson();
       	}
       	$json["features"]=$features;
       }
       return json_encode($json);
    }

	public function write($path='') {
		if($path=='') $path=$this->path;
		$fname = $path.'/'.$this->name.'.geojson';
		file_put_contents($fname, $this->getGeoJson());
	}


}