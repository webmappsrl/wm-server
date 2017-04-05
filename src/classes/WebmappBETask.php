<?php
class WebmappBETask extends WebmappAbstractTask {

	// Code
	private $code;

	public function check() {

		// Controllo parametro list
		if(!array_key_exists('code', $this->options))
			throw new Exception("L'array options deve avere la chiave 'code'", 1);

		$this->code = $this->options['code'];

		// TODO: controllo della risposta delle API http://$code.be.webmapp.it/XXX
			
		return TRUE;
	}

	// GETTERS
	public function getCode() { return $this->code; }
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
    	return $this->getAPI('wp','map');
    }
    // END of GETTERS

    public function process(){

    	// Scarica le mappe da elaborare
    	$maps=$this->loadAPI($this->getMapAPI());

    	// Esegui i loop sulle singole mappe
    	return TRUE;
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