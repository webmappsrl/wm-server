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
 * "fonteDati":["Rielaborazione Webmapp"]
 * "class":"Ost"
 *
 * Mapping campi (rename) a tutti i POI e TRACK
 * "name" -> "title"
 * "id" -> "idExt"
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

    private $surface_mapping = array(
        'asphalt' => 'FONDO IN ASFALTO',
        'compacted' => 'FONDO IN TERRA BATTUTA',
        'ground' => 'FONDO NATURALE',
        'concrete' => 'FONDO IN CEMENTO',
        'sett' => 'FONDO LASTRICATO',
        'sand' => 'FONDO IN GHIAIA FINE',
        'grass' => 'FONDO ERBOSO',
        'pebblestone' => 'FONDO CON GHIAIA GROSSOLANA'
    ) ;

    public function check() {
        $this->wp = new WebmappWP('http://intense2.be.webmapp.it/');
        return TRUE;
    }


    public function process(){
    $this->processPois();
    $this->processTracks();
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
    private function processPois() {
        // Lista delle route:
        $pois = $this->getListByType('poi');
        if(count($pois)>0){
            foreach ($pois as $pid => $date) {
                $p=new WebmappPoiFeature($this->wp->getBaseUrl().'/wp-json/wp/v2/poi/'.$pid);
                // Intense2 mod
                $name = preg_replace('|&#8211;|','-', $p->getProperty('name'));
                $name = preg_replace('|&#8217;|','\'',$name);

                // Meta fissi
                $p->addProperty('action','add');
                $p->addProperty('fonteDati', array('Rielaborazione Webmapp'));
                $p->addProperty('class','Ost');
                //$p->addProperty('tematica','NON DEFINITA');

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
                        $p->addProperty('tematica','');
                        break;

                    case 377:
                    case 375:
                        $p->addProperty('subclass','ost_servizi');
                        $p->addProperty('tipologia','STAZIONE/FERMATA FERROVIARIA');
                        $p->addProperty('tematica','TRASPORTI');
                        break;

                }

                // Mapping
                $id = $p->getId();
                $p->addProperty('idExt',$id);
                $p->addProperty('title',$name);

                // Remove property
                $p->removeProperty('noDetails');
                $p->removeProperty('noInteraction');
                $p->removeProperty('accessibility');
                $p->removeProperty('locale');
                $p->removeProperty('source');
                $p->removeProperty('wp_edit');
                $p->removeProperty('web');
                $p->removeProperty('name');
                $p->removeProperty('id');
                $p->removeProperty('taxonomy');

                $j=array();
                $j[0]['type']='FeatureCollection';
                $j[0]['features']=array($p->getArrayJson());
                $j[1]['type']='FeatureCollection';
                $j[1]['features']=array();
                file_put_contents($this->getRoot().'/resources/'.$id.'.geojson',json_encode($j));
            }
        }
    }
    private function processTracks() {
        // Lista delle route:
        $tracks = $this->getListByType('track');
        if(count($tracks)>0){
            foreach ($tracks as $tid => $date) {
                $t=new WebmappTrackFeature($this->wp->getBaseUrl().'/wp-json/wp/v2/track/'.$tid);
                // Intense2 mod
                // Meta fissi per le track
                $t->addProperty('action','add');
                $t->addProperty('fonteDati', array('Rielaborazione Webmapp'));
                $t->addProperty('class','Ost');
                $t->addProperty('tematica','NON DEFINITA');
                // Gestione tassonomie
                $taxs = $t->getProperty('taxonomy');
                $term_id = (int) $taxs['activity'][0];
                $name = preg_replace('|&#8211;|','-', $t->getProperty('name'));
                echo "TERMID:$term_id NAME:$name ";


                // Surface
                $is_mtb = FALSE;
                if($t->hasProperty('surface')) {
                    $s=$t->getProperty('surface');
                    if(count($s)>0) {
                        $tot_perc = 0;
                        $tipologiaDelFondo = array();
                        foreach ($s as $s_type => $s_perc) {
                            //Se contiene almeno un tratto diverso da asfalto, assumiamo MTB
                            if($this->surface_mapping[$s_type]!=='FONDO IN ASFALTO'){
                                $is_mtb = TRUE;
                            }
                            $tot_perc += $s_perc;
                            echo "$s_type ($s_perc)";
                            $tipologiaDelFondo[]=array('percentuale'=>$s_perc,'tipologiaFondo'=>$this->surface_mapping[$s_type]);
                        }
                        echo "TOT:$tot_perc ";
                        if ($tot_perc<0.99) {
                            $perc_unknown = round(1-$tot_perc,1);
                            $tipologiaDelFondo[]=array('percentuale'=>$perc_unknown,'tipologiaFondo'=>'DATO SUL FONDO NON PRESENTE');
                            echo "DATO SUL FONDO NON PRESENTE ($perc_unknown) ";
                        }
                        $t->addProperty('tipologiaDelFondo',$tipologiaDelFondo);
                    }
                }

                echo "MTB:$is_mtb\n\n";


                // 373 Bicicletta
                if($term_id==373) {
                    $t->addProperty('subclass','ost_sentiero_percorso');
                    $t->addProperty('tipologia','Corsia ciclabile e/o ciclopedonale');
                    if($is_mtb){
                        $t->addProperty('fruizione','MOUNTAIN BIKING');
                    }
                    else {
                        $t->addProperty('fruizione','BICICLETTA DA STRADA');
                    }
                }
                // 372 Trekking
                if($term_id==372) {
                    $t->addProperty('subclass','ost_sentiero_percorso');
                    $t->addProperty('tipologia','Sentiero Escursionistico');
                    $t->addProperty('fruizione','TREKKING');
                }
                // 378 Trenino verde
                if($term_id==378) {
                    $t->addProperty('subclass','ost_sentiero_percorso');
                    $t->addProperty('tipologia','Trenino verde');
                }
                // Mapping
                $id = $t->getId();
                $t->addProperty('idExt',$id);
                $t->addProperty('title',$name);

                // Remove property
                $t->removeProperty('surface');
                $t->removeProperty('noDetails');
                $t->removeProperty('noInteraction');
                $t->removeProperty('accessibility');
                $t->removeProperty('locale');
                $t->removeProperty('source');
                $t->removeProperty('wp_edit');
                $t->removeProperty('web');
                $t->removeProperty('name');
                $t->removeProperty('id');
                $t->removeProperty('taxonomy');

                $j=array();
                $j[0]['type']='FeatureCollection';
                $j[0]['features']=array($t->getArrayJson());
                $j[1]['type']='FeatureCollection';
                $j[1]['features']=array();
                file_put_contents($this->getRoot().'/resources/'.$id.'.geojson',json_encode($j));
            }
        }
    }

}
