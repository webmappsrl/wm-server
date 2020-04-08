<?php

class WebmappCovidRTTask extends WebmappAbstractTask {

    private $province;
    private $rt_csv;
    private $rt_data;
    private $dates_interval=array();
	public function check() {


	    $this->rt_csv=$this->getRoot().'/resources/rtdata.csv';
	    // Check data file
        if(!file_exists($this->rt_csv)) {
            $msg = "No file data in {$this->rt_csv}\n";
            throw new Exception($msg);
        }

		return TRUE;
	}

    public function process()
    {
        $this->readCSV();
        $this->setProvince();
        $this->fillProvince();
        $this->fillDatesInterval();
        $this->generateJson();
        return TRUE;
    }

    private function readCSV() {
        if (($handle = fopen($this->rt_csv, "r")) !== FALSE) {
            while (($data = fgetcsv($handle)) !== FALSE) {
                $this->rt_data[]=$data;
            }
            fclose($handle);
        }
    }

    private function setProvince() {
	    $j = <<<EOF
[
    {
        "stato": "ITA",
        "codice_regione": "09",
        "denominazione_regione": "Toscana",
        "codice_provincia": "048",
        "denominazione_provincia": "Firenze",
        "sigla_provincia": "FI",
        "lat": 43.76923077,
        "long": 11.25588885
    },
    {
        "stato": "ITA",
        "codice_regione": "09",
        "denominazione_regione": "Toscana",
        "codice_provincia": "100",
        "denominazione_provincia": "Prato",
        "sigla_provincia": "PO",
        "lat": 43.88062274,
        "long": 11.09703315
    },
    {
        "stato": "ITA",
        "codice_regione": "09",
        "denominazione_regione": "Toscana",
        "codice_provincia": "047",
        "denominazione_provincia": "Pistoia",
        "sigla_provincia": "PT",
        "lat": 43.933465,
        "long": 10.91734146
    },
    {
        "stato": "ITA",
        "codice_regione": "09",
        "denominazione_regione": "Toscana",
        "codice_provincia": "045",
        "denominazione_provincia": "Massa Carrara",
        "sigla_provincia": "MS",
        "lat": 44.03674425,
        "long": 10.14173829
    },
    {
        "stato": "ITA",
        "codice_regione": "09",
        "denominazione_regione": "Toscana",
        "codice_provincia": "046",
        "denominazione_provincia": "Lucca",
        "sigla_provincia": "LU",
        "lat": 43.84432283,
        "long": 10.50151366
    },
    {
        "stato": "ITA",
        "codice_regione": "09",
        "denominazione_regione": "Toscana",
        "codice_provincia": "050",
        "denominazione_provincia": "Pisa",
        "sigla_provincia": "PI",
        "lat": 43.71553206,
        "long": 10.40127259
    },
    {
        "stato": "ITA",
        "codice_regione": "09",
        "denominazione_regione": "Toscana",
        "codice_provincia": "049",
        "denominazione_provincia": "Livorno",
        "sigla_provincia": "LI",
        "lat": 43.55234873,
        "long": 10.3086781
    },
    {
        "stato": "ITA",
        "codice_regione": "09",
        "denominazione_regione": "Toscana",
        "codice_provincia": "051",
        "denominazione_provincia": "Arezzo",
        "sigla_provincia": "AR",
        "lat": 43.46642752,
        "long": 11.88228844
    },
    {
        "stato": "ITA",
        "codice_regione": "09",
        "denominazione_regione": "Toscana",
        "codice_provincia": "052",
        "denominazione_provincia": "Siena",
        "sigla_provincia": "SI",
        "lat": 43.31816374,
        "long": 11.33190988
    },
    {
        "stato": "ITA",
        "codice_regione": "09",
        "denominazione_regione": "Toscana",
        "codice_provincia": "053",
        "denominazione_provincia": "Grosseto",
        "sigla_provincia": "GR",
        "lat": 42.76026758,
        "long": 11.11356398
    }
]
EOF;
	    $this->province=json_decode($j,TRUE);

    }

    private function fillProvince() {
        $this->fillType('totale_casi');
        $this->fillType('nuovi_positivi');
        $this->fillType('totale_casi_perc_10000');
        $this->fillType('deceduti');
        $this->fillType('totale_deceduti_perc_positivi');
        $this->fillType('deceduti_perc_10000');
    }

    private function fillType($type) {

	    $start_by_type = array(
            'totale_casi' => 2,
	        'nuovi_positivi' => 19,
	        'totale_casi_perc_10000' => 37,
	        'deceduti' => 55,
	        'totale_deceduti_perc_positivi' => 73,
	        'deceduti_perc_10000' => 91,
        );
	    $start = $start_by_type[$type];
	    //  FI / PO / PT / X / MS / LU / PI / LI / X / AR / SI / GR
        $this->fillTypeProvincia(0,$type,$this->rt_data[$start]); //FI
        $this->fillTypeProvincia(1,$type,$this->rt_data[$start+1]); //PO
        $this->fillTypeProvincia(2,$type,$this->rt_data[$start+2]); //PT

        $this->fillTypeProvincia(3,$type,$this->rt_data[$start+4]); // MS
        $this->fillTypeProvincia(4,$type,$this->rt_data[$start+5]); // LU
        $this->fillTypeProvincia(5,$type,$this->rt_data[$start+6]); // PI
        $this->fillTypeProvincia(6,$type,$this->rt_data[$start+7]); // LI

        $this->fillTypeProvincia(7,$type,$this->rt_data[$start+9]); // AR
        $this->fillTypeProvincia(8,$type,$this->rt_data[$start+10]); // SI
        $this->fillTypeProvincia(9,$type,$this->rt_data[$start+11]); // GR


    }

    private function fillTypeProvincia($prov_pos,$type,$data) {
	    foreach ($data as $i => $val) {
	        if ($i>=1) {
	            $ts = strtotime('02/23/2020 +'.$i.'days') + 61200;
	            $date = date('Y-m-d',$ts);
	            // Convert val to float
                $val_new = preg_replace('/\./','',$val);
                $val_new = preg_replace('/,/','.',$val_new);
                $this->province[$prov_pos]['data'][$date][$type]=$val_new;
            }
        }
    }

    private function fillDatesInterval() {
	    $data = $this->province[0]['data'];
	    $this->dates_interval[]='2020-02-24';
	    foreach($data as $date => $vals) {
            if(!empty($vals['totale_casi'])) {
                $this->dates_interval[]=$date;
            }
        }
    }

    private function generateJson() {
	    $j = array();
	    foreach ($this->dates_interval as $date) {
	        foreach($this->province as $provincia) {
	            $item=array();
                $item['data']=$date;
                $item['codice_regione']=$provincia['codice_regione'];
                $item['denominazione_regione']=$provincia['denominazione_regione'];
                $item['codice_provincia']=$provincia['codice_provincia'];
                $item['denominazione_provincia']=$provincia['denominazione_provincia'];
                $item['sigla_provincia']=$provincia['sigla_provincia'];
                $item['lat']=$provincia['lat'];
                $item['long']=$provincia['long'];
                $item['totale_casi']=$provincia['data'][$date]['totale_casi'];
                $item['nuovi_positivi']=$provincia['data'][$date]['nuovi_positivi'];
                $item['totale_casi_perc_10000']=$provincia['data'][$date]['totale_casi_perc_10000'];
                $item['deceduti']=$provincia['data'][$date]['deceduti'];
                $item['totale_deceduti_perc_positivi']=$provincia['data'][$date]['totale_deceduti_perc_positivi'];
                $item['deceduti_perc_10000']=$provincia['data'][$date]['deceduti_perc_10000'];
                $j[]=$item;
            }
        }
	    file_put_contents($this->getRoot().'/geojson/drt-covid19-toscana.json',json_encode($j));
    }
}
