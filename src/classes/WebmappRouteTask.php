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
            $poi_layers = array();
            $this->route= new WebmappRoute($this->getUrl());
            $tracks = $this->route->getTracks();
            if (is_array($tracks) && count($tracks)>0) {
                $this->tracks_layer = new WebmappLayer('tracks',$this->project_structure->getPathGeojson());
                // LOOP sulle tracce
                foreach ($tracks as $track) {
                    $this->tracks_layer->addFeature($track);

                    // LOOP sui POI delle tracce
                    $pois = $track->getRelatedPois();
                    if (is_array($pois) && count($pois) >0) {
                        foreach ($pois as $poi) {
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
                // Scrivi il file geojson di tutte le tracce
                $this->tracks_layer->write();
                // Scrivi i file geojson per i pois
                if (count($poi_layers)>0) {
                    foreach ($poi_layers as $l) {
                        $l->write();
                    }
                }
                // Creazione dei file della mappa (config.js config.json index.html)

            }
            return TRUE;
        }

    }