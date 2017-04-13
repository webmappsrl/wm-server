<?php

// Definisce la struttura di tutte le cartelle di un progetto webmapp
// a partire dalla root
// root/server/project.conf
// root/geojson
// 
// La funzione check chiama la lettura del file di configurazione (readconf)
//
// La readconf si aspetta un json file con almeno un task definito nell'array "tasks"

class WebmappProjectStructure {
	// Root directory del progetto
	private $root;
	// Percorso dei geojson
	private $path_geojson;
	// CLIENT
	private $path_client;
	private $path_client_index;
	private $path_client_conf;

	private $path_base;
	private $url_base;

	private $url_geojson;
	// CLIENT
	private $url_client;
	private $url_client_index;
	private $url_client_conf;
	// Percorso del file di configurazione del server
	private $conf;
	// Array dei tasks del progetto
	private $tasks = array();

	// Costruttore
	public function __construct($root,$path_base='/root/api.webmapp.it',$url_base='http://api.webmapp.it') {

		$this->root = rtrim($root, '/');
		$this->path_base=$path_base;
		$this->url_base=$url_base;
		$this->path_geojson = $this->root.'/geojson';
		$this->path_client = $this->root.'/client';
		$this->path_client_index = $this->root.'/client/index.html';
		$this->path_client_conf = $this->root.'/client/conf.js';
		$this->conf = $this->root.'/server/project.conf';

		$url = preg_replace("|$path_base|", '', $root);

		$this->url_geojson = $this->url_base.$url.'/geojson';
		$this->url_client = $this->url_base.$url.'/client';
		$this->url_client_index = $this->url_base.$url.'/client/index.html';
		$this->url_client_conf = $this->url_base.$url.'/client/conf.js';

		// TODO: controllare esistenza del root path. Se non esiste crearlo. Se non lo fa creare eccezione

		// TODO: controllare esistenza delle directory obbligatorie per il funzionamento del servr
		// Stesso controllo e azione del precedente

	}

	// Getters
	public function getRoot() { return $this->root;}
	public function getPathBase() { return $this->path_base;}
	public function getUrlBase() { return $this->url_base;}
	public function getPathGeojson() { return $this->path_geojson;}
	public function getPathClient() { return $this->path_client;}
	public function getPathClientIndex() { return $this->path_client_index;}
	public function getPathClientConf() { return $this->path_client_conf;}
	public function getURLGeojson() { return $this->url_geojson;}
	public function getURLClient() { return $this->url_client;}
	public function getURLClientIndex() { return $this->url_client_index;}
	public function getURLClientConf() { return $this->url_client_conf;}
	public function getConf() { return $this->conf;}
	public function getTasks() { return $this->tasks; }
	// fine getters

	// Verifica la struttura di un progetto esistente
	public function check() {
		if (!file_exists($this->root)) 
			throw new Exception("La directory {$this->root} non esiste", 1);
		if (!file_exists($this->path_geojson)) 
			throw new Exception("La directory {$this->path_geojson} non esiste", 1);
		if (!file_exists($this->conf)) 
			throw new Exception("Il file di configurazione {$this->conf} non esiste", 1);

		// legge il file di configurazione e imposta i tasks da eseguire

		return $this->readConf();
	}

	// Legge il file di configurazione e imposta i tasks da eseguire
	private function readConf () {
		// Leggi il file e converti in json
		$json=json_decode(file_get_contents($this->conf),TRUE);
		if(!$json)
			throw new Exception("Impossibile convertire il file di configurazione in JSON.", 1);

		// TODO: presenza dell'array dei TASKS
		if(!array_key_exists('tasks', $json))
			throw new Exception("Il file di configurazione non ha tasks.", 1);
			
		// TODO: Interpretazione dei TASKS e verifica dei singoli
		foreach ($json['tasks'] as $name => $options ) {
			$t = WebmappTaskFactory::Task($name,$options,$this);
			if(!$t->check()) return FALSE;
			array_push($this->tasks, $t);
		}

		return TRUE;
	}
}