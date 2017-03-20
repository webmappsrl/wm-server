<?php


class WebmappTaskFactory {

    // $conf_file = Path to task configuration file
	public function  Task($conf_file) {
  
		$c = new ReadConf($conf_file);
		$c->check();
		$json=$c->getJson();
		$task_type=$json['task_type'];


		$class_name = 'Webmapp'.ucfirst($task_type).'Task';
		if(!class_exists($class_name))
			throw new Exception("Error: wrong type. ".$class_name, 1);			
		return new $class_name($name,$project_root,$json);
	}


}