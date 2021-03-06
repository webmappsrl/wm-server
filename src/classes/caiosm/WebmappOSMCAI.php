<?php

// Parametri del TASK:
// list: nome del file che contiene la lista delle relation da scaricare per la creazione del geojson

class WebmappOSMCAITask extends WebmappAbstractTask {

    private $geojson;
    private $list;

    private $items = array();
    private $sezioni = array();
    private $sezioni_counter = array();

    // Da usare in versioni future rifattorizzando il tutto con classi OSM / OSM CAI / ecc. ecc.
    private $fields = array(
      // CAMPI PRINCIPALI
     'regione',
     'area',
     'settore',
     'codice_REI',
     'ref',
     'name',
     'cai_scale',
     'operator',
     'distance',
     'ele_gain_positive',
     'ele_gain_negative',
     'ele_start',
     'ele_end',
     'ele_min',
     'ele_max',
     'duration_forward',
     'duration_backward',

     // LINK a servizi OSM e GPX generati
     'gpx',
     'gpx_3d',
     'osm_reltion',
     'wmt_relation',
     'analizer_relation',

     // CAMPI OSM PIU' TECNICI
     'osm_id',
     'type',
     'route',
     'network',
     'roundtrip',
     'source',
     'survey:date',
     'osmc:symbol',

     // CAMPI ANALISI TRACCE
     'tracks',
     'has_multi_segments',
     'trackpoints',
     'has_ele',
     );

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

    private function readList() {
        $sezioni = array();
        $list = array();
        foreach ($this->items as $item) {
           $sezioni[]=trim($item['Sezione']);
           if(!empty($item['OSMID'])) $list[]=$item['OSMID']; 
        }
       $this->sezioni=array_unique($sezioni);
       sort($this->sezioni);
       $this->list = $list ;

       // COunter
       foreach ($this->sezioni as $sezione) {
        $this->sezioni_counter[$sezione]['tot']=0;
        $this->sezioni_counter[$sezione]['osm']=0;
        $this->sezioni_counter[$sezione]['km']=0;
       }
       foreach ($this->items as $item) {
        $this->sezioni_counter[trim($item['Sezione'])]['tot']++;
        if(!empty($item['OSMID'])) {
            $this->sezioni_counter[trim($item['Sezione'])]['osm']++;
        } 
       }

    }

    public function process(){

        $this->readList();
        $this->total = count($this->items);
        $this->total_osm = count($this->list);
        $this->total_sezioni = count ($this->sezioni);

        echo "TOTALE: $this->total \n";
        echo "OSM: $this->total_osm \n";
        echo "SEZIONI: $this->total_sezioni \n";

        $this->processSezioni();
        $this->processGeoJson();
        $this->processGPX();
        $this->processIndex();
        $this->processSHP();

        $out_file = $this->project_structure->getRoot() . '/geojson/' . $this->name . '_relations.json';
        file_put_contents($out_file, json_encode($this->geojson));

    	return TRUE;
    }

    private function processSezioni(){
        require('sezioni.html.php');
        file_put_contents($this->project_structure->getRoot() .'/sezioni.html', $html);

        foreach ($this->sezioni as $sezione) {
            echo "Processing sezione: $sezione \n";
            $this->processSezione($sezione);
        }
    }

    private function processSezione($sezione) {
        require('sezione.html.php');
        $name = WebmappUtils::slugify($sezione) . '.html';
        file_put_contents($this->project_structure->getRoot() . '/resources/' . $name, $html);        
    }

    private function processGeoJson() {
        $root = $this->project_structure->getRoot();
        $ids = $this->list;
        // Overpass
        $overpass_query = '[out:json][timeout:1200];(';
        foreach ($ids as $id) {
            $overpass_query .= 'relation('.$id.');';
        }
        $overpass_query .= '); out tags;';
        //$overpass_url = 'https://overpass-api.de/api/interpreter?data='.urlencode($overpass_query);
        $overpass_url = 'https://overpass-api.de/api/interpreter';
        $data=urlencode($overpass_query);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,$overpass_url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS,"data=$data");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $server_output = curl_exec ($ch);
        curl_close ($ch);
        $results = json_decode($server_output,TRUE);

        //$results = json_decode(file_get_contents($overpass_url),TRUE);

        $geojson = array();
        $geojson['type'] = 'FeatureCollection';
        $features = array();
        foreach($results['elements'] as $item) {
            $id = $item['id'];
            echo "processig id $id ";

             $feature = array();
             $feature['type'] = 'Feature';
             $props = array ();
             $props = $item['tags'];
             $props['id'] = $id;
             $feature['properties'] = $props;
             $features[]=$feature;

             $ref = $props['ref'];

             echo "(ref:$ref) \n\n";
        }

        $geojson['features']=$features;
        $this->geojson=$geojson;

    }

    private function setProps($props,$name) {
        if(array_key_exists($name, $props)) {
            return $props[$name];
        }
        return 'ND';
    }

    private function getTDClass($val) {
        $ret = 'ok';
        if ($val=='ND') $ret = 'red';
        if (empty($val)) $ret = 'red';
        return $ret;
    }

    private function processIndex() {

        $keys = array('type',
                      'route',
                      'network',
                      'name',
                      'cai_scale',
                      'roundtrip',
                      'source',
                      'survey:date',
                      'osmc:symbol',
                      'operator',
                      'tracks',
                      'has_multi_segments',
                      'trackpoints',
                      'has_ele',
                      'distance',
                      'ele_gain_positive',
                      'ele_gain_negative',
                      'ele_start',
                      'ele_end',
                      'ele_min',
                      'ele_max',
                      'duration_forward',
                      'duration_backward'
                      );

        $thead ="<thead><tr><th>REF</th><th>ID OSM</th><th>LINK</th>";
        foreach ($keys as $key ) {
            $thead .= "<th>$key</th>";
        }
        $thead .= "</tr></thead>";

        $features = $this->geojson['features'];
        $rows='';
        foreach($features as $feature) {
            $props = $feature['properties'];

            $ref = $this->setProps($props,'ref');
            $class_ref = $this->getTDClass($ref);

            $id = $this->setProps($props,'id');
            $link = "<br/>
            <a href=\"http://www.openstreetmap.org/relation/$id\" target=\"_blank\">[OSM]</a>
            <a href=\"http://www.openstreetmap.org/edit?relation=$id\" target=\"_blank\">[OSM_EDIT]</a>
            <a href=\"http://ra.osmsurround.org/analyzeRelation?relationId=$id\" target=\"_blank\">[ANALYZER]</a>
            <a href=\"http://hiking.waymarkedtrails.org/#route?id=$id\" target=\"_blank\">[WMT]</a>
            <a href=\"https://hiking.waymarkedtrails.org/api/details/relation/$id/gpx\" target=\"_blank\">[GPX]</a>
            ";

            if ($props['has_ele']==1) {
                $link .= "<a href=\"resources/$ref-$id-3d.gpx\" target=\"_blank\">[GPX_3D]</a>";
            }

            $row="<tr><td class=\"$class_ref\">$ref</td><td>$id</td><td>$link</td>";
            foreach ($keys as $key) {
                $val=$this->setProps($props,$key);
                $class=$this->getTDClass($val);
                $row .= "<td class=\"$class\">$val</td>";
            }
            $row .= "</tr>";

        $rows = $rows . $row;

        }

        $title = 'Catasto - ' . $this->name;
        $zip = '/resources/' . $this->name . '_all_gpx.zip';
        $shp = '/resources/' . $this->name . '_all_shp.zip';

        include('home.html.php');


file_put_contents($this->project_structure->getRoot().'/index.html', $html);
    }

    public function processGPX() {

        $features = $this->geojson['features'];
        $root = $this->project_structure->getRoot();
        $gpx_path = $root.'/resources/gpx';

        if(!file_exists($gpx_path)) {
            system("mkdir $gpx_path");
        }

        $features_new = array();
        foreach($features as $feature) {
            $props = $feature['properties'];
            $ref = $this->setProps($props,'ref');
            $id = $this->setProps($props,'id');
            $WMT_GPX_URL = "https://hiking.waymarkedtrails.org/api/details/relation/$id/gpx";
            $gpx_route_path = $root . "/resources/gpx/$ref-$id.gpx";
            $gpx3d_path = $root . "/resources/gpx/$ref-$id-3d.gpx";
            echo "processing GPX: from $WMT_GPX_URL to $gpx_path ... ";
            file_put_contents($gpx_route_path, fopen($WMT_GPX_URL, 'r'));

            // GPX Analyze
            $info = WebmappUtils::GPXAnalyze($gpx_route_path);
            $track_num=$info['tracks'];
            $multi=$info['has_multi_segments'];
            if($track_num==1 && !$multi) {
                WebmappUtils::GPXAddEle($gpx_route_path,$gpx3d_path);
                $info=WebmappUtils::GPXAnalyze($gpx3d_path);
            }
            foreach ($info as $key => $value) {
                $feature['properties'][$key]=$value;
            }
            $features_new[]=$feature;
            echo "DONE! \n";
        }
        $this->geojson['features']=$features_new;

        // Creazione ZIP
        $zip_name = $this->name ."_all_gpx.zip";
        echo $cmd = "cd $root/resources && zip -r gpx gpx && mv gpx.zip $zip_name";
        system($cmd);


    }

    private function processSHP() {
        $root = $this->project_structure->getRoot();
        $resources_path = $root.'/resources';
        $path = $root.'/resources/shp';
        if(!file_exists($path)) {
            $cmd = "mkdir $path";
            system($cmd);
        }
        $shp = "$path/$this->name";
        // Create SHP DIR
        $new_list = array();
        foreach ($this->list as $item) {
           $new_list[] = "'-".$item."'";
        }
        // pgsql2shp -P T1tup4atmA -f rel_6080932 -h 46.101.124.52 -u webmapp osm_hiking 
        $where = "(" . implode(',', $new_list) .")";
        $query = "SELECT osm_id, type, route, route_name, name, ref, operator, state, cai_scale, ST_transform(way, 25832) as way FROM planet_osm_line WHERE osm_id IN $where";
        $cmd = "pgsql2shp -P T1tup4atmA -f $shp -h 46.101.124.52 -u webmapp osm_hiking \"$query\"";
        system($cmd);
        $zip_name = $this->name ."_all_shp.zip";
        echo $cmd = "cd $resources_path && zip -r shp shp && mv shp.zip $zip_name";
        system($cmd);
    }

}
