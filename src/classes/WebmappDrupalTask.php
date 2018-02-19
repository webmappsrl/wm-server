<?php
class WebmappDrupalTask extends WebmappAbstractTask {

   // Code
   private $code;

   // ID della mappa
   private $id;

   // Oggetto WebmappWP per la gestione delle API
   private $wp;

   // Oggetto WebmappMap
   private $map;

   private $poi_layers = array();

   private $track_layers = array();


   public function check() {

            // Controllo parametro code http://[code].be.webmapp.it
    if(!array_key_exists('code', $this->options))
        throw new Exception("L'array options deve avere la chiave 'code'", 1);

            // Controllo parametro id (id della mappa)
    if(!array_key_exists('id', $this->options))
        throw new Exception("L'array options deve avere la chiave 'id' corrispondente all'id della mappa", 1);

            // Controlla esistenza della mappa prima di procedere

    $this->code = $this->options['code'];
    $this->id = $this->options['id'];

    $wp = new WebmappWP($this->code);

            // Controlla esistenza della piattaforma
    if (!$wp->check()) {
        throw new Exception("ERRORE: La piattaforma {$wp->getBaseUrl()} non risponde o non esiste.", 1);
    }
            // Controlla esistenza della mappa
    if(!$wp->checkMap($this->id)) {
        throw new Exception("Errore: la mappa {$wp->getApiMap($this->id)} non esiste o non risponde.", 1);
    }
            // Crea la mappa carcando i meta dall'URL
    $this->wp = $wp;
    $this->map=new WebmappMap($this->project_structure);
    $this->map->setFilterIcon('wm-icon-ios7-settings-strong');
    $this->map->setStartUrl('/page/home');
    $this->map->loadMetaFromUrl($this->wp->getApiMap($this->id));

    return TRUE;
}

    	// GETTERS
public function getCode() { 
    return $this->code; 
}
public function getId() { 
    return $this->id; 
}
public function process(){

    // POI
    $this->loadPois('http://www.tavarnellevp.it');
    $this->loadPois('http://www.sancascianovp.net');
    if (count($this->poi_layers)>0) {
        foreach ($this->poi_layers as $l) {
            $l->write($this->project_structure->getPathGeojson());
            $this->map->addPoisWebmappLayer($l);
        }
    }

    $this->loadTracks('http://www.tavarnellevp.it');
    $this->loadTracks('http://www.sancascianovp.net');
    if (count($this->track_layers)>0) {
        foreach ($this->track_layers as $l) {
            $l->write($this->project_structure->getPathGeojson());
            $this->map->addTracksWebmappLayer($l);
        }
    }

    //$this->map->buildStandardMenu();
    $this->map->resetMenu();
    $this->map->addMenuItem('Home','home','#486C2C','wm-icon-generic');
    $this->map->addMenuItem('Mappa','map','#486C2C','wm-icon-generic');
    if (count($this->poi_layers)>0) {
       $this->map->addMenuLayerGroup($this->poi_layers,'Luoghi','#E79E19','wm-icon-generic');        
    }
    if (count($this->track_layers)>0) {
        $this->map->addMenuLayerGroup($this->track_layers,'Itinerari','#E79E19','wm-icon-generic');
    }
    $this->map->addMenuItem('Offline','page');

    //Manage Pages
    $this->map->resetPages();
    $this->map->addPage('Home','home',false);

    //REPORT
    $this->map->resetReport();
    $this->map->activateReport('alessiopiccioli@webmapp.it');


    $this->map->writeConf();
    $this->map->writeIndex();
    $this->map->writeInfo();
    return TRUE;
}

// TODO: prendere gli endpoint dalla piattaforma editoriale? (anche no)
private function loadPois($base_url) {
    $this->loadPoisPage($base_url,0);
    $this->loadPoisPage($base_url,1);
}

private function loadPoisPage($base_url,$page) {

    $url = "$base_url/json/node?parameters[type]=poi&page=$page";
    echo "\n\n LOADING POIS from $url \n\n";
    $pa = json_decode(file_get_contents($url),TRUE);
    if(count($pa)>0) {
        foreach ($pa as $item) {
            $uri = $item['uri'];
            $pi = json_decode(file_get_contents($uri),TRUE);
            $name = $pi['title'];
            echo "\nPOI $name ($uri) - ";
            if(isset($pi['field_turismo']['und'][0]['value']) && 
                $pi['field_turismo']['und'][0]['value'] == 1 ) {
            //if(true){
                        // Mapping per renderlo compatibile con una Feature che arriva da WP
                $wm = array();
            $wm['id'] = $pi['nid'];
            $wm['title']['rendered'] = $pi['title'];
            if (isset($pi['body']) && isset($pi['body']['und'])) {
                $wm['content']['rendered'] = $pi['body']['und'][0]['value'];
            } else {
             $wm['content']['rendered'] = 'ND'; 
         }
         $wm['n7webmap_coord']['lat'] = $pi['field_posizione']['und'][0]['latitude'];
         $wm['n7webmap_coord']['lng'] = $pi['field_posizione']['und'][0]['longitude'];
         if (isset($pi['field_posizione']['und'][0]['city'])) {
            $wm['address'] = $pi['field_posizione']['und'][0]['street'].', '.
            $pi['field_posizione']['und'][0]['city'];
        }

        

        $poi = new WebmappPoiFeature($wm);
        if(isset($pi['field_immagine_evento']['und'][0]['uri'])) {
            $image = $pi['field_immagine_evento']['und'][0]['uri'];
            $image = preg_replace('|public://|', $base_url.'/files/', $image);
            $poi->setImage($image);
        }


            // GESTIONE DELLA CATEGORIA dei POI:
        $cat_id = $pi['field_categoria']['und'][0]['tid'];
        $l=$this->getPoiLayer($cat_id,$base_url);
        echo " - Aggiungo alla categoria\n";
        $l->addFeature($poi);
    } 
    else {
        echo " - turismo OFF\n";
    }
} 
}
}

private function getPoiLayer($cat_id,$base_url) {
    $uri_cat = "$base_url/json/taxonomy_term/$cat_id";
    $jc = json_decode(file_get_contents($uri_cat),TRUE);

    $cat_uid = $jc['field_codice_categoria_app']['und'][0]['value'];
    if (!isset($this->poi_layers[$cat_uid])) {
                // Crea il layer e aggiungilo all'array
        echo " Creo categoria $cat_uid ($uri_cat) ";
        $l = new WebmappLayer('pois_'.$cat_uid,$this->project_structure->getPathGeojson());
        $l->setLabel($jc['name']);
        $l->setColor($jc['field_colore']['und'][0]['value']);
        $l->setIcon($jc['field_icona_marker']['und'][0]['value']);
        $this->poi_layers[$cat_uid]=$l;
    } 
    else {
        echo " Categoria $cat_uid ";
        $l = $this->poi_layers[$cat_uid];
    }
    return $l;
}

private function getTrackLayer($cat_id,$base_url) {
    $uri_cat = "$base_url/json/taxonomy_term/$cat_id";
    $jc = json_decode(file_get_contents($uri_cat),TRUE);

    $cat_uid = $jc['field_codice_categoria_app']['und'][0]['value'];
    if (!isset($this->track_layers[$cat_uid])) {
                // Crea il layer e aggiungilo all'array
        echo " Creo categoria $cat_uid ($uri_cat) ";
        $l = new WebmappLayer('track_'.$cat_uid,$this->project_structure->getPathGeojson());
        $l->setLabel($jc['name']);
        $l->setColor($jc['field_colore']['und'][0]['value']);
        $l->setIcon($jc['field_icona_marker']['und'][0]['value']);
        $this->track_layers[$cat_uid]=$l;
    } 
    else {
        echo " Categoria $cat_uid ";
        $l = $this->track_layers[$cat_uid];
    }
    return $l;
}

private function loadTracks($base_url) {
    $url = "$base_url/json/node?parameters[type]=itinerari";
    echo "\n\n LOADING TRACKS from $url \n\n";
    $pa = json_decode(file_get_contents($url),TRUE);
    if(count($pa)>0) {
        foreach ($pa as $item) {
            $uri = $item['uri'];
            $pi = json_decode(file_get_contents($uri),TRUE);
            $name = $pi['title'];
            echo "\nTRACK $name ($uri) - ";
        
            if(isset($pi['field_turismo']['und'][0]['value']) && $pi['field_turismo']['und'][0]['value'] == 1 ) {
            $wm = array();
            $wm['id'] = $pi['nid'];
            $wm['title']['rendered'] = $pi['title'];
            $wm['content']['rendered'] = $pi['body']['und'][0]['value'];
            $gpx_filename = $pi['field_geometria']['und'][0]['filename'];
            $gpx_uri = "$base_url/files/itinerari/$gpx_filename";
            $decoder = new gisconverter\GPX();
            $wm['n7webmap_geojson'] = serialize(json_decode($decoder->geomFromText(file_get_contents($gpx_uri))->toGeoJSON(),true));
            if (isset($pi['field_punti_correlati']['und'])) {
                $pois = $pi['field_punti_correlati']['und'];
                if (is_array($pois) && count($pois)>0) {
                    $related_pois = array();
                    foreach ($pois as $poi) {
                        $related_pois[]=$poi['target_id'];
                    }
                    $wm['related_pois'] = $related_pois;
                }
            }
            $wm['n7webmap_start'] = "Morrocco";
            $wm['n7webmap_end'] = "Tavarnelle";
            $wm['ref'] = "CAI 131";
            $wm['ascent'] = "250 m";
            $wm['distance'] = "2,5 Km";
            $wm['duration:forward'] = "1h 30m";
            $wm['cai_scale'] = "T";

            $track = new WebmappTrackFeature($wm);
            if(isset($pi['field_immagine_evento']['und'][0]['uri'])) {
                $image = $pi['field_immagine_evento']['und'][0]['uri'];
                $image = preg_replace('|public://|', $base_url.'/files/', $image);
                $poi->setImage($image);
            }

                // Gestione della categoria
            $cat_id = $pi['field_categoria']['und'][0]['tid'];
            $l=$this->getTrackLayer($cat_id,$base_url);
            echo " - Aggiungo alla categoria\n";
            $l->addFeature($track);
        }
    }


}

}

}
