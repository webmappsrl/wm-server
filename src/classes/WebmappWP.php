<?php

class WebmappWP {
	private $code;
	private $base_url;
	private $api_url;
	private $api_pois;
	private $api_tracks;
	private $api_routes;
	private $api_areas;
	private $api_maps;
	private $api_categories;

	public function __construct($code) {
		$this->code = $code;
		$this->base_url = "http://$code.be.webmapp.it";
		$this->api_url = "{$this->base_url}/wp-json/wp/v2";
		$this->api_pois = "{$this->api_url}/poi";
		$this->api_tracks = "{$this->api_url}/track";
		$this->api_routes = "{$this->api_url}/route";
		$this->api_areas = "{$this->api_url}/area";
		$this->api_maps = "{$this->api_url}/map";
		$this->api_categories = "{$this->api_url}/webmapp_category";

	}

	public function getCode() {
		return $this->code;
	}

	// BASIC GETTERS

	public function getBaseUrl() {
		return $this->base_url;      
	}

	public function getApiUrl() {
		return $this->api_url;
	}

	// MULTIPLE API GETTERS

	public function getApiPois() {
		return $this->api_pois;
	}

	public function getApiTracks() {
		return $this->api_tracks;
	}

	public function getApiRoutes() {
		return $this->api_routes;
	}

	public function getApiAreas() {
		return $this->api_areas;
	}

	public function getApiMaps() {
		return $this->api_maps;
	}

	public function getApiCategories() {
		return $this->api_categories;
	}

	// SINGLE API GETTERS
	public function getApiPoi($id) {
		return $this->api_pois.'/'.$id;
	}

	public function getApiTrack($id) {
		return $this->api_tracks.'/'.$id;
	}

	public function getApiRoute($id) {
		return $this->api_routes.'/'.$id;
	}

	public function getApiArea($id) {
		return $this->api_areas.'/'.$id;
	}

	public function getApiMap($id) {
		return $this->api_maps.'/'.$id;
	}

	public function getApiCategory($id) {
		return $this->api_categories.'/'.$id;
	}

	// CONTROLLI DI RISPOSTA DALLA PIATTAFORMA

	private function checkUrl($url) {
		$a = get_headers($url);
		$match = (preg_match('/200/', $a[0]));
		if($match==0) return false;
		return true;
	}

	public function check() {
		return $this->checkUrl($this->base_url);
	}

	public function checkPoi($id) {
		return $this->checkUrl($this->getApiPoi($id));
	}

	public function checkTrack($id) {
		return $this->checkUrl($this->getApiTrack($id));
	}

	public function checkRoute($id) {
		return $this->checkUrl($this->getApiRoute($id));
	}

	public function checkArea($id) {
		return $this->checkUrl($this->getApiArea($id));
	}

	public function checkMap($id) {
		return $this->checkUrl($this->getApiMap($id));
	}

	public function checkCategory($id) {
		return $this->checkUrl($this->getApiCategory($id));
	}

	// Categorie e layers

	public function getCategoriesArray() {
		$json = json_decode(file_get_contents($this->getApiCategories()),true);
		if (!is_array($json) || count($json) == 0) {
			return array();
		}
		$cats = array();
		foreach ($json as $cat) {
           $cats[] = $cat['id'];
 		}
 		return $cats;
	}

}