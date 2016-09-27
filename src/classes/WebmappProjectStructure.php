<?php

// Definisce la struttura di tutte le cartelle di un progetto webmapp
// a partire dalla root

// In particolare:
// root/server -> contiene i file di configurazione per i task che vengono lanciati per il progetto
// root/server/log -> contiene i logfile di un progetto
// root/geojson -> contiene tutti file dai layer di dati organizzati in tre subdir:
// root/geojson/poi -> Punti di interesse (file lista, uno per ogni layer)
// root/geojson/poi/id -> Dettaglio del singolo punto di interesse
// root/geojson/routes -> Itinerari (file lista, uno per ogni layer)
// root/geojson/routes/id -> Dettaglio del singolo itinerario
// root/geojson/events -> Eventi
// root/geojson/events/id -> Singolo evento


class WebmappProjectStructure {

	private $root;
	private $geojson;
	private $poi;
	private $poi_single;

	private $all_dir = array();

	public function __construct ( $root ) {
		$this->root = $root;
		if(substr($this->root, -1)!='/') $this->root .= '/';
		$this->geojson = $this->root.'geojson/';
		$this->poi = $this->geojson.'poi/';
		$this->poi_single = $this->poi.'id/';

		$this->all_dir = 
		  array(
		  	$this->root,
		  	$this->geojson,
		  	$this->poi,
		  	$this->poi_single
		  	);
	}

	public function getRoot() { return $this->root; }
	public function getGeojson() { return $this->geojson; }
	public function getPoi() { return $this->poi; }
	public function getPoiSingle() { return $this->poi_single; }

	public function create() {
		// Crea le directory della struttura
		foreach ($this->all_dir as $dir) {
			if(!file_exists($dir)) {
				// TODO: test exception
				if(!mkdir($dir)) {
					throw new Exception("Error cant't create directory $dir", 1);
				}
			}
		}
	}

	public function checkPoi() {
		// Controlla la struttura per i POI (geojson, poi, poi_single)
		if(!file_exists($this->geojson))
			throw new Exception("Error: directory geojson does not exist - ".$this->geojson, 1);
		if(!file_exists($this->poi))
			throw new Exception("Error: directory poi does not exist - ".$this->poi, 1);
		if(!file_exists($this->poi_single))
			throw new Exception("Error: directory poi_single does not exist - ".$this->poi_single, 1);
		return true;
	}


}