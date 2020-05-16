<?php
class WebmappSingleTask extends WebmappAbstractTask {

 private $wp;
 private $distance=5000;
 private $limit=10;

 private $path;
 private $track_path;
 private $route_path;

 private $routes = array();
 private $tracks = array();
 private $pois = array();


 public function check() {

    // Controllo parametro code http://[code].be.webmapp.it
    if(!array_key_exists('url_or_code', $this->options))
        throw new Exception("'url_or_code' option is mandatory", 1);

    // Controllo parametro code http://[code].be.webmapp.it
    if(array_key_exists('routes', $this->options)) {
        $this->routes = $this->options['routes'];
    }

    // Controllo parametro code http://[code].be.webmapp.it
    if(array_key_exists('tracks', $this->options)) {
        $this->tracks = $this->options['tracks'];
    }
    // Controlla esistenza della mappa prima di procedere
    $wp = new WebmappWP($this->options['url_or_code']);
    // Controlla esistenza della piattaforma
    if (!$wp->check()) {
        throw new Exception("ERRORE: La piattaforma {$wp->getBaseUrl()} non risponde o non esiste.", 1);
    }
    $this->wp = $wp;
    return TRUE;

    return TRUE;
}


public function process(){

    $this->path = $this->project_structure->getRoot().'/geojson';
    $this->track_path = $this->project_structure->getRoot().'/track';
    $this->route_path = $this->project_structure->getRoot().'/route';
    if(!file_exists($this->track_path)) {
        $cmd = "mkdir {$this->track_path}";
            system($cmd);
    }
    if(!file_exists($this->route_path)) {
        $cmd = "mkdir {$this->route_path}";
            system($cmd);
    }

    $this->processRoutes();
    $this->processTracks();

    return true;

}

private function processTracks() {
    echo "\n";
    if(count($this->tracks)>0){
        foreach ($this->tracks as $id) {
            $t = new WebmappTrackFeature($this->wp->getApiTrack($id));
            echo "TRACK: {$t->getId()}\n";
            $ps = $t->getRelatedPois();
            if(count($ps)>0){
                foreach($ps as $p) {
                    echo "POI: {$p->getId()}\n";
                    $p->writeToPostGis();
                    $p->addRelated($this->distance,$this->limit);
                    $p->write($this->path);
                }
            }
            $t->addRelated($this->distance,$this->limit);
            $t->writeToPostGis();
            $t->add3D();
            $t->setComputedProperties2();
            $t->addBBox();
            $t->writeRBRelatedPoi($this->track_path);
            $t->generateAllImages('',$this->track_path);
            $t->generateLandscapeRBImages('',$this->track_path);
            $t->write($this->path);
        }
    }

}

private function processRoutes() {
    echo "\n";
    if(count($this->routes)>0){
        foreach ($this->routes as $id) {
            $r = new WebmappRoute($this->wp->getApiRoute($id));
            echo "ROUTE: {$r->getId()}\n";
            $ts = $r->getTracks();
            if (count($ts)>0) {
                foreach ($ts as $t) {
                    echo "TRACK: {$t->getId()}\n";
                    $ps = $t->getRelatedPois();
                    if(count($ps)>0){
                        foreach($ps as $p) {
                            echo "POI: {$p->getId()}\n";
                            $p->writeToPostGis();
                            $p->addRelated($this->distance,$this->limit);
                            $p->write($this->path);
                        }
                    }
                    $t->addRelated($this->distance,$this->limit);
                    $t->writeToPostGis();
                    $t->add3D();
                    $t->setComputedProperties2();
                    $t->addBBox();
                    $t->writeRBRelatedPoi($this->track_path);
                    $t->generateAllImages('',$this->track_path);
                    $t->generateLandscapeRBImages('',$this->track_path);
                    $t->write($this->path);
                }
            }
            $r->writeToPostGis();
            $r->addBBox();
            $r->generateAllImages('',$this->route_path);
            $r->write($this->path);
            echo "\n\n";
        }
    }

}

}
