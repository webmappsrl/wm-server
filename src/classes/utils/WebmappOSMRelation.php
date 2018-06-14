<?php // WebmappOSMRelation

class WebmappOSMRelation {
	private $url = '';
	private $properties = array();
	public function __construct($osmid) {
		// Controlla esistenza della relation
		$this->url = "https://www.openstreetmap.org/api/0.6/relation/$osmid/full";
	}

	public function getProperties() {
		return $this->properties;
	}

	public function load() {
		$h = get_headers($this->url);
		if (!preg_match('/200/',$h[0])) {
            throw new WebmappExceptionNoOSMRelation("Error: can't load ".$this->url,1);         
		}
		$xml = simplexml_load_file($this->url);

		// Relation
		$relation = $xml->relation;
		// <relation id="4200445" visible="true" version="29" changeset="59153348" timestamp="2018-05-21T14:47:57Z" user="Andrea Del Sarto" uid="3215770">
		$this->properties['user']=$relation['user']->__toString();
		$this->timestamp['timestamp']=$relation['timestamp']->__toString();

		// Other tags
		foreach ($relation->tag as $tag) {
			$k=$tag['k']->__toString();
			$v=$tag['v']->__toString();
			$this->properties[$k]=$v;
		}
		return true;
	}
}