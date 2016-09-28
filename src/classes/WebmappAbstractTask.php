<?php
abstract class WebmappAbstractTask {

	// Nome del Task
	protected $name;

	// Struttura del progetto
	protected $project_structure;

	// Json con tutte le info aggiuntive del task
	protected $json;

	// File di output
	protected $poi_file;

	// Bounding box
	protected $bounds;

	// Messaggio di errore
	protected $error = 'NONE';

	public function __construct ($name,$project_root,$json) {
		$this->name = $name;
		$this->project_structure=new WebmappProjectStructure($project_root); 
		$this->poi_file = $this->project_structure->getPoi().$this->name.'.geojson';
		$this->json=$json;
		if(isset($json['bounds'])) $this->bounds = new WebmappBounds($json['bounds']);
	}
	
	public function getName() { return $this->name; }
	public function getProjectStructure() { return $this->project_structure; }
	public function getPoiFile() { return $this->poi_file; }
	public function getBounds() { return $this->bounds; }
	public function getType() { 
		$class_name = get_class($this);
		$type = preg_replace('/^Webmapp/', '', $class_name);
		$type = preg_replace('/Task$/', '', $type);
		return lcfirst($type);
	}

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

	abstract public function check();
	abstract public function process();

}