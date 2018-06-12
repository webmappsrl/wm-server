<?php

// Legge dal foglio excel condiviso google sheet
// Crea i singoli file geojson/routes/OSMID.geojson (senza geometria)
// INPUT: 
// 1. URL
// 2. OSMID field
// 3. Sezione field

class WebmappOCListTask extends WebmappAbstractTask {
  private $items=array();

  public function check() {

    // Controllo parametro list
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
        foreach($this->items as $num => $item) {
            echo "Processing row $num ... ";
            $sezione = $item['Sezione'];
            $osmid = $item['OSMid'] ;
            if (empty($osmid)) {
                echo "SKIPPING no OSMID $sezione";
            }
            else {
                echo "$osmid $sezione";
                $a = array('id'=>$osmid);
                $f = new WebmappTrackFeature($a);
                $f->addProperty('sezione',$sezione);
                $f->write($this->project_structure->getPathGeoJson());
            }
            echo "\n"; 
        }
      return TRUE;
    }

}
