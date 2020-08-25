<?php

/** SINGLETON USED TO QUERY OSM MIRROR
TODO: prevent writing
List of relations
Schema |        Name        | Type  | Owner
--------+--------------------+-------+-------
public | planet_osm_line    | table | root
public | planet_osm_nodes   | table | root
public | planet_osm_point   | table | root
public | planet_osm_polygon | table | root
public | planet_osm_rels    | table | root
public | planet_osm_roads   | table | root
public | planet_osm_ways    | table | root
public | spatial_ref_sys    | table | root
(8 rows)
 **/

final class WebmappPostGisOSM
{

    private $resource;

    public static function Instance()
    {
        static $inst = null;
        if ($inst === null) {
            $inst = new WebmappPostGisOSM();
        }
        return $inst;
    }

    private function __construct()
    {
        global $wm_config;
        if (!isset($wm_config['postgisosm'])) {
            throw new WebmappExceptionConfPostgis("No Postgist section in conf.json", 1);
        }

        // TODO: check other parametr
        $pgconf = $wm_config['postgisosm'];

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

    public function getWayMeta($osmid)
    {
        $q = "SELECT * from planet_osm_line WHERE osm_id=$osmid";
        $a = $this->select($q);
        return array('highway' => $a[0]['highway'], 'surface' => $a[0]['surface']);
    }

    private function getFeatureJsonGeometry($type, $osmid)
    {
        switch ($type) {
            case 'relation':
                $table = 'planet_osm_line';
                $geo_field = 'way';
                break;
            case 'way':
                $table = 'planet_osm_line';
                $geo_field = 'way';
                break;

            default:
                throw new WebmappExceptionPostgisOSM("TYPE:$type not valid or not yet implemented.", 1);
                break;
        }
        // SELECT  ST_AsGeoJSON(ST_Transform (way, 4326)) as geojson
        // FROM planet_osm_line
        // WHERE osm_id = '-7006731';

        $q = "SELECT ST_AsGeoJSON(ST_Transform ($geo_field, 4326)) as geojson
		      FROM $table
		      WHERE osm_id=$osmid ;
		";

        $a = $this->select($q);

        if (count($a) > 0) {

            // Check inverted
            if ($type == 'relation') {
                $ja = json_decode($a[0]['geojson'], true);
                $lon0 = $ja['coordinates'][0][0];
                $lat0 = $ja['coordinates'][0][1];
                $rid = -$osmid;
                $q = "select members from planet_osm_rels where id='$rid'";
                $b = $this->select($q);
                if (count($b) > 0) {
                    $members = $b[0]['members'];
                    $members = preg_replace('|{|', '', $members);
                    $members = preg_replace('|}|', '', $members);
                    $members = explode(',', $members);
                    foreach ($members as $member) {
                        if (preg_match('|w|', $member)) {
                            $member = preg_replace('|w|', '', $member);
                            break;
                        }
                    }
                    try {
                        $way_geojson = $this->getFeatureJsonGeometry('way', $member);
                        $way_array = json_decode($way_geojson, true);
                        $first = $way_array['coordinates'][0];
                        $last = end($way_array['coordinates']);
                        $to_invert = true;
                        if ($first[0] == $lon0 && $first[1] == $lat0) {
                            $to_invert = false;
                        } else if ($last[0] == $lon0 && $last[1] == $lat0) {
                            $to_invert = false;
                        }
                    } catch (WebmappExceptionPostgisEmptySelect $e) {
                        echo "WARN - Way $member does not exist in the DB";
                    }

                    if ($to_invert) {
                        $q = "SELECT ST_AsGeoJSON(ST_Transform (ST_Reverse($geo_field), 4326)) as geojson
			      			  FROM $table
		      			     WHERE osm_id=$osmid ;";
                        $a = $this->select($q);
                    }
                }
            }

            return $a[0]['geojson'];
        } else {
            throw new WebmappExceptionPostgisEmptySelect();
        }
    }

    public function getRelationJsonGeometry($osmid)
    {
        return $this->getFeatureJsonGeometry('relation', -$osmid);
    }

}
