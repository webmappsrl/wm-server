<?php

// Gestione di una mappa (creazione file index, file di configurazione)

class WebmappMap {

	private $map;
    private $structure;
    private $type;
    private $title;
    private $bb;
    private $pois_layers=array();

    public function __construct($map,$structure) {
      $this->map = $map;
      $this->structure = $structure;
      if(isset($this->map['n7webmap_type'])){
        $this->type = $this->map['n7webmap_type'];
    }
    else {
        throw new Exception("Parametro n7webmap mancante", 1);
    }
      $this->title = $this->map['title']['rendered'];
      $this->bb = $this->map['n7webmap_map_bbox'];

    } 

    public function getType() { return $this->type;}
    public function getTitle() { return $this->title;}
    public function getBB() { return $this->bb;}

    public function addPoisLayer($url,$label,$color='#FF3812',$icon='wm-icon-generic',$showByDefault=true){
        $layer = array (
            'geojsonUrl' => $url,
            'label' => $label,
            'color' => $color,
            'icon' => $icon,
            'showByDefault' => $showByDefault
            );
        array_push($this->pois_layers, $layer);
    }

    public function writeConf() {
        $conf = $this->getConf();
        file_put_contents($this->structure->getPathClientConf(), $conf);
    }

    public function writeIndex() {
        $conf = $this->getIndex();
        file_put_contents($this->structure->getPathClientIndex(), $conf);
    }

    public function getConf() {

// Gestione degli ovlerlay_layers
// Prima i POI:
    $pois_string = '';
    if (count($this->pois_layers)):
        $first = true;
        foreach ($this->pois_layers as $layer) {
            $label=$layer['label'];
            $icon=$layer['icon'];
            $color=$layer['color'];
            $geojsonUrl=$layer['geojsonUrl'];
            $showByDefault = 'true';
            if(isset($layer['showByDefault']) && $layer['showByDefault']===false) {
                $showByDefault = 'false';
            } 
            $out = <<<EOS

        {
            label: '$label',
            type: 'poi_geojson',
            color: '$color',
            icon: '$icon',
            geojsonUrl: '$geojsonUrl',
            showByDefault: $showByDefault
        }
EOS;
    if(!$first) $out = ','.$out;
    $first = false;
    $pois_string = $pois_string.$out;
        }
    endif;
    $overlay_layers = "OVERLAY_LAYERS: [$pois_string]";

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
            tilesUrl: 'http://{s}.tile.osm.org/',
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
                image: 'picture_url',
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