<?php 

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

        if($info['tracks']>0) {
        	if (!empty($t->segments->seg0->points->trackpt0->elevation)) {
        		$info['has_ele']=true;
        	}
        }

        if($info['tracks']==1) {
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
	public static function getBingElevation($lat,$lng) {
		$ele = self::getBingElevations(array(array($lat,$lng)));
		return $ele[0];
	}
}