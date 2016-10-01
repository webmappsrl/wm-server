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
	private $server;
	private $conf;
	private $geojson;
	private $poi;
	private $poi_single;

	private $all_dir = array();

	public function __construct ( $root ) {
		$this->root = $root;
		if(substr($this->root, -1)!='/') $this->root .= '/';
		$this->server = $this->root.'server/';
		$this->conf= $this->root.'server/project.conf';
		$this->geojson = $this->root.'geojson/';
		$this->poi = $this->geojson.'poi/';
		$this->poi_single = $this->poi.'id/';

		$this->all_dir = 
		  array(
		  	$this->root,
		  	$this->server,
		  	$this->geojson,
		  	$this->poi,
		  	$this->poi_single
		  	);
	}

	public function getRoot() { return $this->root; }
	public function getServer() { return $this->server; }
	public function getConf() { return $this->conf; }
	public function getGeojson() { return $this->geojson; }
	public function getPoi() { return $this->poi; }
	public function getPoiSingle() { return $this->poi_single; }

    // Restituisce un array con la lista dei file di configurazione
    // (path completo) di tutti i singoli task del progetto

	public function getTaskConfFiles() {
	    $conf_files = array();
    	$d = dir($this->server);
    	while (false !== ($entry = $d->read())) {
    		if(preg_match('/conf/', $entry) && $entry != 'project.conf') $conf_files[] = $this->server.$entry;
    	}
    	return $conf_files;

	}

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
		if(!file_exists($this->geojson)) return false;
		if(!file_exists($this->poi)) return false;
		if(!file_exists($this->poi_single)) return false;
		return true;
	}


}