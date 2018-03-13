<?php 
class WebmappVetrinaToscanaTask extends WebmappAbstractTask {

    private $url;
    private $max;
    private $types = array();

	public function check() {

		// OPTIONS
		if(!array_key_exists('url', $this->options)) throw new Exception("L'opzione URL è obbligatoria", 1);
        if(!array_key_exists('max', $this->options)) throw new Exception("L'opzione max è obbligatoria", 1);
        if(!array_key_exists('types', $this->options)) throw new Exception("L'opzione types è obbligatoria", 1);

        $this->url = $this->options['url'];
        $this->max = $this->options['max'];
        $this->types = explode(',',$this->options['types']);

        // $a = get_headers($this->url);
		// $match = (preg_match('/301/', $a[0]));
		// if($match==0) {
		// 	echo "Error connecting to server: \n";
		// 	print_r($a);
		// 	return false;
		// }
		return true;
	}

	public function process() {
		echo "\n\nProcessing WebmappVetrinaToscanaTask\n\n";
		foreach($this->types as $type) {
	     	$this->processLayer($type);
		}
	}

	private function processLayer($type) {
		echo "\nProcessing Layer for $type\n";
		$l = new WebmappLayer($type);
		$page = 0;
		$count = 0;
		$total = 0;
		do {
			$page++;
			$api = $this->url . '/' . $type . "?per_page=10&page=$page";
			echo "Getting data form URL $api ... ";
			$items = WebmappUtils::getJsonFromApi($api);
			$count = count($items);
			$total += $count;
			if ($count > 0 ) {
				echo "Found $count items... adding to layer\n";
				// Esempio di MAPPING per la creazione di un POI
				foreach ($items as $ja) {
					$j=array();
					$j['id']=$ja['id'];
					$j['title']['rendered']=$ja['title']['rendered'];
					$j['content']['rendered']=$ja['content']['rendered'];
					if (isset($j['acf']['vt_gallery'])) $j['n7webmap_media_gallery']=$j['acf']['vt_gallery'];
					if (isset($j['acf']['vt_indirizzo'])) $j['address']=$ja['acf']['vt_indirizzo'];
					if (isset($j['acf']['vt_telefono'])) $j['contact:phone']=$ja['acf']['vt_telefono'];
					if (isset($j['acf']['vt_email'])) $j['contact:email']=$ja['acf']['vt_email'];
				/* TODO: orari di apertura
				  vt_chiusura: "domenica",
                  vt_dalleorepranzo: "12,30",
                  vt_alleorepranzo: "15.00",
                  vt_dalleorecena: "19,30",
                  vt_alleorecena: "23",
				  $j['opening_hours']=$ja['acf'][''];
				*/
				//$j['capacity']=$ja['acf'][''];
				  $j['n7webmap_coord']['lng']=$ja['acf']['vt_google_map']['lng'];
				  $j['n7webmap_coord']['lat']=$ja['acf']['vt_google_map']['lat'];
				  $poi = new WebmappPoiFeature($j);
				  $l->addFeature($poi);
				}
			}
			else {
				echo "No more items found.\n";
			}

		} while ($count>0 && $total < $this->max);
        echo "Writing $total POI\n";
		$l->write($this->project_structure->getPathGeojson());

	}


}