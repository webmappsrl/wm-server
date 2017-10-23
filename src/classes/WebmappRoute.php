<?php

class WebmappRoute {

	private $id;
	private $title;
	private $json_array = array();
	private $tracks = array();
	private $base_url;

    // Array con le lingue presenti
	private $languages = array();

	public function __construct ($array_or_url) {
		$this -> base_url = preg_replace('|route/.*|', '', $array_or_url);
		$this->json_array = json_decode(file_get_contents($array_or_url),true);
		

		if (isset($this->json_array['wpml_translations']) && 
			is_array($this->json_array['wpml_translations']) &&
			count($this->json_array['wpml_translations'])>0) {
			foreach($this->json_array['wpml_translations'] as $t ) {
				$lang = $t['locale'];
				// Converti en_XX -> en
				$lang=preg_replace('|_.*$|', '', $lang);
				array_push($this->languages, $lang);
			}
		}


		$this->id = $this->json_array['id'];
		$this->title = $this->json_array['title']['rendered'];
		if (isset($this->json_array['n7webmap_route_related_track']) && 
			count($this->json_array['n7webmap_route_related_track']) > 0 ) {
			$this->loadTracks();
	}
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

private function loadTracks() {
	foreach ($this->json_array['n7webmap_route_related_track'] as $track ) {
		$url = $this->base_url .'track/' . $track['ID'];
		array_push($this->tracks, new WebmappTrackFeature($url));
	}
}

}