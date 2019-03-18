<?php 

// CUSTOM Exceptions
class ExceptionWebmappUtilsGPXAddEleMultipleSegments extends Exception {}
class ExceptionWebmappUtilsGPXAddEleMultipleTracks extends Exception {}
class WebmappUtilsExceptionsGPXNoEle extends Exception {}

class WebmappUtils {
	/**
	returns an has array with stats info
	$info['tracks'] Number of tracks
	$info['trackpoints'] Number of trackpoints
	$info['distance'] Overall distance
	$info['has_ele'] Has elevation info (true/false)
	$info['ele_max']) Elevation max
	$info['ele_min']) Elevation min
	$info['ele_start']) Elevation @ start point
	$info['ele_end']) Elevation @ end point
	$info['ele_gain_positive']) D+ (from start to stop)
	$info['ele_gain_negative']) D- (from start to stop)
	$info['duration_forward']) Durata (solo se disponibile ELE, calcolato con sforzo equivalente)
	$info['duration_backward']) Durata (solo se disponibile ELE, calcolato con sforzo equivalente)
	**/
	public static function GPXAnalyze($file) {
		$g = new GPXIngest\GPXIngest();
		$g->enableExperimental('calcElevationGain');
		$g->loadFile($file);
		$g->ingest();

		$info = array();
		$ts = $g->getTrackIDs();
		$info['tracks'] = count($ts);
		//print_r($t);
		// Per convertire  da piedi in metri: 0.3048
        $stats = $g->getJourneyStats();
        $meta = $g->getMetadata();

        $info['trackpoints']=$stats->trackpoints;
        $info['ele_gain_positive']=0;
        $info['ele_gain_negative']=0;
        $info['ele_start']=0;
        $info['ele_end']=0;
        $info['has_ele']=false;

        $t = $g->getTrack($ts[0]);

        $info['has_multi_segments'] = false;
        if(isset($t->segments->seg1)) {
        	$info['has_multi_segments'] = true;
        }

        if($info['tracks']>0) {
        	if (!empty($t->segments->seg0->points->trackpt0->elevation)) {
        		$info['has_ele']=true;
        	}
        }

        if($info['tracks']==1 && !$info['has_multi_segments']) {
        	$info['ele_start'] = $t->segments->seg0->points->trackpt0->elevation;
        	$trackpt = 'trackpt'.($info['trackpoints']-1);
        	$info['ele_end'] = $t->segments->seg0->points->$trackpt->elevation;
        	for ($i=0; $i < $info['trackpoints']; $i++) { 
        		$trackpt = 'trackpt'.$i;
        		$meta = $t->segments->seg0->points->$trackpt;
        		$dist = $meta->distanceTravelled*0.3048;
        		$delta = $meta->elevationChange;
        		// Cut off 3 m step
        		if($delta > 3) {
        			$info['ele_gain_positive'] += $delta;
        		}
        		else if ($delta < -3){
        			$info['ele_gain_negative'] += -($delta);
        		}
        	}
        }

        $info['ele_max']=$stats->elevation->max;
        $info['ele_min']=$stats->elevation->min;
        $info['distance']=round($stats->distanceTravelled/3280,1);

        // Average speed 3.5 Km / h
        $hiking_mean_speed = 3.5;
        $df = ( $info['distance'] + ($info['ele_gain_positive'] / 100)) / $hiking_mean_speed;
        $db = ( $info['distance'] + ($info['ele_gain_negative'] / 100)) / $hiking_mean_speed;
        $info['duration_forward'] = self::formatDuration($df);
        $info['duration_backward'] = self::formatDuration($db);


        return $info;
	}

	/**
	Add elevation info on GPX with no Elevation:
	IN <trkpt lat="46.0820515873" lon="11.1021300999" />
	OUT <trkpt lat="46.0820515873" lon="11.1021300999"><ele>194.0</ele></trkpt>
		$in = GPX file input (no ele)
		$out = GPX file output (with)
	**/
	public static function GPXAddEle($in,$out) {

		// File input deve esistere
		if(!file_exists($in)) {
			throw new Exception("File $in does not exists", 1);
			
		}
		$info = self::GPXAnalyze($in);

		// Se ha più di una traccia non funziona
		if($info['tracks']>1) {
			throw new ExceptionWebmappUtilsGPXAddEleMultipleTracks("GPX not valid: multiple tracks not supported.", 1);	
		}
		// Se ha più di una traccia non funziona
		if($info['has_multi_segments']) {
			throw new ExceptionWebmappUtilsGPXAddEleMultipleSegments("GPX not valid: multiple segments not supported.", 1);	
		}

		// Se ha già elevation copia IN -> OUT ritorna TRUE
		if ($info['has_ele']) {
			$cmd = "cp $in $out";
			system($cmd);
			return true;
		}

		// Da qui in poi assumo di avere singola traccia e singolo SEG
		$xml = simplexml_load_string(file_get_contents($in));
		$points = array();
		foreach($xml->trk->trkseg->trkpt as $pt) {
			$lat = (float) $pt->attributes()->lat->__toString();
			$lon = (float) $pt->attributes()->lon->__toString();
			$points[]=array($lat,$lon);
		}
		$elevations = self::getElevations($points);
		$i=0;
		foreach($xml->trk->trkseg->trkpt as $pt) {
			$pt->addChild('ele',$elevations[$i]);
			$i = $i+1;
		}
		return $xml->asXML($out);

	}

	public static function GPXGenerateProfile($in,$out) {
		if(!file_exists($in)) {
			throw new WebmappExceptionNoFile('File $in does not exist');
		}
		if(!file_exists(dirname($out))) {
			throw new WebmappExceptionNoDirectory('Directory '.dirname($out).' does not exist');
		}
		$info = self::GPXAnalyze($in);
		if(!$info['has_ele']) {
			throw new WebmappUtilsExceptionsGPXNoEle('Invalid GPX: no elevation data needed for profile');
		}

		return false;

	}

	public static function formatDuration($h){
		if ($h==0) return '0:00';
		$hs = floor($h);
		$ms = ($h*60) % 60;
		if ($h>=0.5) {
			$ms = floor($ms/10)*10;
		}
		else {
			$ms = floor($ms/5)*5;
		}
		if ($hs==0 && $ms==0) {
			$ms = '05';
		}
		else {
			$ms = str_pad($ms,2,'0');
		}
		return "$hs:$ms";
	}

	/**
	$points = array (
	  array(lat1,lng1),
	  array(lat2,lng2),
	  ...
	  array(latN,lngN)
	)
	**/
	public static function getBingElevations($points) {
		if(!is_array($points)) {
			throw new Exception("Points must be array", 1);
		}
		if (count($points)==0) {
			throw new Exception("No elements in array", 1);
		}
		// Prepare Points
		$p1 = array();
		foreach($points as $point) {
          $p1[]=implode(',', $point);
		}
		$p2=implode(',', $p1);

		$bing_url = 'http://dev.virtualearth.net/REST/v1/Elevation/List';
		$bing_key='Amw2lh34kxU-6D0i5kpZjjsBw8HecF7ZjVDtSNixG3H2-MEOw-14JS-lgCT9W0BD';
		$post = "points=$p2&heights=ellipsoid&key=$bing_key";
		$url_get = "$bing_url?$post";
		$r = json_decode(file_get_contents($url_get),TRUE);
		return $r['resourceSets'][0]['resources'][0]['elevations'];

	}
	/**
	$points = array (
	  array(lat1,lng1),
	  array(lat2,lng2),
	  ...
	  array(latN,lngN)
	)
	**/
	// ATTENZIONE INPUT $points ha lat lon invertiti!
	public static function getElevations($points) {
		if(!is_array($points)) {
			throw new Exception("Points must be array", 1);
		}
		if (count($points)==0) {
			throw new Exception("No elements in array", 1);
		}
		$pg = WebmappPostGis::Instance();
		foreach($points as $p) {
			$elevations[]=$pg->getEle($p[1],$p[0]);
		}
		return $elevations;

	}
	public static function getBingElevation($lat,$lng) {
		$ele = self::getBingElevations(array(array($lat,$lng)));
		return $ele[0];
	}
    
    // Gestire la cache tramite SQLLITE
	public static function getJsonFromApi($url) {
		global $wm_config;
		$debug = false;
		if (isset($wm_config['debug']) && $wm_config['debug']) {
			$debug=true;
		}
		if ($debug) echo "Fecthing data from $url ... ";
		$download = true;
		$webcache = false;
		if (isset($wm_config['webcache']) && 
			isset($wm_config['webcache']['enabled']) && 
			$wm_config['webcache']['enabled']==true) {
			if(!isset($wm_config['webcache']['db'])) {
				throw new Exception("config.json malconfigurato: webcache enabled e db non definito.", 1);
			}
			$db_file=$wm_config['webcache']['db'];
			if(file_exists($db_file)) {
				$webcache=true;
				$db=new SQLite3($db_file);
			}
			else {
				echo "WARN: webcache db not created. Use CLI to create it.";
			}
		}

		if ($webcache) {
			// Try to retrieve from cache
			$q="SELECT content from webcache where url='$url'";
			$r=$db->query($q);
			while ($row=$r->fetchArray()) {
				$output = $row['content'];
				$download = false;
				if ($debug) echo " cache.";
			}
		}
		if ($download) {

			// switch file / URL
			if(preg_match('/^http/',$url)) {
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, $url);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
				curl_setopt($ch,CURLOPT_USERAGENT,'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');
				$output = curl_exec($ch);
				curl_close($ch);				
			} else {
			 	$output=file_get_contents($url);				
				if ($debug) echo " direct download.";
			}

			if ($webcache) {
				// Write on DB
				$q="INSERT into webcache (url,content,timestamp) VALUES (:url,:content,:time)";
				$s=$db->prepare($q);
				$s->bindParam(':url',$url);
				$s->bindParam(':content',$output);
				$time=time();
				$s->bindParam(':time',$time);
				$s->execute();
			}
		}
		if ($debug) echo "\n";
		return json_decode($output,TRUE);
	}
	// Returns an array of multiple JSON API CALLS paged (?per_page=XX)
	public static function getMultipleJsonFromApi($url) {
		$r=array();

		$page=1;
		$go_next=false;
        $paged_url=self::getPagedUrl($url,$page);
		$headers=get_headers($paged_url);
		$ret=$headers[0];
		if (preg_match('/200/',$ret)){
			$res=self::getJsonFromApi($paged_url);
			if(is_array($res) && count($res)>0){
				$r=array_merge($r,$res);
				$go_next=true;
			}
		}

		while($go_next) {
			$page=$page+1;
			$go_next=false;
            $paged_url=self::getPagedUrl($url,$page);
			$headers=get_headers($paged_url);
			$ret=$headers[0];
			if (preg_match('/200/',$ret)){
				$res=self::getJsonFromApi($paged_url);
				if(is_array($res) && count($res)>0){
					$r=array_merge($r,$res);
					$go_next=true;
				}
			}
		}
		return $r;
	}
    // Gestire la cache tramite SQLLITE
	public static function getXMLFromUrl($url) {
		// echo "getJsonFromApi($url) \n";
		global $wm_config;
		//echo "Fecthing XML from $url ... ";
		$download = true;
		$webcache = false;
		if (isset($wm_config['webcache']) && 
			isset($wm_config['webcache']['enabled']) && 
			$wm_config['webcache']['enabled']==true) {
			if(!isset($wm_config['webcache']['db'])) {
				throw new Exception("config.json malconfigurato: webcache enabled e db non definito.", 1);
			}
			$db_file=$wm_config['webcache']['db'];
			if(file_exists($db_file)) {
				$webcache=true;
				$db=new SQLite3($db_file);
			}
			else {
				echo "WARN: webcache db not created. Use CLI to create it.";
			}
		}

		if ($webcache) {
			// Try to retrieve from cache
			$q="SELECT content from webcache where url='$url'";
			$r=$db->query($q);
			while ($row=$r->fetchArray()) {
				$output = simplexml_load_string($row['content']);
				$download = false;
				//echo " cache.";
			}
		}
		if ($download) {
			$content = file_get_contents($url);
			$output = simplexml_load_string($content);
			if ($webcache) {
				// Write on DB
				$q="INSERT into webcache (url,content,timestamp) VALUES (:url,:content,:time)";
				$s=$db->prepare($q);
				$s->bindParam(':url',$url);
				$s->bindParam(':content',$content);
				$time=time();
				$s->bindParam(':time',$time);
				$s->execute();
			}
		}
		// echo "\n";
		return $output;
	}

    // Gestire la cache tramite SQLLITE
	public static function getContentFromUrl($url) {
		// echo "getJsonFromApi($url) \n";
		global $wm_config;
		//echo "Fecthing CONTENTE from $url ... ";
		$download = true;
		$webcache = false;
		if (isset($wm_config['webcache']) && 
			isset($wm_config['webcache']['enabled']) && 
			$wm_config['webcache']['enabled']==true) {
			if(!isset($wm_config['webcache']['db'])) {
				throw new Exception("config.json malconfigurato: webcache enabled e db non definito.", 1);
			}
			$db_file=$wm_config['webcache']['db'];
			if(file_exists($db_file)) {
				$webcache=true;
				$db=new SQLite3($db_file);
			}
			else {
				echo "WARN: webcache db not created. Use CLI to create it.";
			}
		}

		if ($webcache) {
			// Try to retrieve from cache
			$q="SELECT content from webcache where url='$url'";
			$r=$db->query($q);
			while ($row=$r->fetchArray()) {
				$output = $row['content'];
				$download = false;
				//echo " cache.";
			}
		}
		if ($download) {
			$output = file_get_contents($url);
			if ($webcache) {
				// Write on DB
				$q="INSERT into webcache (url,content,timestamp) VALUES (:url,:content,:time)";
				$s=$db->prepare($q);
				$s->bindParam(':url',$url);
				$s->bindParam(':content',$output);
				$time=time();
				$s->bindParam(':time',$time);
				$s->execute();
			}
		}
		// echo "\n";
		return $output;
	}



	public static function getPagedUrl($url,$page) {
		if(preg_match('|\?|',$url)) {
			$paged_url = $url . "&page=$page";
		}
		else {
			$paged_url = $url . "?page=$page";
		}
		return $paged_url;
	}

	public static function slugify($text)
	{
		$text = preg_replace('~[^\pL\d]+~u', '-', $text);
		$text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
		$text = preg_replace('~[^-\w]+~', '', $text);
		$text = trim($text, '-');
		$text = preg_replace('~-+~', '-', $text);
		$text = strtolower($text);
		if (empty($text)) {
			return 'n-a';
		}
		return $text;
	}
	public static function getSettoriByOSMID($osmid) {
		$settori = '';
		$q = "SELECT DISTINCT CONCAT(settori_cai_toscana.area,settori_cai_toscana.settore) as settore FROM settori_cai_toscana, planet_osm_line WHERE planet_osm_line.osm_id=-$osmid AND ST_Intersects(settori_cai_toscana.geom, ST_transform(planet_osm_line.way,4326));";
        $d = pg_connect("host=46.101.124.52 port=5432 dbname=general user=webmapp password=T1tup4atmA");
        $r = pg_query($d,$q);
        while($row = pg_fetch_array($r)) {
        	$settori = $settori .' ' . $row['settore'];
        }
		return $settori;
	}
	public static function getSettoriIDByOSMID($osmid) {
		$ids = '';
		$q = "SELECT DISTINCT settori_cai_toscana.id as id FROM settori_cai_toscana, planet_osm_line WHERE planet_osm_line.osm_id=-$osmid AND ST_Intersects(settori_cai_toscana.geom, ST_transform(planet_osm_line.way,4326));";
        $d = pg_connect("host=46.101.124.52 port=5432 dbname=general user=webmapp password=T1tup4atmA");
        $r = pg_query($d,$q);
        while($row = pg_fetch_array($r)) {
        	$ids = $ids .' ' . $row['id'];
        }
		return $ids;
	}

	public static function cleanPostGis() {
		echo "Cleaning Postgis\n";
        $cmd = "psql -h 46.101.124.52 -U webmapp webmapptest -c \"DELETE FROM poi_tmp\"";
        system($cmd);
        $cmd = "psql -h 46.101.124.52 -U webmapp webmapptest -c \"DELETE FROM track_tmp\"";
        system($cmd);
	}
	// Crea un layer di POI generato a partire dalle ROUTE
	public static function createRouteIndexLayer($api_url){
		$track_api_url = preg_replace('|/wp-json/wp/v2/route|', '/wp-json/wp/v2/track', $api_url);
		$l = new WebmappLayer('route_index');
		$routes = self::getMultipleJsonFromApi($api_url);
		if (count($routes)>0) {
			foreach ($routes as $route) {
				$poi = new WebmappPoiFeature($route,true);
				if (is_array($route['n7webmap_route_related_track'])&&count($route['n7webmap_route_related_track'])>0){
					$first_track = $route['n7webmap_route_related_track'][0];
					$track = self::getJsonFromApi($track_api_url.'/'.$first_track['ID']);
					if (isset($track['n7webmap_geojson'])&&!empty($track['n7webmap_geojson'])) {
						$geom = unserialize($track['n7webmap_geojson']);
						if (isset($geom['coordinates'])
							&&is_array($geom['coordinates'])
							&&count($geom['coordinates'])>0) {
							$lon = $geom['coordinates'][0][0];
							$lat = $geom['coordinates'][0][1];
							$poi->setGeometry($lon,$lat);
							$l->addFeature($poi);
						} else {
							echo "WARN: X bad geometry track (".$track['id']."): SKIPPING\n";
						}
					} else {
						echo "WARN: bad geometry track (".$track['id']."): SKIPPING\n";
					}
				}
				else {
					echo "WARN: no tracks in route (".$route['id']."): SKIPPING\n";
				}				
			}
		}
		return $l;
	}

	public static function googleGeocode($address) {
		global $wm_config;
		$key=$wm_config['google']['api_key'];
		$url='';

	}

	// It returns a random point array($lon,$lat) centered in $lon0,$lat0 with
	// radius (in degree) $rho
	public static function getRandomPoint($lon0,$lat0,$rho) {
		$theta = 2 * pi() * mt_rand(0,1000) / 1000;
		$r = $rho * mt_rand(0,1000) / 1000;
		$lon = $lon0 + $r * sin($theta);
		$lat = $lat0 + $r * cos($theta);
		return array($lon,$lat);
	}

	// Estensione degli assert di phpunit x verificare che un 
	// numero è in un range
	public function testDelta($expected,$actual,$delta) {
			$test->assertGreaterThan($actual,$expected-$delta);
			$test->assertLesserThan($actual,$expected+$delta);
	}

	// Returns distance in KM (haversineGreatCircleDistance)
	// REF: https://stackoverflow.com/questions/10053358/measuring-the-distance-between-two-coordinates-in-php
	public static function distance(
		$longitudeFrom, $latitudeFrom, $longitudeTo, $latitudeTo, $earthRadius = 6371)
	{
  // convert from degrees to radians
		$latFrom = deg2rad($latitudeFrom);
		$lonFrom = deg2rad($longitudeFrom);
		$latTo = deg2rad($latitudeTo);
		$lonTo = deg2rad($longitudeTo);

		$latDelta = $latTo - $latFrom;
		$lonDelta = $lonTo - $lonFrom;

		$angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
			cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
		return $angle * $earthRadius;
	}

	public static function getOptimalBBox($bbox,$width,$height,$perc=0.05) {
		    $bbox_array = explode(',',$bbox);
            $xmin = $bbox_array[0];
            $ymin = $bbox_array[1];
            $xmax = $bbox_array[2];
            $ymax = $bbox_array[3];
            $dx = abs($xmax-$xmin);
            $dy = abs($ymax-$ymin);

            if($dx/$dy > $width/$height) {
                $d=($height/$width*$dx-$dy)*0.5;
                $ymax = $ymax + $d;
                $ymin = $ymin - $d;
            }
            else if ($dx/$dy < $width/$height) {
                $d=($width/$height*$dy-$dx)*0.5;
                $xmax = $xmax + $d;
                $xmin = $xmin - $d;
            }

            // Allargare del $perc %
            $dx = abs($xmax-$xmin);
            $dy = abs($ymax-$ymin);
            $xmin = $xmin - $dx*$perc;
            $xmax = $xmax + $dx*$perc;
            $ymin = $ymin - $dy*$perc;
            $ymax = $ymax + $dy*$perc;

            return implode(',', array($xmin,$ymin,$xmax,$ymax));
	}

	public static function generateImage($geojson_url,$bbox,$width,$height,$img_path,$fit=TRUE) {

        if($fit) {
	        $bbox=WebmappUtils::getOptimalBBox($bbox,$width,$height);        	
        }

		$post_data = array(
			'geojson_url' => $geojson_url,
			'bbox' => $bbox,
			'width' => $width,
			'height' => $height
			);

		$ch = curl_init('http://qgs.webmapp.it/track.php');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
		$image_url = curl_exec($ch);
		curl_close($ch);
		file_put_contents($img_path, file_get_contents($image_url));
	}

	public static function generateImageWithPois($geojson_url,$pois_geojson_url,$bbox,$width,$height,$img_path,$fit=TRUE) {

        if($fit) {
	        $bbox=WebmappUtils::getOptimalBBox($bbox,$width,$height);        	
        }

		$post_data = array(
			'geojson_url' => $geojson_url,
			'pois_geojson_url' => $pois_geojson_url,
			'bbox' => $bbox,
			'width' => $width,
			'height' => $height
			);

		$ch = curl_init('http://qgs.webmapp.it/track.php');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
		$image_url = curl_exec($ch);
		curl_close($ch);
		file_put_contents($img_path, file_get_contents($image_url));
	}

}