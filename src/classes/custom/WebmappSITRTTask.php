<?php

// Task per la realizzazione della mappa interattiva
// del Sentiero Italia (SIMAP)
class WebmappSITRTTask extends WebmappAbstractTask {

    // Task parameter
    private $limit=0;
    private $skip=array();


    private $percorsi = array();
    private $percorsi_tratte = array();
    private $tratte = array();

    private $pg_osm;

	public function check() {
        if(array_key_exists('limit', $this->options)) {
            $this->limit=$this->options['limit'];
        }
        if(array_key_exists('skip', $this->options)) {
            $this->skip=$this->options['skip'];
        }

        return TRUE;
    }

    private function initPercorsi() {
        $item = array();

        $item[]='IDRT';
        $item[]='CODCAI';
        $item[]='CODSETTORE';
        $item[]='CODAREA';
        $item[]='FKPRESIDIO';
        $item[]='DISMESSO';
        $item[]='DATADISMES';
        $item[]='DIFFICOLTA';
        $item[]='ATTENZIONE';
        $item[]='NOTAATTENZ';
        $item[]='LOCPARTENZ';
        $item[]='LOCARRIVO';
        $item[]='TEMPOANDAT';
        $item[]='TEMPORITOR';
        $item[]='DISLIVPOS';
        $item[]='DISLIVNEG';
        $item[]='ANELLO';
        $item[]='URLPAGE';

        $this->percorsi[]=$item;
    }

    public function initPercorsiTratte() {
        $item = array();
        $item[]='SENTIERIPK';
        $item[]='TRATTEPK';
        $item[]='SEQUENZA';

        $this->percorsi_tratte[]=$item;
    }

    public function initTratte() {
        $item = array();
        $item[]='IDRT';
        $item[]='FKFONDO';
        $item[]='FKSEDE';   
        $item[]='NOTADIFFIC';   
        $item[]='DATARILGEO';   
        $item[]='DATARILATT';   

        $this->tratte['label']=$item;
    }

    public function process() {

        $this->pg_osm = WebmappPostGisOSM::Instance();

        // INIT
        $this->initPercorsi();
        $this->initTratte();
        $this->initPercorsiTratte();

        // Loop
        $this->processPercorsi();

        // WRITE
        $this->writeCSV();
        $this->writeSHP();

    }

    private function processPercorsi() {
        $overpassquery = "https://overpass-api.de/api/interpreter?data=%5Bout%3Ajson%5D%5Btimeout%3A85%5D%3B%0Aarea%283600041977%29->.searchArea%3B%0A%28%0A%20relation%5B\"type\"%3D\"route\"%5D%5B\"route\"%3D\"hiking\"%5D%5B\"source\"%3D\"survey%3ACAI\"%5D%28area.searchArea%29%3B%0A%29%3B%0Aout%20ids%3B";
        $data = WebmappUtils::getJsonFromApi($overpassquery);

        $limit = 0;
        if(isset($data['elements']) && is_array($data['elements']) && count($data['elements'])>0) {
            foreach ($data['elements'] as $info) {
                $rid = $info['id'];
                if(!in_array($rid, $this->skip)) {
                    $limit ++;
                    echo "$limit. processing relation $rid\n";
                    $r = new WebmappOSMRelation($rid);
                    $this->mapPercorsi($r);
                    $this->mapTratte($r);
                    if ($this->limit !=0 && $limit==$this->limit) {
                        break;
                    }                    
                } 
                else {
                    echo "Skipping $rid (due to skip option)\n";
                }
            }
        }
        else {
            echo "NO elements in query\n";
        }
    }

    private function writeCSV() {
        $percorsi_fname = $this->getRoot().'/resources/CAISITA_percorsi_'.date('Ymd').'.csv';
        $fp = fopen($percorsi_fname, 'w');
        foreach ($this->percorsi as $fields) {
            fputcsv($fp, $fields);
        }
        fclose($fp);

        $percorsi_tratte_fname = $this->getRoot().'/resources/CAISITA_percorsi_tratte_'.date('Ymd').'.csv';
        $fp = fopen($percorsi_tratte_fname, 'w');
        foreach ($this->percorsi_tratte as $fields) {
            fputcsv($fp, $fields);
        }
        fclose($fp);        

        $tratte_fname = $this->getRoot().'/resources/CAISITA_tratte_'.date('Ymd').'.csv';
        $fp = fopen($tratte_fname, 'w');
        foreach ($this->tratte as $fields) {
            fputcsv($fp, $fields);
        }
        fclose($fp);        
    }

    private function mapPercorsi($r) {
        $item = array();
        $item[]=$r->getProperty('id');
        $item[]=$this->mapTag($r,'ref');
        $item[]='ND';
        $item[]='ND';
        $item[]=$this->mapTag($r,'source:ref');
        $item[]='ND';
        $item[]='ND';
        $item[]=$this->mapTag($r,'cai_scale');
        $item[]='ND';
        $item[]='ND';
        $item[]=$this->mapTag($r,'from');
        $item[]=$this->mapTag($r,'to');
        $item[]=$this->mapTag($r,'duration:forward');
        $item[]=$this->mapTag($r,'duration:backward');
        $item[]=$this->mapTag($r,'ascent');
        $item[]=$this->mapTag($r,'descent');
        $item[]=$this->mapTag($r,'roundtrip');;
        $item[]='ND';

        $this->percorsi[]=$item;
    }

    private function mapTratte($r) {
        $members = $r->getMembers();
        if(is_array($members) && count($members)>0) {
            echo "  > Processing members ";
            $seq = 0;
            foreach ($members as $member) {
                $type = $member['type'];
                $ref = $member['ref'];
                if($type=='way') {
                    // DO mapping
                    WebmappUtils::showStatus($seq+1,count($members));
                    $meta = $this->pg_osm->getWayMeta($ref);

                    // Percorsi_tratte
                    $item = array();
                    //$item[]='SENTIERIPK';
                    $item[]= $r->getProperty('id');
                    //$item[]='TRATTEPK';
                    $item[]= $ref;
                    //$item[]='SEQUENZA';
                    $item[]= $seq;
                    $seq++;

                    $this->percorsi_tratte[]=$item;

                    // Tratte
                    if(!array_key_exists($ref, $this->tratte)) {
                        $item = array();
                        // $item[]='IDRT';
                        $item[] = $ref;
                        // $item[]='FKFONDO';
                        $item[] = $meta['surface'];
                        // $item[]='FKSEDE';   
                        $item[] = $meta['highway'];
                        // $item[]='NOTADIFFIC';   
                        $item[] = 'ND';
                        // $item[]='DATARILGEO';   
                        $item[] = 'ND';
                        // $item[]='DATARILATT';   
                        $item[] = 'ND';
                        $this->tratte[$ref]=$item;                        
                    }
                }
            }
        }
        else {
            echo "  > WARNING: no members in relation";
        }
        echo "\n";
    }

    private function mapTag($r,$tag) {
        if($r->hasTag($tag)) {
            return $r->getTag($tag);
        }
        return 'ND';
    }

    private function writeSHP() {

        global $wm_config;
        if(!isset($wm_config['postgisosm'])) {
            throw new WebmappExceptionConfPostgis("No Postgist section in conf.json", 1);  
        }

        $pgconf = $wm_config['postgisosm'];
        $username = $pgconf['username'];
        $database = $pgconf['database'];
        $host = $pgconf['host'];
        $password= $pgconf['password'];

        $shp = $this->getRoot().'/resources/CAISITA_tratte_'.date('Ymd');
        $ids_all = array_keys($this->tratte);
        $ids = array_shift($ids_all);
        $where = "(" . implode(',', $ids_all ) .")";
        $query = "SELECT osm_id AS IDRT, ST_transform(way, 25832) as geom FROM planet_osm_line WHERE osm_id IN $where";
        $cmd = "pgsql2shp -P $password -f $shp -h $host -u $username $database \"$query\"";
        system($cmd);

    }

}
