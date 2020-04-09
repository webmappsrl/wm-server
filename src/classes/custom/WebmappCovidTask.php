<?php
class WebmappCovidTask extends WebmappAbstractTask {

    private $data_covid;
    private $last_update=0;
    private $province=array();
    private $toscana=array();
    private $province_toscana=array(
        '045',
        '046',
        '047',
        '048',
        '049',
        '050',
        '051',
        '052',
        '053',
        '100'
    );
    private $data;
    private $series;
    private $min=1000000;
    private $max=0;
    private $min_toscana=1000000;
    private $max_toscana=0;
    private $rt_data=array();

    // https://hihayk.github.io/scale/#4/6/50/80/-51/67/20/14/C237FB/194/55/251
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
        $this->readProvinceIstat();
        $this->readProvinceData();
        $this->clean();
        $this->setMinMax();
        $this->addFillColor();
        $this->processToscana();

        $this->writeGeoJson($this->getRoot().'/geojson/covid_italia_aree.geojson',array_values($this->province));
        $this->writeGeoJson($this->getRoot().'/geojson/covid_toscana_aree.geojson',array_values($this->toscana));

        echo "LAST UPDATE: {$this->last_update}\n";
        echo "MIN: {$this->min} MAX: {$this->max}\n";
        echo "TOSCANA:\n";
        echo "MIN: {$this->min_toscana} MAX: {$this->max_toscana}\n";

        return TRUE;
    }

    private function processToscana() {

        $this->readToscanaData();

        $max=0;
        $min=100000000;
        foreach($this->province as $cod => $f) {
            if(in_array($cod,$this->province_toscana)) {
                $rt_data=$this->rt_data[$f['properties']['codice_provincia']][$f['properties']['modified']];
                $f['properties']['rt_totale_casi']=$rt_data['totale_casi'];
                $f['properties']['rt_nuovi_positivi']=$rt_data['nuovi_positivi'];
                $f['properties']['rt_totale_casi_perc_10000']=$rt_data['totale_casi_perc_10000'];
                $f['properties']['rt_deceduti']=$rt_data['deceduti'];
                $f['properties']['rt_totale_deceduti_perc_positivi']=$rt_data['totale_deceduti_perc_positivi'];
                $f['properties']['rt_deceduti_perc_10000']=$rt_data['deceduti_perc_10000'];
                $this->toscana[$cod]=$f;
                if ($rt_data['totale_casi_perc_10000']<$min) { $min = $rt_data['totale_casi_perc_10000']; }
                if ($rt_data['totale_casi_perc_10000']>$max) { $max = $rt_data['totale_casi_perc_10000']; }
            }
        }
        foreach($this->toscana as $cod => $item) {
            $p=$item['properties'];
            $fillColor=$this->getColor($p['rt_totale_casi_perc_10000'],$min,$max);
            $this->toscana[$cod]['properties']['fillColor']=$fillColor;
        }
    }

// $fillColor=$this->getColor($f['properties']['totale_casi'],$this->min_toscana,$this->max_toscana);
// $f['properties']['fillColor']=$fillColor;

    private function getColor($val,$min,$max) {
        $alfa = -9/($max-$min)*$min;
        $beta = 9/($max-$min);
        $color_val=$alfa+$beta*$val;
        $color=$this->colors[floor($color_val)];
        return $color;
    }

    private function readToscanaData() {
	    $j = json_decode(file_get_contents($this->getRoot().'/geojson/drt-covid19-toscana.json'),TRUE);
	    foreach($j as $data) {
	        if( $data['codice_provincia']!='000') {
	            $this->rt_data[$data['codice_provincia']][$data['data']]=$data;
            }
        }
    }

    private function setMinMax() {
        foreach($this->province as $cod => $f) {
            $d=$f['properties'];
            if($d['totale_casi']>$this->max) {
                $this->max=$d['totale_casi'];
            }
            if($d['totale_casi']<$this->min) {
                $this->min=$d['totale_casi'];
            }
            if (in_array($cod,$this->province_toscana)) {
                if($d['totale_casi']>$this->max_toscana) {
                    $this->max_toscana=$d['totale_casi'];
                }
                if($d['totale_casi']<$this->min_toscana) {
                    $this->min_toscana=$d['totale_casi'];
                }
            }
        }
    }

    private function clean() {
	    $to_be_removed = array();
        foreach($this->province as $cod => $f) {
            if(!isset($f['properties']['totale_casi'])) {
                $to_be_removed[]=$cod;
            }
        }
        if(count($to_be_removed)>0) {
            foreach ($to_be_removed as $cod) {
                unset($this->province[$cod]);
            }
        }

    }

    private function addFillColor() {
        // Build area
        $alfa = -9/($this->max-$this->min)*$this->min;
        $beta = 9/($this->max-$this->min);
        foreach($this->province as $cod => $f) {
            if(isset($f['properties']['totale_casi'])) {
                $this->province[$cod]['properties']['fillColor']=$this->colors[floor($alfa+$beta*$f['properties']['totale_casi'])];
            }
            else {
                echo "Attenzione: no totale casi $cod\n";
                print_r($f['properties']);
            }
        }
    }

    private function writeGeoJson($file,$fs) {
        // geojson
        $j = array();
        $j['type']='FeatureCollection';
        $j['features']=$fs;
        $data = json_encode($j);
        // WRITE CRYPT FILE
        file_put_contents($file,$data);
    }

    private function readProvinceData() {
        /**
         (
        [data] => 2020-03-26T17:00:00
        [stato] => ITA
        [codice_regione] => 5
        [denominazione_regione] => Veneto
        [codice_provincia] => 999
        [denominazione_provincia] => In fase di definizione/aggiornamento
        [sigla_provincia] =>
        [lat] => 0
        [long] => 0
        [totale_casi] => 260
        [note_it] =>
        [note_en] =>
        )
         **/
        $this->series=array();
        $file=$this->data_covid.'/dati-json/dpc-covid19-ita-province.json';
        $j=json_decode(file_get_contents($file),TRUE);
        foreach ($j as $d) {
            if(!empty($d['sigla_provincia'])) {
                $cod = str_pad($d['codice_provincia'],3,'0',STR_PAD_LEFT);
                // CSV structure
                // 1. data (YYYY-mm-dd)
                // 2. totale_casi
                // 3. Incremento nuovi casi
                // 4. Incremento percentuale
                $date = date('Y-m-d',strtotime($d['data']));
                $this->last_update=$date;
                $incremento_totale_casi=0;
                $incremento_totale_casi_perc=0;
                if(isset($this->series[$cod])) {
                    $prev_totale_casi = $this->series[$cod][count($this->series[$cod])-1][1];
                    if ($prev_totale_casi>0){
                        $incremento_totale_casi=$d['totale_casi']-$prev_totale_casi;
                        $incremento_totale_casi_perc=$incremento_totale_casi/$prev_totale_casi;
                    }
                }
                // Update $this->province (will be geojson)
                /**
                id: "047",
                name: "Pistoia",
                modified: "2020-03-29",
                totale_casi: "309",
                nuovi_casi: 11,
                regione: "Toscana",
                description: "Il 2020-03-29 nella provincia di Pistoia sono stati registrati 309 casi.",
                fillColor: "#fbe5b9"
                 */
                $this->province[$cod]['properties']['name']=$d['denominazione_provincia'];
                $this->province[$cod]['properties']['codice_provincia']=$cod;
                $this->province[$cod]['properties']['totale_casi']=$d['totale_casi'];
                $this->province[$cod]['properties']['nuovi_casi']=$incremento_totale_casi;
                $this->province[$cod]['properties']['incremento_totale_casi_perc']=number_format($incremento_totale_casi_perc*100,2);
                $this->province[$cod]['properties']['regione']=$d['denominazione_regione'];
                $this->province[$cod]['properties']['modified']=$date;
                $this->province[$cod]['properties']['description']="Il $date nella provincia di {$d['denominazione_provincia']} sono stati registrati {$d['totale_casi']} casi.";

                $item = array($date,$d['totale_casi'],$incremento_totale_casi,number_format($incremento_totale_casi_perc*100,2));
                $this->series[$cod][]=$item;
            }
        }
        // Write CSV
        foreach($this->series as $provincia => $data) {
            $fname = $this->getRoot().'/resources/'.$provincia.'.csv';
            echo "\n\n$fname\n";
            $fp = fopen($fname  , 'w');
            foreach ($data as $fields) {
                fputcsv($fp, $fields);
            }
            fclose($fp);
        }
    }

    private function readProvinceIstat() {
        $j=json_decode(file_get_contents("https://a.webmapp.it/covid/geojson/covid_italia_aree_pop.geojson"),TRUE);
        $this->province=array();
        foreach ($j['features'] as $f) {
            $id=$f['properties']['id'];
            $f['properties']['popolazione']=$f['properties']['maschi']+$f['properties']['femmine'];
            unset($f['properties']['maschi']);
            unset($f['properties']['femmine']);
            unset($f['properties']['popolazion']);
            $this->province[$id]=$f;
        }
    }
}
