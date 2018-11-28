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
            if(!in_array($key, array('NAVIGATION', 'MENU','PAGES','COMMUNICATION','INCLUDE', 'LOGIN','OPTIONS','OVERLAY_LAYERS','SEARCH','DETAIL_MAPPING','MAP','STYLE'))) {
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
                case 'NAVIGATION':
                    $this->processNavigation($val);
                    break;
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
                case 'OPTIONS':
                    $this->processOptions($val);
                    break;
                case 'SEARCH':
                    $this->processSearch($val);
                    break;
                case 'OVERLAY_LAYERS':
                    $this->processOverlayLayers($val);
                    break;
                case 'DETAIL_MAPPING':
                    $this->processDetailMapping($val);
                    break;
                case 'MAP':
                    $this->processMap($val);
                    break;
                case 'STYLE':
                    $this->processStyle($val);
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

    private function processNavigation($val) {
        $navigation = array();
        if(isset($this->conf_array['NAVIGATION'])) {
            $navigation = $this->conf_array['NAVIGATION'];
        }
        if(!is_array($val)) {
            throw new Exception("Il valore della varabile di configurazione NAVIGATION deve essere un array.", 1);
        }
        foreach ($val as $key => $value) {
            $navigation[]=$value;
        }
        $this->conf_array['NAVIGATION']=$navigation;
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
    private function processOverlayLayers($val) {
        $ov = array();
        if(isset($this->conf_array['OVERLAY_LAYERS'])) {
            $ov = $this->conf_array['OVERLAY_LAYERS'];
        }
        if(!is_array($val)) {
            throw new Exception("Il valore della variabile di configurazione OVERLAY LAYERS deve essere un array.", 1);
        }
        foreach ($val as $key => $value) {
            $ov[]=$value;
        }
        $this->conf_array['OVERLAY_LAYERS']=$ov;
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
    private function processSearch($val) {
        $c = array();
        if(isset($this->conf_array['SEARCH'])) {
            $c = $this->conf_array['SEARCH'];
        }
        if(!is_array($val)) {
            throw new Exception("Il valore della variabile di configurazione SEARCH deve essere un array.", 1);
        }
        foreach ($val as $key => $value) {
            $c[$key]=$value;
        }
        $this->conf_array['SEARCH']=$c;
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
    private function processOptions($val) {
        $c = array();
        if(isset($this->conf_array['OPTIONS'])) {
            $c = $this->conf_array['OPTIONS'];
        }
        if(!is_array($val)) {
            throw new Exception("Il valore della variabile di configurazione OPTIONS deve essere un array.", 1);
        }
        foreach ($val as $key => $value) {
            $c[$key]=$value;
        }
        $this->conf_array['OPTIONS']=$c;
    }

    private function processDetailMapping($val) {
        $c = array();
        if(isset($this->conf_array['DETAIL_MAPPING'])) {
            $c = $this->conf_array['DETAIL_MAPPING'];
        }
        if(!is_array($val)) {
            throw new Exception("Il valore della variabile di configurazione DETAIL_MAPPING deve essere un array.", 1);
        }
        foreach ($val as $key => $value) {
            $c[$key]=$value;
        }
        $this->conf_array['DETAIL_MAPPING']=$c;
    }

    private function processMap($val) {
        $c = array();
        if(isset($this->conf_array['MAP'])) {
            $c = $this->conf_array['MAP'];
        }
        if(!is_array($val)) {
            throw new Exception("Il valore della variabile di configurazione MAP deve essere un array.", 1);
        }
        foreach ($val as $key => $value) {
            $c[$key]=$value;
        }
        $this->conf_array['MAP']=$c;
    }

    private function processStyle($val) {
        $c = array();
        if(isset($this->conf_array['STYLE'])) {
            $c = $this->conf_array['STYLE'];
        }
        if(!is_array($val)) {
            throw new Exception("Il valore della variabile di configurazione STYLE deve essere un array.", 1);
        }
        foreach ($val as $key => $value) {
            $c[$key]=$value;
        }
        $this->conf_array['STYLE']=$c;
    }

}
