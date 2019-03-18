<?php
class WebmappWPTask extends WebmappAbstractTask {

 private $wp;

 private $process_poi = TRUE;
 private $process_track = TRUE;
 private $process_route = TRUE;

 private $distance=5000;
 private $limit=10;

 public function check() {

    // Controllo parametro code http://[code].be.webmapp.it
    if(!array_key_exists('url_or_code', $this->options))
        throw new Exception("'url_or_code' option is mandatory", 1);

    // Controllo parametro code http://[code].be.webmapp.it
    if(array_key_exists('process_poi', $this->options)) {
        $this->process_poi = $this->options['process_poi'];
    }
    // Controllo parametro code http://[code].be.webmapp.it
    if(array_key_exists('process_track', $this->options)) {
        $this->process_track = $this->options['process_track'];
    }
    // Controllo parametro code http://[code].be.webmapp.it
    if(array_key_exists('process_route', $this->options)) {
        $this->process_route = $this->options['process_route'];
    }


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
    if ($this->process_poi) {
        $pois = $this->wp->getAllPoisLayer($path);
        $pois->writeAllFeatures();        
    }

    // TRACKS
    if ($this->process_track) {
       $tracks = $this->wp->getAllTracksLayer($path);
        $tracks->writeAllFeatures();
    }

    // ROUTES
    if ($this->process_route) {
        $routes = $this->wp->getAllRoutesLayer($path);
        $routes->writeAllFeatures();
     }

    $tax_path = $this->project_structure->getRoot().'/taxonomies';
    $this->wp->pruneTaxonomies();
    $this->wp->writeTaxonomies($tax_path);

    // ADD related and WRITE to PostGIS
    if ($this->process_poi && $pois->count() >0){
        foreach($pois->getFeatures() as $poi){
            $poi->addRelated($this->distance,$this->limit);
            $poi->writeToPostGis();
        }
    }
    if ($this->process_track && $tracks->count() >0){
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

    if($this->process_route && $routes->count()>0) {
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
    if($this->process_poi) $pois->writeAllFeatures();
    if($this->process_track) $tracks->writeAllFeatures();
    if($this->process_route) $routes->writeAllFeatures();

    if($this->process_poi) $pois->writeAllRelated($path);
    if($this->process_track) $tracks->writeAllRelated($path);

    if($this->process_poi) $pois->write($path);

    // route_index.geojson
    if($this->process_route) $route_index = WebmappUtils::createRouteIndexLayer($this->wp->getApiUrl().'/route');
    if($this->process_route) $route_index->write($path);

    return true;
}

}
