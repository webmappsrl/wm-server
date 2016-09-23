<?php
class WebmappOverpassNodeTask extends WebmappAbstractTask {

	private $query;
	private $encoded_query;

	public function check() {
		// Esistenza del parametro query
		if (!isset($this->json['query']))
		{
			$this->error='ERROR: parameter query in json is mandatory for this type of task.';
			return FALSE;
		}
		$this->query = $this->json['query'];
		$this->encoded_query = rawurlencode($this->query);

		// Esistenza della directory geojson
		// Possibilità di aprire in scrittura il file geojson
		return FALSE;
	}

	public function getQuery() { return $this->query; }
	public function getEncodedQuery() { return $this->encoded_query; }

	public function process() {
		return FALSE;
	}
}