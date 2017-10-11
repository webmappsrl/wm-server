<?php

// Parametri del TASK:
// list: nome del file che contiene la lista delle relation da scaricare per la creazione del geojson

class WebmappOSMCAIRelationsTask extends WebmappAbstractTask {

    private $geojson;

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

    public function process(){

        $this->processGeoJson();
        $this->processIndex();

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
        $out_file = $this->project_structure->getRoot() . '/geojson/' . $this->name . '_relations.json';
        file_put_contents($out_file, json_encode($geojson));

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
        $features = $this->geojson['features'];
        $rows='';
        foreach($features as $feature) {
            $props = $feature['properties'];

            $ref = $this->setProps($props,'ref');
            $class_ref = $this->getTDClass($ref);

            $id = $this->setProps($props,'id');
            $osm = "$id <br/>
            <a href=\"http://www.openstreetmap.org/relation/$id\" target=\"_blank\">[O]</a>
            <a href=\"http://ra.osmsurround.org/analyzeRelation?relationId=$id\" target=\"_blank\">[A]</a>
            <a href=\"http://hiking.waymarkedtrails.org/#route?id=$id\" target=\"_blank\">[W]</a>
            <a href=\"https://hiking.waymarkedtrails.org/api/details/relation/$id/gpx\" target=\"_blank\">[G]</a>
            ";

            $type = $this->setProps($props,'type');
            $class_type = $this->getTDClass($type);

            $route = $this->setProps($props,'route');
            $class_route = $this->getTDClass($route);

            $network = $this->setProps($props,'network');
            $class_network = $this->getTDClass($network);

            $name = $this->setProps($props,'name');
            $class_name = $this->getTDClass($name);

            $cai_scale = $this->setProps($props,'cai_scale');
            $class_cai_scale = $this->getTDClass($cai_scale);

            $roundtrip = $this->setProps($props,'roundtrip');
            $class_roundtrip = $this->getTDClass($roundtrip);

            $source = $this->setProps($props,'source');
            $class_source = $this->getTDClass($source);

            $survey_date = $this->setProps($props,'survey:date');
            $class_survey_date = $this->getTDClass($survey_date);

            $osmc_symbol = $this->setProps($props,'osmc:symbol');
            $class_osmc_symbol = $this->getTDClass($osmc_symbol);

            $operator = $this->setProps($props,'operator');
            $class_operator = $this->getTDClass($operator);

$row = <<<EOF
<tr>
    <td class="$class_ref">$ref</td>
    <td>$osm</td>
    <td class="$class_type">$type</td>
    <td class="$class_route">$route</td>
    <td class="$class_network">$network</td>
    <td class="$class_name">$name</td>
    <td class="$class_cai_scale">$cai_scale</td>
    <td class="$class_roundtrip">$roundtrip</td>
    <td class="$class_source">$source</td>
    <td class="$class_survey_date">$survey_date</td>
    <td class="$class_osmc_symbol">$osmc_symbol</td>
    <td class="$class_operator">$operator</td>
  </tr>
EOF;
        $rows = $rows . $row;

        }

        $title = 'Catasto - ' . $this->name;

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

<table id="catasto" >
<thead>
  <tr>
    <th>REF</th>
    <th>OSM</th>
    <th>type</th>
    <th>route</th>
    <th>network</th>
    <th>name</th>
    <th>cai_scale</th>
    <th>roundtrip</th>
    <th>source</th>
    <th>survey:date</th>
    <th>osmc:symbol</th>
    <th>operator</th>
  </tr>
</thead>

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
}
