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
}
