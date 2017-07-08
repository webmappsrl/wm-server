<?php
class WebmappBETask extends WebmappAbstractTask {

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
public function getCode() { return $this->code; }
public function getId() { return $this->id; }
public function process(){
    $poi_layers = $this->wp->getPoiLayers();
    if(is_array($poi_layers) && count($poi_layers)>0) {
        foreach($poi_layers as $layer) {
            $layer->write($this->project_structure->getPathGeojson());
            $this->map->addPoisWebmappLayer($layer);
        }
    }
    $track_layers = $this->wp->getTrackLayers();
    if(is_array($track_layers) && count($track_layers)>0) {
        foreach($track_layers as $layer) {
            $layer->write($this->project_structure->getPathGeojson());
            $this->map->addTracksWebmappLayer($layer);
        }
    }
    $this->map->buildStandardMenu();
    $this->map->writeConf();
    $this->map->writeIndex();
    return TRUE;
}

}
