<?php
class WebmappOSMListTask extends WebmappAbstractTask {

    private $csv;
    private $layer;

    private $counter = 0 ;

	public function check() {

        // Controllo parametro list
        if(!array_key_exists('url', $this->options))
            throw new Exception("L'array options deve avere la chiave 'url'", 1);

        // Controllo parametro list
        if(!array_key_exists('column', $this->options))
            throw new Exception("L'array options deve avere la chiave 'column'", 1);
 
        // Controlla che il file esista
        // COntrolla che la colonna esista
        $this->csv = array_map('str_getcsv', file($this->options['url']));

		return TRUE;
	}

    public function process(){

        $this->layer = new WebmappLayer('rifugi');

        if (count($this->csv)>0){
            foreach ($this->csv as $row_num => $row) {
                $val = $row[$this->options['column']];
                echo "Processing ROW: $row_num, value: $val";
                if ( preg_match('|^https://www.openstreetmap.org|', $val)) {
                    if($this->counter<=1000) {
                       echo ' C='.$this->counter.' ';
                       $this->processVal($val);
                       $this->counter = $this->counter + 1;
                   }
                   else {
                    echo " SKIP (too much) \n";
                   }
                }
                else {
                    echo " SKIP! \n";
                }
            }
        }

        $this->layer->write($this->project_structure->getPathGeojson());

    	return TRUE;
    }

    private function processVal($val) {

        if (preg_match('|www.openstreetmap.org/node|', $val )) {
            $this->processNode($val);
        }
        elseif (preg_match('|www.openstreetmap.org/way|', $val )) {
            $this->processWay($val);
        }
        else {
            echo "WARN - tipo non valido (node,way) SKIP\n";
            return;
        }
        
        echo " OK \n";
        return;
    }

    private function processNode($val) {
        echo " NODE ";
        $id = preg_replace("|https://www.openstreetmap.org/node/|",'', $val);
        $json = $this->getOverpassJson($val,'node');

        if (isset($json['elements'][0]) && isset($json['elements'][0]['tags'])) {
            $a=array();
            $a['title']='';
            $a['content']='';
            $lat = $json['elements'][0]['lat'];
            $lon = $json['elements'][0]['lon'];
            $a['n7webmap_coord']['lat'] = $lat;
            $a['n7webmap_coord']['lng'] = $lon;

            $rifugio = new WebmappPoiFeature($a);
            $rifugio->map($json['elements'][0]['tags']);
            $rifugio->setDescription("COORD: $lat $lon");
            $rifugio->addProperty('osm',$val);

            $this->layer->addFeature($rifugio);
        }
        else {
            echo " ERROR - No elements found (!) ";
        }


    }

    private function processWay($val) {
        echo " WAY ";
        $json = $this->getOverpassJson($val,'way');
        $id = preg_replace("|https://www.openstreetmap.org/way/|",'', $val);
        if (isset($json['elements'][0]) && isset($json['elements'][0]['tags'])) {
            $a = array();
            $a['title']='';
            $a['content']='';

            $lat = $json['elements'][1]['lat'];
            $lon = $json['elements'][1]['lon'];
            $a['n7webmap_coord']['lat'] = $lat;
            $a['n7webmap_coord']['lng'] = $lon;

            $rifugio = new WebmappPoiFeature($a);
            $rifugio->map($json['elements'][0]['tags']);
            $rifugio->setDescription("COORD: $lat $lon");
            $rifugio->addProperty('osm',$val);
            $this->layer->addFeature($rifugio);
        }
        else {
            echo " ERROR - No elements found (!) ";
        }

    }

    private function getOverpassJson($val,$type) {
        $id = preg_replace("|https://www.openstreetmap.org/$type/|",'', $val);
        echo " $id ";
        $overpass_query = "[out:json][timeout:25];($type($id););out body;>;out skel qt;";
        $url = 'https://overpass-api.de/api/interpreter?data='.urlencode($overpass_query);
        return json_decode(file_get_contents($url),TRUE);
    }


}
