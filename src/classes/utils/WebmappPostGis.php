<?php

final class WebmappPostGis {

	private $resource;
	private $all_tables = array('poi','track','feature_taxonomy','related_track');

	public static function Instance()
	{
		static $inst = null;
		if ($inst === null) {
			$inst = new WebmappPostGis();
		}
		return $inst;
	}

	private function __construct()
	{
		global $wm_config;
		if(!isset($wm_config['postgis'])) {
			throw new WebmappExceptionConfPostgis("No Postgist section in conf.json", 1);  
		}

        // TODO: check other parametr
		$pgconf = $wm_config['postgis'];

		$username = $pgconf['username'];
		$database = $pgconf['database'];
		$host = $pgconf['host'];
		$password= $pgconf['password'];

        // TODO: gestire l'errore con una eccezione
		$this->resource = pg_connect("host=$host dbname=$database user=$username password=$password")
		or die('Could not connect: ' . pg_last_error());

	}

	public function getResource() {
		return $this->resource;
	}

    // Use this to perform insert and delete query
    // return true if execution is ok
    // TODO: gestione eccezione
	public function execute($q) {
		$result = pg_query($this->resource,$q) or die('Query failed: ' . pg_last_error());
		return TRUE;
	}

    // returns array of associative array
    // TODO: gestione eccezione
	public function select($q) {
		$r = pg_query($this->resource,$q) or die('Query failed: ' . pg_last_error());
		$a = array();
		while ($row = pg_fetch_assoc($r)) {
			$a[]=$row;
		}
		return $a;
	}
    // TODO: inserire anche ele
	public function insertPoi($instance_id,$poi_id,$lon,$lat) {
		$q="INSERT INTO poi(instance_id,poi_id, geom) VALUES('$instance_id',$poi_id, ST_GeomFromText('POINT($lon $lat )', 4326));";
		$this->execute($q);
	}

	public function clearTables($instance_id) {
		foreach($this->all_tables as $table) {
			$q = "DELETE FROM $table;";
			$this->execute($q);
		}
	}
}