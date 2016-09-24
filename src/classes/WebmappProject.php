<?php
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
        $c = new ReadConf($this->confProjectPath);
        if(!$c->check()){
            $this->error=$c->getError();
            return FALSE;
        }
        

    	// Costruisci la variabile tasks, esegui il check sui file di configurazione
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