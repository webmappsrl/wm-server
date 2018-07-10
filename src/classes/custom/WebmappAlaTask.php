<?php
class WebmappAlaTask extends WebmappAbstractTask {

    private $categories=array();

	public function check() {
		return TRUE;
	}

    public function process(){
        echo "\nRetrieve Webmapp categories\n";
        $url = 'http://montepisanotree.org/wp-json/wp/v2/webmapp_category/?per_page=100';
        $j=WebmappUtils::getJsonFromApi($url);
        foreach($j as $item) {
            $this->categories[$item['id']]=array(
                "title" => $item['name'],
                "description" => $item['description']
                );
        }

        echo "\nStart loop on geojson\n";
        $path = $this->project_structure->getPathGeoJson();
        $files = scandir($path);
        foreach ($files as $file) {
            if(preg_match('/\.geojson/',$file))
                $path_file = $path.'/'.$file;
            echo "Processing $path_file\n";
            $j=WebmappUtils::getJsonFromApi($path_file);
            $features=$j['features'];
            $new_fatures=array();
            foreach ($features as $feature) {                
                $properties=$feature['properties'];
                $id=$properties['id'];
                echo "Processing POI $id\n";
                ;
                if (isset($properties['taxonomy']['webmapp_category'][0])) {
                    $cat = $properties['taxonomy']['webmapp_category'][0];
                    $specie_title = $this->categories[$cat]['title'];
                    $specie_description = $this->categories[$cat]['description'];
                    $description = "<h3>$specie_title</h3>";
                    $description .= "<p>$specie_description</p>";
                    $properties['description']=$description;
                    $feature['properties']=$properties;
                    $new_fatures[]=$feature;
                } else {
                    echo "WARN: nessuna specie trovata. SKIP\n";
                }
            }
            $geojson = array();
            $geojson['type']='FeatureCollection';
            $geojson['features']=$new_fatures;
            file_put_contents($path_file, json_encode($geojson));
            echo "File Updated\n";
        }


    	return TRUE;
    }

}
