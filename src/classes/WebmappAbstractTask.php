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

	// Messaggio di errore
	protected $error = 'NONE';

	public function __construct ($name,$path,$json) {
		$this->name = $name; 
		$this->path = $path;
		$this->project_path = preg_replace('/server$/','',dirname($path));
		$this->json=$json;

	}
	
	public function getName() { return $this->name; }
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

	abstract public function check();
	abstract public function process();

}