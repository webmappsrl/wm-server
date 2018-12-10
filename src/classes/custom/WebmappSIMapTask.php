<?php
// Task per la realizzazione della mappa interattiva
// del Sentiero Italia (SIMAP)
class WebmappSIMapTask extends WebmappAbstractTask {

    private $layers;
    private $limit=0;
    private $sleep=0;

    private $all_tracks;
    private $all_tracks_osmid_mapping;

	public function check() {
        // Check mandatory parameters;
        if(array_key_exists('limit', $this->options)) {
            $this->limit=$this->options['limit'];
        }
        if(array_key_exists('sleep', $this->options)) {
            $this->sleep=$this->options['sleep'];
        }
        // Other checks

        // END CHECK
        return TRUE;
    }

    public function process() {
        echo "Processing Sentiero Italia \n\n";
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
            $this->processRegion($ref);
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

    private function processRegion($id) {
        $regione = new WebmappOSMSuperRelation($id);
        $layer = new WebmappLayer($regione->getTag('name'));
        $layer->setLabel($regione->getTag('name'));
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
                    // ENRICH from WP:
                    if(array_key_exists($ref,$this->all_tracks_osmid_mapping)) {
                        echo "enrich ";
                        $wpt = $this->all_tracks->getFeature($this->all_tracks_osmid_mapping[$ref]);
                        $track->addProperty('description',$wpt->getProperty('description'));
                        if ($wpt->hasProperty('image'))
                            $track->addProperty('image',$wpt->getProperty('image'));
                        if($wpt->hasProperty('imageGallery'))
                            $track->addProperty('imageGallery',$wpt->getProperty('imageGallery'));
                    } else {
                        echo "can't enrich (not in WP) ";
                    }
                    // Gestione del colore
                    $color = '#636363';
                    if($track->hasProperty('source') && 
                       $track->getProperty('source') == 'survey:CAI') {
                        $color = '#A63FD1';
                        if($track->hasProperty('osmc_symbol') && 
                           $track->getProperty('osmc_symbol') == 'red:red:white_stripe:SI:black') {
                            $color = '#E35234';
                        }
                    }
                    $track->setColor($color);
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
