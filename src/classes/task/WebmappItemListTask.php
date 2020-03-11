<?php
class WebmappItemListTask extends WebmappAbstractTask {


 public function check() {

     // ENDPOINT API Url for items
     if(!array_key_exists('endpoint', $this->options))
         throw new Exception("'url' option is mandatory", 1);

     // Name of the item to be downloaded: API will be endpoint.'/'.name
     if(!array_key_exists('name', $this->options))
         throw new Exception("'name' option is mandatory", 1);

     // Output file name
     if(!array_key_exists('out', $this->options))
         throw new Exception("'out' option is mandatory", 1);

    return TRUE;
}


public function process(){

     // Retrieve items
    $items = WebmappUtils::getMultipleJsonFromApi($this->options['endpoint'].'/'.$this->options['name']);
    // Loop on items and build features array
    $features = array();
    if (is_array($items) && count($items)>0) {
        foreach ($items as $item) {
            $props=array();
            $id = $item['id'];
            $name = $item['title']['rendered'];
            echo "processing $id $name\n";

            $image = '';
            $image_id = $item['featured_media'];
            if (!empty($image_id)) {
                $j = WebmappUtils::getJsonFromApi($this->options['endpoint'].'/media/'.$image_id);
                if (isset($j['media_details']['sizes']['medium']['source_url']))
                    $image = $j['media_details']['sizes']['medium']['source_url'];
            }
            $in = $item['excerpt']['rendered'];
            $in = preg_replace('|\[|', '<', $in);
            $in = preg_replace('|\]|', '>', $in);
            $excerpt = strip_tags($in);

            $web = $item['link'];

            $props['id']=$id;
            $props['name']=$name;
            if(!empty($image)) $props['image']=$image;
            $props['excerpt']=$excerpt;
            $props['web']=$web;

            $feature = array();
            $feature['properties']=$props;
            $features[]=$feature;
        }
    }

    // Write json file in resources
    $json = array('type'=>'featureCollection','features'=>$features);
    file_put_contents($this->getRoot().'/resources/'.$this->options['out'].'.json',json_encode($json));
    return true;
}

}
