<?php 

class WebmappUtils {
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
        $info['distance']=$stats->distanceTravelled*0.3048;

        print_r($info);

        return $info;
	}
}