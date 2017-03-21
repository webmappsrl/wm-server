<?php

// Classe principale: definisce un progetto WEBMAPP

class WebmappProject {
    // Root directory del progetto (base delle API), path assoluto
	private $root;

    // Struttura del progetto è un oggetto WebmappProjectStructure;
    private $structure;

    public function __construct($root) {
        $this->root=rtrim($root, '/');
        $this->structure = new WebmappProjectStructure($this->root);
    } 

    // Getters
    public function getRoot() {return $this->root;}
    public function getStructure() {return $this->structure;}
    // Fine dei getters

    // Metodi pubblici

    // Controllo di una struttura esistente delegato alla classe Structure
    public function check() {
        return $this->structure->check();
    }

    // Fine dei metodi pubblici

}