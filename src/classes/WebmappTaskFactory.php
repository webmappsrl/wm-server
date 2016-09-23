<?php


class WebmappTaskFactory {

	private $error ='NONE';

	public function getError() { return $this->error; }
	// Restituisce un oggetto della classe Webmbapp[Type]Task
	// Nel caso la classe non esistesse, restituisce FALSE e
	// la variabile errore contiene l'errore 
	public function  getTask($type) {

		$class_name = 'Webmapp'.ucfirst($type).'Task';
		if(class_exists($class_name)) {
			return new $class_name();
		}
		else {
     		$this->error='ERROR: '; 
            return NULL;
		}
	}


}