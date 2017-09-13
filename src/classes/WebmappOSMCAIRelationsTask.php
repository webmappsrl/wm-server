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

    private function processIndex() {
        $features = $this->geojson['features'];
        $rows='';
        foreach($features as $feature) {
            $props = $feature['properties'];

            $ref = $this->setProps($props,'ref');

            $id = $this->setProps($props,'id');
            $osm = "$id <br/>
            <a href=\"http://www.openstreetmap.org/relation/$id\" target=\"_blank\">[O]</a>
            <a href=\"http://ra.osmsurround.org/analyzeRelation?relationId=$id\" target=\"_blank\">[A]</a>
            <a href=\"http://hiking.waymarkedtrails.org/#route?id=$id\" target=\"_blank\">[W]</a>
            <a href=\"https://hiking.waymarkedtrails.org/api/details/relation/$id/gpx\" target=\"_blank\">[G]</a>
            ";

            $type = $this->setProps($props,'type');
            $route = $this->setProps($props,'route');
            $network = $this->setProps($props,'network');
            $name = $this->setProps($props,'name');
            $cai_scale = $this->setProps($props,'cai_scale');
            $roundtrip = $this->setProps($props,'roundtrip');
            $source = $this->setProps($props,'source');
            $survey_date = $this->setProps($props,'survey:date');
            $osmc_symbol = $this->setProps($props,'osmc:symbol');
            $operator = $this->setProps($props,'operator');

$row = <<<EOF
<tr>
    <td>$ref</td>
    <td>$osm</td>
    <td>$type</td>
    <td>$route</td>
    <td>$network</td>
    <td>$name</td>
    <td>$cai_scale</td>
    <td>$roundtrip</td>
    <td>$source</td>
    <td>$survey_date</td>
    <td>$osmc_symbol</td>
    <td>$operator</td>
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
</style>
</head>
<body>

<table>
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

 $rows

</table>

</body>
</html>

EOF;

file_put_contents($this->project_structure->getRoot().'/index.html', $html);
    }
}
