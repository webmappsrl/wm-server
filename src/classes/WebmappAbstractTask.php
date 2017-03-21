<?php
abstract class WebmappAbstractTask {

	// Nome del Task
	protected $name;

	// ARray associativo con le opzioni che definiscono il task
	protected $options;

	// Root dir del progetto
	protected $root;

	public function __construct ($name,$options,$root) {
		$this->name = $name;
		$this->options = $options;
		$this->root = rtrim($root, '/');
	}
	
	// getters
	public function getName() { return $this->name; }
	// end of getters

	abstract public function check();

}