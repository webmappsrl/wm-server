<?php

// Classe per la gestione dei Bounding Box

// TODO: inserire la gestione degli errori (passare alle eccezioni?)

class WebmappBounds {
	// Imposta tutte le proprietà di base
	// $json è un array associativo che rappresenta il seguente json:
	// PRECISIONE: 11 cifre decimali dopo la virgola
	/**
	{
      "southWest": [43.56984,10.21466],
      "northEast": [43.87756,10.6855]
    } 
	
	array("southWest"=>array(lat,lon),
	      "northEast":array(lat,lon));

**/

	private $southWest = array();
	private $northEast = array();
	private $southWestLon;
	private $southWestLat;
	private $northEastLat;
	private $northEastLon;

	public function __construct($json) {
		$this->southWest = $json['southWest'];
		$this->southWestLat = $json['southWest'][0];
		$this->southWestLon = $json['southWest'][1];
		$this->northEast = $json['northEast'];
		$this->northEastLat = $json['northEast'][0];
		$this->northEastLon = $json['northEast'][1];

	}

	public function getSouthWest() { return $this->southWest; }
	public function getNorthEast() { return $this->northEast; }
	public function getSouthWestLat() { return $this->southWestLat; }
	public function getSouthWestLon() { return $this->southWestLon; }
	public function getNorthEastLat() { return $this->northEastLat; }
	public function getNorthEastLon() { return $this->northEastLon; }

	public function getForOverpass() {
     	// 43.704367081989325,10.338478088378906,43.84839376489157,10.637855529785156 
		return rawurlencode(implode(',',array($this->southWestLat,$this->southWestLon,$this->northEastLat,$this->northEastLon)));
	}

}