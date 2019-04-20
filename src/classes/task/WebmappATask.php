<?php
class WebmappATask extends WebmappAbstractTask {

 private $wp;
 private $distance=5000;
 private $limit=10;

 private $path;
 private $track_path;
 private $route_path;
 private $tax_path;

 // Array utilizzato nelle configurazioni per forzare la rigenerazione di tipi specici
 private $force_type=array();

 private  $taxonomies=array();

 public function check() {

    // Controllo parametro code http://[code].be.webmapp.it
    if(!array_key_exists('url_or_code', $this->options))
        throw new Exception("'url_or_code' option is mandatory", 1);

    // Controllo parametro code http://[code].be.webmapp.it
    if(array_key_exists('force_type', $this->options)) {
        $this->force_type=$this->options['force_type'];
    }

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
    $this->tax_path = $this->project_structure->getRoot().'/taxonomies';
    if (!file_exists($this->tax_path)) {
        if (!is_writable($this->project_structure->getRoot())) {
            throw new Exception("Error Processing Request", 1);
        }
        $cmd = "mkdir {$this->tax_path}";
        system($cmd);
    }

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
    $this->processTaxonomies();
    $this->processRouteIndex();

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

private function processTaxonomies() {
    $this->wp->loadTaxonomies();
    $this->wp->addItemsAndPruneTaxonomies($this->taxonomies);
    $this->wp->writeTaxonomies($this->tax_path);
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
                if(in_array($type,$this->force_type)){
                    $to_process=TRUE;
                }
                else if(isset($j['properties']['modified'])) {
                    $poi_mod = $j['properties']['modified'];
                    if(strtotime($mod)>strtotime($poi_mod)) {
                        echo " $type need to be updated ($mod VS $poi_mod)";
                        $to_process = TRUE;
                    }
                    else {
                        echo "$type updated ($mod VS $poi_mod). Skipping ";
                        $this->taxonomies[$type][$id]=$j['properties']['taxonomy'];
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
    $j=json_decode($poi->getJson(),TRUE);
    if (isset($j['properties']['taxonomy']))
        $this->taxonomies['poi'][$id]=$j['properties']['taxonomy'];
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
    $j=json_decode($t->getJson(),TRUE);
    if (isset($j['properties']['taxonomy']))
        $this->taxonomies['track'][$id]=$j['properties']['taxonomy'];
    $t->write($this->path);
}

private function processRoute($id) {
    $r = new WebmappRoute($this->wp->getApiRoute($id));
    $r->writeToPostGis();
    $r->addBBox();
    //$r->generateAllImages('',$this->route_path);
    $j=json_decode($r->getJson(),TRUE);
    if (isset($j['properties']['taxonomy']))
        $this->taxonomies['route'][$id]=$j['properties']['taxonomy'];
    $r->write($this->path);

}

private function getListByType($type) {
    return WebmappUtils::getJsonFromAPI($this->wp->getBaseUrl().'/wp-json/webmapp/v1/list?type='.$type);
}

private function processRouteIndex() {
    // Lista delle route:
    $routes = $this->getListByType('route');
    $features = array();
    if(count($routes)>0){
        foreach ($routes as $rid => $date) {
            echo "\n\n\n Processing route $rid\n";
            $feature=array();
            $r=WebmappUtils::getJsonFromAPI($this->path.'/'.$rid.'.geojson');
            $feature['properties']=$r['properties'];
            // CAMBIA QUI il TYPE
            $feature['type']='Feature';
            // Geometry (solo se ha le related track)
            if(isset($r['properties']['related']['track']['related']) && 
                count($r['properties']['related']['track']['related'])>0) {
                $first_track_id = $r['properties']['related']['track']['related'][0];
                $t=WebmappUtils::getJsonFromAPI($this->path.'/'.$first_track_id.'.geojson');
                if(isset($t['geometry']['coordinates'])) {
                    $lon = $t['geometry']['coordinates'][0][0];
                    $lat = $t['geometry']['coordinates'][0][1];
                    $feature['geometry']['type']='Point';
                    $feature['geometry']['coordinates']=array($lon,$lat);
                } else {
                    echo "Warning no GEOMETRY In first track $first_track_id\n";
                }
            } else {
                echo "Warning no RELATED TRACK\n";
            }
            $features[]=$feature;
        }
        $j=array();
        $j['type']='FeatureCollection';
        $j['features']=$features;
        file_put_contents($this->path.'/route_index.geojson',json_encode($j));
    }
}

}
