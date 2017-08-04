<?php
class WebmappDrupalTask extends WebmappAbstractTask {

    	// Code
   private $code;

        // ID della mappa
   private $id;

        // Oggetto WebmappWP per la gestione delle API
   private $wp;

        // Oggetto WebmappMap
   private $map;


   public function check() {

            // Controllo parametro code http://[code].be.webmapp.it
    if(!array_key_exists('code', $this->options))
        throw new Exception("L'array options deve avere la chiave 'code'", 1);

            // Controllo parametro id (id della mappa)
    if(!array_key_exists('id', $this->options))
        throw new Exception("L'array options deve avere la chiave 'id' corrispondente all'id della mappa", 1);

            // Controlla esistenza della mappa prima di procedere

    $this->code = $this->options['code'];
    $this->id = $this->options['id'];

    $wp = new WebmappWP($this->code);

            // Controlla esistenza della piattaforma
    if (!$wp->check()) {
        throw new Exception("ERRORE: La piattaforma {$wp->getBaseUrl()} non risponde o non esiste.", 1);
    }
            // Controlla esistenza della mappa
    if(!$wp->checkMap($this->id)) {
        throw new Exception("Errore: la mappa {$wp->getApiMap($this->id)} non esiste o non risponde.", 1);
    }
            // Crea la mappa carcando i meta dall'URL
    $this->wp = $wp;
    $this->map=new WebmappMap($this->project_structure);
    $this->map->loadMetaFromUrl($this->wp->getApiMap($this->id));

    return TRUE;
}

    	// GETTERS
public function getCode() { 
    return $this->code; 
}
public function getId() { 
    return $this->id; 
}
public function process(){

    $this->loadPois();
    $this->loadTracks();

    $this->map->buildStandardMenu();
    $this->map->writeConf();
    $this->map->writeIndex();
    $this->map->writeInfo();
    return TRUE;
}

// TODO: prendere gli endpoint dalla piattaforma editoriale? (anche no)
private function loadPois() {
    $url = "http://www.tavarnellevp.it/json/node?parameters[type]=poi";
    $pa = json_decode(file_get_contents($url),TRUE);
    if(count($pa)>0) {
        $layer = new WebmappLayer('pois');
        $layer->setLabel('Luoghi');
        foreach ($pa as $item) {
            $uri = $item['uri'];
            $pi = json_decode(file_get_contents($uri),TRUE);
            // Mapping per renderlo compatibile con una Feature che arriva da WP
            $wm = array();
            $wm['id'] = $pi['nid'];
            $wm['title']['rendered'] = $pi['title'];
            $wm['content']['rendered'] = $pi['body']['und'][0]['value'];
            $wm['n7webmap_coord']['lat'] = $pi['field_posizione']['und'][0]['latitude'];
            $wm['n7webmap_coord']['lng'] = $pi['field_posizione']['und'][0]['longitude'];
            if (isset($pi['field_posizione']['und'][0]['city'])) {
                $wm['address'] = $pi['field_posizione']['und'][0]['street'].', '.
                                 $pi['field_posizione']['und'][0]['city'];
            }

            $poi = new WebmappPoiFeature($wm);
            if(isset($pi['field_immagine_evento']['und'][0]['uri'])) {
                $image = $pi['field_immagine_evento']['und'][0]['uri'];
                $image = preg_replace('|public://|', 'http://www.tavarnellevp.it/files/', $image);
                $poi->setImage($image);
            }
            $layer->addFeature($poi);

        }
        $layer->write($this->project_structure->getPathGeojson());
        $this->map->addPoisWebmappLayer($layer);

    }

}
private function loadTracks() {
    $url = "http://www.tavarnellevp.it/json/node?parameters[type]=itinerari";
    $pa = json_decode(file_get_contents($url),TRUE);
    if(count($pa)>0) {
        $layer = new WebmappLayer('tracks');
        $layer->setLabel('Itinerari');
        foreach ($pa as $item) {
            $uri = $item['uri'];
            $pi = json_decode(file_get_contents($uri),TRUE);
            // Mapping per renderlo compatibile con una Feature che arriva da WP
            $wm = array();
            $wm['id'] = $pi['nid'];
            $wm['title']['rendered'] = $pi['title'];
            $wm['content']['rendered'] = $pi['body']['und'][0]['value'];

            // GEOMETRIA
            $gpx_filename = $pi['field_geometria']['und'][0]['filename'];
            $gpx_uri = "http://www.tavarnellevp.it/files/itinerari/$gpx_filename";
            // GEOMETRIA FAKE
            $wm['n7webmap_geojson'] = "a:2:{s:4:\"type\";s:10:\"LineString\";s:11:\"coordinates\";a:81:{i:0;a:2:{i:0;d:11.21598;i:1;d:43.571620000000003;}i:1;a:2:{i:0;d:11.21611;i:1;d:43.571269999999998;}i:2;a:2:{i:0;d:11.21632;i:1;d:43.570880000000002;}i:3;a:2:{i:0;d:11.216519999999999;i:1;d:43.570610000000002;}i:4;a:2:{i:0;d:11.21677;i:1;d:43.570320000000002;}i:5;a:2:{i:0;d:11.21743;i:1;d:43.569699999999997;}i:6;a:2:{i:0;d:11.21766;i:1;d:43.569090000000003;}i:7;a:2:{i:0;d:11.217879999999999;i:1;d:43.568779999999997;}i:8;a:2:{i:0;d:11.21796;i:1;d:43.568669999999997;}i:9;a:2:{i:0;d:11.21809;i:1;d:43.568350000000002;}i:10;a:2:{i:0;d:11.21815;i:1;d:43.568129999999996;}i:11;a:2:{i:0;d:11.21815;i:1;d:43.567950000000003;}i:12;a:2:{i:0;d:11.218120000000001;i:1;d:43.567790000000002;}i:13;a:2:{i:0;d:11.21889;i:1;d:43.56738;}i:14;a:2:{i:0;d:11.21912;i:1;d:43.567210000000003;}i:15;a:2:{i:0;d:11.21937;i:1;d:43.566859999999998;}i:16;a:2:{i:0;d:11.21954;i:1;d:43.566580000000002;}i:17;a:2:{i:0;d:11.219860000000001;i:1;d:43.566209999999998;}i:18;a:2:{i:0;d:11.220269999999999;i:1;d:43.565820000000002;}i:19;a:2:{i:0;d:11.220470000000001;i:1;d:43.565559999999998;}i:20;a:2:{i:0;d:11.22068;i:1;d:43.565350000000002;}i:21;a:2:{i:0;d:11.220940000000001;i:1;d:43.565159999999999;}i:22;a:2:{i:0;d:11.221629999999999;i:1;d:43.564779999999999;}i:23;a:2:{i:0;d:11.22184;i:1;d:43.564729999999997;}i:24;a:2:{i:0;d:11.222630000000001;i:1;d:43.56476;}i:25;a:2:{i:0;d:11.223364999835001;i:1;d:43.564765002354001;}i:26;a:2:{i:0;d:11.2241;i:1;d:43.564770000000003;}i:27;a:2:{i:0;d:11.22448;i:1;d:43.56474;}i:28;a:2:{i:0;d:11.22476;i:1;d:43.564720000000001;}i:29;a:2:{i:0;d:11.22527;i:1;d:43.564720000000001;}i:30;a:2:{i:0;d:11.22592;i:1;d:43.564639999999997;}i:31;a:2:{i:0;d:11.226459999999999;i:1;d:43.564500000000002;}i:32;a:2:{i:0;d:11.22701;i:1;d:43.564190000000004;}i:33;a:2:{i:0;d:11.227169999999999;i:1;d:43.564059999999998;}i:34;a:2:{i:0;d:11.227679999999999;i:1;d:43.563270000000003;}i:35;a:2:{i:0;d:11.2279;i:1;d:43.562980000000003;}i:36;a:2:{i:0;d:11.228009999999999;i:1;d:43.562690000000003;}i:37;a:2:{i:0;d:11.22805;i:1;d:43.562429999999999;}i:38;a:2:{i:0;d:11.228149999999999;i:1;d:43.562179999999998;}i:39;a:2:{i:0;d:11.22846;i:1;d:43.561839999999997;}i:40;a:2:{i:0;d:11.228925003454;i:1;d:43.561400000886998;}i:41;a:2:{i:0;d:11.22939;i:1;d:43.560960000000001;}i:42;a:2:{i:0;d:11.229609999999999;i:1;d:43.560690000000001;}i:43;a:2:{i:0;d:11.22993;i:1;d:43.560369999999999;}i:44;a:2:{i:0;d:11.230040000000001;i:1;d:43.560169999999999;}i:45;a:2:{i:0;d:11.230040000000001;i:1;d:43.559950000000001;}i:46;a:2:{i:0;d:11.22992;i:1;d:43.559750000000001;}i:47;a:2:{i:0;d:11.22987;i:1;d:43.559609999999999;}i:48;a:2:{i:0;d:11.229979999999999;i:1;d:43.559449999999998;}i:49;a:2:{i:0;d:11.230510000000001;i:1;d:43.55921;}i:50;a:2:{i:0;d:11.230589999999999;i:1;d:43.559130000000003;}i:51;a:2:{i:0;d:11.23068;i:1;d:43.558959999999999;}i:52;a:2:{i:0;d:11.2308;i:1;d:43.558630000000001;}i:53;a:2:{i:0;d:11.23091;i:1;d:43.558520000000001;}i:54;a:2:{i:0;d:11.23138;i:1;d:43.558309999999999;}i:55;a:2:{i:0;d:11.231769999999999;i:1;d:43.558169999999997;}i:56;a:2:{i:0;d:11.232749999999999;i:1;d:43.558019999999999;}i:57;a:2:{i:0;d:11.23359;i:1;d:43.558;}i:58;a:2:{i:0;d:11.234080000000001;i:1;d:43.557969999999997;}i:59;a:2:{i:0;d:11.234310000000001;i:1;d:43.558019999999999;}i:60;a:2:{i:0;d:11.234719999999999;i:1;d:43.557980000000001;}i:61;a:2:{i:0;d:11.234970000000001;i:1;d:43.557969999999997;}i:62;a:2:{i:0;d:11.235429999999999;i:1;d:43.558010000000003;}i:63;a:2:{i:0;d:11.235659999999999;i:1;d:43.558070000000001;}i:64;a:2:{i:0;d:11.23582;i:1;d:43.558059999999998;}i:65;a:2:{i:0;d:11.236079999999999;i:1;d:43.557980000000001;}i:66;a:2:{i:0;d:11.23723;i:1;d:43.557879999999997;}i:67;a:2:{i:0;d:11.23826;i:1;d:43.557740000000003;}i:68;a:2:{i:0;d:11.23861;i:1;d:43.557560000000002;}i:69;a:2:{i:0;d:11.23901;i:1;d:43.557310000000001;}i:70;a:2:{i:0;d:11.239649999999999;i:1;d:43.557000000000002;}i:71;a:2:{i:0;d:11.240080000000001;i:1;d:43.556939999999997;}i:72;a:2:{i:0;d:11.24094;i:1;d:43.557130000000001;}i:73;a:2:{i:0;d:11.241809999999999;i:1;d:43.557490000000001;}i:74;a:2:{i:0;d:11.242290000000001;i:1;d:43.557589999999998;}i:75;a:2:{i:0;d:11.242749999999999;i:1;d:43.55753;}i:76;a:2:{i:0;d:11.243410000000001;i:1;d:43.557369999999999;}i:77;a:2:{i:0;d:11.243919999999999;i:1;d:43.55706;}i:78;a:2:{i:0;d:11.24428;i:1;d:43.556539999999998;}i:79;a:2:{i:0;d:11.24441;i:1;d:43.555840000000003;}i:80;a:2:{i:0;d:11.244389999999999;i:1;d:43.555790000000002;}}}";

            // RELATED POIS
            if (isset($pi['field_punti_correlati']['und'])) {
                $pois = $pi['field_punti_correlati']['und'];
                if (is_array($pois) && count($pois)>0) {
                    $related_pois = array();
                    foreach ($pois as $poi) {
                        $related_pois[]=$poi['target_id'];
                    }
                    $wm['related_pois'] = $related_pois;
                }
            }

            // DETTAGLI FAKE
            $wm['n7webmap_start'] = "Morrocco";
            $wm['n7webmap_end'] = "Tavarnelle";
            $wm['ref'] = "CAI 131";
            $wm['ascent'] = "250 m";
            $wm['distance'] = "2,5 Km";
            $wm['duration:forward'] = "1h 30m";
            $wm['cai_scale'] = "T";

            $track = new WebmappTrackFeature($wm);
            if(isset($pi['field_immagine_evento']['und'][0]['uri'])) {
                $image = $pi['field_immagine_evento']['und'][0]['uri'];
                $image = preg_replace('|public://|', 'http://www.tavarnellevp.it/files/', $image);
                $poi->setImage($image);
            }
            $layer->addFeature($track);

        }

        $layer->write($this->project_structure->getPathGeojson());
        $this->map->addTracksWebmappLayer($layer);

    }

}

}
