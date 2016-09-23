<?php
class WebmappProject {
    // Nome del progetto (preso dalla directory del costruttore)
	private $name;

	// Directory del progetto
	private $path;

	// Stringa di errore
	private $error='NONE';

	// Costruttore
	public function __construct($path)
    {
        $this->path = $path;
    }

    // Getters
    public function getPath() { return $this->path; }
    public function getName() { return $this->name; }
    public function getError() { return $this->error; }

    // Open (check directory exists)
    public function open() {
    	if( ! file_exists($this->path)) {
    		$this->error='ERROR:'.$this->path.' is not valid path.';
    		return FALSE;
    	}

    	// Imposta il nome del progetto
    	$this->name=basename($this->path);

    	return TRUE;
    }

}