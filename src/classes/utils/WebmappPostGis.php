<?php

final class WebmappPostGis
{

    private $resource;
    private $all_tables = array('poi', 'track', 'feature_taxonomy', 'related_track');

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
        if (!isset($wm_config['postgis'])) {
            throw new WebmappExceptionConfPostgis("No Postgist section in conf.json", 1);
        }

        // TODO: check other parametr
        $pgconf = $wm_config['postgis'];

        $username = $pgconf['username'];
        $database = $pgconf['database'];
        $host = $pgconf['host'];
        $password = $pgconf['password'];

        // TODO: gestire l'errore con una eccezione
        $this->resource = pg_connect("host=$host dbname=$database user=$username password=$password")
        or die('Could not connect: ' . pg_last_error());

    }

    public function getResource()
    {
        return $this->resource;
    }

    // Use this to perform insert and delete query
    // return true if execution is ok
    // TODO: gestione eccezione
    public function execute($q)
    {
        $result = pg_query($this->resource, $q);
        if ($result === false) {
            // TODO: go to log system
            echo "\n Query failed: $q\n";
            echo "Error: " . pg_last_error() . "\n";
            return false;
        }
        return true;
    }

    // returns array of associative array of results
    // TODO: gestione eccezione
    public function select($q)
    {
        $r = pg_query($this->resource, $q) or die('Query failed: ' . pg_last_error());
        $a = array();
        while ($row = pg_fetch_assoc($r)) {
            $a[] = $row;
        }
        return $a;
    }

    // TODO: inserire anche ele
    public function insertPoi($instance_id, $poi_id, $lon, $lat)
    {
        $poi_id = (int)$poi_id;
        $q = "
		INSERT INTO poi(instance_id,poi_id, geom)
		VALUES('$instance_id','$poi_id', ST_GeomFromText('POINT($lon $lat )', 4326))
		ON CONFLICT (instance_id,poi_id) DO
		   UPDATE SET geom=ST_GeomFromText('POINT($lon $lat )', 4326)
		;";
        $this->execute($q);
    }

    // TODO: inserire anche ele
    // $instance_id = http
    public function insertTrack($instance_id, $track_id, $geom)
    {
        // SELECT ST_GeomFromText
        // ('LINESTRING(-71.160281 42.258729,
        //              -71.160837 42.259113,
        //              -71.161144 42.25932)');

        // Convert $geom (php array geojson geometry)
        if (isset($geom['coordinates']) &&
            is_array($geom['coordinates']) &&
            count($geom['coordinates']) > 0
        ) {
            $linestring = array();
            foreach ($geom['coordinates'] as $point) {
                $lon = $point[0];
                $lat = $point[1];
                $linestring[] = "$lon $lat";
            }
            $geom_string = "ST_GeomFromText('LINESTRING(" . implode(',', $linestring) . ")',4326)";
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
    public function insertRoute($instance_id, $route_id, $tracks)
    {
        if (count($tracks) > 0) {
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

    public function getPoiGeoJsonGeometry($instance_id, $poi_id)
    {
        $q = "SELECT ST_AsGeoJSON(geom) AS geojson
		      FROM poi
		      WHERE instance_id='$instance_id' AND poi_id=$poi_id
		      ;";
        $a = $this->select($q);
        if (count($a) > 0) {
            return $a[0]['geojson'];
        } else {
            throw new WebmappExceptionPostgisEmptySelect();
        }
    }

    public function getTrackBBox($instance_id, $track_id)
    {
        if ($this->trackExists($instance_id, $track_id)) {
            $q = "
		 SELECT
			CONCAT (
			ST_Xmin(ST_Envelope(ST_Collect(geom))),',',
			ST_Ymin(ST_Envelope(ST_Collect(geom))),',',
			ST_Xmax(ST_Envelope(ST_Collect(geom))),',',
			ST_Ymax(ST_Envelope(ST_Collect(geom)))) as bbox
 		 FROM track WHERE
 		 track_id = $track_id AND instance_id='$instance_id';";
            $a = $this->select($q);
            return $a[0]['bbox'];
        }
    }

    public function getTrackBBoxMetric($instance_id, $track_id)
    {
        if ($this->trackExists($instance_id, $track_id)) {
            $q = "
		 SELECT
			CONCAT (
			ROUND(ST_Xmin(ST_Transform(ST_Envelope(ST_Collect(geom)), 3857))),',',
			ROUND(ST_Ymin(ST_Transform(ST_Envelope(ST_Collect(geom)), 3857))),',',
			ROUND(ST_Xmax(ST_Transform(ST_Envelope(ST_Collect(geom)), 3857))),',',
			ROUND(ST_Ymax(ST_Transform(ST_Envelope(ST_Collect(geom)), 3857)))) as bbox
 		 FROM track WHERE
 		 track_id = $track_id AND instance_id='$instance_id';";
            $a = $this->select($q);
            return $a[0]['bbox'];
        }
    }

    // TODO: calculate dx proportionally to latitude
    public function getRouteBBox($instance_id, $route_id)
    {
        if ($this->routeExists($instance_id, $route_id)) {
            $q = "
		 SELECT
			CONCAT (
			ST_Xmin(ST_Envelope(ST_Collect(geom))),',',
			ST_Ymin(ST_Envelope(ST_Collect(geom))),',',
			ST_Xmax(ST_Envelope(ST_Collect(geom))),',',
			ST_Ymax(ST_Envelope(ST_Collect(geom)))) as bbox
 		 FROM track
 		 WHERE track_id
 		    IN (SELECT track_id FROM related_track WHERE route_id=$route_id AND instance_id='$instance_id')
         AND instance_id='$instance_id'
            ;";
            $a = $this->select($q);
            $bbox = $a[0]['bbox'];
            $bboxArray = explode(',', $bbox);
            $dx = 0.05;
            $dy = 0.05;
            $bboxArray[0] -= $dx;
            $bboxArray[1] -= $dy;
            $bboxArray[2] += $dx;
            $bboxArray[3] += $dy;

            return implode(',', $bboxArray);
        }
    }

    public function getRouteBBoxMetric($instance_id, $route_id)
    {
        if ($this->routeExists($instance_id, $route_id)) {
            $q = "
		 SELECT
			CONCAT (
			ROUND(ST_Xmin(ST_Transform(ST_Envelope(ST_Collect(geom)), 3857))),',',
			ROUND(ST_Ymin(ST_Transform(ST_Envelope(ST_Collect(geom)), 3857))),',',
			ROUND(ST_Xmax(ST_Transform(ST_Envelope(ST_Collect(geom)), 3857))),',',
			ROUND(ST_Ymax(ST_Transform(ST_Envelope(ST_Collect(geom)), 3857)))) as bbox
 		 FROM track
 		 WHERE track_id
 		    IN (SELECT track_id FROM related_track WHERE route_id=$route_id AND instance_id='$instance_id')
         AND instance_id='$instance_id'
            ";
            $a = $this->select($q);

            $bbox = $a[0]['bbox'];
            $bboxArray = explode(',', $bbox);
            $dx = 5000;
            $dy = 5000;
            $bboxArray[0] -= $dx;
            $bboxArray[1] -= $dy;
            $bboxArray[2] += $dx;
            $bboxArray[3] += $dy;

            return implode(',', $bboxArray);
        }
    }

    public function trackExists($instance_id, $track_id)
    {
        $q = "SELECT track_id FROM track WHERE track_id=$track_id AND instance_id='$instance_id'";
        $a = $this->select($q);
        if (count($a) > 0) {
            return true;
        }
        return false;
    }

    public function routeExists($instance_id, $route_id)
    {
        $q = "SELECT * FROM related_track WHERE route_id=$route_id AND instance_id='$instance_id'";
        $a = $this->select($q);
        if (count($a) > 0) {
            return true;
        }
        return false;
    }

    // TODO: gestione del caso fuori dal DEM
    public function getEle($lon, $lat)
    {
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
        if (count($a) > 0) {
            return (float)$a[0]['zeta'];
        }
        return -1;
    }

    // $geom = stringa della geometria geojson SENZA altezza
    // RETURN = stringa arricchita con altezza
    public function addEle($geom)
    {
        // TODO check $geom
        $j = json_decode($geom, true);
        $type = $j['type'];
        $coord = $j['coordinates'];
        $new_coord = array();

        switch ($type) {
            case 'LineString':
                echo "Computing ele progress:     ";
                $tot = count($coord);
                $count = 0;
                foreach ($coord as $l) {
                    // Progress %
                    $perc = floor($count / $tot * 100);
                    echo "\033[4D";
                    echo str_pad($perc, 3, ' ', STR_PAD_LEFT) . "%";
                    $count++;

                    // CALC
                    $new_coord[] = array($l[0], $l[1], self::getEle($l[0], $l[1]));
                }
                echo "\n";
                break;

            case 'Point':
                $new_coord = array($coord[0], $coord[1], self::getEle($coord[0], $coord[1]));
                break;

            default:
                throw new WebmappExceptionGeoJsonBadGeomType("$type not valid geojson type or not yet implemented in WebmappPostGis::addEle method.", 1);
                break;
        }
        $j_new = array('type' => $type, 'coordinates' => $new_coord);

        return json_encode($j_new);
    }

    public function clearTables($instance_id)
    {
        $q = "DELETE FROM poi WHERE instance_id='$instance_id';";
        $this->execute($q);
        $q = "DELETE FROM track WHERE instance_id='$instance_id';";
        $this->execute($q);
        $q = "DELETE FROM related_track WHERE instance_id='$instance_id';";
        $this->execute($q);
    }
}
