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

 private $BBfirst=true;
 private $lngMax;
 private $lngMin;
 private $latMax;
 private $latMin;

 // TODO: leggere il parametro da options
 private $add_related = false;



 public function check() {

            // Controllo parametro code http://[code].be.webmapp.it
    if(!array_key_exists('code', $this->options))
        throw new Exception("L'array options deve avere la chiave 'code'", 1);

            // Controllo parametro id (id della mappa)
    if(!array_key_exists('id', $this->options))
        throw new Exception("L'array options deve avere la chiave 'id' corrispondente all'id della mappa", 1);

    if(array_key_exists('add_related', $this->options)) {
        $this->add_related = $this->options['add_related'];
    }

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
    $poi_layers = $this->wp->getPoiLayers();
    if(is_array($poi_layers) && count($poi_layers)>0) {

        // Primo loop Inserisci nel POSTGIS (da eliminare dopo sync)
        if ($this->add_related) {
        foreach($poi_layers as $layer) {
            if (!$layer->getExclude()) {
             // Aggiungi i singoli POI del layer al DB Postgis
                foreach($layer->getFeatures() as $poi) {
                    $poi->writeToPostGis();
                }

        } }

        }
        // Secondo LOOP Trova i vicni e scrivi singolo
        foreach($poi_layers as $layer) {
            if (!$layer->getExclude()) {
                if($this->add_related) {
                foreach($layer->getFeatures() as $poi) {
                    $poi->addRelated();
                    $poi->write($this->project_structure->getPathGeojson().'/poi');
                }}
            $this->computeBB($layer);
            $layer->write($this->project_structure->getPathGeojson());
            $this->map->addPoisWebmappLayer($layer);
        }
        }
    }
    $track_layers = $this->wp->getTrackLayers();
    if(is_array($track_layers) && count($track_layers)>0) {
        foreach($track_layers as $layer) {
            if(!$layer->getExclude()) {
            $this->computeBB($layer);
            $layer->write($this->project_structure->getPathGeojson());
            $this->map->addTracksWebmappLayer($layer);
            }
        }
    }

    // Bounding Box
    // TODO: gestione del delta 0.045 (costante, modificabile?)
    if(empty($this->map->getBB())) {
        $this->map->setBB($this->latMin-0.045,$this->lngMin-0.045,$this->latMax+0.045,$this->lngMax+0.045);
    }
    $this->map->buildStandardMenu();
    $this->map->writeConf();
    $this->map->writeIndex();
    $this->map->writeInfo();
    return TRUE;
}

private function computeBB($l) {
    $bb=$l->getBB();
    if(is_array($bb)&&count($bb>0)){
        $lngMin=$bb['bounds']['southWest'][1];
        $lngMax=$bb['bounds']['northEast'][1];
        $latMin=$bb['bounds']['southWest'][0];
        $latMax=$bb['bounds']['northEast'][0];
        if ($this->BBfirst) {
            $this->latMin = $latMin;
            $this->latMax = $latMax;
            $this->lngMin = $lngMin;
            $this->lngMax = $lngMax;
            $this->BBfirst=false;
        }
        else {
            if($latMin<$this->latMin) $this->latMin=$latMin;
            if($latMax>$this->latMax) $this->latMax=$latMax;
            if($lngMin<$this->lngMin) $this->lngMin=$lngMin;
            if($lngMax>$this->lngMax) $this->lngMax=$lngMax;
        }
    }
}

}
