<?php
class WebmappCovidTask extends WebmappAbstractTask {

    private $data_covid;
    private $last_update=0;
    private $province=array('Arezzo','Firenze','Grosseto','Livorno','Lucca','Massa Carrara','Pisa','Pistoia','Prato','Siena');
    private $data;
    private $series;

	public function check() {

	    $this->data_covid=$this->getRoot().'/COVID-19';
	    // Check data file
        if(!file_exists($this->data_covid)) {
            $msg = "No data in {$this->data_covid} download with git clone https://github.com/pcm-dpc/COVID-19.git";
            throw new Exception($msg);
        }

		return TRUE;
	}

    public function process(){
	    // READ data
        $this->data = $this->readProvinceData($this->data_covid.'/dati-province/dpc-covid19-ita-province.csv');
        echo "LAST UPDATE: {$this->last_update}\n";

        $this->processSeries();
        $this->processToscana();
        $this->processItalia();

    	return TRUE;
    }

    /**
     * (
    [0] => 2020-03-17 17:00:00
    [1] => ITA
    [2] => 05
    [3] => Veneto
    [4] => 024
    [5] => Vicenza
    [6] => VI
    [7] => 45.547497
    [8] => 11.54597109
    [9] => 325
    )
     */
    private function processToscana() {
        // Elaborate DATA
        $features=array();
        foreach ($this->data as $data) {
            if(date('Y-m-d',strtotime($data[0]))==$this->last_update && in_array($data[5],$this->province)) {
                $props=array();
                $props['id']=$data[4];
                $props['name']=$data[5];
                $props['modified']=$this->last_update;
                $props['totale_casi']=$data[9];
                $props['nuovi_casi']=$data[9]-$this->series[$data[4]][count($this->series[$data[4]])-2][1];
                $props['regione']=$data[3];
                $description = "Il {$this->last_update} nella provincia di {$data[5]} sono stati registrati {$data[9]} casi.";
                $props['description']=$description;

                $geom=array();
                $geom['type']='Point';
                $geom['coordinates']=array(floatval($data[8]),floatval($data[7]));

                $feature=array();
                $feature['type']='Feature';
                $feature['properties']=$props;
                $feature['geometry']=$geom;

                $features[]=$feature;
            }
        }
        // WRITE output
        $j = array();
        $j['type']='FeatureCollection';
        $j['features']=$features;
        file_put_contents($this->getRoot().'/geojson/covid_toscana.geojson',json_encode($j));

        // Build AREA File
        // Build props array
        $feature_props = array();
        foreach($features as $feature) {
            $props = $feature['properties'];
            $id = $props['id'];
            $feature_props[$id]=$props;
        }
        // Build area
        $aree = json_decode(file_get_contents('https://a.webmapp.it/covid/geojson/province_toscana.geojson'),TRUE);

        $feature_aree = $aree['features'];
        $new_features = array();
        foreach ($feature_aree as $feature) {
            $props = $feature['properties'];
            $id = $props['id'];

            $new_feature = array();
            $new_feature['type']='Feature';
            $new_feature['properties']=$feature_props[$id];
            $new_feature['geometry']=$feature['geometry'];
            $new_features[]=$new_feature;
        }

        // WRITE output
        $j = array();
        $j['type']='FeatureCollection';
        $j['features']=$new_features;
        file_put_contents($this->getRoot().'/geojson/covid_toscana_aree.geojson',json_encode($j));

    }

    private function processItalia() {
        // Elaborate DATA
        $features=array();
        foreach ($this->data as $data) {
            if(date('Y-m-d',strtotime($data[0]))==$this->last_update && $data[5]!='In fase di definizione/aggiornamento') {
                $props=array();
                $props['id']=$data[4];
                $props['name']=$data[5];
                $props['modified']=$this->last_update;
                $props['totale_casi']=$data[9];
                $props['nuovi_casi']=$data[9]-$this->series[$data[4]][count($this->series[$data[4]])-2][1];
                $props['regione']=$data[3];
                $description = "Il {$this->last_update} nella provincia di {$data[5]} sono stati registrati {$data[9]} casi.";
                $props['description']=$description;

                $geom=array();
                $geom['type']='Point';
                $geom['coordinates']=array(floatval($data[8]),floatval($data[7]));

                $feature=array();
                $feature['type']='Feature';
                $feature['properties']=$props;
                $feature['geometry']=$geom;

                $features[]=$feature;
            }
        }
        // WRITE output
        $j = array();
        $j['type']='FeatureCollection';
        $j['features']=$features;
        file_put_contents($this->getRoot().'/geojson/covid_italia.geojson',json_encode($j));
    }

    private function processSeries() {
        // SERIES
        $series = array();
        foreach ($this->data as $data) {
                echo "\n\n\n DATA[5]: {$data[5]} ({$data[3]}{$data[2]})\n\n\n";
                if ($data[5]!='In fase di definizione/aggiornamento') {
                    $new = 0;
                    if(isset($series[$data[4]])) {
                        $new = (int) $data[9] - $series[$data[4]][count($series[$data[4]])-1][1];
                    }
                    $series[$data[4]][] = array(date('Y-m-d',strtotime($data[0])),(int) $data[9],$new);
                }
        }
        // WRITE
        foreach($series as $provincia => $data) {
            $fname = $this->getRoot().'/resources/'.$provincia.'.csv';
            echo "\n\n$fname\n";
            $fp = fopen($fname  , 'w');
            foreach ($data as $fields) {
                fputcsv($fp, $fields);
            }
            fclose($fp);
        }
        $this->series=$series;
    }

    private function readProvinceData($fname) {
	    $ret=array();
        if (($handle = fopen($fname, "r")) !== FALSE) {
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $t = strtotime($data[0]);
                if($t>$this->last_update) $this->last_update=$t;
                $ret[]=$data;
            }
            fclose($handle);
        }
        $this->last_update=date('Y-m-d',$this->last_update);
        return $ret;
    }
}
