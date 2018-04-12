<?php // mapIndex.html.php
$index = <<<EOS

<!DOCTYPE html>
<html>
    <head>
        <!-- <base href="/mappalo/"></base> -->

        <meta charset="utf-8">
        <meta name="viewport" content="initial-scale=1, maximum-scale=1, user-scalable=no, width=device-width">

        <meta http-equiv="Content-Security-Policy" content="default-src gap://ready http://127.0.0.1:8282 file://* * * 'unsafe-inline' 'unsafe-eval' data: blob:;">

        <title>$this->title</title>

        <!-- CSS -->
        <link href="core/fonts/webmapp-icons/style.css" rel="stylesheet">

        <link href="core/lib/leaflet_plugin/leaflet-control-locate/L.Control.Locate.mapbox.css" rel="stylesheet">
        <link href="core/lib/leaflet_plugin/leaflet-markercluster/MarkerCluster.css" rel="stylesheet">
        <link href="core/lib/leaflet_plugin/leaflet-markercluster/MarkerCluster.Default.css" rel="stylesheet">

        <link href="core/lib/leaflet_plugin/leaflet.groupedlayercontrol/leaflet.groupedlayercontrol.min.css" rel="stylesheet">
        <link href="core/lib/leaflet_plugin/leaflet.vector-markers/leaflet-vector-markers.css" rel="stylesheet">

        <link href="core/lib/ionic/css/ionic.css" rel="stylesheet">

        <link href="core/css/font-awesome.min.css" rel="stylesheet">
        <link href="core/lib/leaflet/leaflet.css" rel="stylesheet">
        <!-- <link href='core/css/mapbox2.2.2.min.css' rel='stylesheet' /> -->
        <!-- <link href="core/css/fonts.css" rel="stylesheet" />
        <link href="core/css/style.min.css" rel="stylesheet" /> -->

        <link rel="stylesheet" type="text/css" href="core/css/fonts.css" media="none" onload="document.addEventListener('DOMContentLoaded', function() {setTimeout(function() {document.body.className+=' fontsloaded';}, 1000);}); this.media='all';">
        <link rel="stylesheet" type="text/css" href="core/css/style.min.css" media="none" onload="this.media='all';">
        <link rel="stylesheet" type="text/css" href="resources/css/custom-style.css" media="none" onload="this.media='all';">
        <!-- JS -->

        <!-- LIB -->
        <script src="core/lib/proj4js/proj4.js"></script>
        <script src="core/lib/LPF.js"></script>
        <script src="core/lib/jquery/dist/jquery.min.js"></script>
        <script src="core/lib/ionic/js/ionic.bundle.min.js"></script>

        <script src="core/lib/clipboard/dist/clipboard.min.js"></script>
        <script src="core/lib/jsSHA/src/sha1.js"></script>
        <script src="core/lib/porter-stemmer/porterStemmer1980.min.js"></script>
        <script src="core/lib/js-search/dist/js-search.min.js"></script>
        <script src="core/lib/igTruncate/igTruncate.js"></script>
        <script src="core/lib/JsBarcode/dist/JsBarcode.all.min.js"></script>

        <script src="core/lib/leaflet/leaflet.js"></script>
        <script src="core/lib/leaflet_plugin/leaflet-hash-mod/leaflet-hash.js"></script>
        <script src="core/lib/leaflet_plugin/leaflet.groupedlayercontrol/leaflet.groupedlayercontrol.min.js"></script>
        <script src="core/lib/leaflet_plugin/leaflet.utfgrid.js"></script>
        <script src="core/lib/leaflet_plugin/leaflet.vector-markers/leaflet-vector-markers.min.js"></script>
        <script src="core/lib/leaflet_plugin/leaflet-control-locate/L.Control.Locate.min.js"></script>
        <script src="core/lib/leaflet_plugin/leaflet-markercluster/leaflet.markercluster.js"></script>
        <script src="core/lib/leaflet_plugin/leaflet.kkn.min.js"></script>
        <script src="core/lib/leaflet_plugin/leaflet.geometryutil.js"></script>
        <script src="core/lib/leaflet_plugin/leaflet.almostover.js"></script>
        <script src="core/lib/leaflet_plugin/L.UTFGrid-min.js"></script>
        <script src="core/lib/leaflet_plugin/leaflet-polylinedecorator/dist/leaflet.polylineDecorator.js"></script>
        <script src="core/lib/leaflet_plugin/leaflet-knn/leaflet-knn.js"></script>

        <script src="core/lib/angular-translate/angular-translate.min.js"></script>
        <script src="core/lib/angular-translate-loader-static-files/angular-translate-loader-static-files.min.js"></script>
        <script src="core/lib/leaflet_plugin/sql.js"></script>
        <script src="core/lib/leaflet_plugin/Leaflet.TileLayer.MBTiles.js"></script>
        <script src="core/lib/ngCordova/dist/ng-cordova.js"></script>
        <script src="core/lib/ng-country-select/dist/ng-country-select.js"></script>
        <script src="core/lib/pouchdb/pouchdb.min.js"></script>
        <script src="cordova.js"></script>

        <!-- DEVELOP -->
        <!-- <script src="core/lib/connector.js" data-channel="mp-vc" id="consolerescript"></script> -->
        <!-- <script src="_cordova/cordova.js"></script> -->

        <!-- APP -->

        <script type="text/javascript">
            var templateBasePath = 'core/',
                templateCustomPath = '';
        </script>

        <script src="core/js/app.js"></script>

        <script src="core/js/settings/configProvider.js"></script>
        <script src="core/js/settings/translate.js"></script>
        <script src="core/js/settings/overwrite.js"></script>
        <script src="core/js/settings/routes.js"></script>
        <script src="core/js/settings/run.js"></script>
        <script src="core/js/settings/filters.js"></script>
        <script src="core/js/settings/compile.js"></script>

        <script src="config.js"></script>

        <script src="core/js/services/communication.factory.js"></script>
        <script src="core/js/services/auth.factory.js"></script>
        <script src="core/js/services/account.factory.js"></script>
        <script src="core/js/services/tracking.factory.js"></script>
        <script src="core/js/services/search.factory.js"></script>
        <script src="core/js/services/model.factory.js"></script>
        <script src="core/js/services/utils.factory.js"></script>
        <script src="core/js/services/offline.factory.js"></script>
        <script src="core/js/services/map.factory.js"></script>
        <script src="core/js/services/languages.factory.js"></script>
        <script src="core/js/services/package.factory.js"></script>

        <script src="core/js/components/languages/languages.controller.js"></script>
        <script src="core/js/components/welcome/welcome.controller.js"></script>
        <script src="core/js/components/coupons/coupons.controller.js"></script>
        <script src="core/js/components/packages/packages.controller.js"></script>
        <script src="core/js/components/settings/settings.controller.js"></script>
        <script src="core/js/components/search/search.controller.js"></script>
        <script src="core/js/components/mapView/mapView.controller.js"></script>
        <script src="core/js/components/list/list.controller.js"></script>
        <script src="core/js/components/card/card.controller.js"></script>
        <script src="core/js/components/details/details.controller.js"></script>
        <script src="core/js/components/detailRoute/detailRoute.controller.js"></script>
        <script src="core/js/components/main/main.controller.js"></script>
        <script src="core/js/components/menu/menu.controller.js"></script>
        <script src="core/js/components/custom/custom.controller.js"></script>
        <script src="core/js/components/webmapp/webmapp.controller.js"></script>
        <script src="core/js/components/attribution/attribution.controller.js"></script>
        <script src="core/js/components/home/home.controller.js"></script>
        <script src="core/js/components/help/help.controller.js"></script>
        <script src="core/js/components/detailTaxonomy/detailTaxonomy.controller.js"></script>
        <script src="core/js/components/taxonomy/taxonomy.controller.js"></script>

        <script src="core/js/components/popupOpener/popupOpener.controller.js"></script>


    </head>

    <body ng-app="webmapp">
        <!-- <h1 style="
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        ">LOADING ...</h1> -->
        <div
            style="
                border-top: 16px solid #3D3D3B;
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                margin-top: -60px;
                margin-left: -60px;
            "
            class="loader">
        </div>
        <ion-nav-view></ion-nav-view>
        <!-- <ion-nav-view style="top: 15px"></ion-nav-view> -->
    </body>
</html>

EOS;

