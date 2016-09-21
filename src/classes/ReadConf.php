<?php
class ReadConf
{
    // File di configurazione (PATH completo)
    private $confFile;

    // Json (array associativo) letto dal file di configurazione
    private $json;

    // Eventuale messaggio di errore durante la lettura del file di configurazione
    private $error='NONE';

    // Parametri obbligatori del file di configurazione 
    // Primo livello di json, validi per tutti i tipi del TASK da eseguire
    private $mandatoryPars = 
          array('project_name',
                'bounds',
                'file_name',
                'file_type'
                );

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

    public function getJson() {
        return $this->json;
    }

    public function check() {
        // Se il file non esiste restituisci FALSE
        if (!file_exists($this->confFile)) {
            $this->error='ERROR.'.$this->confFile.' file does not exist';
            return FALSE;
        }

        // Leggi il file JSON
        $this->json = json_decode(file_get_contents($this->confFile),TRUE);

        // Controllo validità del JSON nel file di configurazione
        if(is_null($this->json)) {
            $this->error='ERROR.'.$this->confFile.' json is not valid';
            return FALSE;
        }

    
        // Controllo parametri presenti nel file di configurazione

        // project_name: obbligatorio
        // project_path: 1) se non definito nella variabile deve è 
        //                  /var/www/html/api.webmapp.it/[projetc_name]
        //               2) Controllare che la directory esista
        // bounds: obbligatorio
        // bounds:        bounds: {
        //                         'southWest': [43.56984,10.21466],
        //                         'northEast': [43.87756,10.6855]
        //                      }
        // file_name: obbligatorio
        // file_type: obbligatorio
        // file_type: deve essere definito tra valori consentiti 
        //            (ovvero deve esistere una classe che poi fa i lavori che deve fare)

        // Controllo Parametri obbligatori: project_name, bounding_box, file_name, file_type
        $mandatoryErrors = array();
        foreach ($this->mandatoryPars as $par) {
            if(!array_key_exists($par, $this->json)) $mandatoryErrors[]=$par;
        }
        if(count($mandatoryErrors)>=1){
            $this->error='ERROR. Mandatory pars missing '. implode(',', $mandatoryErrors);
            return FALSE;
        }



        return TRUE;
    }
}