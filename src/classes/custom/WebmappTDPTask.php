<?php
// Terre di Pisa Custom Class
class WebmappTDPTask extends WebmappAbstractTask {

    private $categories = array();

	public function check() {
		return TRUE;
	}

    public function process(){
        echo "\n\n Processing\n\n";
        $pois_url = 'http://www.terredipisa.it/wp-json/wp/v2/territorio/';
        $pois = WebmappUtils::getMultipleJsonFromApi($pois_url);
        self::setCategories();

        $num_pois = count($pois);
        $num_cats = count($this->categories);
        echo "\n\n Processing $num_cats CATS $num_pois POIS \n\n";
        $path = $this->getRoot().'/geojson';

        foreach ($pois as $ja) {
            // Esempio di MAPPING per la creazione di un POI
            $j=array();
            $j['id']=$ja['id'];
            $j['title']['rendered']=$ja['title']['rendered'];
            $j['content']['rendered']=$ja['content']['rendered'];
            //$j['color']=$ja[''];
            //$j['icon']=$ja[''];
            //$j['noDetails']=$ja[''];
            //$j['noInteractions']=$ja[''];
            //$j['n7webmap_media_gallery']=$j[''];
            //$j['addr:street']=$ja[''];
            //$j['addr:housenumber']=$ja[''];
            //$j['addr:postcode']=$ja[''];
            //$j['addr:city']=$ja[''];
            //$j['contact:phone']=$ja[''];
            //$j['contact:email']=$ja[''];
            //$j['opening_hours']=$ja[''];
            //$j['capacity']=$ja[''];
            //$j['address']=$ja[''];
            $j['n7webmap_coord']['lng']=$ja['acf']['territorio_geolocalizzazione']['lng'];
            $j['n7webmap_coord']['lat']=$ja['acf']['territorio_geolocalizzazione']['lat'];
            $poi = new WebmappPoiFeature($j);
            $poi->write($path);
            $cat_id = $ja['categories'][0];
            $l=$this->categories[$cat_id];
            $l->addFeature($poi);
        }

        foreach ($this->categories as $id => $l) {
            if($l->count() > 0) {
                $l->write($path);
            }
        }
        echo "\n\nDONE!\n\n";

    	return TRUE;
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


}
