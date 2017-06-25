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
        public function getUrl() {
            return 'http://' . $this->code .'.be.webmapp.it/wp-json/wp/v2/route/' . $this->id;

        }
        public function getRoute() { return $this->route; }
        public function getTracksLayer() {
            return $this->tracks_layer;
        }
        public function process(){
            $this->route= new WebmappRoute($this->getUrl());
            $tracks = $this->route->getTracks();
            if (is_array($tracks) && count($tracks)>0) {
                $this->tracks_layer = new WebmappLayer('tracks',$this->project_structure->getRoot());
                // LOOP sulle tracce
                foreach ($tracks as $track) {
                    $this->tracks_layer->addFeature($track);
                }
            }
            return TRUE;
        }

    }