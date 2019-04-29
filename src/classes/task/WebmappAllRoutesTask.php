<?php

class WebmappAllRoutesTask extends WebmappAbstractTask {

 private $url;

 private $endpoint;

 public function check() {

    // Controllo parametro code http://[code].be.webmapp.it
    if(!array_key_exists('url_or_code', $this->options))
        throw new WebmappExceptionConfTask("L'array options deve avere la chiave 'url_or_code'", 1);
    $code=$this->options['url_or_code'];
    if(preg_match('|^http://|', $code) || preg_match('|^https://|', $code)) {
        $this->url = $code;
    }
    else {
        $this->url = "http://$code.be.webmapp.it";
    }

    global $wm_config;
    if(!isset($wm_config['endpoint']['a'])) {
        throw new WebmappExceptionConfEndpoint("No ENDPOINT section in conf.json", 1);  
    }

    $this->endpoint = $wm_config['endpoint']['a'].'/'.preg_replace("(^https?://)", "", $this->url );

    if(!file_exists($this->endpoint)) {
        throw new WebmappExceptionAllRoutesTaskNoEndpoint("Directory {$this->endpoint} does not exists", 1);        
    }

    return TRUE;
}

public function process(){

    // 1. Creare i link simbolici alla directory geojson
    $this->processSymLinks();

    // 2. Pulire le tassonomie della parte comune iniziale /taxonomies/* 
    // rimuovendo la sezione items relativa a POI e TRACK
    $this->processMainTaxonomies();

    // 3. Creare le directory routes/[route_id]
         // 4. Creazione del file di tassonomia 
         // /routes/[route_id]/taxonomies/activity.json 
         // deve avere solo la sezione "term_id":"items":"track" 
         // con la lista di tutte le TRACK di quel termine

         // 5. Creazione del file di tassonomia 
         // /routes/[route_id]/taxonomies/webmapp_category.json 
         // deve avere solo la sezione "term_id":"items":"poi" 
         // con la lista di tutti i POI di quel termine

    $this->processRoutes();


    return TRUE;
}

private function processSymLinks() {
    $src = $this->getRoot().'/geojson';
    $trg = $this->endpoint.'/geojson';
    $cmd = "rm -Rf $src"; system($cmd);
    $cmd = "ln -s $trg $src"; system($cmd);

    $tax_dir = $this->getRoot().'/taxonomies';
    if (!file_exists($tax_dir)) {
        $cmd = "mkdir $tax_dir"; system($cmd);
    }

}

private function processMainTaxonomies() {
    // webmapp_category: tolgo items
    $src = $this->endpoint.'/taxonomies/webmapp_category.json';
    $trg = $this->getRoot().'/taxonomies/webmapp_category.json';
    $ja = json_decode(file_get_contents($src),TRUE);
    $ja_new = array();
    foreach($ja as $id => $term) {
        if(isset($term['items'])) unset($term['items']);
        $ja_new[$id]=$term;
    }
    file_put_contents($trg, json_encode($ja_new));

    // activity
    $src = $this->endpoint.'/taxonomies/activity.json';
    $trg = $this->getRoot().'/taxonomies/activity.json';
    $ja = json_decode(file_get_contents($src),TRUE);
    $ja_new = array();
    foreach($ja as $id => $term) {
        if(isset($term['items']['poi'])) unset($term['items']['poi']);
        if(isset($term['items']['track'])) unset($term['items']['track']);
        $ja_new[$id]=$term;
    }
    file_put_contents($trg, json_encode($ja_new));

    // theme
    $src = $this->endpoint.'/taxonomies/theme.json';
    $trg = $this->getRoot().'/taxonomies/theme.json';
    $cmd = "cp -f $src $trg"; system($cmd);

    // when
    $src = $this->endpoint.'/taxonomies/when.json';
    $trg = $this->getRoot().'/taxonomies/when.json';
    $cmd = "cp -f $src $trg"; system($cmd);

    // where
    $src = $this->endpoint.'/taxonomies/where.json';
    $trg = $this->getRoot().'/taxonomies/where.json';
    $cmd = "cp -f $src $trg"; system($cmd);

    // who
    $src = $this->endpoint.'/taxonomies/who.json';
    $trg = $this->getRoot().'/taxonomies/who.json';
    $cmd = "cp -f $src $trg"; system($cmd);

}

private function processRoutes() {
    $route_index = $this->endpoint.'/geojson/route_index.geojson';
    if(!file_exists($this->getRoot().'/routes')) {
        $cmd = 'mkdir '.$this->getRoot().'/routes'; system($cmd);
    }
    if(file_exists($route_index)) {
        $ja=json_decode(file_get_contents($this->endpoint.'/geojson/route_index.geojson'),TRUE);
        if(isset($ja['features'])&&count($ja['features'])>0){
            foreach ($ja['features'] as $route) {
                $this->processRoute($route['properties']['id']);
            }
        }        
    }
}

private function processRoute($id) {
    $route_path=$this->getRoot().'/routes/'.$id;
    $route_tax_path=$this->getRoot().'/routes/'.$id.'/taxonomies';
    if(!file_exists($route_path)) {
        $cmd = "mkdir $route_path"; system($cmd);
    }
    if(!file_exists($route_tax_path)) {
        $cmd = "mkdir $route_tax_path"; system($cmd);
    }

    // LOAD ROUTE FILE
    $ja = json_decode(file_get_contents($this->endpoint.'/geojson/'.$id.'.geojson'),TRUE);

    // LOOP ON RELATED TRACK
    $activities = array();
    $webmapp_categories = array();
    if(isset($ja['features']) && count($ja['features'])>0) {
        foreach($ja['features'] as $track) {
            if (isset($track['properties']['taxonomy']) && 
                isset($track['properties']['taxonomy']['activity']) && 
                count ($track['properties']['taxonomy']['activity'])>0 ) {
                foreach ($track['properties']['taxonomy']['activity'] as $term_id) {
                    $activities[$term_id]['items']['track'][]=$track['properties']['id'];
                }
            }
            if(isset($track['properties']['related']) && 
                isset($track['properties']['related']['poi']) && 
                isset($track['properties']['related']['poi']['related']) && 
                count($track['properties']['related']['poi']['related'])>0) {
                foreach($track['properties']['related']['poi']['related'] as $pid) {
                    $poi = json_decode(file_get_contents($this->endpoint.'/geojson/'.$pid.'.geojson'),TRUE);
                    if(isset($poi['properties']['taxonomy']) && 
                        isset($poi['properties']['taxonomy']['webmapp_category']) &&
                        count($poi['properties']['taxonomy']['webmapp_category'])>0) {
                        foreach($poi['properties']['taxonomy']['webmapp_category'] as $term_id) {
                            $webmapp_categories[$term_id]['items']['poi'][]=$poi['properties']['id'];
                        }
                    }
                }
            }
        }
    }
    if(count($activities)>0) {
        file_put_contents($route_tax_path.'/activity.json',json_encode($activities));
    }
    if(count($webmapp_categories)>0) {
        file_put_contents($route_tax_path.'/webmapp_category.json',json_encode($webmapp_categories));
    }

    // Generazione del file map.json
    // Al momento si deve distinguere il caso presente nelle API e non
    $map = array();
    $jb = WebmappUtils::getJsonFromApi($ja['properties']['source']);
    if(isset($jb['n7webmapp_route_bbox']) && !empty($jb['n7webmapp_route_bbox'])) {
        echo "Building map.json info from WP\n";
        $bb = json_decode($jb['n7webmapp_route_bbox'],TRUE);
        if (is_array($bb)) {
            $map['maxZoom']=$bb['maxZoom'];
            $map['minZoom']=$bb['minZoom'];
            $map['defZoom']=$bb['defZoom'];
            $map['center'][0]=$bb['center']['lng'];
            $map['center'][1]=$bb['center']['lat'];
            $map['bbox'][0]=$bb['bounds']['southWest'][1];
            $map['bbox'][1]=$bb['bounds']['southWest'][0];
            $map['bbox'][2]=$bb['bounds']['northEast'][1];
            $map['bbox'][3]=$bb['bounds']['northEast'][0];        
        } else {
            echo "Building map.json info from route bbox\n";
            $map['maxZoom']=17;
            $map['minZoom']=8;
            $map['defZoom']=9;
            $map['center'][0]=($ja['properties']['bbox'][0]+$ja['properties']['bbox'][2])/2;
            $map['center'][1]=($ja['properties']['bbox'][1]+$ja['properties']['bbox'][3])/2;
            $map['bbox']=$ja['properties']['bbox'];            
        }
    } else {
        echo "Building map.json info from route bbox\n";
        $map['maxZoom']=17;
        $map['minZoom']=8;
        $map['defZoom']=9;
        $map['center'][0]=($ja['properties']['bbox'][0]+$ja['properties']['bbox'][2])/2;
        $map['center'][1]=($ja['properties']['bbox'][1]+$ja['properties']['bbox'][3])/2;
        $map['bbox']=$ja['properties']['bbox'];
    }
    // Writing file
    file_put_contents($route_path.'/map.json',json_encode($map));
}

}
