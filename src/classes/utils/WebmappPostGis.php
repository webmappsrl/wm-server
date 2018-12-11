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

    // returns array of associative array of results
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
		$q = " 
		INSERT INTO poi(instance_id,poi_id, geom) 
		VALUES('$instance_id',$poi_id, ST_GeomFromText('POINT($lon $lat )', 4326))
		ON CONFLICT (instance_id,poi_id) DO 
		   UPDATE SET geom=ST_GeomFromText('POINT($lon $lat )', 4326)
		;";
		$this->execute($q);
	}

	public function getPoiGeoJsonGeometry($instance_id,$poi_id) {
		$q = 'SELECT ST_AsGeoJSON(geom) as geojson from poi;';
		$a = $this->select($q);
		if (count($a)>0) {
			return $a[0]['geojson'];
		}
		else {
			throw new WebmappExceptionPostgisEmptySelect();
		}
	}

	// TODO: gestione del caso fuori dal DEM
	public function getEle($lon,$lat) {
		$q = <<< EOFQUERY
SELECT 
   ST_Value(eu_dem_100mx100m.rast, ST_Transform(ST_GeomFromText('POINT($lon $lat)', 4326),3035)) AS zeta
FROM 
   eu_dem_100mx100m 
WHERE 
   ST_Intersects(eu_dem_100mx100m.rast, ST_Transform(ST_GeomFromText('POINT($lon $lat)', 4326),3035))
   ;
EOFQUERY;
	$a = $this->select($q);
	return $a[0]['zeta'];
	}

	// $geom = stringa della geometria geojson SENZA altezza
	// RETURN = stringa arricchita con altezza
	public function addEle($geom) {
		// TODO check $geom
		$j=json_decode($geom,TRUE);
		$type=$j['type'];
		$coord=$j['coordinates'];
		$new_coord=array();
		foreach ($coord as $l) {
			$new_coord[]=array($l[0],$l[1],self::getEle($l[0],$l[1]));
		}
		$j_new=array('type'=>$type,'coordinates'=>$new_coord);
		return json_encode($j_new);
	}

	public function clearTables($instance_id) {
		foreach($this->all_tables as $table) {
			$q = "DELETE FROM $table;";
			$this->execute($q);
		}
	}
}