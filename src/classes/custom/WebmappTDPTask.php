<?php
// Terre di Pisa Custom Class
class WebmappTDPTask extends WebmappAbstractTask {

    private $categories = array();
    private $pois;

	public function check() {
		return TRUE;
	}

    public function process(){
        echo "\n\n Processing\n\n";
        self::setCategories();
        self::processTerritori();
        self::processCamper();

        echo "\n\nDONE!\n\n";

    	return TRUE;
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
