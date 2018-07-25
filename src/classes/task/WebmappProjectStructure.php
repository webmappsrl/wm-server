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
	private $url_base;

	// CLIENT
	private $path_client;
	private $path_client_index;
	private $path_client_conf;
	// CLIENT
	private $url_client;
	private $url_client_index;
	private $url_client_conf;

	// Percorso dei geojson
	private $path_geojson;
	private $url_geojson;

	// Percorso del file di configurazione del server
	private $conf;
	// Array dei tasks del progetto
	private $tasks = array();

	// Costruttore
	public function __construct($root,$url_base='') {

		$this->root = rtrim($root, '/');
		$this->path_geojson = $this->root.'/geojson';
		$this->path_client = $this->root.'/client';
		$this->path_client_index = $this->root.'/client/index.html';
		$this->path_client_conf = $this->root.'/client/config.js';
		$this->conf = $this->root.'/server/project.conf';

		$this->url_base=$url_base;
		if($this->url_base=='') {
			$this->url_base = 'http://'.basename($this->root);
		}
		// La directory client deve avere un link simbolico del tipo geojson -> ../geojson
		
		$this->url_geojson = $this->url_base.'/geojson';
		$this->url_client = $this->url_base;
		$this->url_client_index = $this->url_base.'/index.html';
		$this->url_client_conf = $this->url_base.'/config.js';

		// TODO: controllare esistenza del root path. Se non esiste crearlo. Se non lo fa creare eccezione

		// TODO: controllare esistenza delle directory obbligatorie per il funzionamento del servr
		// Stesso controllo e azione del precedente

	}

	public function activateHTTPS() {
		$this->url_base = preg_replace('/http/','https',$this->url_base);
		$this->url_geojson = preg_replace('/http/','https',$this->url_geojson);
		$this->url_client = preg_replace('/http/','https',$this->url_client);
		$this->url_client_index = preg_replace('/http/','https',$this->url_client_index);
		$this->url_client_conf = preg_replace('/http/','https',$this->url_client_conf);
	}

	// Getters
	public function getRoot() { return $this->root;}
	public function getUrlBase() { return $this->url_base;}
	public function getPathGeojson($lang='') { 
		if ($lang=='') {
			return $this->path_geojson;			
		}
		else {
			return $this->path_geojson.'/languages/'.$lang;
		}
	}
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

	public function create() {
		mkdir($this->root);
		mkdir($this->root.'/client');
		mkdir($this->root.'/server');
		mkdir($this->root.'/geojson');
		mkdir($this->root.'/geojson/poi');
		mkdir($this->root.'/geojson/track');
		mkdir($this->root.'/tiles');
		mkdir($this->root.'/media');
		mkdir($this->root.'/media/images');
		mkdir($this->root.'/resources');
		mkdir($this->root.'/pages');
	}

	public function clean() {
		$root = $this->root;
      system("rm -f $root/client/*");
      system("rm -f $root/geojson/*");
      system("rm -f $root/media/images/*");
      system("rm -f $root/media/*");
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