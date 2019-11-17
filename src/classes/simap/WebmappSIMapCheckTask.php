<?php

// Task per la realizzazione della mappa interattiva
// del Sentiero Italia (SIMAP)
class WebmappSIMapCheckTask extends WebmappAbstractTask {

    private $regioni_osm = 
array(
    "7011030" => "z-sardegna",
    "7011950" => "v-sicilia",
    "7125614" => "u-calabria",
    "7164643" => "t-basilicata",
    "7186477" => "s-campania",
    "9290765" => "r-puglia",
    "7220974" => "q-molise",
    "7401588" => "p-abruzzo",
    "7246181" => "m-lazio",
    "7448629" => "n-umbria-marche",
    "7468319" => "l-toscana-emilia-romagna",
    "7561168" => "g-liguria",
    "9521613" => "f-valle-d-aosta",
    "7029511" => "e-piemonte",
    "7029512" => "d-lombardia",
    "7029513" => "c-trentino-alto-adige",
    "7029514" => "b-veneto",
    "7332771" => "a-friuli-venezia-giulia"
);
    
    // Variabili relative a WP
    private $wp;
    // Tassonomia places_to_go
    private $wp_regioni;

    // OUTPUT (json con tutte le informazioni)
    private $out;

    private $layers;
    private $limit=0;
    private $sleep=0;
    private $limit_region = array();

    private $all_tracks;
    private $all_tracks_osmid_mapping;

	public function check() {
        // Other checks

        // END CHECK
        return TRUE;
    }

    public function process() {
        echo "Verifico la struttura dati per la creazione del Sentiero Italia \n\n";
        $this->wp = new WebmappWP('simap');

        // Scarica i places
        // Verifica: devono essere presenti tutti e 18 (ID fissato, da associare agli ID di OSM)
        $this->processWpRegioni();

        // Scarica i meta di tutte le route (no geometry)

        // Scarica tutte le track dal sito

        // Loop su tutte le relation scaricate da OSM e effettua per ogni relation al seguente verifica:
        // 1. esiste track corrispondente nel sito
        // Se esiste
        // 2. La regione associata con tassonomia sia quella corretta
        // 3. Il tiolo sia quello corretto
        // Fasi successive
        // Controllo dello START POI
        // Controllo del PREV/NEXT

        // Output in un json human-readable che può essere poi usato da un task di wp per aggiornare la piattaforma


        die();

        $path = $this->getRoot().'/geojson';

        // Getting POI from WP
        $wp = new WebmappWP('simap');
        echo "\n\nGetting POIS from WP\n\n";
        $all_pois = $wp->getAllPoisLayer($path);
        $all_pois->setId('all-pois');
        $all_pois->setLabel('Punti tappa');
        $all_pois->setColor('#dd3333');
        $all_pois->setIcon('wm-icon-flag');

        echo "\n\nGetting TRACKS from WP\n\n";
        $this->all_tracks = $wp->getAllTracksLayer($path);
        foreach ($this->all_tracks->getFeatures() as $id => $track) {
            $this->all_tracks_osmid_mapping[$track->getProperty('osmid')]=$id;
        }


        $italia = new WebmappOSMSuperRelation(1021025);
        foreach ($italia->getMembers() as $ref => $member ) {
            // Processa solo le regioni presenti in limit_region (se non vuoto)
            if(count($this->limit_region)==0) {
                $this->processRegion($ref);
            }
            else if (in_array($ref,$this->limit_region)) {
                $this->processRegion($ref);                
            }
            else {
                echo "SKIPPING REF $ref (not in limit_region)\n";
            }
        }
        // WRITING LAYERS
        echo "\n\n\n WRITING LAYERS AND RELATIONS:\n\n";
        foreach ($this->layers as $layer) {
            echo "Writing Layer ".$layer->getName()."\n";
            $layer->write($path);
            $layer->writeAllFeatures($path);
        }
        $all_pois->write($path);

        // Crea la mappa
        $m = new WebmappMap($this->project_structure);
        $m->buildOfflineConfArray();
        foreach ($this->layers as $layer) {
            $m->addTracksWebmappLayer($layer);
        }
        $m->addPoisWebmappLayer($all_pois);
        $m->buildStandardMenu();
        $m->writeConf();
        $m->writeIndex();
        $m->writeInfo();

        $this->end();
        return TRUE;
    }

    private function processWpRegioni() {
        $this->wp->loadTaxonomy('where');
        $taxs = $this->wp->getTaxonomies();
        $this->wp_regioni=$taxs['where'];
        $slugs = array_column($this->wp_regioni,'slug');

        if(count($slugs)!=18) {
            echo "FATAL ERROR: il numero delle regioni nella Piattaforma editoriale è errato\n\n";
            print_r($slugs);
            die();
        }

        echo "Numero delle regioni OK - procedo\n";

        // Ferifica presenza di tutte le regioni attese

        $error = false;
        $regioni_error = array();

        foreach($this->regioni_osm as $osmid => $slug) {
            if(!in_array($slug, $slugs)) {
                $error = true;
                $regioni_error[]=$slug;
            }
        }

        if($error) {
            echo "FATAL ERROR: mancano una o più regioni nella piattaforma editoriale\n\n";
            print_r($regioni_error);
            die();
        }

        echo "Regioni OK - procedo\n\n";

    }

    private function processRegion($id) {
        $regione = new WebmappOSMSuperRelation($id);

        $name = $this->regioni_osm[$id];
        // $regione->getTag('name')
        $layer = new WebmappLayer($this->regioni_osm[$id]);
        $layer->setLabel($this->regioni_osm[$id]);
        $layer->setId($id);
        echo "Processing Regione ($id) ";
        echo $regione->getTag('name') . "\n";
        $count = 0 ;
        foreach ($regione->getMembers() as $ref => $member) {
            if ($this->limit >0 && $count >= $this->limit ) break;
            if ($member['type']=='relation') {
                try {
                    $tappa = new WebmappOSMRelation($ref);
                    $tappa_name = $tappa->getTag('name');
                    echo "  -> Processing TAPPA ($ref) $tappa_name ... ";


                    $track = $tappa->getTrack();
                    // Gestione Liguria
                    if($id==7561168 && $tappa->hasTag('alt_name')) {
                        $track->addProperty('name',$tappa->getTag('alt_name'));
                    }
                    // ENRICH from WP:
                    if(array_key_exists($ref,$this->all_tracks_osmid_mapping)) {
                        echo "enrich ";
                        $wpt = $this->all_tracks->getFeature($this->all_tracks_osmid_mapping[$ref]);
                        $track->addProperty('description',$wpt->getProperty('description'));
                        if ($wpt->hasProperty('image'))
                            $track->addProperty('image',$wpt->getProperty('image'));
                        if($wpt->hasProperty('imageGallery'))
                            $track->addProperty('imageGallery',$wpt->getProperty('imageGallery'));
                        if($wpt->hasProperty('wp_edit')) {
                            $track->addProperty('wp_edit',$wpt->getProperty('wp_edit'));                            
                        }
                    } else {
                        echo "can't enrich (not in WP) ";
                    }
                    // Gestione del colore
                    // Fare una lista di sybmols validi
                    $red_symbols = array(
                         'red:red:white_stripe:SI:black',
                         'red:red:white_stripe:AV:black'
                        );
                    $color = '#636363';
                    if($track->hasProperty('source') && 
                       $track->getProperty('source') == 'survey:CAI') {
                        $color = '#A63FD1';
                        if($track->hasProperty('osmc:symbol') && 
                           in_array($track->getProperty('osmc:symbol'),$red_symbols)) {
                            $color = '#E35234';
                        }
                    }
                    $track->addProperty('color',$color);
                    // GEstione dei LINK
                    $url = $this->getUrlBase();
                    $url_gpx = $url.'/resources/'.$ref.'.gpx';
                    $url_kml = $url.'/resources/'.$ref.'.kml';
                    $url_geojson = $url.'/geojson/'.$ref.'.geojson';
                    $url_osm = 'https://www.openstreetmap.org/relation/'.$ref;
                    $url_wmt = 'https://hiking.waymarkedtrails.org/#route?id='.$ref;
                    $url_analyzer = 'http://ra.osmsurround.org/analyzeRelation?relationId='.$ref;
                    $url_ideditor = 'https://www.openstreetmap.org/edit?relation='.$ref;

                    $link_gpx = '<a href="'.$url_gpx.'">Scarica il tracciato in formato gpx</a>';
                    $link_kml = '<a href="'.$url_kml.'">Scarica il tracciato in formato kml</a>';
                    $link_geojson = '<a href="'.$url_geojson.'" download>Scarica il tracciato della tappa in formato geojson </a>';
                    $link_osm = '<a href="'.$url_osm.'">Vedi il tracciato su OpenStreetMap </a>';
                    $link_wmt = '<a href="'.$url_wmt.'">Vedi il tracciato su WayMarkedTrails </a>';
                    $link_analyzer = '<a href="'.$url_analyzer.'">Vedi il tracciato su OSM Relation Analyzer </a>';
                    $link_ideditor = '<a href="'.$url_ideditor.'">Modifica il tracciato con ID Editor di OpenStreetMap </a>';

                    if($track->hasProperty('description')) {
                        $new_desc = $track->getProperty('description') .
                        "<p>".
                        $link_gpx."<br/>".
                        $link_kml."<br/>".
                        $link_geojson."<br/>".
                        $link_osm."<br/>".
                        $link_wmt."<br/>".
                        $link_analyzer."<br/>".
                        $link_ideditor."</p>";
                        $track->addProperty('description',$new_desc);                        
                    }

                    $path_res = $this->getRoot().'/resources/';
                    $track->addEle();
                    $track->writeGPX($path_res);
                    $track->writeKML($path_res);

                    $layer->addFeature($track);
                    $count++;
                    if($this->sleep >0 ) {
                        sleep($this->sleep);
                    } 
                    echo "... DONE\n";                   
                } catch (Exception $e) {
                    echo "  ===> WARNING CAN'T LOAD MEMBER ($ref ".get_class($e).") ... SKIP \n";                
                }
            } else {
                echo "  ===> WARNING MEMBER IS NOT RELATION ($ref) ... SKIP \n";
            }
        }
        $this->layers[]=$layer;
        echo "\n\n";
    }

}
