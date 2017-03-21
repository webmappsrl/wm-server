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

        return FALSE;
    }

    // Crea una struttura di progetto base secondo la project structure e 
    // inserisce un file di configurazione di esempio (funzionante)
    public function create() {
        return FALSE;
    }

    // Esegue i TASK definiti nel file di configurazione
    public function process() {

        return FALSE;
    }

    // Fine dei metodi pubblici

}