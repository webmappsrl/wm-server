<?php
class WebmappCAIFITask extends WebmappAbstractTask {


	public function check() {
		return TRUE;
	}

    public function process(){
	    // Download ID
        /**
         * [out:json][timeout:85];
        {{geocodeArea:Tuscany}}->.searchArea;
        (
        relation["type"="route"]["route"="hiking"]["source"="survey:CAI"]["source:ref"=9226001](area.searchArea);
        );
        out ids;
         */
        $overpass_query="https://overpass-api.de/api/interpreter?data=%5Bout%3Ajson%5D%5Btimeout%3A85%5D%3B%0Aarea%283600041977%29-%3E.searchArea%3B%0A%28%0A%20relation%5B%22type%22%3D%22route%22%5D%5B%22route%22%3D%22hiking%22%5D%5B%22source%22%3D%22survey%3ACAI%22%5D%5B%22source%3Aref%22%3D9226001%5D%28area.searchArea%29%3B%0A%29%3B%0Aout%20ids%3B%0A%0A";
        $ja = WebmappUtils::getJsonFromApi($overpass_query);
        $items = array();
        foreach ($ja['elements'] as $data ) {
            if ($data['type']=='relation') {
                $osmid = $data['id'];
                echo "Find track with osmid $osmid\n";
                $items[]=$osmid;
            }
        }

        // Create and elaborate tracks
        foreach ($items as $osmid) {
            echo "Processing track $osmid ... ";
            $relation = new WebmappOSMRelation($osmid);
            $track = $relation->getTrack();
            $track->write($this->getRoot().'/geojson');
            echo "DONE!\n";
        }
    }

}
