<?php

// Task per la realizzazione della mappa interattiva
// del Sentiero Italia (SIMAP)
class WebmappSIMapCheckTask extends WebmappAbstractTask {

    // Variabili OSM
    private $osm_italia_id = 1021025;
    private $osm_regioni;

    // Variabili relative a WP
    private $wp;
    // Tassonomia places_to_go
    private $wp_regioni;


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
    
    // OUTPUT (json con tutte le informazioni)
    private $out;


	public function check() {
        // NO parameters no check
        return TRUE;
    }

    public function process() {
        echo "Verifico la struttura dati per la creazione del Sentiero Italia \n\n";
        $this->wp = new WebmappWP('simap');

        // Scarica i places
        // Verifica: devono essere presenti tutti e 18 (ID fissato, da associare agli ID di OSM)
        $this->processWpRegioni();

        // Scarica i meta di tutte le route (no geometry)
        // Verifica: devono essere presenti tutti e 18 (ID fissato, da associare agli ID di OSM)
        $this->processOSMRoutes();

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

        $path = $this->getRoot().'/geojson';
        $this->end();

        return TRUE;
    }

    private function processWpRegioni() {
        // Scarica i places
        // Verifica: devono essere presenti tutti e 18 (ID fissato, da associare agli ID di OSM)

        $this->wp->loadTaxonomy('where');
        $taxs = $this->wp->getTaxonomies();
        $this->wp_regioni=$taxs['where'];
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
            $this->osm_regioni[]=$regione;
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
    }

}
