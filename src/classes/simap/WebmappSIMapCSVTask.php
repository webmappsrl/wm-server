<?php

// Task per la realizzazione della mappa interattiva
// del Sentiero Italia (SIMAP)
class WebmappSIMapCSVTask extends WebmappAbstractTask {


	public function check() {
        // NO parameters no check
        return TRUE;
    }

    public function process() {
        // Regioni 
        $r[359] = 'Abruzzo';
        $r[372] = 'Basilicata';
        $r[373] = 'Calabria';
        $r[371] = 'Campania';
        $r[361] = 'Friuli Venezia Giulia';
        $r[369] = 'Lazio';
        $r[365] = 'Liguria';
        $r[364] = 'Lombardia';
        $r[370] = 'Molise';
        $r[360] = 'Piemonte';
        $r[378] = 'Puglia';
        $r[358] = 'Sardegna';
        $r[374] = 'Sicilia';
        $r[366] = 'Toscana e Emilia-Romagna';
        $r[363] = 'Trentino Alto Adige';
        $r[368] = 'Umbria e Marche';
        $r[379] = 'Valle d\'Aosta';
        $r[362] = 'Veneto';

        // Recupero gli ID delle track dal BE
        $tracks=WebmappUtils::getJsonFromAPI('http://simap.be.webmapp.it/wp-json/webmapp/v1/list?type=track');
        // LOOP sulle track: costruisco il csv
        $rows=array();
        $labels = array(
            'Nome',
            'Regione',
            'Da',
            'A',
            'Lunghezza',
            'Dislivello positivo',
            'Dislivello negativo',
            'Quota minima',
            'Quota massima',
            'Quota partenza',
            'Quota arrivo',
            'Descrizione',
            'id',
            'url',
            'url webapp',
            'url gpx',
            'url kml'
        );
        $rows[] = $labels;

        foreach ($tracks as $tid => $date) {
            $url = "https://a.webmapp.it/simap.be.webmapp.it/geojson/$tid.geojson";
            echo "Processing Track $tid ($url)\n";

            $row = array();
            $d = WebmappUtils::getJsonFromAPI($url);
            $p = $d['properties'];

            $row[] = $p['name'];
            $row[] = $r[$p['taxonomy']['where'][0]];
            $row[] = $p['from'];
            $row[] = $p['to'];
            $row[] = $p['distance'];
            $row[] = $p['ascent'];
            $row[] = $p['descent'];
            $row[] = $p['ele:min'];
            $row[] = $p['ele:max'];
            $row[] = $p['ele:from'];
            $row[] = $p['ele:to'];
            $row[] = $p['description'];

            $row[] = $tid;
            $row[] = $url;

            $row[] = "https://mappasentieroitalia.cai.it/#/main/details/$tid";
            $row[] = "https://a.webmapp.it/simap.be.webmapp.it/track/$tid.gpx";
            $row[] = "https://a.webmapp.it/simap.be.webmapp.it/track/$tid.kml";

            $rows[]= $row;

        }

        // Scrittura file
        $out = $this->getRoot().'/resources/simap_tappe.csv';
        $fp = fopen($out, 'w');
        foreach ($rows as $row) {
            fputcsv($fp, $row);
        }
        fclose($fp);
    }


}
