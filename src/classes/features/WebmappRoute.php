<?php

class WebmappRoute {

	private $id;
	private $title;
	private $json_array = array();
	private $tracks = array();
	private $base_url;

	private $properties = array();
	private $features = array();

    // Array con le lingue presenti
	private $languages = array();

	public function __construct ($array_or_url,$base_url='') {
		if(is_array($array_or_url)) {
			$this->json_array=$array_or_url;
			$this->base_url=$base_url;		  
		}
		else {
			$this -> base_url = preg_replace('|route/.*|', '', $array_or_url);
			$this->json_array = WebmappUtils::getJsonFromApi($array_or_url);
		}

		if (isset($this->json_array['wpml_translations']) && 
			is_array($this->json_array['wpml_translations']) &&
			count($this->json_array['wpml_translations'])>0) {
			foreach($this->json_array['wpml_translations'] as $t ) {
				$lang = $t['locale'];
				// Converti en_XX -> en
				$lang=preg_replace('|_.*$|', '', $lang);
				array_push($this->languages, $lang);
			}

			// Aggiungi la lingua standard
			$actual = $this->json_array['wpml_current_locale'];
			$lang=preg_replace('|_.*$|', '', $actual);
			array_push($this->languages, $lang);
		}


		$this->id = $this->json_array['id'];
		$this->title = $this->json_array['title']['rendered'];
		if (isset($this->json_array['n7webmap_route_related_track']) && 
			count($this->json_array['n7webmap_route_related_track']) > 0 ) {
			$this->loadTracks();
	}
	        // Taxonomies
	$this->addTaxonomy('webmapp_category');
	$this->addTaxonomy('activity');
	$this->addTaxonomy('theme');
	$this->addTaxonomy('where');
	$this->addTaxonomy('when');
	$this->addTaxonomy('who');

	$this->buildPropertiesAndFeatures();

}

public function getId() {
	return $this->id ;
}

public function getTitle() {
	return $this->title ;
}

public function getTracks() {
	return $this->tracks;
}

public function getLanguages() {
	return $this->languages;
}

public function getProperties() {
	return $this->properties;
}

public function getTaxonomies() {
	if(isset($this->properties['taxonomy'])) {
		return $this->properties['taxonomy'];
	}
	return array();
}

private function loadTracks() {
	if( isset($this->json_array['n7webmap_route_related_track']) &&
		is_array($this->json_array['n7webmap_route_related_track']) &&
		count($this->json_array['n7webmap_route_related_track'])>0){
		foreach ($this->json_array['n7webmap_route_related_track'] as $track ) {
			$url = $this->base_url .'track/' . $track['ID'];
			array_push($this->tracks, new WebmappTrackFeature($url));
		}		
	}
}
    // TODO Patch per APIWP non coerente su ROUTE da sistemare dopo
    // aver sistemato APIWP per route con multimappa

private function addTaxonomy($name) {
	if (isset($this->json_array[$name]) &&
		is_array($this->json_array[$name]) &&
		count($this->json_array[$name])>0) {
		if($name=='activity'){
			foreach ($this->json_array[$name] as $term ) {
				$this->properties['taxonomy'][$name][]=$term['term_id'];
			}
		}
		else {
	        	    //  Ripristinare questo codice (vedi TODO sopra) anche per activity
			$this->properties['taxonomy'][$name]=$this->json_array[$name];
		}
	}       
}

private function buildPropertiesAndFeatures() {
	$this->properties['id']=$this->id;
	$this->properties['name']=$this->title;
	if(count($this->tracks) >0 ) {
		$related=array();
		foreach($this->tracks as $track) {
			$this->features[]=json_decode($track->getJson(),TRUE);
			$related[]=$track->getId();
		}
		$this->properties['related']['track']['related']=$related;
	}

}

public function getJson() {
	$json = array();
	$json['type']='FeatureCollection';
	$json['properties']=$this->properties;
	$json['features']=$this->features;
	return json_encode($json);
}

public function writeToPostGis($instance_id='') {
	// Gestione della ISTANCE ID
	if(empty($instance_id)) {
		$instance_id = WebmappProjectStructure::getInstanceId();
	}
	$route_id=$this->getId();
	if(count($this->tracks)>0) {
		$pg = WebmappPostGis::Instance();
		$pg->insertRoute($instance_id,$this->getId(),$this->properties['related']['track']['related']);
	}
}

public function addBBox($instance_id='') {
	// Gestione della ISTANCE ID
	if(empty($instance_id)) {
		$instance_id = WebmappProjectStructure::getInstanceId();
	}
	$pg = WebmappPostGis::Instance();
	$bb = $pg->getRouteBBox($instance_id,$this->getId());
	if(!empty($bb)) {
		$this->properties['bbox']=$bb;
		$bb = $pg->getRouteBBoxMetric($instance_id,$this->getId());
		$this->properties['bbox_metric']=$bb;
	}
}

public function write($path) {
	file_put_contents($path.'/'.$this->id.'.geojson', $this->getJson());
}

public function generateAllImages($instance_id='',$path='') {

            // Gestione della ISTANCE ID
	if(empty($instance_id)) {
		$instance_id = WebmappProjectStructure::getInstanceId();
	}

	$sizes = array(
		array(491,624),
		array(400,300),
		array(200,200),
		array(1000,1000)
		);
	foreach ($sizes as $v) {
		$this->generateImage($v[0],$v[1],$instance_id,$path);
	}

}

public function generateImage($width,$height,$instance_id='',$path='') {
        	// Ignore route without tracks
	if(count($this->tracks)==0) {
		return;
	}
            // TODO: check parameter

            // Gestione della ISTANCE ID
	if(empty($instance_id)) {
		$instance_id = WebmappProjectStructure::getInstanceId();
	}           
	if(!isset($this->properties['bbox'])) {
		$this->addBBox($instance_id);
	}

	$geojson_url = 'https://a.webmapp.it/'.preg_replace('|http://|', '', $instance_id).'/geojson/'.$this->getId().'.geojson';
	$img_path = $path.'/'.$this->getId().'_map_'.$width.'x'.$height.'.png';

	WebmappUtils::generateImage($geojson_url,$this->properties['bbox_metric'],$width,$height,$img_path);
}

public function generateRBHTML($path,$instance_id='') {

	if(empty($instance_id)) {
		$instance_id = WebmappProjectStructure::getInstanceId();
	}           

	$file = $path.'/'.$this->getId().'_rb.html';
	$html = '<!DOCTYPE html><html><body>';
	$html .= '<h1>'.$this->json_array['title']['rendered'].'</h1>';
	$html .= $this->json_array['content']['rendered'];
	$html .= '</body></html>';

	file_put_contents($file,$html);
}



}