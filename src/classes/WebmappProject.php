<?php
class WebmappProject {
    // Nome del progetto (preso dalla directory del costruttore)
	private $name;

	// Directory del progetto
	private $path;

	// Stringa di errore
	private $errore='NONE';

	// Costruttore
	public function __construct($path)
    {
        $this->path = $path;
    }

    // Getters
    public function getPath() { return $this->path; }
    public function getName() { return $this->name; }

    // Open (check directory exists)
    public function open() {
    	return FALSE;
    }

}