<?php

// Task per la realizzazione della mappa interattiva
// del Sentiero Italia (SIMAP)
class WebmappSIMapFindGhostTask extends WebmappAbstractTask {

    // Variabili relative a WP
    private $wp;
    private $wp_all_tracks;
    private $osm_id_list = array();

    // OUTPUT (json con tutte le informazioni)
    private $out;

	public function check() {
        // NO parameters no check
        return TRUE;
    }

    public function process() {
        echo "Verifico esistenza di track con OSMID non più esistente \n\n";
        $this->wp = new WebmappWP('simap');
        $this->loadTracksFromEP();
        $this->write();
        $this->end();
        return TRUE;
    }

    private function loadTracksFromEP() {
        $this->all_tracks = $this->wp->getAllTracksLayer($this->getRoot().'/geojson');
        foreach ($this->all_tracks->getFeatures() as $id => $track) {
            echo "processing Track $id ... ";
            $info = array();
            $info['wp_edit'] = "http://simap.be.webmapp.it/wp-admin/post.php?post=$id&action=edit&lang=it";
            if(!$track->hasProperty('osmid')) {
                echo "MISSING";
                $info['osmid_status'] = 'MISSING';
                $this->out[$id]=$info;
            } else {
                if(!$track->hasGeometry()) {
                    echo "INVALID";
                    $osmid = $track->getProperty('osmid');
                    $info['osmid'] = $osmid;
                    $info['osmid_status'] = 'INVALID';
                    $info['osm'] = "https://www.openstreetmap.org/relation/$osmid";
                    $this->out[$id]=$info;
                }
            }
            echo "\n";
        }
    }
    private function write() {
        // Tutte le tappe (per update con wm-cli)
        $fname = $this->getRoot().'/resources/si_ghost_'.date('Ymd').'.json';
        file_put_contents($fname,json_encode($this->out));
    }

}
