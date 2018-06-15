<?php

// Legge dal foglio excel condiviso google sheet
// Crea i singoli file geojson/routes/OSMID.geojson (senza geometria)
// INPUT: 
// 1. URL
// 2. OSMID field
// 3. Sezione field

class WebmappOCListTask extends WebmappAbstractTask {
  private $items=array();

  private $layer;

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
        $this->layer=new WebmappLayer('ALL',$this->project_structure->getPathGeoJson());
        foreach($this->items as $num => $item) {
            $row_num=$num+2;
            echo "Processing row $row_num ... ";
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
                   $f->addProperty('osm_quality',$this->getQuality($rel));
                   $f->cleanProperties();
                   $path = $this->project_structure->getPathGeoJson().'/track';
                   if (!file_exists($path)) {
                    $cmd = "mkdir $path";
                    system($cmd);
                   }
                   $this->layer->addFeature($f);
                   $f->write($path);     
                } catch (Exception $e) {
                    echo $e;
                }
            }
            echo "\n"; 
        }

        $tracks = $this->layer->getFeatures();
        if(count($tracks)>0) {
          echo "\n\n CREATING CSV FILE ";
          $csv_path = $this->project_structure->getRoot().'/resources/cai_osm_'.$this->name.'_'.date('Y_m_d').'.csv';
          $fp=fopen($csv_path,'w');
          $header = array('sezione','ref','q_score','osmid','osm_user','osm_url','geojson_url',
                           'q_mandatory_tags','q_mandatory_tags_notes','q_source','q_source_notes');
          fputcsv($fp,$header);
          foreach ($tracks as $t) {
            $j = $t->getArrayJson();
            $v=array();
            $p=$j['properties'];
            isset($p['sezione']) ? $v[] = $p['sezione'] : $v[]='';
            isset($p['osm']) && isset($p['osm']['ref']) ? $v[] = $p['osm']['ref'] : $v[]='';
            isset($p['osm_quality']) ? $v[] = $p['osm_quality']['score'] : $v[]='';
            isset($p['id']) ? $v[] = $p['id'] : $v[]='';
            isset($p['osm']) && isset($p['osm']['user']) ? $v[] = $p['osm']['user'] : $v[]='';
            isset($p['id']) ? $v[] = 'https://openstreetmap.org/relation/'.$p['id'] : $v[]='';
            isset($p['id']) ? $v[] = $this->project_structure->getUrlGeojson().'/track/'.$p['id'].'.geojson' : $v[]='';
            isset($p['osm_quality']) ? $v[] = $p['osm_quality']['mandatory_tags']['val'] : $v[]='';
            isset($p['osm_quality']) ? $v[] = $p['osm_quality']['mandatory_tags']['notes'] : $v[]='';
            isset($p['osm_quality']) ? $v[] = $p['osm_quality']['source']['val'] : $v[]='';
            isset($p['osm_quality']) ? $v[] = $p['osm_quality']['source']['notes'] : $v[]='';
            fputcsv($fp,$v);
            echo ".";
          }
          echo " DONE!\n";
          fclose($fp);
        } else {
            echo "\n\n No tracks found: no CSV file generated \n\n";
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

        $val_tot = 0;

        $p = $rel->getProperties();
        // Mandatory_tags
        $val = 1;
        $notes = '';

        if(!isset($p['type'])) {
            $val = $val - 0.1;
            $notes .= 'Tag type non presente.';
        } else if ($p['type']!='route') {
            $val = $val - 0.1;
            $notes .= 'Tag type diverso da route.';
        }

        if(!isset($p['route'])) {
            $val = $val - 0.2;
            $notes .= 'Tag route non presente. ';
        } else if ($p['route']!='hiking') {
            $val = $val - 0.2;
            $notes .= 'Tag route diverso da hiking.';
        }
        
        if(!isset($p['network'])) {
            $val = $val - 0.2;
            $notes .= 'Tag network non presente. ';
        } else if (!in_array($p['network'], array('lwn','rwn','nwn','iwn'))) {
            $val = $val - 0.2;
            $notes .= 'Tag network non ha uno dei valori consentiti lwn rwn nwn iwn. ';
        }
        if(!isset($p['name'])) {
            if (!isset($p['from']) || !isset($p['to'])) {
               $val = $val - 0.2;
               $notes .= 'Tag name o from / to non presenti. ';
            } 
        }
        if(!isset($p['ref'])) {
            $val = $val - 0.2 ;
            $notes .= 'Tag ref non presente. ';
        }
        if(!isset($p['cai_scale'])) {
            $val = $val - 0.2;
            $notes .= 'Tag cai_scale non presente. ';
        } else if (!in_array($p['cai_scale'], array('T','E','EE','EEA'))) {
            $val = $val - 0.2;
            $notes .= 'Tag cai_scale non ha uno dei valori consentiti T E EE EEA. ';
        }
        $q['mandatory_tags']['val']=$val;
        $q['mandatory_tags']['notes']=$notes;
        $val_tot = $val_tot + $val;

        // SOURCE TAGS
        $val = 1;
        $notes = '';
        if(!isset($p['source'])) {
            $val = $val - 0.5;
            $notes .= 'Tag source non presente. ';
        } else if (preg_match('/survey/',$p['source'])) {
            $val = $val - 0.5;
            $notes .= 'Tag source non è di tipo survey ';
        }
        if(!isset($p['survey:date'])) {
            $val = $val - 0.5;
            $notes .= 'Tag survey:date non presente. ';
        } else {
            $today = date("Y-m-d");
            $survey_date = $p['survey:date'];
            $today_time = strtotime($today);
            $survey_time = strtotime($survey_date);
            $expire_time = $survey_time + 31536000;
            if( $today_time > $expire_time ) {
              $val = $val - 0.5;
              $notes .= 'La survey:date è passata da più di un anno. ';
            }
        }
        $q['source']['val']=$val;
        $q['source']['notes']=$notes;
        $val_tot = $val_tot + $val;

        $q['score'] = $val_tot;

        return $q;
    }

}
