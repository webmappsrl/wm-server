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
                $rel = new WebmappOSMRelation($osmid);
                try {
                   $rel->load();
                   $f->addProperty('sezione',$sezione);
                   $f->addProperty('osm',$rel->getProperties());
                   $f->addProperty('quality',$this->getQuality($rel));
                   $f->write($this->project_structure->getPathGeoJson());     
                } catch (WebmappExceptionNoOSMRelation $e) {
                    echo $e;
                }
            }
            echo "\n"; 
        }
      return TRUE;
    }

    private function getQuality($rel) {
        $q = array();
        $empty = array('val'=>0,'notes'=>'Non verificato');
        $q['mandatory_tags'] = $empty;
        $q['source'] = $empty;
        $q['geometry'] = $empty;
        $q['other_tags'] = $empty;
        $q['rei'] = $empty;

        $p = $rel->getProperties();
        // Mandatory_tags
        $val = 1;
        $notes = '';

        if(!isset($p['type'])) {
            $val = 0;
            $notes .= 'Tag type non presente.';
        } else if ($p['type']!='route') {
            $val = 0;
            $notes .= 'Tag type diverso da route.';
        }

        if(!isset($p['route'])) {
            $val = 0;
            $notes .= 'Tag route non presente. ';
        } else if ($p['route']!='hiking') {
            $val = 0;
            $notes .= 'Tag route diverso da hiking.';
        }
        
        if(!isset($p['network'])) {
            $val = 0;
            $notes .= 'Tag network non presente. ';
        } else if (!in_array($p['network'], array('lwn','rwn','nwn','iwn'))) {
            $val = 0;
            $notes .= 'Tag network non ha uno dei valori consentiti lwn rwn nwn iwn. ';
        }
        if(!isset($p['name'])) {
            $val = 0;
            $notes .= 'Tag name non presente. ';
        }
        if(!isset($p['ref'])) {
            $val = 0;
            $notes .= 'Tag ref non presente. ';
        }
        if(!isset($p['cai_scale'])) {
            $val = 0;
            $notes .= 'Tag cai_scale non presente. ';
        } else if (!in_array($p['cai_scale'], array('T','E','EE','EEA'))) {
            $val = 0;
            $notes .= 'Tag cai_scale non ha uno dei valori consentiti T E EE EEA. ';
        }
        $q['mandatory_tags']['val']=$val;
        $q['mandatory_tags']['notes']=$notes;
        return $q;
    }

}
