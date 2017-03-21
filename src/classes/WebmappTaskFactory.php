<?php

class WebmappTaskFactory {

    // $conf_file = Path to task configuration file
	static public function  Task($name,$options,$root) {
		// controlli
		if(!is_array($options))
			throw new Exception("Il parametro options deve essere un array", 1);
		if(!array_key_exists('type', $options))
			throw new Exception("Manca la chiave type nell'array options", 1);			
		$class_name = 'Webmapp'.ucfirst($options['type']).'Task';
		if(!class_exists($class_name))
			throw new Exception("Error: wrong type. ".$class_name, 1);			

		return new $class_name($name,$options,$root);

	}


}