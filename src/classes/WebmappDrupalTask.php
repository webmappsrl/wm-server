<?php
class WebmappDrupalTask extends WebmappAbstractTask {

    	// Code
   private $code;

        // ID della mappa
   private $id;

        // Oggetto WebmappWP per la gestione delle API
   private $wp;

        // Oggetto WebmappMap
   private $map;


   public function check() {

            // Controllo parametro code http://[code].be.webmapp.it
    if(!array_key_exists('code', $this->options))
        throw new Exception("L'array options deve avere la chiave 'code'", 1);

            // Controllo parametro id (id della mappa)
    if(!array_key_exists('id', $this->options))
        throw new Exception("L'array options deve avere la chiave 'id' corrispondente all'id della mappa", 1);

            // Controlla esistenza della mappa prima di procedere

    $this->code = $this->options['code'];
    $this->id = $this->options['id'];

    $wp = new WebmappWP($this->code);

            // Controlla esistenza della piattaforma
    if (!$wp->check()) {
        throw new Exception("ERRORE: La piattaforma {$wp->getBaseUrl()} non risponde o non esiste.", 1);
    }
            // Controlla esistenza della mappa
    if(!$wp->checkMap($this->id)) {
        throw new Exception("Errore: la mappa {$wp->getApiMap($this->id)} non esiste o non risponde.", 1);
    }
            // Crea la mappa carcando i meta dall'URL
    $this->wp = $wp;
    $this->map=new WebmappMap($this->project_structure);
    $this->map->loadMetaFromUrl($this->wp->getApiMap($this->id));

    return TRUE;
}

    	// GETTERS
public function getCode() { 
    return $this->code; 
}
public function getId() { 
    return $this->id; 
}
public function process(){

    $this->loadPois();

    $this->map->buildStandardMenu();
    $this->map->writeConf();
    $this->map->writeIndex();
    $this->map->writeInfo();
    return TRUE;
}

// TODO: prendere gli endpoint dalla piattaforma editoriale? (anche no)
private function loadPois() {
    $url = "http://www.tavarnellevp.it/json/node?parameters[type]=poi";
    $pa = json_decode(file_get_contents($url),TRUE);
    if(count($pa)>0) {
        $layer = new WebmappLayer('pois');
        foreach ($pa as $item) {
            $uri = $item['uri'];
            $pi = json_decode(file_get_contents($uri),TRUE);
            // Mapping per renderlo compatibile con una Feature che arriva da WP
            $wm = array();
            $wm['id'] = $pi['nid'];
            $wm['title']['rendered'] = $pi['title'];
            $wm['content']['rendered'] = $pi['body']['und'][0]['value'];
            $wm['n7webmap_coord']['lat'] = $pi['field_posizione']['und'][0]['latitude'];
            $wm['n7webmap_coord']['lng'] = $pi['field_posizione']['und'][0]['longitude'];
            //$wm[''] = $pi[''][''][''];

            $poi = new WebmappPoiFeature($wm);
            $layer->addFeature($poi);

        }
        $layer->write($this->project_structure->getPathGeojson());
        $this->map->addPoisWebmappLayer($layer);

    }

}

}
