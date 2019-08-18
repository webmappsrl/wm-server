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

	// TAXONOMIES management
	private $taxonomies=array();

	// active Language code
	private $languages_active='';
	// Others lanugages code
	private $languages_others=array();

	public function __construct($code) {
		$this->code = $code;

		if(preg_match('|^http://|', $code) || preg_match('|^https://|', $code)) {
			$this->base_url = $code;
		}
		else {
			$this->base_url = "http://$code.be.webmapp.it";
		}

		$this->api_wpml_list = "{$this->base_url}/wp-json/webmapp/v1/wpml/list";
		$this->api_url = "{$this->base_url}/wp-json/wp/v2";
		$this->api_pois = "{$this->api_url}/poi";
		$this->api_tracks = "{$this->api_url}/track";
		$this->api_routes = "{$this->api_url}/route";
		$this->api_areas = "{$this->api_url}/area";
		$this->api_maps = "{$this->api_url}/map";
		$this->api_categories = "{$this->api_url}/webmapp_category";

		$this->loadLanguages();

	}

	public function loadLanguages() {
		$r = WebmappUtils::getJsonFromApi($this->api_wpml_list);
		if(isset($r['active'])){
			$this->languages_active=$r['active'];
			$this->languages_others=$r['others'];			
		}
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

	public function loadTaxonomy($name) {
		$parents=array();
		$url=$this->api_url.'/'.$name;
		$res = WebmappUtils::getMultipleJsonFromApi($url);
		$new=array();
		if(is_array($res) && count($res)>0){
			foreach($res as $item){
			if(isset($item['featured_image']) && is_array($item['featured_image'])) {
				if (isset($item['featured_image']['sizes']['medium_large'])){
					$item['image']=($item['featured_image']['sizes']['medium_large']);
				} else if (isset($jm['media_details']['sizes']['medium'])) {
					$item['image']=($item['featured_image']['sizes']['medium']);
				}
			}
			//Other values
			$source = $item['source']=$item['_links']['self'][0]['href'];
			$item['web']=$this->base_url."/$name/{$item['slug']}";
			$item['wp_edit']=$this->base_url."/wp-admin/term.php?taxonomy=$name&tag_ID={$item['id']}";

			// Prune empty values
			unset($item['featured_image']);
			unset($item['meta']);
			unset($item['_links']);
			if(empty($item['color'])) unset($item['color']);
			if(empty($item['icon'])) unset($item['icon']);	
			if(empty($item['title'])) unset($item['title']);	
			if(empty($item['featured_icon'])) unset($item['featured_icon']);
			// Gestione del parent
			if(isset($item['parent']) && $item['parent']!=0) {
				$parents[]=$item['parent'];
			}
			// Azzera il count
			$item['count']=0;
			// is parent deafult false
			$item['is_parent']=false;

			// TRANSLATIONS
			$item['locale']=$this->languages_active;
			if(count($this->languages_others)>0) {
				foreach($this->languages_others as $lang) {
					// TODO: verificare che funzioni sempre
					$source_lang = $source ."?lang=$lang";
					//echo "Adding lang $lang $source_lang\n";
					$j_trans=WebmappUtils::getJsonFromApi($source_lang);
					if($j_trans['id']!=$item['id']) {
						$item['translations'][$lang]['name']=$j_trans['name'];
						if(!empty($j_trans['description'])) {
							$item['translations'][$lang]['description']=$j_trans['description'];
						}
					}
				}
			}
			$new[$item['id']]=$item;
			}
		}
		// Gestione dei parents
		if(count($parents)>0) {
			$parents = array_unique($parents);
			foreach ($parents as $term_id) {
				$new[$term_id]['is_parent']=true;
			}
		}
		$this->taxonomies[$name]=$new;
	}

	public function getTaxonomies() {
		return $this->taxonomies;
	}

	// TODO: add translations
	public function writeTaxonomies($path) {
		if (count($this->taxonomies)>0) {
			if (!file_exists($path)) {
				throw new Exception("Directory $path does not exist.", 1);		
			}
			if(!is_writeable($path)) {
				throw new Exception("Directory $path is not writable", 1);
			}
			foreach ($this->taxonomies as $taxonomy => $items) {
				$file = $path.'/'.$taxonomy.'.json';
				file_put_contents($file, json_encode($items));
			}			
		}
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

	public function getLanguageActive() {
		return $this->languages_active;
	}

	public function getLanguageOthers() {
		return $this->languages_others;
	}

	// CONTROLLI DI RISPOSTA DALLA PIATTAFORMA

	private function checkUrl($url) {
		$ch = curl_init();
		$headers = [];
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_FRESH_CONNECT, TRUE);
		curl_setopt($ch,CURLOPT_USERAGENT,'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');

		// this function is called by curl for each header received
		curl_setopt($ch, CURLOPT_HEADERFUNCTION,
		  function($curl, $header) use (&$headers)
		  {
		    $len = strlen($header);
		    $header = explode(':', $header, 2);
		    if (count($header) < 2) // ignore invalid headers
		      return $len;

		    $headers[strtolower(trim($header[0]))][] = trim($header[1]);

		    return $len;
		  }
		);

		$data = curl_exec($ch);
		//print_r($headers);
		$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		//echo "CODE $httpcode\n\n\n\n";

		$match = (preg_match('/200|301/', $httpcode));
		if($match==0) {
			echo "\n\nWARN: header[0] ! 200:" .$httpcode. "\n\n";
			return false;
		}
		return true;
	}

	public function check() {
		$url=$this->base_url.'/wp-json/wp/v2';
		return $this->checkUrl($url);
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
			//$api = $api . '&per_page='.$this->per_page.'&orderby=title&order=asc' ;
			//$features = WebmappUtils::getJsonFromApi($api);
			$api = $api . '&orderby=title&order=asc' ;
			$features = WebmappUtils::getMultipleJsonFromApi($api);
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

	private function addPoiToTaxonomies($id,$props) {
		$this->addItemToTaxonomies('poi',$id,$props);
	}
	private function addTrackToTaxonomies($id,$props) {
		$this->addItemToTaxonomies('track',$id,$props);
	}
	private function addRouteToTaxonomies($id,$props) {
		$this->addItemToTaxonomies('route',$id,$props);
	}

	private function addItemToTaxonomies($feature_type,$id,$props) {
		if(isset($props['taxonomy'])
			&& is_array($props['taxonomy']) &&
			count($props['taxonomy'])>0) {
			foreach($props['taxonomy'] as $tax_name => $items) {
				if(is_array($items) && count($items) >0) {
					foreach($items as $term_id) {
						$this->taxonomies[$tax_name][$term_id]['items'][$feature_type][]=$id;
					}
				}
			}
		}
	}

	public function pruneTaxonomies() {
		$this->pruneTaxonomy('webmapp_category');
		$this->pruneTaxonomy('activity');
		$this->pruneTaxonomy('theme');
		$this->pruneTaxonomy('who');
		$this->pruneTaxonomy('where');
		$this->pruneTaxonomy('when');
	}
	public function pruneTaxonomy($name) {
		$t=$this->taxonomies[$name];
		if(count($t)>0) {
			// First loop: find parents
			$parents=array();
			foreach ($t as $id => $item) {
					$parents[]=$item['parent'];
			}
			// Second loop: remove term with no items that are not Parent
			$to_remove=array();
			foreach($t as $id=>$item) {
				if(!isset($item['items']) && !in_array($id,$parents)) {
					$to_remove[]=$id;
				}
			}
			if(count($to_remove)>0) {
				foreach ($to_remove as $id) {
					unset($this->taxonomies[$name][$id]);
				}
			}
		}

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
            			if (isset($this->taxonomies['webmapp_category'][$props['taxonomy']['webmapp_category'][0]]['icon']))
            				$p->addProperty('icon',$this->taxonomies['webmapp_category'][$props['taxonomy']['webmapp_category'][0]]['icon']);
     
            	}
            	if(empty($p->getColor())){
            		if(isset($this->taxonomies['webmapp_category']) && 
            			isset($props['taxonomy']) &&
            			isset($props['taxonomy']['webmapp_category']) &&
            			isset($props['taxonomy']['webmapp_category'][0])
            			)
            			if (isset($this->taxonomies['webmapp_category'][$props['taxonomy']['webmapp_category'][0]]['color']))
	            			$p->addProperty('color',$this->taxonomies['webmapp_category'][$props['taxonomy']['webmapp_category'][0]]['color']);
            	}
            	$this->addPoiToTaxonomies($p->getId(),$props);
            	$p->writeToPostGis();
            	$l->addFeature($p);
            }
		}
		return $l;
	}

	public function getAllRoutesLayer($path) {
		$l=new WebmappLayer('all-routes',$path);
		$items = WebmappUtils::getMultipleJsonFromApi($this->api_routes);
		if(is_array($items) && count($items)>0) {
            foreach ($items as $item) {
            	$p = new WebmappRoute($item,$this->base_url.'/wp-json/wp/v2/');
            	$l->addFeature($p);
            	$this->addRouteToTaxonomies($p->getId(),$p->getProperties());
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
            			if (isset($this->taxonomies['activity'][$props['taxonomy']['activity'][0]]['color']))
            				$t->addProperty('color',$this->taxonomies['activity'][$props['taxonomy']['activity'][0]]['color']);            			
            	}
            	$this->addTrackToTaxonomies($t->getId(),$props);
            	$t->write($path);
            	$t->writeToPostGis();
            	$l->addFeature($t);
            }
		}
		return $l;
	}

	// $items = array con la lista di tutte le associazioni features -> term_id
// 	Array
// (
//     [poi] => Array
//         (
//             [971] => Array
//                 (
//                     [webmapp_category] => Array
//                         (
//                             [0] => 9
//                         )

//                 )
	public function addItemsAndPruneTaxonomies($tax) {
		foreach ($tax as $type => $items) {
			foreach ($items as $id => $taxs) {
				if(is_array($taxs) && count($taxs)>0) {
					foreach($taxs as $tax_name=>$values) {
						if(is_array($values) && count($values)>0) {
							foreach($values as $term_id) {
								echo "taxonomies[$tax_name][$term_id]['items'][$type][]=$id\n";
								$this->taxonomies[$tax_name][$term_id]['items'][$type][]=$id;
								if(!isset($this->taxonomies[$tax_name][$term_id]['count'])) {
									$this->taxonomies[$tax_name][$term_id]['count']=1;
								}
								else {
									$this->taxonomies[$tax_name][$term_id]['count']++;
								}
							}
						}
					}
				}
			}
		}
		// Find terms to remove !is_parent && count==0
		$to_remove=array();
		foreach ($this->taxonomies as $tax_name => $terms) {
			foreach ($terms as $term_id => $meta) {
				if($meta['is_parent']==false && $meta['count']==0) {
					$to_remove[]=array($tax_name,$term_id);
				}
			}
		}

		// Remove terms
		if(count($to_remove)>0) {
			foreach ($to_remove as $term) {
				echo "Removing term {$term[0]} {$term[1]}\n";
				unset($this->taxonomies[$term[0]][$term[1]]);
			}
		}
	}
}