<?php

// Parametri del TASK:
// list: nome del file che contiene la lista delle relation da scaricare per la creazione del geojson

class WebmappOSMCAIRelationsTask extends WebmappAbstractTask {

    private $geojson;
    private $list;

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
		if(!array_key_exists('list', $this->options))
			throw new Exception("L'array options deve avere la chiave 'list'", 1);

		// Controllo esistenza file lista
		if(!file_exists($this->getPathList()))
			throw new Exception("Il file ".$this->getPathList()." non esiste.", 1);

		// TODO: controllo dell'esistenza di almeno un elemento (?)
			
		return TRUE;
	}

	public function getPathList() {
        $root = $this->project_structure->getRoot();
		return $root . '/' . ltrim($this->options['list'], '/');
	}

    private function readList() {
       $this->list = file($this->getPathList(),FILE_IGNORE_NEW_LINES);
    }

    public function process(){

        $this->readList();

        $this->processGeoJson();
        $this->processGPX();
        $this->processIndex();
        $this->processSHP();

        $out_file = $this->project_structure->getRoot() . '/geojson/' . $this->name . '_relations.json';
        file_put_contents($out_file, json_encode($this->geojson));

    	return TRUE;
    }

    private function processGeoJson() {
        $root = $this->project_structure->getRoot();
        $ids = file($this->getPathList(),FILE_IGNORE_NEW_LINES);
        // Overpass
        $overpass_query = '[out:json][timeout:1200];(';
        foreach ($ids as $id) {
            $overpass_query .= 'relation('.$id.');';
        }
        $overpass_query .= '); out tags;';
        $overpass_url = 'https://overpass-api.de/api/interpreter?data='.urlencode($overpass_query);
        $results = json_decode(file_get_contents($overpass_url),TRUE);

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
        $zip = '/resources/' . $this->name . '_all_gpx_.zip';
        $shp = '/resources/' . $this->name . '_all_shp.zip';
        $html = <<<EOF
<!DOCTYPE html>
<html>
<head>
<title>$title</title>
<link href="https://cdn.datatables.net/1.10.16/css/jquery.dataTables.min.css" rel="stylesheet" type="text/css">
<style>
table {
    font-family: arial, sans-serif;
    border-collapse: collapse;
    width: 100%;
}

td, th {
    border: 1px solid #dddddd;
    text-align: left;
    padding: 8px;
}

tr:nth-child(even) {
    background-color: #dddddd;
}
td.red {
    background-color: #FF0000;
}
</style>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
<script src="https://cdn.datatables.net/1.10.16/js/jquery.dataTables.min.js"></script>
<script>
$(document).ready(function() {
    $('#catasto').DataTable();
} );
</script>
</head>
<body>

<h1>$title</h1>

<p>
  <a href="$zip">Download all GPX (created by WMT)</a>
  <a href="$shp">Download SHP file</a>
</p>

<table id="catasto" >
$thead
<tbody>
 $rows
</tbody>

</table>

<div id="footer">
<p>
  Questa pagina web &egrave; stata realizzata utilizzando i dati di <a href="http://openstreetmap.org">Openstreetmap</a>.<br />
  I metadati utilizzati sono tag di OSM definiti nella <a href="http://wiki.openstreetmap.org/wiki/CAI">convenzione</a> tra OSM e il Club Alpino Italiano.<br />
  Il software &egrave; stato realizzato da <a href="mailto:alessiopiccioli@webmapp.it">Alessio Piccioli</a> di <a href="http://webmapp.it">Webmapp s.r.l.</a>. 
</p>
</div>

</body>
</html>

EOF;

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
