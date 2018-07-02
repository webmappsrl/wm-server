<?php


class WebmappCustomConfigTask extends WebmappAbstractTask {

    private $append = array();
    private $conf_path = '';
    private $conf_array = array();

    public function check() {
        echo "Checking Process - TYPE:".get_class($this)." - NAME:".$this->name."\n";
        if(!array_key_exists('append', $this->options)) {
            echo "WARN: no append defined ... skipping task.\n";
        }
        else {
           $this->append=$this->options['append'];
           if(!is_array($this->append)) {
            throw new Exception("File di configurazione malformato: parametro APPEND deve essere di tipo array", 1);
           }
           foreach($this->append as $key => $val) {
            if(!in_array($key, array('MENU','PAGES','COMMUNICATION','INCLUDE', 'LOGIN'))) {
                throw new Exception("La chiave $key non è supportata", 1);
            }
           }

           // TODO: check dei singoli valori

           // Verifica esistenza file config.json
        }
        return true;
    }

    public function process(){
        echo "Starting Process - TYPE:".get_class($this)." - NAME:".$this->name."\n";
           $this->conf_path = $this->project_structure->getRoot().'/config.json';
           if(!file_exists($this->conf_path)) {
              throw new Exception("il file ".$this->conf_path." non esiste. Questo task deve essere lanciato in sequenza ad un task precedente che generi un file di configurazione. Controlla le impostazioni del file di configurazione del server.", 1);            
           }
           $this->conf_array = json_decode(file_get_contents($this->conf_path),TRUE);
        if (count($this->append)==0) {
            echo "WARN: no append defined ... skipping task.\n";
            return TRUE;
        }
        // Process
        foreach($this->append as $key => $val) {
            switch ($key) {
                case 'MENU':
                    $this->processMenu($val);
                    break;
                case 'PAGES':
                    $this->processPages($val);
                    break;
                case 'COMMUNICATION':
                    $this->processCommunication($val);
                    break;
                case 'INCLUDE':
                    $this->processInclude($val);
                    break;
                case 'LOGIN':
                    $this->processLogin($val);
                    break;
                
                default:
                    throw new Exception("$key non valida.", 1);
                    break;
            }
        }

        // public function getConf() {
        //    return "angular.module('webmapp').constant('GENERAL_CONFIG', ".$this->getConfJson().");";
        // }
        // Scrittura file
        // JSON
        $json = json_encode($this->conf_array);
        file_put_contents($this->conf_path, $json);
        // JS
        $jspath = $this->project_structure->getRoot().'/config.js';
        $js="angular.module('webmapp').constant('GENERAL_CONFIG', $json);";
        file_put_contents($jspath, $js);

        return TRUE;
    }

    private function processMenu($val) {
        $menu = array();
        if(isset($this->conf_array['MENU'])) {
            $menu = $this->conf_array['MENU'];
        }
        if(!is_array($val)) {
            throw new Exception("Il valore della varabile di configurazione MENU deve essere un array.", 1);
        }
        foreach ($val as $key => $value) {
            $menu[]=$value;
        }
        $this->conf_array['MENU']=$menu;
    }
    private function processPages($val) {
        $pages = array();
        if(isset($this->conf_array['PAGES'])) {
            $pages = $this->conf_array['PAGES'];
        }
        if(!is_array($val)) {
            throw new Exception("Il valore della variabile di configurazione PAGES deve essere un array.", 1);
        }
        foreach ($val as $key => $value) {
            $pages[]=$value;
        }
        $this->conf_array['PAGES']=$pages;        
    }
    private function processCommunication($val) {
        $c = array();
        if(isset($this->conf_array['COMMUNICATION'])) {
            $c = $this->conf_array['COMMUNICATION'];
        }
        if(!is_array($val)) {
            throw new Exception("Il valore della variabile di configurazione COMMUNICATION deve essere un array.", 1);
        }
        foreach ($val as $key => $value) {
            $c[$key]=$value;
        }
        $this->conf_array['COMMUNICATION']=$c;        
    }
    private function processInclude($val) {
        $c = array();
        if(isset($this->conf_array['INCLUDE'])) {
            $c = $this->conf_array['INCLUDE'];
        }
        if(!is_array($val)) {
            throw new Exception("Il valore della variabile di configurazione INCLUDE deve essere un array.", 1);
        }
        foreach ($val as $key => $value) {
            $c[$key]=$value;
        }
        $this->conf_array['INCLUDE']=$c;        
    }
    private function processLogin($val) {
        $c = array();
        if(isset($this->conf_array['LOGIN'])) {
            $c = $this->conf_array['LOGIN'];
        }
        if(!is_array($val)) {
            throw new Exception("Il valore della variabile di configurazione LOGIN deve essere un array.", 1);
        }
        foreach ($val as $key => $value) {
            $c[$key]=$value;
        }
        $this->conf_array['LOGIN']=$c;        
    }

}
