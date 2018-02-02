    <?php
    class WebmappRouteTask extends WebmappAbstractTask {

    	// Code
    	private $code;

        // Id
        private $id;

        // Oggetto WebmappRoute
        private $route;

        // Tracks Layer: unico layer con tutte le tracce (non viene considerata la categoria)
        private $tracks_layer;

        // Oggetto WebmappMap
        private $map;

        public function check() {

            // Controllo parametro code http://[code].be.webmapp.it
            if(!array_key_exists('code', $this->options))
                throw new Exception("L'array options deve avere la chiave 'code'", 1);

            // Controllo parametro id (id della route)
            if(!array_key_exists('id', $this->options))
                throw new Exception("L'array options deve avere la chiave 'id' corrispondente all'id della route", 1);

            $this->code = $this->options['code'];
            $this->id = $this->options['id'];
            
            return TRUE;
        }

    	// GETTERS
        public function getCode() { return $this->code; }

        public function getBaseUrl() {
            return 'http://' . $this->code .'.be.webmapp.it';

        }

        public function getApiBaseUrl() {
            return 'http://' . $this->code .'.be.webmapp.it/wp-json/wp/v2';

        }

        public function getUrl() {
            return 'http://' . $this->code .'.be.webmapp.it/wp-json/wp/v2/route/' . $this->id;

        }
        public function getRoute() { return $this->route; }
        public function getTracksLayer() {
            return $this->tracks_layer;
        }
        public function process(){

            // Bounding Box
            $BBfirst = true;

            $poi_layers = array();
            $this->route= new WebmappRoute($this->getUrl());
            $tracks = $this->route->getTracks();
            if (is_array($tracks) && count($tracks)>0) {
                $this->tracks_layer = new WebmappLayer('tracks',$this->project_structure->getPathGeojson());
                // TODO: rivedere la logica del recupero delle lingue
                $this->tracks_layer->setLabel('Itinerari');
                $this->tracks_layer->translateLabel('it','Itinerari');
                $this->tracks_layer->translateLabel('en','Routes');

                // LOOP sulle tracce
                foreach ($tracks as $track) {
                    // Bounding Box
                    if ($BBfirst) {
                        $latMin = $track->getLatMin();
                        $latMax = $track->getLatMax();
                        $lngMin = $track->getLngMin();
                        $lngMax = $track->getLngMax();
                        $BBfirst=false;
                    }
                    else {
                        if($track->getLatMin()<$latMin) $latMin=$track->getLatMin();
                        if($track->getLatMax()>$latMax) $latMax=$track->getLatMax();
                        if($track->getLngMin()<$lngMin) $lngMin=$track->getLngMin();
                        if($track->getLngMax()>$lngMax) $lngMax=$track->getLngMax();
                    }
                    $this->tracks_layer->addFeature($track);

                    // LOOP sui POI delle tracce
                    $pois = $track->getRelatedPois();
                    if (is_array($pois) && count($pois) >0) {
                        foreach ($pois as $poi) {
                            // Bounding BOX
                            if($poi->getLatMin()<$latMin) $latMin=$poi->getLatMin();
                            if($poi->getLatMax()>$latMax) $latMax=$poi->getLatMax();
                            if($poi->getLngMin()<$lngMin) $lngMin=$poi->getLngMin();
                            if($poi->getLngMax()>$lngMax) $lngMax=$poi->getLngMax();
                            // Aggiungi all'array di layer
                            $cat_ids = $poi -> getWebmappCategoryIds();
                            if (is_array($cat_ids) && count($cat_ids) >0 ) {
                                foreach($cat_ids as $cat_id) {
                                    if (!isset($poi_layers[$cat_id])) {
                                        // Creazione layer
                                        $l = new WebmappLayer('pois_'.$cat_id,$this->project_structure->getPathGeojson());
                                        $l->addFeature($poi);
                                        $url = $this->getApiBaseUrl().'/webmapp_category/'.$cat_id;
                                        $l->loadMetaFromUrl($url);
                                        $poi_layers[$cat_id]=$l;
                                    }
                                    else {
                                        $l = $poi_layers[$cat_id];
                                        $l->addFeature($poi);
                                    }
                                }
                            }
                        }
                    }

                }

                $langs = $this->route->getLanguages();

                // Creazione dei file della mappa (config.js config.json index.html)
                $map = new WebmappMap($this->project_structure);
                $map->loadMetaFromUrl($this->getUrl());

                // Bounding Box
                // TODO: gestione del delta 0.045 (costante, modificabile?)
                if(empty($map->getBB())) {
                    $map->setBB($latMin-0.045,$lngMin-0.045,$latMax+0.045,$lngMax+0.045);
                }

                $map->setRouteID($this->id);
                // Scrivi il file geojson di tutte le tracce
                $this->tracks_layer->write();
                if (count($langs)>0) {
                    foreach ($langs as $lang) {
                        $this->tracks_layer->write('',$lang); 
                 }
             }

             $map->addTracksWebmappLayer($this->tracks_layer);
                // Scrivi i file geojson per i pois
             if (count($poi_layers)>0) {
                foreach ($poi_layers as $l) {
                    $l->write();
                    if (count($langs)>0) {
                        foreach ($langs as $lang) {
                         $l->write('',$lang); 
                     }
                 }
                 $map->addPoisWebmappLayer($l);
             }
         }
         $map->setInclude('');
         $map->setTilesType("mbtiles");
         $map->writeConf();
         $map->writeIndex();
         $map->writeInfo();

     }
     return TRUE;
 }

}