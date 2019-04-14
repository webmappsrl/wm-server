<?php
class WebmappATask extends WebmappAbstractTask {

 private $wp;
 private $distance=5000;
 private $limit=10;

 private $path;
 private $track_path;
 private $route_path;

 public function check() {

    // Controllo parametro code http://[code].be.webmapp.it
    if(!array_key_exists('url_or_code', $this->options))
        throw new Exception("'url_or_code' option is mandatory", 1);

    $wp = new WebmappWP($this->options['url_or_code']);
    // Controlla esistenza della piattaforma
    if (!$wp->check()) {
        throw new Exception("ERRORE: La piattaforma {$wp->getBaseUrl()} non risponde o non esiste.", 1);
    }
    $this->wp = $wp;
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

    $this->processPois();
    $this->processTracks();
    $this->processRoutes();

    return true;

}

private function processPois() {
    $this->processFeatures('poi');
}

private function processTracks() {
    $this->processFeatures('track');
}

private function processRoutes() {
    $this->processFeatures('route');
}


private function processFeatures($type) {
    $pois = $this->getListByType($type);
    if (is_array($pois) && count($pois)>0) {
        foreach($pois as $id => $mod) {
            echo "Checking $type $id ... ";
            $to_process = FALSE;
            $geojson = $this->path.'/'.$id.'.geojson';
            if (!file_exists($geojson)) {
                echo "NO Geojson ";
                $to_process = TRUE;
            } else {
                $j=WebmappUtils::getJsonFromAPI($geojson);
                if(isset($j['properties']['modified'])) {
                    $poi_mod = $j['properties']['modified'];
                    if(strtotime($mod)>strtotime($poi_mod)) {
                        echo " $type need to be updated ($mod VS $poi_mod)";
                        $to_process = TRUE;
                    }
                    else {
                        echo "$type updated ($mod VS $poi_mod). Skipping ";
                        $to_process = FALSE;
                    }
                }
                else {
                    echo "Property MODIFIED missing ... updating ";
                    $to_process = TRUE;
                }
            }
            if($to_process) {
                $this->processFeature($type,$id);                
            }
            echo "... DONE.\n\n";
        }
    }
}

private function processFeature($type,$id) {
    switch ($type) {
        case 'poi':
            $this->processPoi($id);
            break;
        case 'track':
            $this->processTrack($id);
            break;
        case 'route':
            $this->processRoute($id);
            break;        
    }
}

private function processPoi($id) {
    $poi = new WebmappPoiFeature($this->wp->getApiPoi($id));
    $poi->write($this->path);
}
private function processTrack($id) {
    $t = new WebmappTrackFeature($this->wp->getApiTrack($id));
    echo "related.";
    $t->addRelated($this->distance,$this->limit);
    echo "postgis.";
    $t->writeToPostGis();
    echo "3d.";
    $t->addEle();
    echo "bbox.";
    $t->addBBox();
    echo "RBpois.";
    $t->writeRBRelatedPoi($this->track_path);
    //$t->generateAllImages('',$this->track_path);
    //$t->generateLandscapeRBImages('',$this->track_path);
    echo "write.";
    $t->write($this->path);
}

private function processRoute($id) {
    $r = new WebmappRoute($this->wp->getApiRoute($id));
    $r->writeToPostGis();
    $r->addBBox();
    //$r->generateAllImages('',$this->route_path);
    $r->write($this->path);

}

private function getListByType($type) {
    return WebmappUtils::getJsonFromAPI($this->wp->getBaseUrl().'/wp-json/webmapp/v1/list?type='.$type);
}

}
