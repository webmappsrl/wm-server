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
		$t = $g->getTrack($ts[0]);
		//print_r($t);
		// Per convertire  da piedi in metri: 0.3048
        $stats = $g->getJourneyStats();
        $meta = $g->getMetadata();

        $info['trackpoints']=$stats->trackpoints;
        $info['ele_max']=$stats->elevation->max;
        $info['ele_min']=$stats->elevation->min;
        $info['distance']=$stats->distanceTravelled*0.3048;

        return $info;
	}
}