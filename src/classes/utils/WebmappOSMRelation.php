<?php // WebmappOSMRelation

class WebmappOSMRelation {
	private $url = '';
	public function __construct($osmid) {
		// Controlla esistenza della relation
		$this->url = "https://www.openstreetmap.org/api/0.6/relation/$osmid/full";
	}
	public function load() {
		$h = get_headers($this->url);
		if (!preg_match('/200/',$h[0])) {
            throw new WebmappExceptionNoOSMRelation("Error: can't load ".$this->url,1);
            
		}
		return true;
	}
}