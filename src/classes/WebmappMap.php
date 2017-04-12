<?php

// Gestione di una mappa (creazione file index, file di configurazione)

class WebmappMap {

	private $map;
    private $structure;

    public function __construct($map,$structure) {
      $this->map = $map;
      $this->structure = $structure;
    } 

    public function getType() { return $this->map['n7webmap_type'];}

}