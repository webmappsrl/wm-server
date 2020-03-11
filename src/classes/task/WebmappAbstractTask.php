<?php
abstract class WebmappAbstractTask {

	// Nome del Task
	protected $name;

	// ARray associativo con le opzioni che definiscono il task
	protected $options;

	// Root dir del progetto
	protected $project_structure;

	private $start_time;

	public function __construct ($name,$options,$project_structure) {
		$this->start_time=time();
		$this->name = $name;
		$this->options = $options;
		if (gettype($project_structure) != 'object' || get_class($project_structure) != 'WebmappProjectStructure') {
			throw new Exception("Wrong type of parameter project_structure", 1);		
		}
		$this->project_structure = $project_structure;
		echo "\n\n==========================================\n";
		echo "Staring TASK: $this->name\n";
		$root=$this->project_structure->getRoot();
		echo "ROOT DIR: $root\n";
		echo "Options: \n";
		var_dump($this->options);
		echo "==========================================\n\n";
	}
	
	// getters
	public function getName() { return $this->name; }
	// end of getters
	public function getRoot() { return $this->project_structure->getRoot();}
	public function getUrlBase() { return $this->project_structure->getUrlBase();}

	abstract public function check();
	abstract public function process();

	protected function end() {
		$delta=time()-$this->start_time;
		echo "\n\n==========================================\n";
		echo "\n\n\n TASK TERMINATED in $delta seconds\n";
		echo "==========================================\n\n\n";
	}

}

/**
class Webmapp[TaskName]Task {
	private $param;
	public function check() {
		// Check mandatory parameters;
		if(!array_key_exists('param', $this->options)) {
			throw new WebmappExceptionParameterMandatory("Parameter PARAM is mandatory", 1);		    	
		} else {
			$this->param=$this->options['param'];
		}
		// Other checks

		// END CHECK
		return TRUE;

	}

	public function process() {
		// DO SOMETHING;
		return TRUE;
	}
}
**/
