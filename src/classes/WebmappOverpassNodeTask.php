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

		// Controllo dei Bounds
		$this->checkBounds();

		// Esistenza della directory geojson
		// TODO: come è possibile testare le eccezioni? (https://phpunit.de/manual/current/en/writing-tests-for-phpunit.html#writing-tests-for-phpunit.exceptions)
		if (!file_exists($this->geojson_path)) 
			   throw new Exception($this->geojson_path . " geojson directory does not exixsts", 1);
		
		// Scrivibilità della directory geojson
	    // TODO: test eccezione
		if (!is_writable($this->geojson_path))
			   throw new Exception($this->geojson_path . " geojson directory is not writable", 1);

		// Scrivibilità del file nel caso incui esista già
	    // TODO test eccezione
		if (file_exists($this->geojson_file) && !is_writable($this->geojson_file))
			throw new Exception($this->geojson_file." geojson file already exists and is not writable.", 1);
			
        
		return TRUE;
	}

	public function getQuery() { return $this->query; }
	public function getEncodedQuery() { return $this->encoded_query; }

	public function process() {
		return FALSE;
	}
}