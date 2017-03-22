<?php
class WebmappOSMListTask extends WebmappAbstractTask {

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
		return $this->root.'/'.ltrim($this->options['list'], '/');
	}

    public function process(){

        // TODO: passare ad un sistema di LOG
        echo "Processing task: {$this->getName()}\n\n";

    	$path_osm = $this->root.'/server/osm';
    	if(!file_exists($path_osm)) mkdir ($path_osm);
    	$urls = file($this->getPathList(),FILE_IGNORE_NEW_LINES);
        $first = true;
    	foreach($urls as $url) {
            echo "Processing URL: $url\n";

            // NODE https://www.openstreetmap.org/node/353049774
            // WAY https://www.openstreetmap.org/way/284449666
    		// RELATION https://www.openstreetmap.org/relation/4174475
    		$components = explode('/', $url);
    		$type = $components[3];
    		$id = $components[4];
            $file_osm = $path_osm.'/'.$id.'.'.$type.'.osm';

    		file_put_contents($file_osm, file_get_contents($this->getOverpassUrl($type,$id)));

            // TODO: Generalizzare questa parte inserendo in un file di configurazione
            //       i parametri di connessione per pgsql

            // OSM2SQL - Parte funzionante solo sul server mappalo
            // osm2pgsql -c -d gis -H localhost -U webmapp rifugi.osm --style /var/www/mappalo-server/openstreetmap-carto.style
            $option='--append';
            if ($first) {
                $first = FALSE;
                $option = '-c';
            }

            $cmd = "osm2pgsql $option -d gis -H localhost -U webmapp $file_osm --style /var/www/mappalo-server/openstreetmap-carto.style"; 
            // TODO: passare ad un sistema di LOG
            echo "$cmd \n";
            system($cmd);

  			// TODO: ogr2ogr per la creazione dei file geojson


    	}
    	return TRUE;
    }

    // TODO: creare una classe per la gestione delle query overpass
    private function getOverpassUrl($type,$id) {
$overpass_query = <<<EOF
[out:xml][timeout:1200];
$type($id);
(._;>;);
out meta;
EOF;
    return 'https://overpass-api.de/api/interpreter?data='.urlencode($overpass_query);
    }
}
