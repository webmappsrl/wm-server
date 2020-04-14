<?php

/**
 * Class WebmappIntense2ExportTask
 *
 * Come il task A recupera tutti i POI e TRACK (endpoint fissato a http://intense2.be.webmapp.it/)
 * e genera due file geojson featureCollection:
 *
 * - resources/intense2poi.geojson
 * - resources/intense2track.geojson
 *
 * Alle properties vengono aggiunte alcuni campi specifici del porgetto Intense2
 * necessari alla procedura di import. Nella fattispecie:
 *
 * Properties da aggiungere a tutti i POI e TRACK:
 * "action":"add"
 * "field_fonte_dati":["Rielaborazione Webmapp"]
 * "class":"OST"
 *
 * Mapping campi (rename) a tutti i POI e TRACK
 * "name" -> "title"
 *
 * Property subclass
 * "subclass": "ost_sentiero_percorso" (TRACK)
 * "subclass": "ost_waypoint" (POI)
 * "subclass": "ost_attrattore" (POI)
 * "subclass": "ost_servizi" (POI)
 *
 * Tassonomie Activity (per TRACK)
 * Bicicletta (373) -> "tipologia":"Corsia ciclabile e/o ciclopedonale"
 * Trekking (372) -> "tipologia":"Sentiero Escursionistico"
 * Trenino verde -> "tipologia":"Trenino verde"
 *
 * Tassonomie Webmapp_category
 * Intermodale (366) -> "subclass": "ost_servizi" , "tipologia" : "PORTO CON SERVIZI DI TRASPORTO PASSEGGERI", "tematica" : "TRASPORTI"
 * Porto (376) -> "subclass": "ost_servizi" , "tipologia" : "PORTO TURISTICO", "tematica" : "TRASPORTI"
 * Punto Sosta (374) -> "subclass": "ost_waypoint", "tipologia" : "INTERSEZIONE"
 * Stazione (375) -> "subclass": "ost_servizi" , "tipologia" : "STAZIONE/FERMATA FERROVIARIA", "tematica" : "TRASPORTI"
 *
 * Pulizia dei meta che non servono
 * noDetails, noInteraction, accesibility, locale, source, wp_edit, web, taxonomy
 *
 */
class WebmappIntense2ExportTask extends WebmappAbstractTask {

    private $wp;

    public function check() {
        $this->wp = new WebmappWP('http://intense2.be.webmapp.it/');
        return TRUE;
    }


    public function process(){
    $this->processPoiIndex();
    //$this->processTrackIndex();
    return true;

    }

    private function getListByType($type) {
        $j = WebmappUtils::getJsonFromAPI($this->wp->getBaseUrl().'/wp-json/webmapp/v1/list?type='.$type);
        if (isset($j['code']) && $j['code']=='rest_no_route') {
            echo "OLD VERSION: downloading all POIS\n\n";
            $new_j = array();
            // build j from usual
            $fs = WebmappUtils::getMultipleJsonFromApi($this->wp->getBaseUrl().'/wp-json/wp/v2/'.$type);
            if(is_array($fs) && count($fs)>0) {
                foreach($fs as $f) {
                    $id = $f['id'];
                    $date = $f['modified'];
                    $new_j[$id]=$date;
                }
            }
            $j = $new_j;
        }
        return $j;
    }
    private function processPoiIndex() {
        $l = new WebmappLayer('intense2pois',$this->getRoot().'/resources');
        // Lista delle route:
        $pois = $this->getListByType('poi');
        if(count($pois)>0){
            foreach ($pois as $pid => $date) {
                $p=new WebmappPoiFeature($this->wp->getBaseUrl().'/wp-json/wp/v2/poi/'.$pid);
                // Intense2 mod

                // Meta fissi
                $p->addProperty('action','add');
                $p->addProperty('field_fonte_dati', array('Rielaborazione Webmapp'));
                $p->addProperty('class','OST');

                // Gestione tassonomie
                $taxs = $p->getProperty('taxonomy');
                $tid = $taxs['webmapp_category']['0'];
                switch ($tid) {
                    case 366:
                        $p->addProperty('subclass','ost_servizi');
                        $p->addProperty('tipologia','PORTO CON SERVIZI DI TRASPORTO PASSEGGERI');
                        $p->addProperty('tematica','TRASPORTI');
                        break;

                    case 376:
                        $p->addProperty('subclass','ost_servizi');
                        $p->addProperty('tipologia','PORTO TURISTICO');
                        $p->addProperty('tematica','TRASPORTI');
                        break;

                    case 374:
                        $p->addProperty('subclass','ost_waypoint');
                        $p->addProperty('tipologia','INTERSEZIONE');
                        // $p->addProperty('tematica','');
                        break;

                    case 375:
                        $p->addProperty('subclass','ost_servizi');
                        $p->addProperty('tipologia','STAZIONE/FERMATA FERROVIARIA');
                        $p->addProperty('tematica','TRASPORTI');
                        break;
                }

                // Remove property
                $p->removeProperty('noDetails');
                $p->removeProperty('noInteraction');
                $p->removeProperty('accessibility');
                $p->removeProperty('locale');
                $p->removeProperty('source');
                $p->removeProperty('wp_edit');
                $p->removeProperty('web');
                $p->removeProperty('taxonomy');

                $l->addFeature($p);
            }
        }
        $l->write();
    }
    private function processTrackIndex() {
        // Lista delle route:
        $pois = $this->getListByType('poi');
        $features = array();
        if(count($pois)>0){
            foreach ($pois as $pid => $date) {
                $skip = false;
                echo "\n\n\n Processing POI $pid\n";
                $p=WebmappUtils::getJsonFromAPI($this->path.'/'.$pid.'.geojson');
                if(!isset($p['geometry'])) {
                    echo "Warning no GEOMETRY: SKIPPING POI\n";
                    $skip=true;
                }
                if(!$skip) $features[]=$p;
            }
            $j=array();
            $j['type']='FeatureCollection';
            $j['features']=$features;
            file_put_contents($this->path.'/poi_index.geojson',json_encode($j));
        }
    }

}
