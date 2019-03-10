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

    // TODO: inserire anche ele
    // $instance_id = http
	public function insertTrack($instance_id,$track_id,$geom) {
		// SELECT ST_GeomFromText
		// ('LINESTRING(-71.160281 42.258729,
		//              -71.160837 42.259113,
		//              -71.161144 42.25932)'); 

		// Convert $geom (php array geojson geometry)
		if (isset($geom['coordinates']) && 
			is_array($geom['coordinates']) &&
			count($geom['coordinates'])>0
			) {
			$linestring = array();
			foreach ($geom['coordinates'] as $point) {
				$lon = $point[0];
				$lat = $point[1];
				$linestring[]="$lon $lat";
			}
			$geom_string = "ST_GeomFromText('LINESTRING(".implode(',',$linestring).")',4326)";
			// Build Postgis query and execute it
			$q = " 
			INSERT INTO track(instance_id,track_id, geom) 
			VALUES('$instance_id',$track_id, $geom_string)
			ON CONFLICT (instance_id,track_id) DO 
		   		UPDATE SET geom=$geom_string
			;";
			$this->execute($q);
		}

	}

	// $tracks array with related track_id
	// $tracks = array (track_id_1,track_id2,...,track_id_n)
	public function insertRoute($instance_id,$route_id,$tracks) {
		if(count($tracks)>0) {
			foreach ($tracks as $track_id) {
				$q = " 
				INSERT INTO related_track(instance_id,route_id, track_id) 
				VALUES('$instance_id',$route_id, $track_id)
				ON CONFLICT (instance_id,route_id,track_id) DO NOTHING
				;";
				$this->execute($q);
			}
		}		
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
	if (count($a)>0) {
		return (float) $a[0]['zeta'];
	}
	return -1;
	}

	// $geom = stringa della geometria geojson SENZA altezza
	// RETURN = stringa arricchita con altezza
	public function addEle($geom) {
		// TODO check $geom
		$j=json_decode($geom,TRUE);
		$type=$j['type'];
		$coord=$j['coordinates'];
		$new_coord=array();

		switch ($type) {
			case 'LineString':
				foreach ($coord as $l) {
					$new_coord[]=array($l[0],$l[1],self::getEle($l[0],$l[1]));
				}
				break;
			
			case 'Point':
			    $new_coord=array($coord[0],$coord[1],self::getEle($coord[0],$coord[1]));
				break;
			
			default:
				throw new WebmappExceptionGeoJsonBadGeomType("$type not valid geojson type or not yet implemented in WebmappPostGis::addEle method.", 1);
				break;
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