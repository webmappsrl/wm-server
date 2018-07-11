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
                    $specie_title = ucfirst($this->categories[$cat]['title']);
                    $specie_description = $this->categories[$cat]['description'];


                    // Recupero delle informazioni specifiche
                    // condizione_vegetativa_pianta_e_principali_caratteristiche: "<p>individuo adulto con sviluppo costretto tra sentiero e muretto a secco</p> ",
                    // spalcatura: false,
                    // descrizione_spalcatura: "",
                    // diradamenti: false,
                    // descrizione_diradamenti: "",
                    // altri_interventi: true,
                    // descrizione_altri_interventi: "<p>rifacimento muretto a secco per il contenimento del terreno</p> ",

                    $jurl = WebmappUtils::getJsonFromApi('http://montepisanotree.org/wp-json/wp/v2/poi/'.$id);
                    $acf = $jurl['acf'];
                    $condizione = $acf['condizione_vegetativa_pianta_e_principali_caratteristiche'];
                    $spalcatura = $acf['spalcatura'];
                    $descrizione_spalcatura = $acf['descrizione_spalcatura'];
                    $diradamenti = $acf['diradamenti'];
                    $descrizione_diradamenti = $acf['descrizione_diradamenti'];
                    $altri_interventi = $acf['altri_interventi'];
                    $descrizione_altri_interventi = $acf['descrizione_altri_interventi'];

                    if (!$spalcatura && !$diradamenti && !$altri_interventi) {
                        $interventi = "<p>Nessun intervento necessario.</p>";
                    } else {
                        $interventi = '';
                        if ($spalcatura) {
                            $interventi .= '<h4>Spalcatura</h4>'.$descrizione_spalcatura;
                        }
                        if($diradamenti) {
                            $interventi .= '<h4>Diradamenti</h4>'.$descrizione_diradamenti;
                        }
                        if ($altri_interventi) {
                            $interventi .= '<h4>Altri Interventi</h4>'.$descrizione_altri_interventi;
                        }
                    }

                    $description = "<h3>$specie_title</h3>";
                    $description .= "<p>$specie_description</p>";
                    $description .= "<h3>Condizione vegetativa e caratteristiche:</h3>";
                    $description .= $condizione;
                    $description .= "<h3>Interventi necessari</h3>";
                    $description .= "$interventi";

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
