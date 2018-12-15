<?php
class WebmappBETask extends WebmappAbstractTask {

    	// Code
 private $code;

        // ID della mappa
 private $id;

        // Oggetto WebmappWP per la gestione delle API
 private $wp;

        // Oggetto WebmappMap
 private $map;

 private $BBfirst=true;
 private $lngMax;
 private $lngMin;
 private $latMax;
 private $latMin;

 private $add_related = false;
 private $add_ele = false;
 private $add_gpx = false;
 private $neighbors_dist = 1000;
 private $neighbors_limit = 0;

 // Languages
 private $has_languages=false;
 private $languages=array();

 // HTTPS
 private $use_https = false;


 public function check() {

            // Controllo parametro code http://[code].be.webmapp.it
    if(!array_key_exists('code', $this->options))
        throw new Exception("L'array options deve avere la chiave 'code'", 1);

            // Controllo parametro id (id della mappa)
    if(!array_key_exists('id', $this->options))
        throw new Exception("L'array options deve avere la chiave 'id' corrispondente all'id della mappa", 1);

    if(array_key_exists('add_related', $this->options)) {
        $this->add_related = $this->options['add_related'];
    }

    if(array_key_exists('add_ele', $this->options)) {
        $this->add_ele = $this->options['add_ele'];
    }

    if(array_key_exists('add_gpx', $this->options)) {
        $this->add_gpx = $this->options['add_gpx'];
    }

    if(array_key_exists('neighbors_dist', $this->options)) {
        $this->neighbors_dist = $this->options['neighbors_dist'];
    }

    if(array_key_exists('neighbors_limit', $this->options)) {
        $this->neighbors_limit = $this->options['neighbors_limit'];
    }

    if(array_key_exists('https',$this->options)) {
        if($this->options['https']==true) {
            echo "\n\n\nUSING HTTPS!\n\n";
            $this->use_https=true;
        } 
    }
    
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
    $this->map->loadMetaFromUrl($this->wp->getApiMap($this->id));
    if($this->use_https) {
        $this->map->activateHTTPS();
    }

    // Languages
    $langs_str=$this->map->getLanguagesList();
    if(is_string($langs_str) && strlen($langs_str)>0 && preg_match('/,/',$langs_str)) {
        echo "\nsetting languages!\n";
        $this->languages=explode(',', $langs_str);
        $this->has_languages=true;
    }

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

    echo "Starting Process - TYPE:".get_class($this)." - NAME:".$this->name."\n";

    // CLEAR TABLE IF NEEDED
    // TODO: passare a tabella definitiva
    if($this->add_related) {
        echo "Cleaning Postgis\n";
        $cmd = "psql -h 46.101.124.52 -U webmapp webmapptest -c \"DELETE FROM poi_tmp\"";
        system($cmd);
        $cmd = "psql -h 46.101.124.52 -U webmapp webmapptest -c \"DELETE FROM track_tmp\"";
        system($cmd);
    }

    echo "Getting POI Layers\n";
    $poi_layers = $this->wp->getPoiLayers();
    if(is_array($poi_layers) && count($poi_layers)>0) {

        // Primo loop Inserisci nel POSTGIS (da eliminare dopo sync)
        if ($this->add_related) {
            echo "Adding POI to Postgis\n";
        foreach($poi_layers as $layer) {
            if (!$layer->getExclude()) {
             // Aggiungi i singoli POI del layer al DB Postgis
                foreach($layer->getFeatures() as $poi) {
                    $poi->writeToPostGis();
                    $poi->write($this->project_structure->getPathGeojson().'/poi');
                }

        } }

        }
        // Secondo LOOP Trova i vicni e scrivi singolo
        echo "POI: BB,write,neighbors\n";
        foreach($poi_layers as $layer) {
            if (!$layer->getExclude()) {
                if($this->add_related) {
                foreach($layer->getFeatures() as $poi) {
                    $poi->addRelated($this->neighbors_dist,$this->neighbors_limit);
                    $poi->write($this->project_structure->getPathGeojson().'/poi');
                }}
            $this->computeBB($layer);
            $layer->write($this->project_structure->getPathGeojson());
            if($this->has_languages) {
                foreach($this->languages as $lang) {
                    $layer->write($this->project_structure->getPathGeojson(),$lang);
                }
            }
            $this->map->addPoisWebmappLayer($layer);
        }
        }
    }
    echo "GETTING Track Layers\n";
    $track_layers = $this->wp->getTrackLayers();
    if(is_array($track_layers) && count($track_layers)>0) {
        foreach($track_layers as $layer) {
            if(!$layer->getExclude()) {
            $this->computeBB($layer);
            if($this->add_ele) {
                echo "Adding elevation to track.\n\n";
                $layer->addEle();
            }

            $tracks = $layer->getFeatures();
            if($this->add_gpx) {
                // First LOOP create geojson and add to POSTGIS
                foreach($tracks as $track) {
                    $gpx_url = $this->getUrlBase().'/resource/'.$track->getId().'.gpx';
                    $kml_url = $this->getUrlBase().'/resource/'.$track->getId().'.kml';
                    $gpx_a = '<a href='.$gpx_url.'>Download GPX</a>';
                    $kml_a = '<a href='.$kml_url.'>Download KML</a>';
                    $desc = $track->getProperty('description');
                    $track->addProperty('description',$desc."<br/>$gpx_a<br/>$kml_a<br/>");
                }
            }

            $layer->write($this->project_structure->getPathGeojson());
            if($this->has_languages) {
                foreach($this->languages as $lang) {
                    $layer->write($this->project_structure->getPathGeojson(),$lang);
                }
            }
            $this->map->addTracksWebmappLayer($layer);
            // ADD RELATED
            if($this->add_gpx) {
                // First LOOP create geojson and add to POSTGIS
                foreach($tracks as $track) {
                    $track->write($this->project_structure->getPathGeojson().'/track');
                    $track->writeToPostGis();
                }
            }
            $path=$this->getRoot().'/resources/';
            if($this->add_gpx) {
                // First LOOP create geojson and add to POSTGIS
                foreach($tracks as $track) {
                    echo "Writing GPX and KML tracks\n";
                    $track->writeGPX($path);
                    $track->writeKML($path);
                }
            }
            }
        }
    }
    // Second LOOP create geojson and add to POSTGIS
    if($this->add_related && is_array($track_layers) && count($track_layers)>0) {
        echo "TRACK: add related";
        foreach($track_layers as $layer) {
            if(!$layer->getExclude()) {
            $tracks = $layer->getFeatures();
            foreach($tracks as $track) {
                    $track->addRelated($this->neighbors_dist,$this->neighbors_limit);
                    $track->write($this->project_structure->getPathGeojson().'/track');
                }
            }
        }
    }

    // Bounding Box
    // TODO: gestione del delta 0.045 (costante, modificabile?)
    echo "BBOX \n";
    if(empty($this->map->getBB())) {
        $this->map->setBB($this->latMin-0.045,$this->lngMin-0.045,$this->latMax+0.045,$this->lngMax+0.045);
    }
    echo "Last operations\n";
    $this->map->buildStandardMenu();
    $this->map->writeConf();
    $this->map->writeIndex();
    $this->map->writeInfo();
    echo "DONE !!!\n";
    return TRUE;
}

private function computeBB($l) {
    $bb=$l->getBB();
    if(is_array($bb)&&count($bb>0)){
        $lngMin=$bb['bounds']['southWest'][1];
        $lngMax=$bb['bounds']['northEast'][1];
        $latMin=$bb['bounds']['southWest'][0];
        $latMax=$bb['bounds']['northEast'][0];
        if ($this->BBfirst) {
            $this->latMin = $latMin;
            $this->latMax = $latMax;
            $this->lngMin = $lngMin;
            $this->lngMax = $lngMax;
            $this->BBfirst=false;
        }
        else {
            if($latMin<$this->latMin) $this->latMin=$latMin;
            if($latMax>$this->latMax) $this->latMax=$latMax;
            if($lngMin<$this->lngMin) $this->lngMin=$lngMin;
            if($lngMax>$this->lngMax) $this->lngMax=$lngMax;
        }
    }
}

}
