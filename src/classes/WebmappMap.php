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

        //$conf = $this->getConf();
        $conf_path = $this->structure->getPathClientConf() ;
        //file_put_contents($conf_path, $conf);
 
        // TODO: migliorare la gestione del file config.json a livello di progetto
        // TODO: ancora meglio unificare una volta per tutte il file di configurazione in un unico file json
        $conf_json_path = preg_replace('/config\.js/', 'config.json', $conf_path);
        $conf_json = $this->getConfJson();
        file_put_contents($conf_json_path, $conf_json);

    }

    public function writeIndex() {
        $conf = $this->getIndex();
        file_put_contents($this->structure->getPathClientIndex(), $conf);
    }

    public function getConf() {

// Gestione degli ovlerlay_layers
    $all_layers = array_merge($this->tracks_layers,$this->pois_layers);
    $layers_string = '';
    if (count($all_layers)):
        $first = true;
        foreach ($all_layers as $layer) {
            $label=$layer['label'];
            $icon=$layer['icon'];
            $color=$layer['color'];
            $geojsonUrl=$layer['geojsonUrl'];
            $showByDefault = 'true';
            if(isset($layer['showByDefault']) && $layer['showByDefault']===false) {
                $showByDefault = 'false';
            } 
            switch ($layer['type']) {
                case 'pois':
                    $type='poi_geojson';
                    break;
                case 'tracks':
                    $type='line_geojson';
                    break;
                
                default:
                    $type='undefined';
                    break;
            }
            $out = <<<EOS

        {
            label: '$label',
            type: '$type',
            color: '$color',
            icon: '$icon',
            geojsonUrl: '$geojsonUrl',
            showByDefault: $showByDefault
        }
EOS;
    if(!$first) $out = ','.$out;
    $first = false;
    $layers_string = $layers_string.$out;
        }
    endif;


    $overlay_layers = "OVERLAY_LAYERS: [$layers_string]";

$conf = <<<EOS
angular.module('webmapp').constant('GENERAL_CONFIG', {
    VERSION: '0.4', // TODO: add clear localStorage if VERSION !==

    OPTIONS: {
        title: '$this->title',
        startUrl: '/',
        useLocalStorageCaching: false,
        advancedDebug: false,
        hideHowToReach: true,
        hideMenuButton: true,
        hideExpanderInDetails: true,
        hideFiltersInMap: false,
        hideDeactiveCentralPointer: true,
        hideShowInMapFromSearch: true,
        avoidModalInDetails: true,
        useAlmostOver: false,
        filterIcon: 'wm-icon-layers'
    },

    STYLE: {
        global: {
            background: '#F3F6E9',
            color: 'black',
            centralPointerActive: 'black',
            buttonsBackground: 'rgba(56, 126, 245, 0.78)'
        },
        details: {
            background: '#F3F6E9',
            buttons: 'rgba(56, 126, 245, 0.78)',
            color: '#929077'
        },
        subnav: {
            color: 'white',
            background: '#387EF5'
        },
        mainBar: {
            color: 'white',
            background: '#387EF5',
            overwrite: true
        },
        menu: {
            color: 'black',
            background: '#F3F6E9'
        },
        search: {
            color: '#387EF5'
        },
        images: {
            background: '#e6e8de'
        },
        line: {
            default: {
                color: 'red',
                weight: 5,
                opacity: 0.65
            },
            highlight: {
                color: '#00FFFF',
                weight: 6,
                opacity: 1
            },
        }
    },

    ADVANCED_DEBUG: false,

    COMMUNICATION: {
        // baseUrl: 'http://dev.be.webmapp.it/',
        // endpoint: 'wp-json/webmapp/v1/',
        // wordPressEndpoint: 'wp-json/wp/v2/'
        // singleFeatureUrl: 'http://www.turismovallecamonica.it/it/get-single-feature-geojson/'
    },

    SEARCH: {
        active: true,
        // indexFields: ['name', 'body', 'email', 'address'],
        indexFields: ['name'],
        showAllByDefault: true,
        stemming: true,
        removeStopWords: true,
        indexStrategy: 'AllSubstringsIndexStrategy', // AllSubstringsIndexStrategy || ExactWordIndexStrategy || PrefixIndexStrategy
        TFIDFRanking: true
    },

     
    // Da riempire solo con menu
    MENU: [],

    LOGIN: {
        useLogin: false,
        forceLogin: false
    },

    OFFLINE: {
        // url: 'http://api.webmapp.it/elba/tiles/map.zip',
        // tms: false,
        // lastRelease: '2016-12-01'
    },

    TRACKING: {
        active: false,
    },

    MAP: {
         $this->bb
         ,  markerClustersOptions: {
            spiderfyOnMaxZoom: true,
            showCoverageOnHover: false,
            maxClusterRadius: 60, // or 40?
            disableClusteringAtZoom: 17
        },
        showCoordinatesInMap: true,
        showScaleInMap: true,
        hideZoomControl: false,
        hideLocationControl: false,

        layers: [{
            label: 'Mappa',
            type: 'maptile',
            // TODO: mettere OSM
            tilesUrl: '$this->tilesUrl',
            default: true
        }, {
            label: 'Satellite',
            type: 'wms',
            tilesUrl: 'http://213.215.135.196/reflector/open/service?',
            layers: 'rv1',
            format: 'image/jpeg'
        }]
    },

    DETAIL_MAPPING: {
        default: {
            table: {
                phone: 'Telefono'
            },
            fields: {
                title: 'name',
                description: 'description',
                image: 'image',
                email: 'mail',
                phone: 'telefono',
                address: 'via'
            }
        }
    },

    // Loop sui layers (da prendere nelle tracce)
    $overlay_layers ,

    PAGES: [],

});


EOS;
return $conf;

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
        $this->conf_array['STYLE'] = $this->buildStyleConfArray();

        // ADVANCED_DEBUG
        $this->conf_array['ADVANCED_DEBUG'] = false;

        // COMMUNICATION
        $geojsonBaseUrl = $this->structure->getURLGeojson();
        $this->conf_array['COMMUNICATION'] = array('resourceBaseUrl'=>$geojsonBaseUrl);

        // SEARCH
        $this->conf_array['SEARCH'] = $this->buildSearchConfArray();

        // MENU
        $this->conf_array['MENU'] = $this->buildMenuConfArray();

        // MAP
        $this->conf_array['MAP'] = $this->buildMapConfArray();

        // DETAIL_MAPPING
        $this->conf_array['DETAIL_MAPPING'] = $this->buildDetailMappingConfArray();

        // PAGES
        $this->conf_array['PAGES'] = array();

        // OVERLAY_LAYERS
        $this->conf_array['OVERLAY_LAYERS'] = array_merge($this->pois_layers,$this->tracks_layers);


    }

    private function buildOptionsConfArray() {
        $options["title"] = "$this->title";
        $options["startUrl"] = "/";
        $options["useLocalStorageCaching"] = false;
        $options["advancedDebug"] = false;
        $options["hideHowToReach"] = true;
        $options["hideMenuButton"] = false;
        $options["hideExpanderInDetails"] = true;
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
        "indexFields": ["name"],
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
                "type" => "mbtiles",
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
            "table" : {
                "phone" : "Telefono"
            },
            "fields" : {
                "title" : "name",
                "description" : "description",
                "image" : "image",
                "email" : "mail",
                "phone" : "telefono",
                "address" : "via"
            }
        }
    }
EOS;
   return json_decode($detail_mapping,TRUE);
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
        <link href="http://client.webmapp.it/core/fonts/webmapp-icons/style.css" rel="stylesheet">
        
        <link href="http://client.webmapp.it/core/lib/leaflet_plugin/leaflet-control-locate/L.Control.Locate.mapbox.css" rel="stylesheet">
        <link href="http://client.webmapp.it/core/lib/leaflet_plugin/leaflet-markercluster/MarkerCluster.css" rel="stylesheet">
        <link href="http://client.webmapp.it/core/lib/leaflet_plugin/leaflet-markercluster/MarkerCluster.Default.css" rel="stylesheet">

        <link href="http://client.webmapp.it/core/lib/leaflet_plugin/leaflet.groupedlayercontrol/leaflet.groupedlayercontrol.min.css" rel="stylesheet">
        <link href="http://client.webmapp.it/core/lib/leaflet_plugin/leaflet.vector-markers/leaflet-vector-markers.css" rel="stylesheet">

        <link href="http://client.webmapp.it/core/lib/ionic/css/ionic.css" rel="stylesheet">
        
        <link href="http://client.webmapp.it/core/css/font-awesome.min.css" rel="stylesheet">
        <link href="http://client.webmapp.it/core/lib/leaflet/leaflet.css" rel="stylesheet">
        <!-- <link href='core/css/mapbox2.2.2.min.css' rel='stylesheet' /> -->
        <!-- <link href="http://client.webmapp.it/core/css/fonts.css" rel="stylesheet" />
        <link href="http://client.webmapp.it/core/css/style.min.css" rel="stylesheet" /> -->

        <link rel="stylesheet" type="text/css" href="http://client.webmapp.it/core/css/fonts.css" media="none" onload="document.addEventListener('DOMContentLoaded', function() {setTimeout(function() {document.body.className+=' fontsloaded';}, 1000);}); this.media='all';">
        <link rel="stylesheet" type="text/css" href="http://client.webmapp.it/core/css/style.min.css" media="none" onload="this.media='all';">

        <!-- JS -->

        <!-- LIB -->
        <script src="http://client.webmapp.it/core/lib/LPF.js"></script>
        <script src="http://client.webmapp.it/core/lib/jquery/dist/jquery.min.js"></script>
        <script src="http://client.webmapp.it/core/lib/ionic/js/ionic.bundle.min.js"></script>

        <script src="http://client.webmapp.it/core/lib/clipboard/dist/clipboard.min.js"></script>
        <script src="http://client.webmapp.it/core/lib/jsSHA/src/sha1.js"></script>
        <script src="http://client.webmapp.it/core/lib/porter-stemmer/porterStemmer1980.min.js"></script>
        <script src="http://client.webmapp.it/core/lib/js-search/dist/js-search.min.js"></script>
        <script src="http://client.webmapp.it/core/lib/igTruncate/igTruncate.js"></script>
        <script src="http://client.webmapp.it/core/lib/JsBarcode/dist/JsBarcode.all.min.js"></script>
        
        <script src="http://client.webmapp.it/core/lib/leaflet/leaflet.js"></script>
        <script src="http://client.webmapp.it/core/lib/leaflet_plugin/leaflet-hash-mod/leaflet-hash.js"></script>
        <script src="http://client.webmapp.it/core/lib/leaflet_plugin/leaflet.groupedlayercontrol/leaflet.groupedlayercontrol.min.js"></script>
        <script src="http://client.webmapp.it/core/lib/leaflet_plugin/leaflet.utfgrid.js"></script>
        <script src="http://client.webmapp.it/core/lib/leaflet_plugin/leaflet.vector-markers/leaflet-vector-markers.min.js"></script>
        <script src="http://client.webmapp.it/core/lib/leaflet_plugin/leaflet-control-locate/L.Control.Locate.min.js"></script>
        <script src="http://client.webmapp.it/core/lib/leaflet_plugin/leaflet-markercluster/leaflet.markercluster.js"></script>
        <script src="http://client.webmapp.it/core/lib/leaflet_plugin/leaflet.kkn.min.js"></script>
        <script src="http://client.webmapp.it/core/lib/leaflet_plugin/leaflet.geometryutil.js"></script>
        <script src="http://client.webmapp.it/core/lib/leaflet_plugin/leaflet.almostover.js"></script>
        <script src="http://client.webmapp.it/core/lib/leaflet_plugin/L.UTFGrid-min.js"></script>
        <script src="https://unpkg.com/leaflet.vectorgrid@latest/dist/Leaflet.VectorGrid.bundled.js"></script>

        <script src="http://client.webmapp.it/core/lib/ngCordova/dist/ng-cordova.js"></script>
        
        <!-- DEVELOP -->
        <!-- <script src="http://client.webmapp.it/core/lib/connector.js" data-channel="mp-vc" id="consolerescript"></script> -->
        <!-- <script src="_cordova/cordova.js"></script> -->

        <!-- APP -->
        
        <script type="text/javascript">
            var templateBasePath = 'http://client.webmapp.it/core/',
                templateCustomPath = '';
        </script>
        
        <script src="http://client.webmapp.it/core/js/app.js"></script>
        <script src="http://client.webmapp.it/core/js/settings/overwrite.js"></script>
        <script src="http://client.webmapp.it/core/js/settings/routes.js"></script>
        <script src="http://client.webmapp.it/core/js/settings/run.js"></script>
        <script src="http://client.webmapp.it/core/js/settings/filters.js"></script>
        <script src="http://client.webmapp.it/core/js/settings/compile.js"></script>
        <script src="config.js"></script>
        
        <script src="http://client.webmapp.it/core/js/services/auth.factory.js"></script>
        <script src="http://client.webmapp.it/core/js/services/account.factory.js"></script>
        <script src="http://client.webmapp.it/core/js/services/tracking.factory.js"></script>
        <script src="http://client.webmapp.it/core/js/services/search.factory.js"></script>
        <script src="http://client.webmapp.it/core/js/services/model.factory.js"></script>
        <script src="http://client.webmapp.it/core/js/services/utils.factory.js"></script>
        <script src="http://client.webmapp.it/core/js/services/offline.factory.js"></script>
        <script src="http://client.webmapp.it/core/js/services/map.factory.js"></script>

        <script src="http://client.webmapp.it/core/js/components/welcome/welcome.controller.js"></script>
        <script src="http://client.webmapp.it/core/js/components/coupons/coupons.controller.js"></script>
        <script src="http://client.webmapp.it/core/js/components/packages/packages.controller.js"></script>
        <script src="http://client.webmapp.it/core/js/components/settings/settings.controller.js"></script>
        <script src="http://client.webmapp.it/core/js/components/search/search.controller.js"></script>
        <script src="http://client.webmapp.it/core/js/components/mapView/mapView.controller.js"></script>
        <script src="http://client.webmapp.it/core/js/components/list/list.controller.js"></script>
        <script src="http://client.webmapp.it/core/js/components/card/card.controller.js"></script>
        <script src="http://client.webmapp.it/core/js/components/details/details.controller.js"></script>
        <script src="http://client.webmapp.it/core/js/components/main/main.controller.js"></script>
        <script src="http://client.webmapp.it/core/js/components/menu/menu.controller.js"></script>
        <script src="http://client.webmapp.it/core/js/components/custom/custom.controller.js"></script>
        <script src="http://client.webmapp.it/core/js/components/webmapp/webmapp.controller.js"></script>
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