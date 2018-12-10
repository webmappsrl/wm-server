<?php // WebmappOSMRelation

class WebmappOSMRelation extends WebmappOSMFeature {
	protected function init(){ 
		$this->url=$this->base_url.'relation/'.$this->id;
	}
	protected function setFeature() {
		$this->feature = $this->xml->relation;
	}

	public function getKMLFromWMT() {
		$url = 'https://hiking.waymarkedtrails.org/api/details/relation/'.$this->id.'/kml';
		return WebmappUtils::getContentFromUrl($url);
	}

	public function getTrack() {
		// Creazione della TRACK
		$ja = array('id'=>$this->id);
		$track = new WebmappTrackFeature($ja);

		// Mapping delle properties
		$track->map($this->getTags());
		$track->map($this->getProperties());
		if(isset($this->tags['from'])) {
			$track->addProperty('start',$this->tags['from']);
		}
		if(isset($this->tags['to'])) {
			$track->addProperty('stop',$this->tags['to']);
		}

		// Geometry (from KML to geojson)
		$decoder = new Symm\Gisconverter\Decoders\KML();
		$geometry = $decoder->geomFromText($this->getKMLFromWMT());
		$track->setGeometryGeoJSON($geometry->toGeoJSON());

		return $track;
	}

}
