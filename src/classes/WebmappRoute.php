<?php

class WebmappRoute {

	private $id;
	private $title;
	private $json_array;

	public function __construct ($array_or_url) {
		if (!is_array($array_or_url)) {
			// E' Un URL quindi leggo API WP e converto in array
			$this->json_array = json_decode(file_get_contents($array_or_url),true);
		}
		else {
			// TODO: implementare la lettura dell'array diretta
			throw new Exception("Lettura diretta array ancora anon implementato", 1);
			$this->json_array = $array_or_url;
		}

		$this->id = $this->json_array['id'];
		$this->title = $this->json_array['title']['rendered'];
	}

	public function getId() {
		return $this->id ;
	}

	public function getTitle() {
		return $this->title ;
	}

}