<?php // WebmappLayer.php

class WebmappLayer {
	private $features = array();
	private $name;
	private $path;

	public function __construct($name,$path) {
		// TODO: check parameter
		$this->name = $name;
		$this->path = $path;
	}

	public function addFeature($feature) {
		// TODO: check feature typeof Webmapp*Feature
        array_push($this->features, $feature);
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

	public function write() {
		$fname = $this->path.'/'.$this->name.'.geojson';
		file_put_contents($fname, $this->getGeoJson());
	}


}