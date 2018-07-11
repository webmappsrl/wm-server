<?php
class WebmappPfEventsTask extends WebmappAbstractTask {

	public function check() {
        echo "\nCheck $this->name\n";
		return TRUE;
	}

    public function process(){
        echo "\nProcess $this->name\n";
        //LOAD Events
        $events = WebmappUtils::getJsonFromApi('http://cosmopoli.travel/wp-json/wp/v2/events/?per_page=100');
        $l = new WebmappLayer('events');
        foreach ($events as $event) {
            $id = $event['id'];
            echo "Processing event $id\n";
            // MAPPING
            // coordinate
            if(is_array($event['acf']['luogo_evento'])){
                $j=array();
                $j['id']=$event['id'];
                $j['title']['rendered']=$event['title']['rendered'];
                $j['content']['rendered']=$event['content']['rendered'];
                if ($event['featured_media']>0){
                    $j['featured_media']=$event['featured_media'];
                    $j['_links']['wp:featuredmedia'][0]['href']=$event['_links']['wp:featuredmedia'][0]['href'];                    
                }
                $j['address']=$event['acf']['luogo_evento']['address'];
                $j['n7webmap_coord']['lng']=$event['acf']['luogo_evento']['lng'];
                $j['n7webmap_coord']['lat']=$event['acf']['luogo_evento']['lat'];
                $poi = new WebmappPoiFeature($j);
                $poi->addProperty('date_start',$event['acf']['data_inizio']);
                $poi->addProperty('date_stop',$event['acf']['data_fine']);
                $l->addFeature($poi);                
            } else {
                echo "WARNING: NO COORDINATES SKIP EVENT\n";
            }
        }        
        $l->write($this->project_structure->getPathGeoJson());
    	return TRUE;
    }

}
