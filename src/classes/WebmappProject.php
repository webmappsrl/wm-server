<?php

// Apre e verifica (open()) e poi processa (process()) un progetto
// Webmapp.
//
// Unico parametro necessario la directory del progetto che deve contenere
// la subdire "/server/" all'interno della quale deve essere presente il file 
// project.conf che definisce i parametri generali di tutto il progetto. Unico
// parametro obbligatorio è bounds che contiene i bounding box geografici del progetto: 
//
//  { 
//   "bounds": 
//      { 
//        "southWest" : [43.704367081989,10.338478088378],
//        "northEast" : [43.84839376489,10.637855529785] 
//      }
//  }
//
// I singoli task da eseguire sono definiti nei file di configurazione [taskName].conf
// per i dettagli vedere la classe WebmappAbstractTask.conf
//

class WebmappProject {
    // Nome del progetto (preso dalla directory del costruttore)
	private $name;

	// Directory del progetto
	private $path;

	// Stringa di errore
	private $error='NONE';

	// Path dei file di configurazione
	private $confPath;

    // Path del file di configurazione di progetto
    private $confProjectPath;

	// File di configurazione
	private $confFiles = array();

    // Bounding box del progetto
    private $bounds;

	// tasks
	private $tasks = array();

	// Costruttore
	public function __construct($path)
    {
        $this->path = $path;
    }

    // Getters
    public function getPath() { return $this->path; }
    public function getConfPath() { return $this->confPath; }
    public function getName() { return $this->name; }
    public function getError() { return $this->error; }
    public function getConfFiles() { return $this->confFiles; }
    public function getTasks() { return $this->tasks; }
    public function getConfProjectPath() { return $this->confProjectPath;}
    public function getBounds() {return $this->bounds; }

    // Open and check configuration files
    public function open() {
    	if( ! file_exists($this->path)) {
    		$this->error='ERROR:'.$this->path.' is not valid path.';
    		return FALSE;
    	}

    	// Imposta il nome del progetto
    	$this->name=basename($this->path);

    	// Imposta il path delle configurazione
    	$this->confPath=$this->path.'/server/';
    	// Rimuovi i doppi slash (sostituisci con singolo slash)
    	$this->confPath = preg_replace('|//|', '/', $this->confPath);

    	// Controlla l'esistenza della directory
    	if( ! file_exists($this->confPath)) {
    		$this->error='ERROR:'.$this->path.' has no subdir server with configuration files.';
    		return FALSE;
    	}

    	// Apri la directory e leggi i file di configurazione
    	$conf_files = array();
    	$d = dir($this->confPath);
    	while (false !== ($entry = $d->read())) {
    		if(preg_match('/conf/', $entry)) $conf_files[] = $entry;
    	}
    	$this->confFiles=$conf_files;
    	// Controlla l'esistenza di project.conf
    	if (! in_array('project.conf', $conf_files)) {
    		$this->error='ERROR: project '.$this->name.' has no project.conf file.';
    		return FALSE;
    	}

        // Imposta proprietà confProjectPath
        $this->confProjectPath=$this->confPath.'project.conf';

        // Leggi project.conf e setta bounds
        // TODO: gestire con eccezione (e impostare caso di test relativo)
        $c = new ReadConf($this->confProjectPath);
        if(!$c->check(TRUE)){
            $this->error=$c->getError();
            return FALSE;
        }

        // Controllo delle bounds
        // TODO: bounds non è obbligatorio sempre
        
        $json = $c->getJson();
        if (!isset($json['bounds'])){
            throw new Exception("Error: no bounds defined in project.conf", 1);            
        }
        $this->bounds = new WebmappBounds ($json['bounds']);
        

        // TODO: il readConf deve essere lanciato direttamente dalla Factory
    	// Costruisci la variabile tasks, esegui il check sui file di configurazione
        // Passare alle eccezioni con relativo test
        
    	$tasks=array();
    	$err = array();
    	foreach ($this->confFiles as $confFile) {
    		if($confFile != 'project.conf') { 
    		    $c = new ReadConf($this->confPath.$confFile);
    		    if (! $c-> check() ){
    			    $err[] = $confFile. ': '. $c->getError(); 
    		    } 
    		    else {
    		    	$name=preg_replace('/\.conf/', '', $confFile);
    		    	$tasks[$name]=array('path'=>$this->confPath.$confFile,'json'=>$c->getJson());
    		    }
    	}
    	}
    	if (count($err)==0) {
           $this->tasks=$tasks;
       	}
       	else {
            $this->error="ERROR: reading configuration files.\n".implode("\n", $err);
            return FALSE;
       	}

    	return TRUE;
    }

}