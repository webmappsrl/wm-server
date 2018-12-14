<?php
class WebmappWPTask extends WebmappAbstractTask {

 private $wp;

 private $distance=5000;
 private $limit=10;

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

private function processTaxonomies() {
    $this->wp->loadTaxonomies();
    $tax_path = $this->project_structure->getRoot().'/taxonomies';
    if (!file_exists($tax_path)) {
        if (!is_writable($this->project_structure->getRoot())) {
            throw new Exception("Error Processing Request", 1);
        }
        $cmd = "mkdir $tax_path";
        system($cmd);
    }
    $this->wp->writeTaxonomies($tax_path);
}

public function process(){

    $path = $this->project_structure->getRoot().'/geojson';
    WebmappUtils::cleanPostGis();

    $this->processTaxonomies();

    // POI
    $pois = $this->wp->getAllPoisLayer($path);
    $pois->writeAllFeatures();

    // TRACKS
    $tracks = $this->wp->getAllTracksLayer($path);
    $tracks->writeAllFeatures();

    // ROUTES
    $routes = $this->wp->getAllRoutesLayer($path);
    $routes->writeAllFeatures();

    // ADD related
    if ($pois->count() >0){
        foreach($pois->getFeatures() as $poi){
            $poi->addRelated($this->distance,$this->limit);
        }
    }
    if ($tracks->count() >0){
        foreach($tracks->getFeatures() as $track){
            $track->addRelated($this->distance,$this->limit);
        }
    }

    // WRITE AGAIN AFTER adding related
    $pois->writeAllFeatures();
    $tracks->writeAllFeatures();

    $pois->writeAllRelated($path);
    $tracks->writeAllRelated($path);

    $pois->write($path);

    // route_index.geojson
    $route_index = WebmappUtils::createRouteIndexLayer($this->wp->getApiUrl().'/route');
    $route_index->write($path);


    return true;
}

}
