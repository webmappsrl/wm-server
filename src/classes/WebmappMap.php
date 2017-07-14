<?php

// Gestione di una mappa (creazione file index, file di configurazione)

class WebmappMap {

	private $map;
    private $structure;
    private $type;
    private $title;
    private $bb;
    private $tilesUrl;
    private $pois_layers = array();
    private $tracks_layers = array();
    private $style = array();

    // Gestione delle pagine
    private $pages = array();

    // Gestione della sezione OFFLINE
    private $has_offline = false;
    private $offline = array();

    // Configurazione del menu
    // TODO: generalizzare la lettura dei dati da interfaccia con un pportuno setters
    // da impostare al momento della lettura dei meta della mappa loadMetaFromUrl
    private $menu = array();
    private $menu_map_label = 'Mappa';
    private $menu_map_color = '#486C2C';
    private $menu_map_icon = 'wm-icon-generic';
    private $menu_pois_label = 'Punti di interesse';
    private $menu_pois_color = '#E94C31';
    private $menu_pois_icon = 'wm-icon-generic';
    private $menu_tracks_label = 'Sentieri tematici';
    private $menu_tracks_color = '#E79E19';
    private $menu_tracks_icon = 'wm-icon-generic';
    private $menu_pages_title = 'About';
    private $menu_offline_label = 'Mappa offline';

    // APP Info
    private $app_id = 'it.webmapp.default' ;
    private $app_description = 'App description' ;
    private $app_icon = 'http://api.webmapp.it/resources/icon.png';
    private $app_splash = 'http://api.webmapp.it/resources/splash.png';

    // Questo array viene utilizzato per la costruzione del json usato per il file di 
    // configurazione
    private $conf_array = array();


    // TODO: rifattorizzare il costruttore per averne uno unico. Va rivisto il BETASK
    // TODO: rivedere il client in modo tale che utilizzi esclusivamente un json come file di configurazione
    public function __construct($map,$structure='') {
        if (getType($map) == 'object' && get_class($map) == 'WebmappProjectStructure') {
            $this->simpleConstruct($map);
        }
        else {
            $this->oldConstruct($map,$structure);
        }
        // Set default values;
        $this->style = $this->buildStyleConfArray();
        // Set default values;
        $this->menu = $this->buildMenuConfArray();
    } 

    public function simpleConstruct($structure) {
       if(get_class($structure) != 'WebmappProjectStructure') {
        throw new Exception("Il parametro del costruttore della classe WebmappMap deve essere di tipo WebmappProjectStructure", 1);
       }
       $this->structure=$structure;
    }

    // Legge i dati da un URL che risponde come le API di WP
    // esempio: http://dev.be.webmapp.it/wp-json/wp/v2/map/408
    public function loadMetaFromUrl($url) {
        // ja è un abbreviazione per JSON ARRAY
        $ja = json_decode(file_get_contents($url),TRUE);

        // Qui vengono inseriti tutti i valori di default (dove ha senso)
        $this->title = "Generic MAP";
        $this->tilesUrl = "map.mbtiles" ;

        if(isset($ja['title']['rendered'])) {
            $this->title = $ja['title']['rendered'];
        }
        if(isset($ja['tiles'])) {
            $this->tilesUrl=$ja['tiles'];
        }

        // Bounding box
        // TODO: gestione del caso di default (vuoto!)
        if (isset($ja['n7webmap_map_bbox'])) {
            $this->bb = json_decode($ja['n7webmap_map_bbox'],TRUE);
        }
        if (isset($ja['n7webmapp_route_bbox'])) {
            $this->bb = json_decode($ja['n7webmapp_route_bbox'],TRUE);
        }

        // STYLE
        if(isset($ja['style']) && !empty($ja['style'])) {
            $this->style = json_decode($ja['style'],TRUE);
        } 

        // Pages and menu pages title
        if(isset($ja['pages_title']) && !empty($ja['pages_title'])) {
            $this->menu_pages_title = $ja['pages_title'];
        } 
        if(isset($ja['pages']) && is_array($ja['pages']) && count($ja['pages'])>0) {
            foreach($ja['pages'] as $page_obj) {
                $guid = $page_obj['guid'];
                $api = preg_replace('|\?page_id=|', 'wp-json/wp/v2/pages/', $guid);
                $page_info = json_decode(file_get_contents($api),TRUE);
                $page = array ("label" => $page_obj['post_title'],
                               "type" => $page_obj['post_name'],
                               "isCustom" => true,
                    );
                if(isset($page_info['menu_color']) && !empty($page_info['menu_color'])) {
                    $page["color"]=$page_info['menu_color'];
                }
                if(isset($page_info['menu_icon']) && !empty($page_info['menu_icon'])) {
                    $page["icon"]=$page_info['menu_icon'];
                }

                array_push($this->pages, $page);
            }
        }
        if(isset($ja['has_offline']) && $ja['has_offline'] == true ) {
            $this->has_offline = true;
        }
        if(isset($ja['offline_menu_label']) && !empty($ja['offline_menu_label'])) {
            $this->menu_offline_label = $ja['offline_menu_label'];
        }

        if ($this->has_offline) {
            $this->buildOfflineConfArray();
        }
        if(isset($ja['app_id']) && $ja['app_id'] != '' ) {
            $this->app_id = $ja['app_id'];
        }
        if(isset($ja['app_description']) && $ja['app_description'] != '' ) {
            $this->app_description = $ja['app_description'];
        }


    }

    // GETTERS
    public function getTitle() {
        return $this->title;
    }
    public function getTilesUrl() {
        return $this->tilesUrl;
    }
    public function getType() { 
        return $this->type;
    }
    public function getBB() { 
        return $this->bb;
    }
    public function getStyle() {
        return $this->style;
    }
    public function hasOffline() {
        return $this->has_offline;
    }

    // SETTERS
    public function setTitle($title) {
        $this->title = $title;
    }


    // TODO: eliminare questa funzione
    public function oldConstruct($map,$structure) {
      $this->map = $map;
      $this->structure = $structure;
      if(isset($this->map['n7webmap_type']))
      {
        $this->type = $this->map['n7webmap_type'];
    }
    else {
        throw new Exception("Parametro n7webmap mancante", 1);
    }
      $this->title = $this->map['title']['rendered'];
      $this->bb = $this->map['n7webmap_map_bbox'];
      $this->tilesUrl = $this->map['tiles'];
    }

    public function addPoisWebmappLayer($layer) {
       $url = $layer->getName().'.geojson';
       $label = $layer->getLabel();
       $color = $layer->getColor();
       $icon = $layer->getIcon();
       $this->addPoisLayer($url,$label,$color,$icon,true);
    }

    public function addTracksWebmappLayer($layer) {
       $url = $layer->getName().'.geojson';
       $label = $layer->getLabel();
       $color = $layer->getColor();
       $icon = $layer->getIcon();
       $this->addTracksLayer($url,$label,$color,$icon,true);
    }

    public function addPoisLayer($url,$label,$color='',$icon='',$showByDefault=true) {
        $this->addLayer('pois',$url,$label,$color,$icon,$showByDefault);
    }
    public function addTracksLayer($url,$label,$color='',$icon='',$showByDefault=true) {
        $this->addLayer('tracks',$url,$label,$color,$icon,$showByDefault);
    }

    public function addLayer($type,$url,$label,$color='',$icon='',$showByDefault=true) {

        // Manage default values
        if ($color == '' ) $color = '#FF3812';
        if ($icon == '' ) $icon = 'wm-icon-generic';

        switch ($type) {
            case 'pois':
                $type_label='poi_geojson';
                break;
            case 'tracks':
                $type_label='line_geojson';
                break;
            
            default:
                throw new Exception("Tipo $type non supportato", 1);
                break;
        }        
        $layer = array (
            'geojsonUrl' => $url,
            'label' => $label,
            'color' => $color,
            'icon' => $icon,
            'showByDefault' => $showByDefault,
            'type' => $type_label
            );
        switch ($type) {
            case 'pois':
                array_push($this->pois_layers, $layer);
                break;
            case 'tracks':
                array_push($this->tracks_layers, $layer);
                break;
            
            default:
                throw new Exception("Tipo $type non supportato", 1);
                break;
        }
    }


    public function writeConf() {

        $conf_path = $this->structure->getPathClientConf() ;
 
        $conf_json_path = preg_replace('/config\.js/', 'config.json', $conf_path);
        $conf_json = $this->getConfJson();
        file_put_contents($conf_json_path, $conf_json);
        // TODO: lo scriviamo anche nella root (poi si eliminerà la directory client che non ha senso)
        file_put_contents($this->structure->getRoot() . '/config.json', $conf_json);

        $conf = $this->getConf();
        $conf_path = $this->structure->getPathClientConf() ;
        file_put_contents($conf_path, $conf);
        file_put_contents($this->structure->getRoot() . '/config.js', $conf);
    }

    public function writeIndex() {
        $conf = $this->getIndex();
        file_put_contents($this->structure->getPathClientIndex(), $conf);
        // TODO: lascare solo la index nella root
        file_put_contents($this->structure->getRoot() . '/index.html', $conf);
    }

    // Write file info.json with APP Info (esempio: http://pnfc.j.webmapp.it/info.json)
    public function writeInfo() {
        $info = array();
        $info['configJs'] = $this->structure->getUrlBase() . '/config.js';
        $info['configJson'] = $this->structure->getUrlBase() . '/config.json';
        $info['config.xml']['id'] = $this->app_id;
        $info['config.xml']['description'] = $this->app_description;
        $info['config.xml']['name'] = $this->title;
        $info['config.xml']['version'] = '1.0.0';
        $info['resources']['icon'] = $this->app_icon;
        $info['resources']['splash'] = $this->app_splash;
        $file = $this->structure->getRoot() . '/info.json';
        file_put_contents($file, json_encode($info));
    }

    public function getConf() {
       return "angular.module('webmapp').constant('GENERAL_CONFIG', ".$this->getConfJson().");";
    }

    public function getConfJson() {
        $this->buildConfArray();
        return json_encode($this->conf_array);
    }

    private function buildConfArray() {
        // VERSION 
        $this->conf_array['VERSION'] = '0.4';

        // OPTIONS
        $this->conf_array['OPTIONS'] = $this->buildOptionsConfArray();

        // STYLE
        $this->conf_array['STYLE'] = $this->style;

        // ADVANCED_DEBUG
        $this->conf_array['ADVANCED_DEBUG'] = false;

        // COMMUNICATION
        $baseUrl = $this->structure->getUrlBase();
        $geojsonBaseUrl = $this->structure->getURLGeojson();
        $this->conf_array['COMMUNICATION'] = 
           array(
            'baseUrl'=>$baseUrl,
            'resourceBaseUrl'=>$geojsonBaseUrl
           );

        // SEARCH
        $this->conf_array['SEARCH'] = $this->buildSearchConfArray();

        // MENU
        $this->conf_array['MENU'] = $this->menu;

        // MAP
        $this->conf_array['MAP'] = $this->buildMapConfArray();

        // DETAIL_MAPPING
        $this->conf_array['DETAIL_MAPPING'] = $this->buildDetailMappingConfArray();

        // PAGES
        if ($this->has_offline) {
           $pages_with_offline = $this->pages; 
           array_push($pages_with_offline,array(
                               "label" => $this->menu_offline_label,
                               "type" => 'settings',
                               "isCustom" => false));
           $this->conf_array['PAGES'] = $pages_with_offline;
        }
        else {
           $this->conf_array['PAGES'] = $this->pages;            
        }

        // OVERLAY_LAYERS
        $this->conf_array['OVERLAY_LAYERS'] = array_merge($this->pois_layers,$this->tracks_layers);

        $this->conf_array['OFFLINE'] = $this->offline;


    }

    private function buildOptionsConfArray() {
        $options["title"] = "$this->title";
        $options["startUrl"] = "/";
        $options["useLocalStorageCaching"] = false;
        $options["advancedDebug"] = false;
        $options["hideHowToReach"] = true;
        $options["hideMenuButton"] = false;
        $options["hideExpanderInDetails"] = false;
        $options["hideFiltersInMap"] = false;
        $options["hideDeactiveCentralPointer"] = true;
        $options["hideShowInMapFromSearch"] = true;
        $options["avoidModalInDetails"] = true;
        $options["useAlmostOver"] = false;
        $options["filterIcon"] = "wm-icon-layers";
        return $options;
    }

    private function buildStyleConfArray() {
        $style = <<<EOS
     {
        "global" : {
            "background" : "#F3F6E9",
            "color" : "black",
            "centralPointerActive" : "black",
            "buttonsBackground" : "rgba(56, 126, 245, 0.78)"
        },
        "details" : {
            "background" : "#F3F6E9",
            "buttons" : "rgba(56, 126, 245, 0.78)",
            "color" : "#929077"
        },
        "subnav" : {
            "color" : "white",
            "background" : "#387EF5"
        },
        "mainBar" : {
            "color" : "white",
            "background" : "#387EF5",
            "overwrite" : true
        },
        "menu" : {
            "color" : "black",
            "background" : "#F3F6E9"
        },
        "search" : {
            "color" : "#387EF5"
        },
        "images" : {
            "background" : "#e6e8de"
        },
        "line" : {
            "default": {
                "color" : "red",
                "weight" : 5,
                "opacity" : 0.65
            },
            "highlight" : {
                "color": "#00FFFF",
                "weight": 6,
                "opacity": 1
            }
        }
    }
EOS;
    return json_decode($style,TRUE);
}

private function buildSearchConfArray() {
    $search = <<<EOS
    {
        "active": true,
        "indexFields": ["name","description","email","address"],
        "showAllByDefault": true,
        "stemming": true,
        "removeStopWords": true,
        "indexStrategy": "AllSubstringsIndexStrategy", 
        "TFIDFRanking": true
    }
EOS;
   return json_decode($search,TRUE);
}

private function buildMenuConfArray() {
    $menu = <<<EOS
        [{
                "label": "Esci dall'itinerario",
                "type": "closeMap"
              }, {
                "label" : "Mappa",
                "type" : "map"
              },{
                "label" : "Cerca",
                "type" : "search"
        }]
EOS;
    return json_decode($menu,TRUE);
}

private function addMenuItem($label,$type,$color='',$icon='',$items=array()) {
    $item = array();
    $item['label'] = $label;
    $item['type'] = $type;
    if($color!='') {
         $item['color'] = $color;        
    }
    if($icon != '' ) {
        $item['icon'] = $icon;        
    }
    if(count($items)>0) {
        $item['items']=$items;
    }
    array_push($this->menu, $item);
}

public function buildStandardMenu() {
    // RESET MENU ARRAY
    $this->menu = array();

    //  MAP ITEM
    $this->addMenuItem($this->menu_map_label,'map',$this->menu_map_color,$this->menu_map_icon);

    // POIS
    $c = count($this->pois_layers);
    if ($c>0) {
        if($c==1) {
            // Add single layer
            $label = $this->pois_layers[0]['label'];
            $color = $this->pois_layers[0]['color'];
            $icon = $this->pois_layers[0]['icon'];
            $this->addMenuItem($label,'layer',$color,$icon);
        }
        else {
            // Add Group layer
            $items = array() ;
            foreach ($this->pois_layers as $layer) {
                $items[] = $layer['label'];
            }
            $label = $this->menu_pois_label;
            $color = $this->menu_pois_color;
            $icon = $this->menu_pois_icon;
            $this->addMenuItem($label,'layerGroup',$color,$icon,$items);
        }
    }

    // TRACKS
    $c = count($this->tracks_layers);
    if ($c>0) {
        if($c==1) {
            // Add single layer
            $label = $this->tracks_layers[0]['label'];
            $color = $this->tracks_layers[0]['color'];
            $icon = $this->tracks_layers[0]['icon'];
            $this->addMenuItem($label,'layer',$color,$icon);
        }
        else {
            // Add Group layer
            $items = arrray() ;
            foreach ($this->tracks_layers as $layer) {
                $items[] = $layer['label'];
            }
            $label = $this->menu_pois_label;
            $color = $this->menu_pois_color;
            $icon = $this->menu_pois_icon;
            $this->addMenuItem($label,'layerGroup',$color,$icon,$items);
        }
    }

    // PAGES
    if(count($this->pages)>0) {
        $items = array();
        foreach ($this->pages as $page) {
            $items[]=$page['label'];
        }
        $this->addMenuItem($this->menu_pages_title,'pageGroup','','',$items);
    }

    // OFFLINE PAGE
    if($this->has_offline) {
        $this->addMenuItem($this->menu_offline_label,'page');
    }
}

private function buildMapConfArray() {
    $map = $this->bb;
    $map['markerClustersOptions'] = array (
            "spiderfyOnMaxZoom" => true,
            "showCoverageOnHover" => false,
            "maxClusterRadius" => 60,
            "disableClusteringAtZoom" => 17
        );
    $map["showCoordinatesInMap"]=true;
    $map["showScaleInMap"]=true;
    $map["hideZoomControl"]=false;
    $map["hideLocationControl"]=false;
    // TODO: gestione del layer dei satelliti
    $map["layers"] = array(
        array(
                "label" => "Mappa",
                "type" => "maptile",
                "tilesUrl" => $this->tilesUrl,
                "default" => true
            )
        );
    return $map;
}

private function buildDetailMappingConfArray() {
    $detail_mapping = <<<EOS
    {
        "default" : {
            "fields" : {
                "title" : "name",
                "image" : "image",
                "description" : "description",
                "email" : "contact:email",
                "phone" : "contact:phone",
                "address" : "address"
            },
            "table" : {
                "ref" : "Percorso",
                "distance" : "Lunghezza",
                "ascent" : "Dislivello positivo",
                "descent" : "Dislivello negativo",
                "duration:forward" : "Tempi",
                "duration:backward" : "Tempi in direzione contraria",
                "cai_scale" : "Difficoltà"
            },
            "urls" : {
                "url" : "Vai al sito web"
            }
        }
    }
EOS;
   return json_decode($detail_mapping,TRUE);
}

private function buildOfflineConfArray() {
    $baseUrl = $this->structure->getUrlBase();
    $this->offline["resourceBaseUrl"] = $baseUrl . '/geojson/';
    $this->offline["pagesUrl"] = $baseUrl . '/pages/';
    $this->offline["urlMbtiles"] = $baseUrl . '/tiles/map.mbtiles';
    $this->offline["urlImages"] = $baseUrl . '/media/images.zip';
}

    public function getIndex() {
        $url=$this->structure->getURLClient().'/';
$index = <<<EOS

<!DOCTYPE html>
<html>
    <head>
        <base href="$url"></base>

        <meta charset="utf-8">
        <meta name="viewport" content="initial-scale=1, maximum-scale=1, user-scalable=no, width=device-width">

        <meta http-equiv="Content-Security-Policy" content="default-src data: gap: *; script-src 'self' 'unsafe-inline' 'unsafe-eval' *; style-src  'self' 'unsafe-inline' *">
        
        <title>$this->title</title>

        <!-- CSS -->
        <link href="http://client2.webmapp.it/core/fonts/webmapp-icons/style.css" rel="stylesheet">
        
        <link href="http://client2.webmapp.it/core/lib/leaflet_plugin/leaflet-control-locate/L.Control.Locate.mapbox.css" rel="stylesheet">
        <link href="http://client2.webmapp.it/core/lib/leaflet_plugin/leaflet-markercluster/MarkerCluster.css" rel="stylesheet">
        <link href="http://client2.webmapp.it/core/lib/leaflet_plugin/leaflet-markercluster/MarkerCluster.Default.css" rel="stylesheet">

        <link href="http://client2.webmapp.it/core/lib/leaflet_plugin/leaflet.groupedlayercontrol/leaflet.groupedlayercontrol.min.css" rel="stylesheet">
        <link href="http://client2.webmapp.it/core/lib/leaflet_plugin/leaflet.vector-markers/leaflet-vector-markers.css" rel="stylesheet">

        <link href="http://client2.webmapp.it/core/lib/ionic/css/ionic.css" rel="stylesheet">
        
        <link href="http://client2.webmapp.it/core/css/font-awesome.min.css" rel="stylesheet">
        <link href="http://client2.webmapp.it/core/lib/leaflet/leaflet.css" rel="stylesheet">
        <!-- <link href='core/css/mapbox2.2.2.min.css' rel='stylesheet' /> -->
        <!-- <link href="http://client2.webmapp.it/core/css/fonts.css" rel="stylesheet" />
        <link href="http://client2.webmapp.it/core/css/style.min.css" rel="stylesheet" /> -->

        <link rel="stylesheet" type="text/css" href="http://client2.webmapp.it/core/css/fonts.css" media="none" onload="document.addEventListener('DOMContentLoaded', function() {setTimeout(function() {document.body.className+=' fontsloaded';}, 1000);}); this.media='all';">
        <link rel="stylesheet" type="text/css" href="http://client2.webmapp.it/core/css/style.min.css" media="none" onload="this.media='all';">

        <!-- JS -->

        <!-- LIB -->
        <script src="http://client2.webmapp.it/core/lib/LPF.js"></script>
        <script src="http://client2.webmapp.it/core/lib/jquery/dist/jquery.min.js"></script>
        <script src="http://client2.webmapp.it/core/lib/ionic/js/ionic.bundle.min.js"></script>

        <script src="http://client2.webmapp.it/core/lib/clipboard/dist/clipboard.min.js"></script>
        <script src="http://client2.webmapp.it/core/lib/jsSHA/src/sha1.js"></script>
        <script src="http://client2.webmapp.it/core/lib/porter-stemmer/porterStemmer1980.min.js"></script>
        <script src="http://client2.webmapp.it/core/lib/js-search/dist/js-search.min.js"></script>
        <script src="http://client2.webmapp.it/core/lib/igTruncate/igTruncate.js"></script>
        <script src="http://client2.webmapp.it/core/lib/JsBarcode/dist/JsBarcode.all.min.js"></script>
        
        <script src="http://client2.webmapp.it/core/lib/leaflet/leaflet.js"></script>
        <script src="http://client2.webmapp.it/core/lib/leaflet_plugin/leaflet-hash-mod/leaflet-hash.js"></script>
        <script src="http://client2.webmapp.it/core/lib/leaflet_plugin/leaflet.groupedlayercontrol/leaflet.groupedlayercontrol.min.js"></script>
        <script src="http://client2.webmapp.it/core/lib/leaflet_plugin/leaflet.utfgrid.js"></script>
        <script src="http://client2.webmapp.it/core/lib/leaflet_plugin/leaflet.vector-markers/leaflet-vector-markers.min.js"></script>
        <script src="http://client2.webmapp.it/core/lib/leaflet_plugin/leaflet-control-locate/L.Control.Locate.min.js"></script>
        <script src="http://client2.webmapp.it/core/lib/leaflet_plugin/leaflet-markercluster/leaflet.markercluster.js"></script>
        <script src="http://client2.webmapp.it/core/lib/leaflet_plugin/leaflet.kkn.min.js"></script>
        <script src="http://client2.webmapp.it/core/lib/leaflet_plugin/leaflet.geometryutil.js"></script>
        <script src="http://client2.webmapp.it/core/lib/leaflet_plugin/leaflet.almostover.js"></script>
        <script src="http://client2.webmapp.it/core/lib/leaflet_plugin/L.UTFGrid-min.js"></script>
        <script src="https://unpkg.com/leaflet.vectorgrid@latest/dist/Leaflet.VectorGrid.bundled.js"></script>

        <script src="http://client2.webmapp.it/core/lib/ngCordova/dist/ng-cordova.js"></script>
        
        <!-- DEVELOP -->
        <!-- <script src="http://client2.webmapp.it/core/lib/connector.js" data-channel="mp-vc" id="consolerescript"></script> -->
        <!-- <script src="_cordova/cordova.js"></script> -->

        <!-- APP -->
        
        <script type="text/javascript">
            var templateBasePath = 'http://client2.webmapp.it/core/',
                templateCustomPath = '';
        </script>
        
        <script src="http://client2.webmapp.it/core/js/app.js"></script>
        <script src="http://client2.webmapp.it/core/js/settings/overwrite.js"></script>
        <script src="http://client2.webmapp.it/core/js/settings/routes.js"></script>
        <script src="http://client2.webmapp.it/core/js/settings/run.js"></script>
        <script src="http://client2.webmapp.it/core/js/settings/filters.js"></script>
        <script src="http://client2.webmapp.it/core/js/settings/compile.js"></script>
        <script src="config.js"></script>
        
        <script src="http://client2.webmapp.it/core/js/services/auth.factory.js"></script>
        <script src="http://client2.webmapp.it/core/js/services/account.factory.js"></script>
        <script src="http://client2.webmapp.it/core/js/services/tracking.factory.js"></script>
        <script src="http://client2.webmapp.it/core/js/services/search.factory.js"></script>
        <script src="http://client2.webmapp.it/core/js/services/model.factory.js"></script>
        <script src="http://client2.webmapp.it/core/js/services/utils.factory.js"></script>
        <script src="http://client2.webmapp.it/core/js/services/offline.factory.js"></script>
        <script src="http://client2.webmapp.it/core/js/services/map.factory.js"></script>

        <script src="http://client2.webmapp.it/core/js/components/welcome/welcome.controller.js"></script>
        <script src="http://client2.webmapp.it/core/js/components/coupons/coupons.controller.js"></script>
        <script src="http://client2.webmapp.it/core/js/components/packages/packages.controller.js"></script>
        <script src="http://client2.webmapp.it/core/js/components/settings/settings.controller.js"></script>
        <script src="http://client2.webmapp.it/core/js/components/search/search.controller.js"></script>
        <script src="http://client2.webmapp.it/core/js/components/mapView/mapView.controller.js"></script>
        <script src="http://client2.webmapp.it/core/js/components/list/list.controller.js"></script>
        <script src="http://client2.webmapp.it/core/js/components/card/card.controller.js"></script>
        <script src="http://client2.webmapp.it/core/js/components/details/details.controller.js"></script>
        <script src="http://client2.webmapp.it/core/js/components/main/main.controller.js"></script>
        <script src="http://client2.webmapp.it/core/js/components/menu/menu.controller.js"></script>
        <script src="http://client2.webmapp.it/core/js/components/custom/custom.controller.js"></script>
        <script src="http://client2.webmapp.it/core/js/components/webmapp/webmapp.controller.js"></script>
    </head>

    <body ng-app="webmapp">
        <h1 style="
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        ">LOADING ...</h1>
        <ion-nav-view></ion-nav-view>
        <!-- <ion-nav-view style="top: 15px"></ion-nav-view> -->
    </body>
</html>


EOS;

return $index;

    }

}