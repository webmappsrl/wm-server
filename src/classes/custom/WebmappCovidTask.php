<?php
class WebmappCovidTask extends WebmappAbstractTask {

    private $data_covid;
    private $last_update=0;
    private $province=array('Arezzo','Firenze','Grosseto','Livorno','Lucca','Massa Carrara','Pisa','Pistoia','Prato','Siena');
    private $data;
    private $series;

    // https://hihayk.github.io/scale/#4/6/50/80/-51/67/20/14/C237FB/194/55/251
    private $colors_old = array('#B7C8FF',
        '#9C9FFF',
        '#9581FF',
        '#9B68FF',
        '#AB4FFE',
        '#C237FB',
        '#D12DDF',
        '#BF24AC',
        '#9F1C74',
        '#801546',
    );
    private $colors = array(
        '#fcf8cd',
        '#fbe5b9',
        '#f9d2a4',
        '#f6be8f',
        '#f3a97b',
        '#f19367',
        '#ee7b55',
        '#eb6244',
        '#e84634',
        '#e52827'
    );

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
        $max = 0;
        $min = 1000000;
        // Elaborate DATA
        $features=array();
        foreach ($this->data as $data) {
            if(date('Y-m-d',strtotime($data[0]))==$this->last_update && in_array($data[5],$this->province)) {
                $props=array();
                $props['id']=$data[4];
                $props['name']=$data[5];
                $props['modified']=$this->last_update;
                $props['totale_casi']=$data[9];
                if ($data[9]<$min) $min = $data[9];
                if ($data[9]>$max) $max = $data[9];
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
        $alfa = -9/($max-$min)*$min;
        $beta = 9/($max-$min);
        echo "\n\nMIN $min MAX $max\n\n";
        $aree = json_decode(file_get_contents('https://a.webmapp.it/covid/geojson/province_toscana.geojson'),TRUE);

        $feature_aree = $aree['features'];
        $new_features = array();
        foreach ($feature_aree as $feature) {
            $props = $feature['properties'];
            $id = $props['id'];

            $new_feature = array();
            $new_feature['type']='Feature';
            $props_new = $feature_props[$id];
            $props_new['color']=$this->colors[floor($alfa+$beta*$props_new['totale_casi'])];
            $new_feature['properties']=$props_new;
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
