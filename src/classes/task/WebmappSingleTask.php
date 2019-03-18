<?php
class WebmappSingleTask extends WebmappAbstractTask {

 private $routes;
 private $wp;
 private $distance=5000;
 private $limit=10;


 public function check() {

    // Controllo parametro code http://[code].be.webmapp.it
    if(!array_key_exists('url_or_code', $this->options))
        throw new Exception("'url_or_code' option is mandatory", 1);

    // Controllo parametro code http://[code].be.webmapp.it
    if(array_key_exists('routes', $this->options)) {
        $this->routes = $this->options['routes'];
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

    $path = $this->project_structure->getRoot().'/geojson';
    $track_path = $this->project_structure->getRoot().'/track';
    $route_path = $this->project_structure->getRoot().'/route';
    if(!file_exists($track_path)) {
        $cmd = "mkdir $track_path";
            system($cmd);
    }
    if(!file_exists($route_path)) {
        $cmd = "mkdir $route_path";
            system($cmd);
    }

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
                            $p->write($path);
                        }
                    }
                    $t->addRelated($this->distance,$this->limit);
                    $t->writeToPostGis();
                    $t->addBBox();
                    $t->writeRBRelatedPoi($track_path);
                    $t->generateAllImages('',$track_path);
                    $t->generateLandscapeRBImages('',$track_path);
                    $t->write($path);
                }
            }
            $r->writeToPostGis();
            $r->addBBox();
            $r->generateAllImages('',$route_path);
            $r->write($path);
            //$r->generateRBHTML($route_path);
            echo "\n\n";
        }
    }
    return true;

}

}
