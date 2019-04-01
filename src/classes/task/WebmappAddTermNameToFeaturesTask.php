<?php

class WebmappAddTermNameToFeaturesTask extends WebmappAbstractTask {
    private $taxonomies;
   public function check() {
    return TRUE;
   }

   public function process() {
    // LOAD TAXONOMIES
    $this->loadTaxonomy('webmapp_category');
    // $this->loadTaxonomy('activity');
    // $this->loadTaxonomy('theme');
    // $this->loadTaxonomy('when');
    // $this->loadTaxonomy('where');
    // $this->loadTaxonomy('who');

    // ADD TERM
    $this->addTerm('webmapp_category');


    return TRUE;
   }

   private function loadTaxonomy($name) {
     $this->taxonomies[$name]=WebmappUtils::getJsonFromApi($this->getRoot().'/taxonomies/'.$name.'.json');
   }
   private function addTerm($name) {
    $names = array();
    if(isset($this->taxonomies[$name]) && count($this->taxonomies[$name])>0) {
        foreach($this->taxonomies[$name] as $term_id => $term) {
            $term_name = $term['name'];
            echo "Checking TERM $name($term_id)\n";
            if(isset($term['items']['poi']) && count($term['items']['poi'])>0) {
                foreach ($term['items']['poi'] as $pid ) {
                    $poi = WebmappUtils::getJsonFromApi($this->getRoot().'/geojson/'.$pid.'.geojson');
                    $poi_name = '';
                    if(isset($poi['properties']['name'])) $poi_name = $poi['properties']['name'];
                    $poi['properties']['name'] = ucfirst($term_name) .' - '.$poi_name;
                    $names[$pid]=ucfirst($term_name) .' - '.$poi_name;
                    echo "--> Adding term name to POI {$poi['properties']['name']} ($pid)\n";
                    file_put_contents($this->getRoot().'/geojson/'.$pid.'.geojson',json_encode($poi));
                }
            }
        }
        // UPDATE all_pois.gojson
        if($name=='webmapp_category' && count($names)>0){
          $file = $this->getRoot().'/geojson/all-pois.geojson';
          $all = WebmappUtils::getJsonFromApi($file);
          $all_new = array();
          if(isset($all['features']) && count($all['features'])>0) {
            foreach($all['features'] as $item) {
              if(isset($names[$item['properties']['id']])){
                $item['properties']['name']=$names[$item['properties']['id']];
              }
              $all_new['features'][]=$item;
            }
            $all_new['type']=$all['type'];
            if(isset($all['properties'])) $all_new['properties']=$all['properties'];
            file_put_contents($file,json_encode($all_new));
          }
        }

    }
   }

}
