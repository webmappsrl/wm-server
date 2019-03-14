<?php
class WebmappFSTTask extends WebmappAbstractTask {

            // ”aata” → Agriturismi
            // ”alba” → Alberghi
            // ”afra” → Affittacamere
            // ”cava” → Case per vacanze ”alla” → Alloggi privati
            // ”stba” → Stabilimenti balneari ”rtaa” → Residenze turistico Alb. ”cama” → Campeggi
            // ”resa” → Residenze
            // ”cafa” → Case per ferie
            // ”repa” → Residenze d’epoca ”osta” → Ostelli
            // ”rala” → Rifugi
            // ”vita” → Villaggi turistici
            // ”asta” → Aree di sosta

    private $all_activity_types = array(
            'aata' => array('name'=>'Agriturismi','color'=>'#285D7A','icon'=>'wm-icon-farm'),
            'alba' => array('name'=>'Alberghi','color'=>'#285D7A','icon'=>'wm-icon-lodging-15')
        );
    private $activities = array();
    private $towns = array();

	public function check() {
        // Parametri del task
        // 1. towns Lista dei comuni (array di stringhe)
        // 2. activity_types Lista delle attività (array di stringhe dei codici)

        if(!array_key_exists('activity_types', $this->options)) {
            throw new WebmappExceptionParameterMandatory ("Parameter activity_types is mandatory", 1);             
        } 
        $this->activity_types=$this->options['activity_types'];
        if(!is_array($this->activity_types)) {
            throw new WebmappExceptionParameterMandatory("Parameter activity_types must be an array", 1);
            
        }
        if(count($this->activity_types)==0) {
            throw new WebmappExceptionParameterMandatory("Parameter activity_types can't be empty", 1);           
        }

        foreach ($this->activity_types as $code) {
            if (! array_key_exists($code,$this->all_activity_types)) {
                throw new WebmappExceptionParameterMandatory("Code $code in activity types not valid", 1);
                
            }
        }

        if(!array_key_exists('towns', $this->options)) {
            throw new WebmappExceptionParameterMandatory ("Parameter towns is mandatory", 1);             
        } 
        $this->towns=$this->options['towns'];
        if(!is_array($this->towns)) {
            throw new WebmappExceptionParameterMandatory("Parameter towns must be an array", 1);
            
        }
        if(count($this->towns)==0) {
            throw new WebmappExceptionParameterMandatory("Parameter towns can't be empty", 1);           
        }

		return TRUE;
	}

    public function process(){
        foreach($this->activity_types as $code) {
            $name = $this->all_activity_types[$code]['name'];
            $color = $this->all_activity_types[$code]['color'];
            $icon = $this->all_activity_types[$code]['icon'];
            $layer = new WebmappLayer($code,$this->getRoot().'/geojson');
            echo "\n\nProcessing activity $code ($name - $color - $icon)\n";
            foreach($this->towns as $town) {
                $fst_url = "https://alloggi.visittuscany.com/html/xml/vtcard.php?op=ricerca&comune=$town&rows=10000&tipologia=$code";
                echo "Adding town $town to layer data source: $fst_url\n";
                $this->addPois($fst_url,$layer,$color,$icon);
            }
            $layer->write();
            echo "\n";
        }
        return TRUE;
    }

    private function addPois($url,$l,$color,$icon) {
        // Download data
        $data = WebmappUtils::getJsonFromApi($url);
        // Map Data
        if(isset($data['grouped']['idhotel_i']['groups']) && 
            is_array($data['grouped']['idhotel_i']['groups']) &&
            count($data['grouped']['idhotel_i']['groups']) >0 
            ) {
            echo "Mapping ... \n";
            foreach ($data['grouped']['idhotel_i']['groups'] as $group) {
                foreach ($group['doclist']['docs'] as $ja ) {

                    // CHECK GEOMETRY store: "44.00525294066539,10.27331095501711",
                    $geom_valid = false;
                    echo "Mapping POI {$ja['nomehotel_n']}\n";
                    if(isset($ja['store'])) {
                        $coord = explode(',',$ja['store']);
                        if(is_array($coord) && count($coord)==2) {
                            $lat=$coord[0];
                            $lon=$coord[1];
                            $geom_valid=true;
                        }
                    }

                    if($geom_valid) {
                    // GENERAL

                    $j['id']='FST_'.$ja['idhotel_i'];
                    $j['title']['rendered']=$ja['nomehotel_n'];
                    $j['content']['rendered']=$ja['descrizionehotelit_t'];
                    $j['color']=$color;
                    $j['icon']=$icon;

                    // SPECIFIC POI
                    // $j['addr:street']=$ja[''];
                    // $j['addr:housenumber']=$ja[''];
                    // $j['addr:postcode']=$ja[''];
                    // $j['addr:city']=$ja[''];
                    $j['contact:phone']=$ja['tel_s'];
                    $j['contact:email']=$ja['mail_s'];
                    // $j['opening_hours']=$ja[''];
                    // $j['capacity']=$ja[''];
                    $j['address']=$ja['indirizzo_n'];

                    // GEOMETRY store: "44.00525294066539,10.27331095501711",

                    $j['n7webmap_coord']['lng']=$lon;
                    $j['n7webmap_coord']['lat']=$lat;

                    // Creazione del POI
                    $poi = new WebmappPoiFeature($j);


                    $poi->addProperty('web','http://magazine-turismo.it/demo/inversilia.com/test/?i='.$ja['idhotel_i'].'|');

                    // Gestione immagini
                    // IMAGE https://alloggi.visittuscany.com/html/xml/vtcard_resize.php?width=1080&height=608&src=26426agriturismo_esterno.jpg
                    // CAMPO images_ss
                    if(isset($ja['images_ss']) && 
                        is_array($ja['images_ss']) &&
                        count($ja['images_ss'])>0 ) {
                        $img_base = 'https://alloggi.visittuscany.com/html/xml/vtcard_resize.php?width=1080&height=608&src=';
                        $poi->addProperty('image',$img_base.$ja['images_ss'][0]);
                        foreach($ja['images_ss'] as $img) {
                            $poi->addImageToGallery($img_base.$img);
                        }
                    }

                    $l->addFeature($poi);
                }
                else {
                    echo "POI NOT VALID (no geometry)\n";
                }

                }
            }
        }
        else {
            echo "No data ... \n";
        }
        // Add to layer
    }
}








