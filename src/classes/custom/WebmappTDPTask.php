<?php
// Terre di Pisa Custom Class
class WebmappTDPTask extends WebmappAbstractTask {

    private $categories = array();
    private $pois;
    private $all_territori;

    public function check() {
      return TRUE;
  }

  public function process(){
    $this->all_territori = new WebmappLayer('Territori');
    echo "\n\n Processing\n\n";

    self::setCategories();
    self::processItinerari();
    self::processTerritori();
    self::processCamper();
    self::processAttrazioni();
    self::processMembers();
    self::processEvents();

    echo "\n\nDONE!\n\n";

    return TRUE;
}

private function processItinerari(){
    $url = 'http://www.terredipisa.it/wp-json/wp/v2/percorso/';
    $items = WebmappUtils::getMultipleJsonFromApi($url);
    $itinerari = new WebmappLayer('Itinerari');
    foreach ($items as $ja) {
        // Mapping
        $id = $ja['id'];
        $name = $ja['title']['rendered'];
        echo "Processing percorso $name ($id) ... ";
        if(isset($ja['acf']['percorso_geometry']) && 
          !empty($ja['acf']['percorso_geometry'])) {
            echo " creating track ";
            $track = new WebmappTrackFeature($ja,true);
            $track->setGeometryGeoJSON($ja['acf']['percorso_geometry']);
            $track->write($this->getRoot().'/geojson/');
            // Traduzioni:
            self::translateItem($ja,$id);
            $itinerari->addFeature($track);
        } else {
            echo " NO GEOMETRY ... skip!";
        }
        echo "\n";
    }
    $itinerari->write($this->getRoot().'/geojson/');
}

private function processTerritori() {

    $pois_url = 'http://www.terredipisa.it/wp-json/wp/v2/territorio/';
    $this->pois = WebmappUtils::getMultipleJsonFromApi($pois_url);

    $num_pois = count($this->pois);
    $num_cats = count($this->categories);
    echo "\n\n Processing $num_cats CATS $num_pois POIS \n\n";
    $path = $this->getRoot().'/geojson';

    foreach ($this->pois as $ja) {
            // Esempio di MAPPING per la creazione di un POI
        $id = $ja['id'];
        $source = "http://www.terredipisa.it/wp-json/wp/v2/territorio/".$id;

        echo "Processing POI ($id) source -> $source\n";

        $j=array();
        $j['id']=$ja['id'];
        $j['title']['rendered']=$ja['title']['rendered'];
        $j['content']['rendered']=$ja['content']['rendered'];
        $j['n7webmap_coord']['lng']=$ja['acf']['territorio_geolocalizzazione']['lng'];
        $j['n7webmap_coord']['lat']=$ja['acf']['territorio_geolocalizzazione']['lat'];
        $poi = new WebmappPoiFeature($j);
        $poi->addProperty('color','#F6A502');
        $poi->addProperty('web',$ja['link']);
        $poi->addProperty('source',$source);

            // Gestione della immagine
        if (isset($ja['featured_media'])) {
            $media_url = "http://www.terredipisa.it/wp-json/wp/v2/media/".$ja['featured_media'];
            $media = WebmappUtils::getJsonFromApi($media_url);
            $poi->addProperty('image',$media['media_details']['sizes']['medium']['source_url']);
        } else { 
            echo "no image\n";
        }

        $poi->write($path);

        // Gestione traduzioni
        self::translateItem($ja,$id);

        $this->all_territori->addFeature($poi);
            // $cat_id = $ja['categories'][0];
        $cat_id=88;
        $l=$this->categories[$cat_id];
        $l->addFeature($poi);
    }

    foreach ($this->categories as $id => $l) {
        if($l->count() > 0) {
            $l->write($path);
        }
    }

}
private function processCamper() {
    $path = $this->getRoot().'/geojson';
    echo "\n\n\n Processing Camper \n\n";

    foreach ($this->pois as $ja) {
            // territorio_areasosta_location
            // wm-icon-parking-15
        $acf = $ja['acf'];
        $id = $ja['id'];

        echo "Processing POI ($id) ... ";


        if (isset($acf['territorio_areasosta_location']['lat']) && 
            isset($acf['territorio_areasosta_location']['lng'])) {
            echo " adding poi_camper \n";
        $j=array();
        $j['id']=$ja['id'].'_camper';
        $j['title']['rendered']='Area Camper';
        $j['n7webmap_coord']['lng']=$ja['acf']['territorio_geolocalizzazione']['lng'];
        $j['n7webmap_coord']['lat']=$ja['acf']['territorio_geolocalizzazione']['lat'];
        $poi = new WebmappPoiFeature($j);
        $poi->addProperty('color','#E43322');
        $poi->addProperty('icon','wm-icon-parking-15');
        $poi->write($path);
    } else {
        echo "No territorio_areasosta_location \n";
    }

}
}

private function processAttrazioni() {
    echo "\n\n\n Processing attrazioni \n\n";
    $path = $this->getRoot().'/geojson/';
    $attrazioni_url = 'http://www.terredipisa.it/wp-json/wp/v2/attrazione/';
    $attrazioni = WebmappUtils::getMultipleJsonFromApi($attrazioni_url);
    $attrazioni_layers = array();
    foreach($attrazioni as $ja) {
        $id = $ja['id'];
        echo "Creating layer id: $id\n";
        $attrazioni_layers[$id] = new WebmappLayer($id);
    }
    foreach ($this->pois as $ja) {
        if (isset($ja['acf']['territorio_attrazioni_rel']) && 
            is_array($ja['acf']['territorio_attrazioni_rel'])) {
            $poi_id = $ja['id'];
        foreach ($ja['acf']['territorio_attrazioni_rel'] as $item) {
            echo "Adding POI($poi_id) to attrazione(".$item['ID'].")\n";
            $attrazioni_layers[$item['ID']]->addFeature($this->all_territori->getFeature($poi_id));
        }            
    }
}
        // Writing Layers
foreach ($attrazioni_layers as $id => $attrazione_layer) {
    echo "Writing layer $id\n";
    $attrazione_layer->write($path);
}
}
private function processMembers() {
    echo "\n\n\n Processing Members \n\n";
    $path = $this->getRoot().'/geojson/';
    $members_url = 'http://www.terredipisa.it/wp-json/wp/v2/users/';
    $members = WebmappUtils::getMultipleJsonFromApi($members_url);
    $all_member_layer = new WebmappLayer('Members');
    $tot = 0;
    $tot_added = 0;
    foreach($members as $m) {
        $tot++;
        $id=$m['id'];
        $name=$m['acf']['user_azienda_nome'];
        if (isset($m['acf']['user_azienda_localita_geolocalizzazione']) && 
            isset($m['acf']['user_azienda_localita_geolocalizzazione']['lng']) &&
            isset($m['acf']['user_azienda_localita_geolocalizzazione']['lng'])
            ) {
            echo "Adding member $name (ID:$id)\n";
        $tot_added++;

        $j['id']=$id.'_user';
        $j['title']['rendered']=$name;
        $j['content']['rendered']=$name;
        $j['n7webmap_coord']['lng']=$m['acf']['user_azienda_localita_geolocalizzazione']['lng'];
        $j['n7webmap_coord']['lat']=$m['acf']['user_azienda_localita_geolocalizzazione']['lat'];
        $poi = new WebmappPoiFeature($j);
        $poi->addProperty('color','#E43322');
        $poi->addProperty('web',preg_replace('|/author/|', '/user/', $m['link']));
        $poi->write($path);
        $all_member_layer->addFeature($poi);
    } else {
        echo "SKIPPING member $name (ID:$id)\n";
    }
}
$all_member_layer->write($path);
echo "\n\nTOTAL MEBMER $tot - ADDED $tot_added\n";
}

private function processEvents() {
    echo "\n\n\n Processing Events \n\n";
    $path = $this->getRoot().'/geojson/';
    $events_url = 'http://www.terredipisa.it/wp-json/wp/v2/event/';
    $events = WebmappUtils::getMultipleJsonFromApi($events_url);
    $all_events_layer = new WebmappLayer('Events');

    // Filter events
    $filtered = array();
    foreach ($events as $ja){
        $id = $ja['id'];
        $name = $ja['title']['rendered'];
        $end_date = $ja['end_date'];
        echo "Filtering event $name (ID:$id end_date=$end_date) ... ";
        if (strtotime($end_date) >= strtotime('today')) {
            echo "Event valid\n";
            $filtered[]=$ja;
        }
        else echo "Event not valid\n";
    }
    $events=$filtered;

    foreach($events as $ja) {
        $id = $ja['id'];
        $name = $ja['title']['rendered'];
        echo "Processing event $name (ID:$id)";
        // Coordinates from gmap:
        //<span class="lat">43.7434249</span> <span class="lng">10.532463000000007</span>
        $content = $ja['content']['rendered'];
        preg_match('|<span class="lat">(.*)</span>|',$content,$match_lat);
        preg_match('|<span class="lng">(.*)</span>|',$content,$match_lon);
        if (isset($match_lat[1]) && isset($match_lon[1])) {
            $lat = $match_lat[1];
            $lon = $match_lon[1];
            echo "lat=$lat lon=$lon";
            $j['id']=$id;
            $j['title']['rendered']=$name;
            $j['content']['rendered']=$ja['content']['rendered'];
            $j['n7webmap_coord']['lng']=$lon;
            $j['n7webmap_coord']['lat']=$lat;
            $poi = new WebmappPoiFeature($j);
            $poi->addProperty('color','#F6A502');
            $poi->addProperty('web',$ja['link']);
            $poi->write($path);

            // GEstione delle traduzioni
            self::translateItem($ja,$id);

            $all_events_layer->addFeature($poi);
        } else {
            " NO COORD: SKIP ";
        }
        $all_events_layer->write($path);
        echo "\n";
    }
    $all_events_layer->write($path);
}

private function setCategories() {
    $cats_url = 'http://www.terredipisa.it/wp-json/wp/v2/categories/';
    $cats = WebmappUtils::getMultipleJsonFromApi($cats_url);
    foreach ($cats as $cat) {
        $l = new WebmappLayer($cat['name']);
        $l->setId($cat['id']);
        $this->categories[$cat['id']]=$l;
    }
}

private function translateItem($ja,$id) {
            if(isset($ja['wpml_translations']) && 
            is_array($ja['wpml_translations']) &&
            count($ja['wpml_translations'])>0) {
            foreach($ja['wpml_translations'] as $item) {
                $id_t = $item['id'];
                $src = $this->getRoot() .'/geojson/'.$id.'.geojson';
                $trg = $this->getRoot() .'/geojson/'.$id_t.'.geojson';
                $cmd = "ln -s $src $trg";
                echo "Translating (cmd: $cmd)\n";
                system($cmd);
            }
        }

}


}
