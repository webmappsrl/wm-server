<?php

class WebmappWP {
	private $code;
	private $base_url;
	private $api_url;
	private $api_pois;
	private $api_tracks;
	private $api_routes;
	private $api_areas;
	private $api_maps;
	private $api_categories;
	private $per_page=100;

	private $taxonomies=array();

	public function __construct($code) {
		$this->code = $code;

		if(preg_match('|^http://|', $code) || preg_match('|^https://|', $code)) {
			$this->base_url = $code;
		}
		else {
			$this->base_url = "http://$code.be.webmapp.it";
		}

		$this->api_url = "{$this->base_url}/wp-json/wp/v2";
		$this->api_pois = "{$this->api_url}/poi";
		$this->api_tracks = "{$this->api_url}/track";
		$this->api_routes = "{$this->api_url}/route";
		$this->api_areas = "{$this->api_url}/area";
		$this->api_maps = "{$this->api_url}/map";
		$this->api_categories = "{$this->api_url}/webmapp_category";

	}

	public function loadTaxonomies() {
		$this->loadTaxonomy('webmapp_category');
		$this->loadTaxonomy('activity');
		$this->loadTaxonomy('theme');
		$this->loadTaxonomy('who');
		$this->loadTaxonomy('where');
		$this->loadTaxonomy('when');
		$this->loadTaxonomy('theme');
	}

	private function loadTaxonomy($name) {
		$url=$this->api_url.'/'.$name;
		$res = WebmappUtils::getMultipleJsonFromApi($url);
		$new=array();
		if(is_array($res) && count($res)>0){
			foreach($res as $item){
				$new[$item['id']]=$item;
			}
		}
		$this->taxonomies[$name]=$new;
	}

	public function getTaxonomies() {
		return $this->taxonomies;
	}

	public function getCode() {
		return $this->code;
	}

	public function setPerPage($per_page) {
		$this->per_page=$per_page;
	}

	public function getPerPage() {
		return $this->per_page;
	}

	// BASIC GETTERS

	public function getBaseUrl() {
		return $this->base_url;      
	}

	public function getApiUrl() {
		return $this->api_url;
	}

	// MULTIPLE API GETTERS

	public function getApiPois($cat_id=0) {
		$append = '';
		if($cat_id!=0) {
			$append="?webmapp_category=$cat_id";
		}
		return $this->api_pois.$append;
	}

	public function getApiTracks($cat_id=0) {
		$append = '';
		if($cat_id!=0) {
			$append="?webmapp_category=$cat_id";
		}
		return $this->api_tracks.$append;
	}

	public function getApiRoutes($cat_id=0) {
		$append = '';
		if($cat_id!=0) {
			$append="?webmapp_category=$cat_id";
		}
		return $this->api_routes.$append;
	}

	public function getApiAreas($cat_id=0) {
		$append = '';
		if($cat_id!=0) {
			$append="?webmapp_category=$cat_id";
		}
		return $this->api_areas.$append;
	}

	public function getApiMaps() {
		return $this->api_maps;
	}

	public function getApiCategories() {
		return $this->api_categories.'?per_page='.$this->per_page;
	}

	// SINGLE API GETTERS
	public function getApiPoi($id) {
		return $this->api_pois.'/'.$id;
	}

	public function getApiTrack($id) {
		return $this->api_tracks.'/'.$id;
	}

	public function getApiRoute($id) {
		return $this->api_routes.'/'.$id;
	}

	public function getApiArea($id) {
		return $this->api_areas.'/'.$id;
	}

	public function getApiMap($id) {
		return $this->api_maps.'/'.$id;
	}

	public function getApiCategory($id) {
		return $this->api_categories.'/'.$id;
	}

	// CONTROLLI DI RISPOSTA DALLA PIATTAFORMA

	private function checkUrl($url) {
		$a = get_headers($url);
		$match = (preg_match('/200/', $a[0]));
		if($match==0) return false;
		return true;
	}

	public function check() {
		return $this->checkUrl($this->base_url);
	}

	public function checkPoi($id) {
		return $this->checkUrl($this->getApiPoi($id));
	}

	public function checkTrack($id) {
		return $this->checkUrl($this->getApiTrack($id));
	}

	public function checkRoute($id) {
		return $this->checkUrl($this->getApiRoute($id));
	}

	public function checkArea($id) {
		return $this->checkUrl($this->getApiArea($id));
	}

	public function checkMap($id) {
		//echo $this->getApiMap($id);
		return $this->checkUrl($this->getApiMap($id));
	}

	public function checkCategory($id) {
		return $this->checkUrl($this->getApiCategory($id));
	}

	// Categorie e layers

	public function getCategoriesArray() {
		$json = WebmappUtils::getJsonFromApi($this->getApiCategories());
		if (!is_array($json) || count($json) == 0) {
			return array();
		}
		$cats = array();
		foreach ($json as $cat) {
			$cats[] = $cat['id'];
		}
		return $cats;
	}

	public function getPoiLayers() {
		return $this->getWebmappLayers('poi');
	}

	public function getTrackLayers() {
		return $this->getWebmappLayers('track');
	}

	// Crea un layer di POI a partire dalle immagini solo se queste hanno 
	// l'informazione su LAT/LON
	public function getImageLayer() {
		$url = $this->api_url.'/media';
		$features = WebmappUtils::getMultipleJsonFromApi($url);
		$l=new WebmappLayer('image');
		if(is_array($features) && count($features) >0) {
			foreach($features as $feature) {
				$original_image = $feature['guid']['rendered'];
				echo "Cehcking file $original_image: ";
				$info = get_headers($original_image,1);
				if (preg_match('/200/',$info[0])) {
					// Check file type (Content-Type)
					if (isset($info['Content-Type'])) {
						if (preg_match('/image/',$info['Content-Type']) && 
							(preg_match('/jpg/i',$info['Content-Type']) ||
							 preg_match('/jpeg/i',$info['Content-Type']) ||
							 preg_match('/tiff/i',$info['Content-Type'])
							 )
							) {
							// Check LAT/LON
							echo " .. downloading .. ";
							$tmpfname = tempnam("/tmp", "WM_IMAGE");
							$img = file_get_contents($original_image);
							file_put_contents($tmpfname, $img);
							echo " $tmpfname ";
							try {
								$info = exif_read_data($tmpfname);
							} catch (Exception $e) {
								$info = false;
							}
							if($info !== false) {
								// LOOK FOR LAT/LON AND ADD TO LAYERS
								//print_r($info);
							}
							else {
								echo "Can't read EXIF DATA. SKIP.";
							}
						} else {
							echo "Not valid image. SKIP.";
						}
					} else {
						echo " NO Content type. SKIP.";
					}
				} else {
					echo " http Error: $info[0]. SKIP.";
				}
				echo "\n";
			}
 		}
 		return $l;
	}

	// Restituisce un array di tutti i layer (piatto)
	// $add_feature = true aggiunge anche tutte le features (poi / tracks o area) nel 
	// layer corrispondente
	private function getWebmappLayers($type) {
		$cats = $this->getCategoriesArray();
		if (count($cats)==0){
			return $cats;
		}
		$layers = array();
		foreach ($cats as $cat_id) {
			switch ($type) {
				case 'poi':
				$api = $this->getApiPois($cat_id);
				break;

				case 'track':
				$api = $this->getApiTracks($cat_id);
				break;

				case 'area':
				$api = $this->getApiAreas($cat_id);
				throw new Exception("Tipo area non ancora implementato", 1);
				break;

				case 'route':
				$api = $this->getApiRoutes($cat_id);
				throw new Exception("Tipo route non ancora implementato", 1);
				break;

				default:
				throw new Exception("Tipo $type non valido. Sono valido solamente i tipi poi, track e area.", 1);
				break;
			}

			//http://dev.be.webmapp.it/wp-json/wp/v2/poi?per_page=100&orderby=title&order=asc
			$api = $api . '&per_page='.$this->per_page.'&orderby=title&order=asc' ;
			$features = WebmappUtils::getJsonFromApi($api);
			if(is_array($features) && count($features) > 0 ) {
				$layer = new WebmappLayer($type.'s_'.$cat_id);
				$layer->loadMetaFromUrl($this->getApiCategory($cat_id));
				foreach($features as $feature) {
					// convetirw lo switch in featureFactory
					switch ($type) 
					{
						case 'poi':
						$wm_feature = new WebmappPoiFeature($feature);
						break;
						case 'track':
						$wm_feature = new WebmappTrackFeature($feature);
						break;
						default:
						throw new Exception("Errore $type è un tipo di feture non ancora implementato.", 1);
						break;
					}
					$layer->addFeature($wm_feature);
				}
				$layers[]=$layer;
			}
		}
		return $layers;
	}

	public function getAllPoisLayer($path) {
		$l=new WebmappLayer('all-pois',$path);
		$items = WebmappUtils::getMultipleJsonFromApi($this->api_pois);
		if(is_array($items) && count($items)>0) {
            foreach ($items as $item) {
            	$p = new WebmappPoiFeature($item);
            	$props = $p->getProperties();
            	if(empty($p->getIcon())){
            		if(isset($this->taxonomies['webmapp_category']) && 
            			isset($props['taxonomy']) &&
            			isset($props['taxonomy']['webmapp_category']) &&
            			isset($props['taxonomy']['webmapp_category'][0])
            			)
            			$p->addProperty('icon',$this->taxonomies['webmapp_category'][$props['taxonomy']['webmapp_category'][0]]['icon']);
     
            	}
            	if(empty($p->getColor())){
            		if(isset($this->taxonomies['webmapp_category']) && 
            			isset($props['taxonomy']) &&
            			isset($props['taxonomy']['webmapp_category']) &&
            			isset($props['taxonomy']['webmapp_category'][0])
            			)
            			$p->addProperty('color',$this->taxonomies['webmapp_category'][$props['taxonomy']['webmapp_category'][0]]['color']);
            	}
            	$p->writeToPostGis();
            	$l->addFeature($p);
            }
		}
		return $l;
	}

	public function getAllTracksLayer($path) {
		$l=new WebmappLayer('all-tracks',$path);
		$items = WebmappUtils::getMultipleJsonFromApi($this->api_tracks);
		if(is_array($items) && count($items)>0) {
            foreach ($items as $item) {
            	$t = new WebmappTrackFeature($item);
            	$props = $t->getProperties();

            	if(empty($t->getColor())){
            		if(isset($this->taxonomies['activity']) && 
            			isset($props['taxonomy']) &&
            			isset($props['taxonomy']['activity']) &&
            			isset($props['taxonomy']['activity'][0])
            			)
            			$t->addProperty('color',$this->taxonomies['activity'][$props['taxonomy']['activity'][0]]['color']);            			
            	}
            	$t->write($path);
            	$t->writeToPostGis();
            	$l->addFeature($t);
            }
		}
		return $l;
	}
}