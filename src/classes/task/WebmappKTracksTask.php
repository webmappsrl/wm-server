<?php

class WebmappKTracksTask extends WebmappAbstractTask {

 // PARAMETERS
 private $endpoint;
 private $tracks=array();

 // Other members
 private $url;
 private $tracks_layer;
 private $pois_layer;
 private $wp;


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

    $wp = new WebmappWP($this->options['url_or_code']);
    // Controlla esistenza della piattaforma
    if (!$wp->check()) {
        throw new Exception("ERRORE: La piattaforma {$wp->getBaseUrl()} non risponde o non esiste.", 1);
    }
    $this->wp = $wp;


    global $wm_config;
    if(!isset($wm_config['endpoint']['a'])) {
        throw new WebmappExceptionConfEndpoint("No ENDPOINT section in conf.json", 1);  
    }

    $this->endpoint = $wm_config['endpoint']['a'].'/'.preg_replace("(^https?://)", "", $this->url );

    if(!file_exists($this->endpoint)) {
        throw new WebmappExceptionAllRoutesTaskNoEndpoint("Directory {$this->endpoint} does not exists", 1);        
    }

    // TRACKS
    if(!array_key_exists('tracks', $this->options))
        throw new WebmappExceptionConfTask("L'array options deve avere la chiave 'tracks'", 1);
    $this->tracks=$this->options['tracks'];

    if(!is_array($this->tracks)) {
        throw new WebmappExceptionConfTask("Options tracks must be array", 1);        
    }

    if(count($this->tracks)==0) {
        throw new WebmappExceptionConfTask("Array tracks must have one element", 1);        
    }


    return TRUE;
}

public function process(){

    $this->tracks_layer=new WebmappLayer('tracks',$this->getRoot().'/resources');
    $this->pois_layer=new WebmappLayer('pois',$this->getRoot().'/resources');

    $this->processSymLinks();

    $this->processTracks();

    $this->processTaxonomies();

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

private function processTaxonomies() {
    $this->processTaxonomy('webmapp_category');
    $this->processTaxonomy('activity');
    $this->processTaxonomy('who');
    $this->processTaxonomy('where');
    $this->processTaxonomy('when');
    $this->processTaxonomy('theme');
}

private function processTaxonomy($name) {
    $src = $this->endpoint.'/taxonomies/'.$name.'.json';
    $trg = $this->getRoot().'/taxonomies/'.$name.'.json';
    $ja = json_decode(file_get_contents($src),TRUE);
    $ja_new = array();

    // UNSET TERMS
    foreach($ja as $id => $term) {
        if(isset($term['items'])) unset($term['items']);
        $ja_new[$id]=$term;
    }

    // ADD TERM - POI
    if($this->pois_layer->count()>0) {
        foreach($this->pois_layer->getFeatures() as $poi) {
            $p=$poi->getProperties();
            if(isset($p['taxonomy'][$name]) && is_array($p['taxonomy'][$name]) && count($p['taxonomy'][$name])) {
                foreach($p['taxonomy'][$name] as $term_id) {
                    $ja_new[$term_id]['items']['poi'][]=$poi->getId();
                }
            }
        }
    }

    // ADD TERM - TRACK
    if($this->tracks_layer->count()>0) {
        foreach($this->tracks_layer->getFeatures() as $track) {
            $p=$track->getProperties();
            if(isset($p['taxonomy'][$name]) && is_array($p['taxonomy'][$name]) && count($p['taxonomy'][$name])) {
                foreach($p['taxonomy'][$name] as $term_id) {
                    $ja_new[$term_id]['items']['track'][]=$track->getId();
                }
            }
        }
    }

    file_put_contents($trg, json_encode($ja_new));
}

private function processTracks() {

    foreach($this->tracks as $tid) {
        $track=new WebmappTrackFeature($this->wp->getApiTrack($tid));
        $this->tracks_layer->addFeature($track);
        $p=$track->getProperties();
        if(isset($p['related']['poi']['related']) && count($p['related']['poi']['related'])>=1) {
            foreach($p['related']['poi']['related'] as $pid) {
                $poi=new WebmappPoiFeature($this->wp->getApiPoi($pid));
                if(!$this->pois_layer->idExists($pid)) {
                    $this->pois_layer->addFeature($poi);
                }
            }
        }
    }
}

}
