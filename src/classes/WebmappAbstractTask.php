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
		$this->project_structure = $project_structure;
	}
	
	// getters
	public function getName() { return $this->name; }
	// end of getters

	abstract public function check();
	abstract public function process();

}