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
                $props['id']=$data[5];
                $props['name']=$data[5];
                $props['modified']=$this->last_update;
                $props['totale_casi']=$data[9];
                $props['nuovi_casi']=$data[9]-$this->series[$data[5]][count($this->series[$data[5]])-2][1];
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
    }

    private function processItalia() {
        // Elaborate DATA
        $features=array();
        foreach ($this->data as $data) {
            if(date('Y-m-d',strtotime($data[0]))==$this->last_update && $data[5]!='In fase di definizione/aggiornamento') {
                $props=array();
                $props['id']=$data[5];
                $props['name']=$data[5];
                $props['modified']=$this->last_update;
                $props['totale_casi']=$data[9];
                $props['nuovi_casi']=$data[9]-$this->series[$data[5]][count($this->series[$data[5]])-2][1];
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
                    $series[$data[5]][] = array(date('Y-m-d',strtotime($data[0])),(int) $data[9]);
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
