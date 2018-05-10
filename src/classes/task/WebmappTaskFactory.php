<?php

class WebmappTaskFactory {

	static public function  Task($name,$options,$project_structure) {

        // Controlli sui parametri
        
		if(!is_array($options))
			throw new Exception("Il parametro options deve essere un array", 1);

		if(!array_key_exists('type', $options))
			throw new Exception("Manca la chiave type nell'array options", 1);

		$class_name = 'Webmapp'.ucfirst($options['type']).'Task';
		if(!class_exists($class_name))
			throw new Exception("Error: wrong type. ".$class_name, 1);

		if(get_class($project_structure) != 'WebmappProjectStructure')
		    throw new Exception("Project Structure is not an instance of WebmappProjectStructure class", 1);
		    		

		return new $class_name($name,$options,$project_structure);

	}


}