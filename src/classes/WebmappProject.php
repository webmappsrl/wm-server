<?php

// Classe principale: definisce un progetto WEBMAPP

class WebmappProject {
    // Root directory del progetto (base delle API), path assoluto
	private $root;

    public function __construct($root) {
        $this->root=$root;
    } 

    // Getters
    public function getRoot() {return $this->root;}

    // Fine dei getters

    // Metodi pubblici

    // effettua il controllo di una root (esistenza dei file obbligatori)
    // Effettua anche la lettura del file di configurazione e imposta l'esecuzione
    // di tutti i task che vengono poi effettivamente eseguiti chiamando il metodo process
    public function check() {
        if(file_exists($this->root)){
            return TRUE;
        }
        return FALSE;
    }

    // Fine dei metodi pubblici

}