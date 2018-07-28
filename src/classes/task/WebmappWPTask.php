<?php
class WebmappWPTask extends WebmappAbstractTask {

 // Code
 private $wp;

 public function check() {

    // Controllo parametro code http://[code].be.webmapp.it
    if(!array_key_exists('url_or_code', $this->options))
        throw new Exception("'url_or_code' option is mandatory", 1);
    // Controlla esistenza della mappa prima di procedere
    $wp = new WebmappWP($this->options['url_or_code']);
    // Controlla esistenza della piattaforma
    if (!$wp->check()) {
        throw new Exception("ERRORE: La piattaforma {$wp->getBaseUrl()} non risponde o non esiste.", 1);
    }
    // Crea la mappa carcando i meta dall'URL
    $this->wp = $wp;
    return TRUE;
}

public function process(){
    $path = $this->project_structure->getRoot().'/geojson';
    
    // POI
    $pois = $this->wp->getAllPoisLayer($path);
    $pois->writeAllFeatures();
    return FALSE;
}


}
