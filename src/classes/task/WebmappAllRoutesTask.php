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
    // 4. Creare i file di tassonomia semplificati per le routes 
    // (solo webmapp_category.json e activity.json) routes/[route_id]/taxonomies/ 
    // che contengono solo gli items specifici della route
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
    if(!file_exists($route_path)) {
        $cmd = "mkdir $route_path"; system($cmd);
    }

}

}
