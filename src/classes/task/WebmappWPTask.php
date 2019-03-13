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

    $tax_path = $this->project_structure->getRoot().'/taxonomies';
    $this->wp->writeTaxonomies($tax_path);

    // ADD related and WRITE to PostGIS
    if ($pois->count() >0){
        foreach($pois->getFeatures() as $poi){
            $poi->addRelated($this->distance,$this->limit);
            $poi->writeToPostGis();
        }
    }
    if ($tracks->count() >0){
        $track_path = $this->project_structure->getRoot().'/track';
        if(!file_exists($track_path)) {
            $cmd = "mkdir $track_path";
            system($cmd);
        }
        foreach($tracks->getFeatures() as $track){
            $track->addRelated($this->distance,$this->limit);
            $track->writeToPostGis();
            $track->addBBox();
            $track->generateAllImages('',$track_path);
        }
    }

    if($routes->count()>0) {
        $route_path = $this->project_structure->getRoot().'/route';
        if(!file_exists($route_path)) {
            $cmd = "mkdir $route_path";
            system($cmd);
        }
        foreach ($routes->getFeatures() as $route) {
            $id = $route->getId();
            $route->writeToPostGis();
            $route->addBBox();
            $route->generateAllImages('',$route_path);
            $route->generateRBHTML($route_path);
        }
    }

    // WRITE AGAIN AFTER adding related
    $pois->writeAllFeatures();
    $tracks->writeAllFeatures();
    $routes->writeAllFeatures();

    $pois->writeAllRelated($path);
    $tracks->writeAllRelated($path);

    $pois->write($path);

    // route_index.geojson
    $route_index = WebmappUtils::createRouteIndexLayer($this->wp->getApiUrl().'/route');
    $route_index->write($path);


    return true;
}

}
