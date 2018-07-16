<?php

// Gestione di una mappa (creazione file index, file di configurazione)

class WebmappMap {

	private $map;
    private $structure;
    private $type;
    private $title;
    private $tilesUrl;
    private $tilesType="maptile";
    private $pois_layers = array();
    private $tracks_layers = array();
    private $additional_overlay_layers = array();
    private $style = array();

    // OPTIONS section
    private $options = array();

    // Bounding BOX array associativo
    private $bb;
    private $routeID = '' ;

    // Gestione delle pagine
    private $pages = array();
    private $pages_no_first_level = false ;

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
    private $menu_pois_color = '#E79E19';
    private $menu_pois_icon = 'wm-icon-generic';
    private $menu_tracks_label = 'Percorsi';
    private $menu_tracks_color = '#E94C31';
    private $menu_tracks_icon = 'wm-icon-generic';
    private $menu_pages_title = 'About';
    private $menu_offline_label = 'Mappa offline';
    private $filterIcon = "wm-icon-layers";
    private $startUrl = "/";

    // Multilanguages
    private $has_languages = false;
    private $languages_menu_label = 'Cambia lingua';
    private $languages_list = array();
    private $languages_actual;
    private $languages = array();

    // APP Info
    private $app_id = 'it.webmapp.default' ;
    private $app_description = 'App description' ;
    private $app_icon = 'http://api.webmapp.it/resources/icon.png';
    private $app_splash = 'http://api.webmapp.it/resources/splash.png';
    
    // Sezione REPORT
    private $report = array();

    // Sezione INCLUDE 
    private $include = '/config.json';

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
        $this->menu = $this->buildMenuConfArray();

    } 

    public function simpleConstruct($structure) {
       if(get_class($structure) != 'WebmappProjectStructure') {
        throw new Exception("Il parametro del costruttore della classe WebmappMap deve essere di tipo WebmappProjectStructure", 1);
       }
       $this->structure=$structure;
       $this->buildOptionsConfArray();

    }

    // Legge i dati da un URL che risponde come le API di WP
    // esempio: http://dev.be.webmapp.it/wp-json/wp/v2/map/408
    public function loadMetaFromUrl($url) {
        // ja è un abbreviazione per JSON ARRAY
        $ja = WebmappUtils::getJsonFromApi($url);

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
        // Pages and menu pages title
        if(isset($ja['pages_no_first_level']) && !empty($ja['pages_no_first_level'])) {
            $this->pages_no_first_level = $ja['pages_no_first_level'];
        } 
        if(isset($ja['pages']) && is_array($ja['pages']) && count($ja['pages'])>0) {
            foreach($ja['pages'] as $page_obj) {
                $guid = $page_obj['guid'];
                $api = preg_replace('|\?page_id=|', 'wp-json/wp/v2/pages/', $guid);
                $page_info = WebmappUtils::getJsonFromApi($api);
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

        // Informazioni per la pubblicazione delle APP
        if(isset($ja['app_id']) && $ja['app_id'] != '' ) {
            $this->app_id = $ja['app_id'];
        }
        if(isset($ja['app_description']) && $ja['app_description'] != '' ) {
            $this->app_description = $ja['app_description'];
        }

        if(isset($ja['app_icon']) && $ja['app_icon'] != '' ) {
            $headers = get_headers($ja['app_icon']);
            if (preg_match('/200/', $headers[0])) {
                $this->app_icon = $this->structure->getUrlBase() . '/resources/icon.png';
                $app_icon_path = $this->structure->getRoot() . '/resources/icon.png';
                file_put_contents($app_icon_path, fopen($ja['app_icon'], 'r'));
            }
        }

        if(isset($ja['app_splash']) && $ja['app_splash'] != '' ) {
            $headers = get_headers($ja['app_splash']);
            if (preg_match('/200/', $headers[0])) {
                $this->app_splash = $this->structure->getUrlBase() . '/resources/splash.png';
                $app_splash_path = $this->structure->getRoot() . '/resources/splash.png';
                file_put_contents($app_splash_path, fopen($ja['app_splash'], 'r'));
            }
        }

        // Cambiata la gestione dell'attivazione del Multilingue: viene settato il campo


        if (isset($ja['default_language']) && !empty($ja['default_language'])) {
            $lang=$ja['default_language'];
        } else if (isset($ja['wpml_current_locale']) && !empty($ja['wpml_current_locale'])) {
            $lang=$ja['wpml_current_locale'];
            $lang=preg_replace('|_.*$|','', $lang);
        } 
        else {
            $lang='it';
        }
        $this->languages_actual=$lang;
        $this->buildLanguagesConfArray();            

        if (isset($ja['has_languages']) && $ja['has_languages']==true) {
            $this->has_languages=true;
            if (isset($ja['languages_menu_label']) && !empty($ja['languages_menu_label'])) {
                $this->languages_menu_label=$ja['languages_menu_label'];
            }
            if (isset($ja['languages_list']) && !empty($ja['languages_list'])) {
                $this->languages_list=$ja['languages_list'];
            }
            else {
                $this->languages_list='it_IT,en_EN';
            }
            $this->buildLanguagesConfArray();
        }

        if (isset($ja['report_email']) && !empty($ja['report_email'])) {
            $this->setReportEmail($ja['report_email']);
        }

        if (isset($ja['report_sms']) && !empty($ja['report_sms'])) {
            $this->setReportSMS($ja['report_sms']);
        }

        // OPTIONS
        if(isset($ja['activate_zoom_control']) && $ja['activate_zoom_control']==true) {
            $this->options['activateZoomControl']=true;
        }
        if(isset($ja['hide_webmapp_page']) && $ja['hide_webmapp_page']==true) {
            $this->options['mainMenuHideWebmappPage']=true;
        }
        if(isset($ja['hide_attribution_page']) && $ja['hide_attribution_page']==true) {
            $this->options['mainMenuHideAttributionPage']=true;
        }

        if(isset($ja['show_accessibility_buttons']) && $ja['show_accessibility_buttons']==true) {
            $this->options['showAccessibilityButtons']=true;
        }

        // ADVANCED OPTIONS
        if(isset($ja['additional_overlay_layers']) && !empty($ja['additional_overlay_layers'])) {
            $this->additional_overlay_layers=json_decode($ja['additional_overlay_layers'],TRUE);

            // AGIUNGI UN ID ARTIFICIALE
            $counter = 1 ;
            $layers= array();
            foreach ($this->additional_overlay_layers as $layer) {
                if(!isset($layer['id'])) {
                    $layer['id'] ='ADD-' . $counter;
                    $counter ++ ;
                }
                $layers[]=$layer;
            }
            $this->additional_overlay_layers=$layers;
        }


        $this->buildOptionsConfArray();
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

    public function getRouteId() {
        return $this->routeID;
    }

    public function setRouteId($id) {
        $this->routeID = $id;
    }

    public function setFilterIcon($v){
        $this->filterIcon=$v;
    }

    public function setStartUrl($v){
        $this->startUrl=$v;
    }

    public function hasRouteId() {
        if ($this->routeID=='') return FALSE;
        return TRUE;
    }

    // SETTERS
    public function setTitle($title) {
        $this->title = $title;
    }
    public function setTilesType($type) {
        $this->tilesType = $type;
    }
    public function setInclude($v) {
        $this->include = $v;
    }
    public function addOption($key,$val) {
        $this->options[$key]=$val;
    }

    public function getLanguages() {
        return $this->languages;
    }

    public function getLanguagesList() {
        return $this->languages_list;
    }

    /**
    {
    maxZoom: 17,
    minZoom: 7,
    defZoom: 9,
    center: {
        lat: 43.71615042181035,
        lng: 10.396804999999983
    },
    bounds: {
        southWest: [
            43.704367081989325,
            10.366287231445314
        ],
        northEast: [
            43.72794077927287,
            10.427398681640627
        ]
    }
    }
   **/
    public function setBB($latMin,$lngMin,$latMax,$lngMax,$maxZoom=17,$minZoom=7,$defZoom=9) {
        $this->bb['maxZoom']=$maxZoom;
        $this->bb['minZoom']=$minZoom;
        $this->bb['defZoom']=$defZoom;
        $this->bb['center']['lat']=($latMax+$latMin)/2;
        $this->bb['center']['lng']=($lngMin+$lngMax)/2;
        $this->bb['bounds']['southWest']=array($latMin,$lngMin);
        $this->bb['bounds']['northEast']=array($latMax,$lngMax);
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
        $this->addLayer('pois',$layer);
    }

    public function addTracksWebmappLayer($layer) {
        $this->addLayer('tracks',$layer);
    }

    public function addLayer($type,$layer) {

       $id = $layer->getId();
       $url = $layer->getName().'.geojson';
       $label = $layer->getLabel();
       $color = $layer->getColor();
       $icon = $layer->getIcon();
       $alert = $layer->getAlert();
       $showByDefault = $layer->getShowByDefault();
       $languages = $layer->getLanguages();

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
            'id' => $id,
            'geojsonUrl' => $url,
            'label' => $label,
            'color' => $color,
            'icon' => $icon,
            'showByDefault' => $showByDefault,
            'type' => $type_label,
            'alert' => $alert
            );

        if(is_array($languages) && count($languages)>0) {
          $layer['languages'] = $languages;
        }

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

        $version = '1.0.0';
        // Gestione versione eventualmente già esistente
        if(file_exists($this->structure->getRoot() . '/info.json')) {
            $ja = json_decode(file_get_contents($this->structure->getRoot() . '/info.json'),true);
            if (isset($ja['config.xml']['version'])) {
                $version = $ja['config.xml']['version'];                
            }
        }
        $info = array();
        $info['configJs'] = $this->structure->getUrlBase() . '/config.js';
        $info['configJson'] = $this->structure->getUrlBase() . '/config.json';
        $info['config.xml']['id'] = $this->app_id;
        $info['config.xml']['description'] = $this->app_description;
        $info['config.xml']['name'] = $this->title;
        $info['config.xml']['version'] = $version;
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

        if ($this->hasRouteId()) {
            $this->conf_array['routeID'] = (int) $this->routeID;
        }

        // OPTIONS
        $this->conf_array['OPTIONS'] = $this->options;

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
        $pages = $this->pages;
        if ($this->has_offline) {
           array_push($pages,array(
                               "label" => $this->menu_offline_label,
                               "type" => 'settings',
                               "isCustom" => false));
        }
        if ($this->has_languages) {
           array_push($pages,array(
                               "label" => $this->languages_menu_label,
                               "type" => 'languages',
                               "isCustom" => false));
        }
        $this->conf_array['PAGES'] = $pages;            

        // OVERLAY_LAYERS
        $this->conf_array['OVERLAY_LAYERS'] = array_merge($this->pois_layers,$this->tracks_layers,$this->additional_overlay_layers);

        $this->conf_array['OFFLINE'] = $this->offline;

        $this->conf_array['LANGUAGES'] = $this->languages;

        $this->conf_array['REPORT'] = $this->report;

        if (!empty($this->include)) {
            $this->conf_array['INCLUDE']['url']=$this->include;
        }


    }

    private function buildOptionsConfArray() {
        $this->options["title"] = "$this->title";
        $this->options["startUrl"] = $this->startUrl;
        $this->options["useLocalStorageCaching"] = false;
        $this->options["advancedDebug"] = false;
        $this->options["hideHowToReach"] = true;
        $this->options["hideMenuButton"] = false;
        $this->options["hideExpanderInDetails"] = false;
        $this->options["hideFiltersInMap"] = false;
        $this->options["hideDeactiveCentralPointer"] = false;
        $this->options["hideShowInMapFromSearch"] = true;
        $this->options["avoidModalInDetails"] = true;
        $this->options["useAlmostOver"] = false;
        $this->options["filterIcon"] = $this->filterIcon;
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

public function resetMenu() {
    $this->menu = array();
}

public function resetPages() {
    $this->pages = array();
}

public function resetReport() {
    $this->report = array();    
}
/**
        "REPORT": {
        "active": true,
        "type": "email",
        "defaultEmail": "alessiopiccioli@webmapp.it",
        "apiUrl": "https://api.webmapp.it/services/share.php"
    },
**/
public function activateReport($defaultEmail,$apiUrl='https://api.webmapp.it/services/share.php') {
   $this->report['active']=true;    
   $this->report['type']='email';    
   $this->report['defaultEmail']=$defaultEmail;    
   $this->report['apiUrl']=$apiUrl;    
}

public function setReportEmail($default,$apiUrl='https://api.webmapp.it/services/share.php') {
    $this->report['email']['apiUrl']=$apiUrl;
    $this->report['email']['default']=$default;
}

public function setReportSMS($default) {
    $this->report['sms']['default']=$default;
}

public function addPage($label,$type,$isCustom=true) {
   $page = array('label'=>$label,'type'=>$type,'isCustom'=>$isCustom);
   array_push($this->pages, $page);
}

public function addMenuItem($label,$type,$color='',$icon='',$items=array(),$options=array()) {
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
    if(count($options)>0){
        foreach ($options as $key => $value) {
            $item[$key]=$value;
        }
    }
    array_push($this->menu, $item);
}

public function addMenuLayerGroup($layers,$label='',$color='',$icon='') {

    if (count($this->pois_layers)>0) {
            // Add Group layer
            $items = array() ;
            foreach ($this->pois_layers as $layer) {
                $items[] = $layer['label'];
            }
            $label = $label;
            $color = $color;
            $icon = $icon;
            $this->addMenuItem($label,'layerGroup',$color,$icon,$items);
        
    }
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
            $items = array() ;
            foreach ($this->tracks_layers as $layer) {
                $items[] = $layer['label'];
            }
            $label = $this->menu_tracks_label;
            $color = $this->menu_tracks_color;
            $icon = $this->menu_tracks_icon;
            $this->addMenuItem($label,'layerGroup',$color,$icon,$items);
        }
    }

    // PAGES
    if(count($this->pages)>0) {
        if ($this->pages_no_first_level) {
            foreach ($this->pages as $page) {
                $label = $page['label'];
                $color = isset($page['color']) ? $page['color'] : '';
                $icon = isset($page['icon']) ? $page['icon'] : '';
                $this->addMenuItem($label,'page',$color,$icon);
            }
        }
        else {
            $items = array();
            foreach ($this->pages as $page) {
                $items[]=$page['label'];
            }
            $this->addMenuItem($this->menu_pages_title,'pageGroup','','',$items);
        }
    }

    // OFFLINE PAGE
    if($this->has_offline) {
        $options = array('hideInBrowser'=>true);
        $this->addMenuItem($this->menu_offline_label,'page','','',array(),$options);
    }

    // LANGUAGES
    if($this->has_languages) {
        $this->addMenuItem($this->languages_menu_label,'page');
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
                "type" => $this->tilesType,
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

private function buildLanguagesConfArray() {
    $this->languages["actual"] = $this->languages_actual;
    if (count($this->languages_list)>0) {
        $this->languages["available"] = explode(',', $this->languages_list);        
    }
}

    public function getIndex() {
    require('mapIndex.html.php');
    return $index;
    }

}