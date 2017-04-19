    <?php
    class WebmappBETask extends WebmappAbstractTask {

    	// Code
    	private $code;

        // ID della mappa
        private $id;

        // Oggetto WebmappMap
        private $map;

        public function check() {

            // Controllo parametro code http://[code].be.webmapp.it
            if(!array_key_exists('code', $this->options))
                throw new Exception("L'array options deve avere la chiave 'code'", 1);

            // Controllo parametro id (id della mappa)
            if(!array_key_exists('id', $this->options))
                throw new Exception("L'array options deve avere la chiave 'id' corrispondente all'id della mappa", 1);

            $this->code = $this->options['code'];
            $this->id = $this->options['id'];
            
            return TRUE;
        }

    	// GETTERS
        public function getCode() { return $this->code; }
        public function getId() { return $this->id; }
        public function getAPI($type,$api){

        	$baseUrl = 'http://'.$this->code.'.be.webmapp.it/';

        	switch ($type) {
        		case 'wp':
             $url = $baseUrl . 'wp-json/wp/v2/'.$api;
             break;
             case 'wm':
             $url = $baseUrl . 'wp-json/webmapp/v1/' . $api;
             break;		
             default:
             throw new Exception("$type non supportato dal metofo getBEURL: sono validi solo wp e wm", 1);
             break;
         }
         return $url;
     }
     public function getMapAPI() {
        return $this->getAPI('wp','map/'.$this->id);
    }

    public function getLayersAPI() {
        return $this->getAPI('wp','webmapp_category');
    }
        // END of GETTERS

    public function process(){

        	// Scarica le mappe da elaborare
       $map=$this->loadAPI($this->getMapAPI());
       $this->map = new WebmappMap($map,$this->project_structure);

       if(!array_key_exists('id', $map)){
        throw new Exception("Errore nel caricamento della mappa con API ".$this->getMapAPI().". Il parametro ID non è presente nella risposta della API." , 1);
    }

    switch ($map['n7webmap_type']  ) {
        case 'all':
        return $this->processAll($map);
        break;

        case 'single_route':
        return $this->processSingleRoot($map);
        break;

        case 'layers':
        return $this->processLayers($map);
        break;

        default:
        throw new Exception("Map typpe not supported: " . $map['n7webmap_type'], 1);

        break;
    }


    }

    private function processAll($map) {

        $layers=$this->loadAPI($this->getLayersAPI());
        $parents=array();
        foreach ($layers as $layer) {
            $parents[]=$layer['parent'];
        }
        $parents=array_unique($parents);
        $map_pois = array();
        foreach ($layers as $layer) {
            if(!in_array($layer['id'],$parents)){
                // TODO: gestire la chiamata con un numero di elementi dinamico (<=100) e la paginazione
                $pois = $this->loadAPI($this->getAPI('wp','poi?webmapp_category='.$layer['id'].'&per_page=100'));
                if(count($pois)>0){
                    $layer['pois']=$this->convertPoisToGeoJSON($pois);
                    $geojson_path = $this->project_structure->getPathGeojson();
                    $path =  $geojson_path . '/pois_'.$layer['id'].'.geojson';
                    file_put_contents($path, json_encode($layer['pois']));
                    $url = $this->project_structure->getUrlGeojson() . '/pois_'.$layer['id'].'.geojson';
                    $color = '';
                    $icon = '';
                    if(isset($layer['color'])) $color = $layer['color'];
                    if(isset($layer['icon'])) $icon = $layer['icon'];
                    $this->map->addPoisLayer($url,$layer['name'],$color,$icon);  

                                      
                }
            }
        }

        // TODO: spostare la scrittura nella process generale
        $this->map->writeConf();
        $this->map->writeIndex();
        return TRUE;
    }

    private function convertPoisToGeoJSON($pois) {

      $result["type"] ='FeatureCollection';
      $features=array();
      foreach ($pois as $poi) {
        $feature=array();
        //setup_postdata($poi);
        $feature['type']="Feature";
        $feature['properties']['id']=$poi['id'];
        $feature['properties']['name']=$poi['title']['rendered'];
        $feature['properties']['description']=$poi['content']['rendered'];

        // no Details
        $noDetails = $poi['noDetails'];
        if($noDetails === true) $feature['properties']['noDetails']= true;

        // icon
        $icon = $poi['icon'];
        if($icon) $feature['properties']['icon']= $icon;

        // color
        $color = $poi['color'];
        if ($color) $feature['properties']['color']= $color;

        // Address
        $addr = $poi['addr:street'];
        if ($addr) $feature['properties']['address']= $addr;

        // Housenumber
        $housenumber = $poi['addr:housenumber'];

        $lng = $poi['n7webmap_coord']['lng'];
        $lat = $poi['n7webmap_coord']['lat'];

        $feature['geometry']['type']='Point';
        $feature['geometry']['coordinates']=array((float) $lng, (float) $lat);

        $features[]=$feature;
    }

    $result['features']=$features;        
    return $result;
    }

    private function processSingleRoot($map) {
        return FALSE;
    }

    private function processLayers($map) {
        return FALSE;
    }

        // Carica una API del BE e restituisce l'array json corrispondente
    public function loadAPI($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,$url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
        $return = curl_exec($ch);
        $json = json_decode($return,true);
        curl_close ($ch);
        return $json;
    }

    }