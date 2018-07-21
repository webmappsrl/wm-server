<?php
class WebmappTranslateTask extends WebmappAbstractTask {

    private $current_locale='';

    public function check() {
    if(!array_key_exists('current_locale', $this->options))
        throw new Exception("L'array options deve avere la chiave 'current_locale'", 1);
        $this->current_locale=$this->options['current_locale'];
        return TRUE;
    }

    public function process(){
        echo "\n\n\nSTART $this->name (current_locale: $this->current_locale)\n\n\n";
        $this->processType('poi');
        $this->processType('track');
    }

    private function processType($type) {
        echo "\n\nProcessing type $type\n\n";
        $path = $this->project_structure->getPathGeoJson().'/'.$type;
        echo "LOOP ON PATH $path\n";
        $files = scandir($path);

        echo "FIRST LOOP: load \n";
        $items = array();
        foreach ($files as $file) {
            if (preg_match('|\.geojson$|',$file)) {
                echo "Loading FILE $file .. ";
                $item = WebmappUtils::getJsonFromApi($path.'/'.$file);
                $id=$item['properties']['id'];
                if(isset($item['properties']['locale']) 
                    && $item['properties']['locale']==$this->current_locale
                    && isset($item['properties']['translations'])) {
                    $items[$id]=$item;
                    echo "DONE";
                }
                else {
                    echo "skip";
                }
                echo "\n";
            }
        }

        echo "\n\nSECOND LOOP: Create translated FILE with translated string values\n";
        $items_translated = array();
        foreach ($items as $id => $item) {
            echo "Processing item $id .. ";
            foreach ($item['properties']['translations'] as $lang => $info) {
                $newid=$info['id'];
                $source = $item['properties']['source'];
                $base_url = preg_replace('|/([0-9])*$|','',$source);
                $url = $base_url . '/' . $newid;
                echo "$lang ($url) .. ";
                $new_content = WebmappUtils::getJsonFromApi($url);
                $new_item=$item;
                $new_item['properties']['id']=$newid;
                $new_item['properties']['web']=$item['properties']['translations'][$lang]['web'];
                
                $val=array();
                $val['id']=$id;
                $val['name']=$item['properties']['name'];
                $val['web']=$item['properties']['web'];
                $new_item['properties']['translations'][$item['properties']['locale']]=$val;

                $new_item['properties']['locale']=$lang;
                $new_item['properties']['source']=$url;
                unset($new_item['properties']['translations'][$lang]);
                $new_item['properties']['name']=$new_content['title']['rendered'];
                $new_item['properties']['description']=$new_content['content']['rendered'];
                $items_translated[$newid]=$new_item;
                echo "Writing ";
                $new_file = $path.'/'.$newid.'.geojson';
                file_put_contents($new_file, json_encode($new_item));
                echo "OK";
            }
            echo "\n";
        }

        echo "\n\n THIRD LOOP: Update related ID\n";
        foreach ($items as $id => $item) {
            echo "Processing item $id .. ";
            foreach ($item['properties']['translations'] as $lang => $info) {
                $newid=$info['id'];
                echo "$lang ($newid) ";
                $new_item = $items_translated[$newid];
                $updated_item = $new_item;
                // ID POIS
                if (isset($new_item['properties']['id_pois']) && 
                    is_array($new_item['properties']['id_pois']) &&
                    count($new_item['properties']['id_pois']) > 0) {
                    $id_pois_new = array();
                    foreach ($new_item['properties']['id_pois'] as $id_poi) {
                        // Controlla esistenza della traduzione dal file originale
                        $file = $this->project_structure->getPathGeoJson().'/poi/'.$id_poi.'.geojson';
                        $j = WebmappUtils::getJsonFromApi($file);
                        if(isset($j['properties']['translations'][$lang])) {
                            $id_pois_new[]=$j['properties']['translations'][$lang]['id'];
                        }
                    }
                    $updated_item['properties']['id_pois']=$id_pois_new;
                }

                // RELATED
                $new_related = array();
                if (isset($new_item['properties']['related']) && 
                    is_array($new_item['properties']['related']) &&
                    count($new_item['properties']['related'])>0 ) {
                    foreach($new_item['properties']['related'] as $feature_type => $relation_types ) {
                        foreach($relation_types as $relation_type => $related_features) {
                            foreach ($related_features as $id_feature => $info) {
                                $file = $this->project_structure->getPathGeoJson()."/$feature_type/".$id_feature.'.geojson';
                                $j = WebmappUtils::getJsonFromApi($file);
                                if(isset($j['properties']['translations'][$lang])) {
                                    $id_feature_new=$j['properties']['translations'][$lang]['id'];
                                    $new_info = $info;
                                    $file = $this->project_structure->getPathGeoJson()."/$feature_type/".$id_feature_new.'.geojson';
                                    $j = WebmappUtils::getJsonFromApi($file);
                                    $new_info['name']=$j['properties']['name'];
                                    $new_info['web']=$j['properties']['web'];
                                    $new_related[$feature_type][$relation_type][$id_feature_new]=$new_info;
                                }
                            }
                        }
                    }
                }
                $updated_item['properties']['related']=$new_related;
                echo "Writing ";
                $new_file = $path.'/'.$newid.'.geojson';
                file_put_contents($new_file, json_encode($updated_item));
                echo "OK";
            }
            echo "\n";
        }

        
    }
}