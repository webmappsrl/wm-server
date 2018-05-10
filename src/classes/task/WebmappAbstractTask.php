<?php
abstract class WebmappAbstractTask {

	// Nome del Task
	protected $name;

	// ARray associativo con le opzioni che definiscono il task
	protected $options;

	// Root dir del progetto
	protected $project_structure;

	public function __construct ($name,$options,$project_structure) {
		$this->name = $name;
		$this->options = $options;
		if (gettype($project_structure) != 'object' || get_class($project_structure) != 'WebmappProjectStructure') {
			throw new Exception("Wrong type of parameter project_structure", 1);		
		}
		$this->project_structure = $project_structure;
	}
	
	// getters
	public function getName() { return $this->name; }
	// end of getters

	abstract public function check();
	abstract public function process();

}