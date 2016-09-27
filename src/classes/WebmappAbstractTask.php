<?php
abstract class WebmappAbstractTask {

	// Nome del Task
	protected $name;

    // Path del file di configurazione
	protected $path;

	// Path dei progetto
	protected $project_path;

	// Json con tutte le info aggiuntive del task
	protected $json;

	// geojson Path
	// Todo: refactoring con classe WebmappProjectStructure (solo name) 
	protected $geojson_path;

    // Poi
	protected $poi_path;
	protected $poi_file;
	protected $poi_single_path;

	protected $bounds;

	// Messaggio di errore
	protected $error = 'NONE';

	public function __construct ($name,$path,$json) {
		$this->name = $name; 
		$this->path = $path;
		$this->project_path = preg_replace('/server$/','',dirname($path));
		$this->geojson_path = $this->project_path.'geojson/';
		$this->poi_path = $this->geojson_path.'poi/';
		$this->poi_file = $this->poi_path.$this->name.'.geojson';
		$this->poi_single_path = $this->poi_path.'id/';


		$this->json=$json;
		if(isset($json['bounds'])) $this->bounds = new WebmappBounds($json['bounds']);
	}
	
	public function getName() { return $this->name; }
	public function getGeojsonPath() { return $this->geojson_path; }
	public function getPoiPath() { return $this->poi_path; }
	public function getPoiFile() { return $this->poi_file; }
	public function getPoiSinglePath() { return $this->poi_single_path; }
	public function getBounds() { return $this->bounds; }
	public function getType() { 
		$class_name = get_class($this);
		$type = preg_replace('/^Webmapp/', '', $class_name);
		$type = preg_replace('/Task$/', '', $type);
		return lcfirst($type);
	}

	public function getPath() { return $this->path; }
	public function getProjectPath() { return $this->project_path; }
	public function getJson() { return $this->json; }
	public function getError() { return $this->error; }

	protected function checkBounds() {
		// Controllo esistenza bounding box
		// TODO: test eccezione
		if(!isset($this->bounds))
			throw new Exception("No bounding box given in json parameter", 1);
			
		// Controllo tipo bounding box (WebmappBounds)
		// TODO: test eccezione
		if (get_class($this->bounds) != 'WebmappBounds')
			throw new Exception("Property bounds is not an instance of WebmappBounds class: ".get_class($this->bounds), 1);	
	}

	protected function checkPoiStructure() {
		// TODO: verifica esistenza della directory geojson/poi/
		// TODO: verifica scrivibilità della directory geojson/poi/
		// TODO: nel caso esista già verifica la scrivibiità del file poi_file
		// TODO: verifica esistenza della directory geojson/poi/id/
		// TODO: verifica la scrivibilità della directory geojson/poi/id

		return true;
	}

	abstract public function check();
	abstract public function process();

}