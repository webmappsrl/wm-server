<?php

// Parametri del TASK:
// list: nome del file che contiene la lista delle relation da scaricare per la creazione del geojson

class WebmappOSMCAI2Task extends WebmappAbstractTask {

    private $stats;
    private $tot = 0 ;
    private $tot_osm = 0 ;
    private $tot_km = 0 ;
    private $osm_id_list = array();

	public function check() {

		if(!array_key_exists('url', $this->options))
			throw new Exception("L'array options deve avere la chiave 'url'", 1);

		// Controllo CSV
        $url = $this->options['url'];
        $csv = array_map('str_getcsv', file($url));
        array_walk($csv, function(&$a) use ($csv) {
           $a = array_combine($csv[0], $a);
        });
        array_shift($csv);        
        $this->items=$csv;
		return TRUE;
	}


    public function process(){
        foreach ($this->items as $item) {
            $ref = $item['Numero'];
            $sezione = trim(strtolower($item['Sezione']));
            $osmid = $item['OSMID'];
            $from = $item['Da'];
            $to = $item['a'];
            $this->tot++;
            isset($this->stats[$sezione]) && isset($this->stats[$sezione]['tot']) 
              ? $this->stats[$sezione]['tot']++ 
              : $this->stats[$sezione]['tot']=1;

            if(!empty(trim($osmid)) && !in_array($osmid,$this->osm_id_list)) {
                $this->osm_id_list[]=$osmid;
                $this->tot_osm++;
                isset($this->stats[$sezione]) && isset($this->stats[$sezione]['tot_osm']) 
                  ? $this->stats[$sezione]['tot_osm']++ 
                  : $this->stats[$sezione]['tot_osm']=1;
                $gpx_path = $this->getRoot().'/resources/'.$osmid.'.gpx';
                $WMT_GPX_URL = "https://hiking.waymarkedtrails.org/api/details/relation/$osmid/gpx";
                file_put_contents($gpx_path, fopen($WMT_GPX_URL, 'r'));
                $info = WebmappUtils::GPXAnalyze($gpx_path);
                $km = $info['distance'];
                $this->tot_km+=$km;
                isset($this->stats[$sezione]) && isset($this->stats[$sezione]['tot_km']) 
                  ? $this->stats[$sezione]['tot_km']+=$km
                  : $this->stats[$sezione]['tot_km']=$km;
                echo "Processing - Sez:$sezione Ref:$ref Da:$from A:$to OSM:$osmid Km=$km\n";
            }

        }
        echo "\n\n\n TOT:$this->tot TOTOSM:$this->tot_osm TOTKM:$this->tot_km\n\n\n";
        foreach($this->stats as $sezione => $vals) {
            if(!isset($vals['tot_osm'])) $this->stats[$sezione]['tot_osm']=0;
            if(!isset($vals['tot_km'])) $this->stats[$sezione]['tot_km']=0;
        }
        ksort($this->stats);
        $csv_path = $this->getRoot().'/resources/SentieriToscana_'.date('Y_m_d').'.csv';
        $csv_rs = fopen($csv_path, 'w');
        $item = array('Sezione','Tot 2012','Tot OSM','SAL(%)','Km');
        fputcsv($csv_rs,$item);
        foreach ($this->stats as $sezione => $vals) {
            $item = array(
                      ucfirst($sezione),
                      $vals['tot'],
                      $vals['tot_osm'],
                      number_format((float)$vals['tot_osm']/$vals['tot']*100, 2, '.', ''),
                      $vals['tot_km']
                      );
            fputcsv($csv_rs,$item);
        }
        fclose($csv_rs);


    }


}
