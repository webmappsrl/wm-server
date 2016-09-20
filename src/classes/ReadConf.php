<?php
class ReadConf
{
    private $confFile;
    private $json;
    private $error='NONE';

    public function __construct($confFile)
    {
        $this->confFile = $confFile;
    }

    public function getConfFile()
    {
        return $this->confFile;
    }

    public function getError() {
        return $this->error;
    }

    public function check() {
        // Se il file non esiste restituisci FALSE
        if (!file_exists($this->confFile)) return FALSE;

        // Leggi il file JSON
        $this->json = json_decode(file_get_contents($this->confFile));

        // Controllo validità del JSON nel file di configurazione
        if(is_null($this->json)) return FALSE;
     

        return TRUE;
    }
}