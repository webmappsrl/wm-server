<?php

// Task per la realizzazione della mappa interattiva
// del Sentiero Italia (SIMAP)
class WebmappSIMapCheckTask extends WebmappAbstractTask {

    // Variabili utili
    private $path;

    // Variabili OSM
    private $osm_italia_id = 1021025;
    private $osm_regioni = array();
    private $osm_tappe = array() ;

    // Variabili relative a WP
    private $wp;
    // Tassonomia places_to_go
    private $wp_regioni = array();
    private $wp_all_tracks;


    // MAPPING DELLE REGIONI
    private $regioni_mapping = 
    array(
        "7011030" => "z-sardegna",
        "7011950" => "v-sicilia",
        "7125614" => "u-calabria",
        "7164643" => "t-basilicata",
        "7186477" => "s-campania",
        "9290765" => "r-puglia",
        "7220974" => "q-molise",
        "7401588" => "p-abruzzo",
        "7246181" => "o-lazio",
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

    private $regioni_mapping_wpid;
    
    // OUTPUT (json con tutte le informazioni)
    private $out;
    private $out_osm = array();
    private $out_new = array();


	public function check() {
        // NO parameters no check
        return TRUE;
    }

    public function process() {
        $this->path = $this->getRoot().'/geojson';

        echo "Verifico la struttura dati per la creazione del Sentiero Italia \n\n";
        $this->wp = new WebmappWP('simap');

        // 1. Scarica i places
        // Verifica: devono essere presenti tutti e 18 (ID fissato, da associare agli ID di OSM)
        $this->processWpRegioni();

        // 2. Scarica i meta di tutte le route (no geometry)
        // Verifica: devono essere presenti tutti e 18 (ID fissato, da associare agli ID di OSM)
        $this->processOSMRoutes();

        // 3. Scarica tutte le track dal sito
        $this->loadTracksFromEP();

        // 4. Loop su tutte le relation scaricate da OSM e effettua per ogni relation al seguente verifica:
        // 4.1. esiste track corrispondente nel sito
        // Se esiste
        // 4.2. La regione associata con tassonomia sia quella corretta
        // 4.3. Il tiolo sia quello corretto (OSMID)
        // 4.4. I campi from e to siano valorizzati correttamente (in accordo con OSM)
        // 4.5. Controllo dello START POI / END POI
        // 4.6. Controllo del PREV/NEXT
        $this->checkRelations();

        // Output in un json human-readable che può essere poi usato da un task di wp per aggiornare la piattaforma
        $this->write();

        $this->end();

        return TRUE;
    }

    private function processWpRegioni() {
        // Scarica i places
        // Verifica: devono essere presenti tutti e 18 (ID fissato, da associare agli ID di OSM)

        $this->wp->loadTaxonomy('where');
        $taxs = $this->wp->getTaxonomies();
        $this->wp_regioni=$taxs['where'];

        // Build $this->regioni_mapping_wpid
        $regioni_mapping_flip = array_flip($this->regioni_mapping);
        foreach($this->wp_regioni as $termid => $term) {
            $this->regioni_mapping_wpid[$regioni_mapping_flip[$term['slug']]]=$termid;
        }

        $slugs = array_column($this->wp_regioni,'slug');

        if(count($slugs)!=18) {
            echo "FATAL ERROR: il numero delle regioni nella Piattaforma editoriale è errato\n\n";
            print_r($slugs);
            die();
        }

        echo "Numero delle regioni in piattaforma OK - procedo\n";

        // Ferifica presenza di tutte le regioni attese

        $error = false;
        $regioni_error = array();

        foreach($this->regioni_mapping as $osmid => $slug) {
            if(!in_array($slug, $slugs)) {
                $error = true;
                $regioni_error[]="$osmid ($slug)";
            }
        }

        if($error) {
            echo "FATAL ERROR: mancano una o più regioni nella piattaforma editoriale\n\n";
            print_r($regioni_error);
            die();
        }

        echo "Regioni in piattaforma OK - procedo\n\n";

    }

    private function processOSMRoutes() {
        // Scarica i meta di tutte le route (no geometry)
        // Verifica: devono essere presenti tutti e 18 (ID fissato, da associare agli ID di OSM)

        echo "Scarico SI da OSM";
        $italia = new WebmappOSMSuperRelation($this->osm_italia_id);
        echo "\n";
        $refs = array();
        foreach ($italia->getMembers() as $ref => $member ) {
            $refs[]=$ref;
            echo "Scarico SI regione $ref da OSM";
            $regione = $regione = new WebmappOSMSuperRelation($ref);
            $this->osm_regioni[$ref]=$regione;
            echo "\n";
        }

        // Check sui ref
        if (count($refs)!=18) {
            echo "FATAL ERROR: Il numero delle regioni di OSM è diverso da 18 (20-2)\n";
            print_r($refs);
            die();
        }
        echo "Numero delle regioni in OSM ok\n";
        $error = false;
        $regioni_error = array();

        foreach($this->regioni_mapping as $osmid => $slug) {
            if(!in_array($osmid, $refs)) {
                $error = true;
                $regioni_error[]="$osmid ($slug)";
            }
        }

        if($error) {
            echo "FATAL ERROR: mancano una o più regioni in OSM\n\n";
            print_r($regioni_error);
            die();
        }
        echo "Regioni in OSM OK - procedo\n\n";

        // Process single region
        foreach ($refs as $ref) {
            $this->processOSMRegion($ref);
        }
    }

    private function processOSMRegion($ref_regione) {
        // Recupera tutte le relation della regione e le mette in un array denominato "tappe"
        echo "Processo Regione $ref_regione ".$this->regioni_mapping[$ref_regione]."\n";
        foreach ($this->osm_regioni[$ref_regione]->getMembers() as $ref => $member) {
            if ($member['type']=='relation') {
                try {
                    echo "  -> Processing TAPPA ($ref)";
                    $tappa = new WebmappOSMRelation($ref);
                    $tappa_name = $tappa->getTag('name');
                    echo "$tappa_name ... ";
                    $this->osm_tappe[$ref]=$tappa;
                    echo "... DONE\n";                   
                } catch (Exception $e) {
                    echo "  ===> WARNING CAN'T LOAD MEMBER ($ref ".get_class($e).") ... SKIP \n";                
                }
            } else {
                echo "  ===> WARNING MEMBER IS NOT RELATION ($ref) ... SKIP \n";
            }
        }
        echo "\n";
    }

    private function loadTracksFromEP() {
        $this->all_tracks = $this->wp->getAllTracksLayer($this->path);
        foreach ($this->all_tracks->getFeatures() as $id => $track) {
            $name = $track->getProperty('name');
            $osmid = $track->getProperty('osmid');
            $this->tracks_osm_mapping[$track->getProperty('osmid')]=$id;
            echo "Ho caricato e aggiungo la traccia $name (ID:$id osmid:$osmid)\n";
        }
    }

    private function checkRelations() {
        // 4. Loop su tutte le relation scaricate da OSM e effettua per ogni relation al seguente verifica:

        // loop sulle regioni
        foreach ($this->regioni_mapping as $ref_regione => $slug) {
            echo "Verifico le tappe della regione $slug (osmid:$ref_regione)";
            // loop sulle singole tappe
            foreach ($this->osm_regioni[$ref_regione]->getMembers() as $ref => $member) {
            if ($member['type']=='relation') {
                $this->verifyRelation($ref,$ref_regione);
            } else {
                echo "  ===> WARNING MEMBER IS NOT RELATION ($ref) ... SKIP \n";
            }
        }

            echo "\n";
        }

    }

    private function verifyRelation($osmid,$ref_regione) {
        $tappa = $this->osm_tappe[$osmid];
        $tappa_info = array();
        $tappa_info['check_track'] = false;

        // Check OSM (name, from, to)
        $tappa_info['osm_edit'] = 'https://www.openstreetmap.org/edit?relation='.$osmid;

        $osm_check = true;
        if($tappa->hasTag('name')) {
            $name = $tappa->getTag('name');
            $tappa_info['osm_check_name'] = true;
        } else {
            $name = 'UNKNOWN';
            $tappa_info['osm_check_name'] = false;
            $osm_check = false;        
        }
        $tappa_info['osm_name'] = $name;

        if($tappa->hasTag('from')) {
            $from = $tappa->getTag('from');
            $tappa_info['osm_check_from'] = true;
        } else {
            $from = 'UNKNOWN';
            $tappa_info['osm_check_from'] = false;
            $osm_check = false;        
        }
        $tappa_info['osm_from'] = $from;

        if($tappa->hasTag('to')) {
            $to = $tappa->getTag('to');
            $tappa_info['osm_check_to'] = true;
        } else {
            $to = 'UNKNOWN';
            $tappa_info['osm_check_to'] = false;
            $osm_check = false;        
        }
        $tappa_info['osm_to'] = $to;

        $tappa_info['wp_expected_where'] = $this->regioni_mapping_wpid[$ref_regione];
        echo "   -> Verifico tappa $name $osmid (ref_regione:$ref_regione)";

        // 4.1. esiste track corrispondente nel sito
        if(array_key_exists($osmid,$this->tracks_osm_mapping)) {
            // Se esiste
            $tappa_info['check_track'] = true;
            $wpid = $this->tracks_osm_mapping[$osmid];
            $tappa_info['wpid'] = $wpid;
            $track = $this->all_tracks->getFeature($wpid);

            // 4.2. La regione associata con tassonomia sia quella corretta
            $tappa_info['wp_where'] = -1;
            if($track->hasProperty('taxonomy')) {
                $taxs = $track->getProperty('taxonomy');
                if(isset($taxs['where'][0])) {
                    $tappa_info['wp_where'] = $taxs['where'][0];
                }                
            }
            $tappa_info['check_where'] = false;
            if ($tappa_info['wp_where']==$tappa_info['wp_expected_where']) {
                $tappa_info['check_where'] = true;
            }

            // 4.3. Il tiolo sia quello corretto (OSMID)
            $tappa_info['check_title'] = false;
            $tappa_info['wp_title'] = $track->getProperty('name');
            $tappa_info['wp_expected_tile'] = $osmid;
            if ($tappa_info['wp_title'] == $tappa_info['wp_expected_tile']) {
                $tappa_info['check_title'] = true;
            }

            // 4.4. I campi from e to siano valorizzati correttamente (in accordo con OSM)

            // 4.5. Controllo dello START POI / END POI
            // 4.6. Controllo del PREV/NEXT
        } else {
          $this->out_new[$this->regioni_mapping[$ref_regione]][$osmid]=$tappa_info;
        }


        $this->out[$this->regioni_mapping[$ref_regione]][$osmid]=$tappa_info;
        if($osm_check==false) {
            $this->out_osm[$this->regioni_mapping[$ref_regione]][$osmid]=$tappa_info;
        }
        echo "\n";
    }

    private function write() {
        // Tutte le tappe (per update con wm-cli)
        $fname = $this->getRoot().'/resources/si_check_'.date('Ymd').'.json';
        file_put_contents($fname,json_encode($this->out));

        // Errori OSM (da correggere prima)
        $fname = $this->getRoot().'/resources/si_osm_check_'.date('Ymd').'.json';
        file_put_contents($fname,json_encode($this->out_osm));

        // Tracce nuove (da inserire)
        $fname = $this->getRoot().'/resources/si_new_track_'.date('Ymd').'.json';
        file_put_contents($fname,json_encode($this->out_new));
    }

}
