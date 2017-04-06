<?php
class WebmappBETask extends WebmappAbstractTask {

	// Code
	private $code;

    // ID della mappa
    private $id;

	public function check() {

        // Controllo parametro code http://[code].be.webmapp.it
        if(!array_key_exists('code', $this->options))
            throw new Exception("L'array options deve avere la chiave 'code'", 1);

        // Controllo parametro id (id della mappa)
        if(!array_key_exists('id', $this->options))
            throw new Exception("L'array options deve avere la chiave 'id' corrispondente all'id della mappa", 1);

        $this->code = $this->options['code'];
        $this->id = $this->options['id'];

		// TODO: controllo della risposta delle API http://$code.be.webmapp.it/XXX
			
		return TRUE;
	}

	// GETTERS
    public function getCode() { return $this->code; }
    public function getId() { return $this->id; }
    public function getAPI($type,$api){

    	$baseUrl = 'http://'.$this->code.'.be.webmapp.it/';
  
    	switch ($type) {
    		case 'wp':
    			$url = $baseUrl . 'wp-json/wp/v2/'.$api;
    			break;
    		case 'wm':
    			$url = $baseUrl . 'wp-json/webmapp/v1/' . $api;
    			break;		
    		default:
    			throw new Exception("$type non supportato dal metofo getBEURL: sono validi solo wp e wm", 1);
    			break;
    	}
    	return $url;
    }
    public function getMapAPI() {
    	return $this->getAPI('wp','map/'.$this->id);
    }
    // END of GETTERS

    public function process(){

    	// Scarica le mappe da elaborare
    	$map=$this->loadAPI($this->getMapAPI());

        if(!array_key_exists('id', $map)){
            throw new Exception("Errore nel caricamento della mappa con API ".$this->getMapAPI().". Il parametro ID non è presente nella risposta della API." , 1);
        }

        switch ($map['n7webmap_type']  ) {
            case 'all':
                return $this->processAll($map);
                break;
            
            case 'single_route':
                return $this->processSingleRoot($map);
                break;
            
            case 'layers':
                return $this->processLayers($map);
                break;
            
            default:
                throw new Exception("Map typpe not supported: " . $map['n7webmap_type'], 1);
                
                break;
        }

    }

    private function processAll($map) {
        return TRUE;
    }

    private function processSingleRoot($map) {
        return FALSE;
    }

    private function processLayers($map) {
        return FALSE;
    }

    // Carica una API del BE e restituisce l'array json corrispondente
    public function loadAPI($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL,$url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
    $return = curl_exec($ch);
    $json = json_decode($return,true);
    curl_close ($ch);
    return $json;
    }

}