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
	public static function getElevations($points) {
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

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,'https://api.webmapp.it/services/3d/get3dgeojsonbylatlon.php');
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS,"l=$p2");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$server_output = curl_exec ($ch);
		curl_close ($ch);
		$r = json_decode($server_output,TRUE);
		$elevations = array();
		foreach($r['features'][0]['geometry']['coordinates'] as $coord) {
			$elevations[]=floor($coord[2]);
		}

		return $elevations;

	}
	public static function getBingElevation($lat,$lng) {
		$ele = self::getBingElevations(array(array($lat,$lng)));
		return $ele[0];
	}

	public static function getJsonFromApi($url) {
		// echo "getJsonFromApi($url) \n";
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch,CURLOPT_USERAGENT,'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');
		$output = curl_exec($ch);
		curl_close($ch);
		return json_decode($output,TRUE);
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
}