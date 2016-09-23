<?php
abstract class WebmappAbstractTask {

	// Nome del Task
	private $name;

	// Info del task (contiene path e json con configurazione)
	private $info;
	
	public function getName() { return $this->name; }
	public function getType() { 
		$class_name = get_class($this);
		$type = preg_replace('/^Webmapp/', '', $class_name);
		$type = preg_replace('/Task$/', '', $type);
		return lcfirst($type);
	}

	public function setName($name) { $this->name = $name; }
	public function setInfo($info) { $this->info = $info; }

	abstract public function check();
	abstract public function process();

}