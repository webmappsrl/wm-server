<?php
// Task per la realizzazione della mappa interattiva
// del Sentiero Italia (SIMAP)
class WebmappSIMapTask extends WebmappAbstractTask {

    private $layers;
    private $limit=0;

	public function check() {
        // Check mandatory parameters;
        if(array_key_exists('limit', $this->options)) {
            $this->limit=$this->options['limit'];
        }
        // Other checks

        // END CHECK
        return TRUE;
    }

    public function process() {
        echo "Processing Sentiero Italia \n\n";
        $italia = new WebmappOSMSuperRelation(1021025);
        foreach ($italia->getMembers() as $ref => $member ) {
            $this->processRegion($ref);

        }
        // WRITING LAYERS
        $path = $this->getRoot().'/geojson';
        echo "\n\n\n WRITING LAYERS AND RELATIONS:\n\n";
        foreach ($this->layers as $layer) {
            echo "Writing Layer ".$layer->getName()."\n";
            $layer->write($path);
            $layer->writeAllFeatures($path);
        }

        // Crea la mappa
        $m = new WebmappMap($this->project_structure);
        $m->buildOfflineConfArray();
        foreach ($this->layers as $layer) {
            $m->addTracksWebmappLayer($layer);
        }
        $m->buildStandardMenu();
        $m->writeConf();
        $m->writeIndex();
        $m->writeInfo();

        echo "\n\n====== DONE\n\n";
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
                $tappa = new WebmappOSMRelation($ref);
                $tappa_name = $tappa->getTag('name');
                $layer->addFeature($tappa->getTrack());
                $count++;
                echo "  -> Processing TAPPA ($ref) $tappa_name\n";
            } else {
                echo "  ===> WARNING MEMBER IS NOT RELATION ($ref) ... SKIP \n";
            }
        }
        $this->layers[]=$layer;
        echo "\n\n";
    }

}
