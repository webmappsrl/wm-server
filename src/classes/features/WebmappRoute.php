<?php

class WebmappRoute {

	private $id;
	private $title;
	private $description;
	private $json_array = array();
	private $tracks = array();
	private $base_url;

	private $properties = array();
	private $features = array();

    // Array con le lingue presenti
	private $languages = array();

	public function __construct ($array_or_url,$base_url='') {
		if(is_array($array_or_url)) {
			$this->json_array=$array_or_url;
			$this->base_url=$base_url;		  
		}
		else {
			$this -> base_url = preg_replace('|route/.*|', '', $array_or_url);
			$this->json_array = WebmappUtils::getJsonFromApi($array_or_url);
			// BAD TRICK: la carico come TRACK e prendo quello che mi serve
			$feature = new WebmappTrackFeature($array_or_url);
			$feature_json = json_decode($feature->getJson(),TRUE);
		}

		// Image
		if(isset($feature_json['properties']['image'])){
			$this->properties['image']=$feature_json['properties']['image'];
		}
		// ImageGallery
		if(isset($feature_json['properties']['imageGallery'])){
			$this->properties['imageGallery']=$feature_json['properties']['imageGallery'];
		}

		if (isset($this->json_array['wpml_translations']) && 
			is_array($this->json_array['wpml_translations']) &&
			count($this->json_array['wpml_translations'])>0) {
			foreach($this->json_array['wpml_translations'] as $t ) {
				$lang = $t['locale'];
				// Converti en_XX -> en
				$lang=preg_replace('|_.*$|', '', $lang);
				array_push($this->languages, $lang);
			}

			// Aggiungi la lingua standard
			$actual = $this->json_array['wpml_current_locale'];
			$lang=preg_replace('|_.*$|', '', $actual);
			array_push($this->languages, $lang);
			$this->properties['locale']=$lang;
		}


		$this->id = $this->json_array['id'];
		$this->title = $this->json_array['title']['rendered'];
		$this->description = $this->json_array['content']['rendered'];

		// TODO: Difficulty
		if(isset($this->json_array['n7webmapp_route_difficulty'])){
			$this->properties['difficulty']=$this->json_array['n7webmapp_route_difficulty'];
		}

		// TODO: CODE
		if(isset($this->json_array['n7webmapp_route_cod'])){
			$this->properties['code']=$this->json_array['n7webmapp_route_cod'];
		}

		if(isset($this->json_array['wm_route_public'])){
			$this->properties['isPublic']=$this->json_array['wm_route_public'];
		}

		if(isset($this->json_array['use_password'])){
			$this->properties['use_password']=$this->json_array['use_password'];
		}

		if(isset($this->json_array['route_password'])){
			global $wm_config;
			$pk = $wm_config['route_password']['private_key'];
			$this->properties['route_password']=md5($pk.$this->json_array['route_password']);
		}

		// TODO: STAGES
		if (isset($this->json_array['n7webmap_route_related_track']) &&
            is_array($this->json_array['n7webmap_route_related_track']) &&
			count($this->json_array['n7webmap_route_related_track']) > 0 ) {
			$this->loadTracks();
		    $this->properties['stages']=count($this->json_array['n7webmap_route_related_track']);
	    }
	    else {
	    	$this->properties['stages']=0;
	    }
	        // Taxonomies
	$this->addTaxonomy('webmapp_category');
	$this->addTaxonomy('activity');
	$this->addTaxonomy('theme');
	$this->addTaxonomy('where');
	$this->addTaxonomy('when');
	$this->addTaxonomy('who');

	$this->buildPropertiesAndFeatures();

	$source = 'unknown';
	$wp_edit = 'unknown';
	if(isset($this->json_array['_links']['self'][0]['href'])) {
		$source = $this->json_array['_links']['self'][0]['href'];
            // ADD wp_edit
		$parse = parse_url($source);
		$host = $parse['host'];
            // http://dev.be.webmapp.it/wp-admin/post.php?post=509&action=edit 
		$wp_edit = 'http://'.$host.'/wp-admin/post.php?post='.$this->getId().'&action=edit';
	}
	$this->properties['source']=$source;
	$this->properties['wp_edit']=$wp_edit;
	$this->properties['modified']=$this->json_array['modified'];

	// LINK WEB
	if(isset($this->json_array['link']) && !empty($this->json_array['link'])) {
		$this->properties['web']=$this->json_array['link'];
	}

	// TRANSLATIONS
	if (isset($this->json_array['wpml_translations'])) {
		$t = $this->json_array['wpml_translations'];
		if (is_array($t) && count($t)>0) {
			$tp = array();
			foreach($t as $item) {
				$locale = preg_replace('|_.*$|', '', $item['locale']);
				$val=array();
				$val['id']=$item['id'];
				$val['name']=$item['post_title'];
				$val['web']=$item['href'];
				$val['source']= preg_replace('|/[0-9]*$|','/'.$val['id'],$this->properties['source']);
				$ja=WebmappUtils::getJsonFromApi($val['source']);
				if(isset($ja['content'])) {
					$val['description']=$ja['content']['rendered'];
				}
				$tp[$locale]=$val;
			}
			$this->properties['translations']=$tp;
		}
	}



}

public function getId() {
	return $this->id ;
}

public function getTitle() {
	return $this->title ;
}

public function getTracks() {
	return $this->tracks;
}

public function getLanguages() {
	return $this->languages;
}

public function getProperties() {
	return $this->properties;
}

public function getTaxonomies() {
	if(isset($this->properties['taxonomy'])) {
		return $this->properties['taxonomy'];
	}
	return array();
}

private function loadTracks() {
	if( isset($this->json_array['n7webmap_route_related_track']) &&
		is_array($this->json_array['n7webmap_route_related_track']) &&
		count($this->json_array['n7webmap_route_related_track'])>0){
		foreach ($this->json_array['n7webmap_route_related_track'] as $track ) {
			$url = $this->base_url .'track/' . $track['ID'];
			$t = new WebmappTrackFeature($url);
			$t->writeToPostGis();
			$t->add3D();
			$t->setComputedProperties2();
			array_push($this->tracks, $t);
		}		
	}
}

private function addTaxonomy($name) {
	if (isset($this->json_array[$name]) &&
		is_array($this->json_array[$name]) &&
		count($this->json_array[$name])>0) {
			$this->properties['taxonomy'][$name]=array_values(array_unique($this->json_array[$name]));
	}       
}

private function buildPropertiesAndFeatures() {
	$this->properties['id']=$this->id;
	$this->properties['name']=$this->title;
	$this->properties['description']=$this->description;
	if(count($this->tracks) >0 ) {
		$related=array();
		foreach($this->tracks as $track) {
			$this->features[]=json_decode($track->getJson(),TRUE);
			$related[]=$track->getId();
		}
		$this->properties['related']['track']['related']=$related;
	}

}

public function getJson() {
	$json = array();
	$json['type']='FeatureCollection';
	$json['properties']=$this->properties;
	$json['features']=$this->features;
	return json_encode($json);
}

public function writeToPostGis($instance_id='') {
	// Gestione della ISTANCE ID
	if(empty($instance_id)) {
		$instance_id = WebmappProjectStructure::getInstanceId();
	}
	$route_id=$this->getId();
	if(count($this->tracks)>0) {
		$pg = WebmappPostGis::Instance();
		$pg->insertRoute($instance_id,$this->getId(),$this->properties['related']['track']['related']);
	}
}

public function addBBox($instance_id='') {
	// Gestione della ISTANCE ID
	if(empty($instance_id)) {
		$instance_id = WebmappProjectStructure::getInstanceId();
	}
	$pg = WebmappPostGis::Instance();
	$bb = $pg->getRouteBBox($instance_id,$this->getId());
	if(!empty($bb)) {
		$this->properties['bbox']=$bb;
		$bb = $pg->getRouteBBoxMetric($instance_id,$this->getId());
		$this->properties['bbox_metric']=$bb;
	}
}

public function write($path) {
	file_put_contents($path.'/'.$this->id.'.geojson', $this->getJson());
}

public function generateAllImages($instance_id='',$path='') {

            // Gestione della ISTANCE ID
	if(empty($instance_id)) {
		$instance_id = WebmappProjectStructure::getInstanceId();
	}

	$sizes = array(
		array(491,624),
		array(400,300),
		array(200,200),
		array(1000,1000)
		);
	foreach ($sizes as $v) {
		$this->generateImage($v[0],$v[1],$instance_id,$path);
	}

}

public function generateImage($width,$height,$instance_id='',$path='') {
        	// Ignore route without tracks
	if(count($this->tracks)==0) {
		return;
	}
            // TODO: check parameter

            // Gestione della ISTANCE ID
	if(empty($instance_id)) {
		$instance_id = WebmappProjectStructure::getInstanceId();
	}           
	if(!isset($this->properties['bbox'])) {
		$this->addBBox($instance_id);
	}

	$geojson_url = 'https://a.webmapp.it/'.preg_replace('|http://|', '', $instance_id).'/geojson/'.$this->getId().'.geojson';
	$img_path = $path.'/'.$this->getId().'_map_'.$width.'x'.$height.'.png';

	WebmappUtils::generateImage($geojson_url,$this->properties['bbox_metric'],$width,$height,$img_path);
}

public function generateRBHTML($path,$instance_id='') {

	if(count($this->tracks)==0) {
		$this->loadTracks();
	}

	if(empty($instance_id)) {
		$instance_id = WebmappProjectStructure::getInstanceId();
	} 
	$code = preg_replace('|http://|','',$instance_id);          

	$file = $path.'/'.$this->getId().'_rb.html';
	$html = '<!DOCTYPE html>'."\n";
	$html .= '<html>' ."\n";
	$html .= '<head>' ."\n";
	$html .= '<link rel="stylesheet" type="text/css" href="https://api.webmapp.it/resources/rbcss/style.css">' ."\n";
	$html .= '<meta charset="UTF-8">'."\n";
	$html .= '</head>' ."\n";
	$html .= '<body>';
	$html .= "\n";
	
	// ROUTE (APERTURA)
	// Classificazione della ROUTE
	// TITOLO DELLA ROUTE
	$html .= '<div class="intro">'."\n";
	$html .= '<h1>'.$this->json_array['title']['rendered'].'</h1>';
	$html .= "\n";
	// TODO: IMMAGINE PRINCIPALE DELLA ROUTE https://dummyimage.com/366x212/000/fff.jpg&text=featured+366x212
	$html.= '<img src="https://dummyimage.com/366x212/000/fff.jpg&text=featured+366x212" />'."\n";

	// DIfficoltà ++ codice
	// CONTENUTO DELLA ROUTE
	$html .= $this->json_array['content']['rendered'];
	// INDICE DELLA ROUTE (primo LOOP sulle TRACK)
	$html .= '<pagebreak/>'."\n";
	if(count($this->tracks)>0){
		$html .= "<h2>Tappe:</h2>\n<ul>";
		foreach($this->tracks as $track) {
			$html .= "<li>".$track->getProperty('name')."</li>\n";
		}
	}
	$html .= '</ul>'."\n";
	$html .= '</div><!-- end class intro -->'."\n";

	// SINGOLE TRACK
	if(count($this->tracks)>0){
		foreach($this->tracks as $track) {
			$html .= $this->getRBTrackHTML($track,$code);
		}
	}

	// ROUTE CHIUSURA
	// MAPPA della ROUTE

	$html .= '<div class="footer">'."\n";
	$html .= '<img src="http://a.webmapp.it/'.$code.'/route/'.$this->getId().'_map_491x624.png"/>';
	$html .= "\n";
	$html .= '</div><!-- end class footer -->'."\n";


	$html .= '</body></html>';

	file_put_contents($file,$html);
}

private function getRBTrackHTML($track,$code) {
	$html ='';
	$html .= '<div class="track">'."\n";

	// MAPPA https://dummyimage.com/491x624/2cbf2f/fff.png&text=mappa+491x624
	$html .= '<img src="http://a.webmapp.it/'.$code.'/track/'.$track->getId().'_map_491x624.png"/>';
	$html .= '<pagebreak/>'."\n";

	// TODO: Classificazione della TRACK
	$html .= "<h2>".$track->getProperty('name')."</h2>\n";
	// TODO: Immagine della track https://dummyimage.com/366x212/000/fff.jpg&text=featured+366x212
	$html.= '<img src="https://dummyimage.com/366x212/000/fff.jpg&text=featured+366x212" />'."\n";
	$html .= "\n";
	// TODO: PROFILO ALTIMETRICO https://dummyimage.com/366x91/ed1552/fff.png&text=profilo+366x91
	$html.= '<img src="https://dummyimage.com/366x91/ed1552/fff.png&text=profilo+366x91" />'."\n";

	$html .= $track->getProperty('description');
	if($track->hasProperty('rb_track_section')){
		$html .= "<h3>Ulteriori Informazioni</h3>\n";
		$html .= $track->getProperty('rb_track_section')."\n";		
	}
	$html .= '<pagebreak/>'."\n";	


	// TODO: POI
	// THUMB del POI https://dummyimage.com/75x75/1539eb/fff.png&text=thumbnail+75x75
	$html .= '<div class="poi">'."\n";
	for ($i=1; $i <=20; $i++) { 
		$html .= '<div class="poi_item">'."\n";
		
		$html .= '<div class="poi_item_image">'."\n";
		$html .= '<span>'.$i.'</span>'."\n";
	    $html.= '<img src="https://dummyimage.com/75x75/1539eb/fff.png&text=thumbnail+75x75" />'."\n";
		$html .= '</div><!-- end class poi_item_image -->'."\n";

		$html .= '<div class="poi_item_info">'."\n";
		$html .= "<h3>POI titolo $i</h3>\n";
		$html .= "<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. 
		Integer congue feugiat nisl, quis tristique lectus gravida pharetra. 
		Duis faucibus libero nec nulla lacinia pellentesque. Nulla mollis quam ante, 
		et suscipit arcu hendrerit in. Etiam imperdiet nec tortor vel finibus. 
		Nulla facilisi. Duis aliquam volutpat massa, eget gravida nibh vehicula a. 
		Nunc elit dolor, scelerisque a velit eget, venenatis imperdiet nulla. Duis ut cursus tortor. 
		Interdum et malesuada fames ac ante ipsum primis in faucibus. 
		Sed nibh arcu, pellentesque eget ante sed, venenatis efficitur elit. 
		Fusce eu tempus leo, suscipit dapibus mauris.</p>" ;
		$html .= "\n";
		$html .= '</div><!-- end class poi_item_info -->'."\n";

		$html .= '</div><!-- end class poi_item -->'."\n";

	}
	$html .= '</div><!-- end class poi -->'."\n";
	$html .= '<pagebreak/>'."\n";	
	
	// TODO: ROADBOOK https://dummyimage.com/624x491/2beb15/fff.png&text=mappa-horz+624x491
	$html .= '<div class="roadbook">'."\n";
	for ($i=1; $i<=5; $i++) {
	    $html.= '<img src="https://dummyimage.com/624x491/2beb15/fff.png&text=mappa-horz+624x491" />'."\n";
		$html .= '<pagebreak/>'."\n";	
	}
	$html .= '</div><!-- end class roadbook -->'."\n";

	$html .= '</div><!-- end class track -->'."\n";
	$html .= "\n";
	return $html;
}

}