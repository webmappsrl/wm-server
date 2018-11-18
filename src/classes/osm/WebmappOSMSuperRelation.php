<?php

class WebmappOSMSuperRelation extends WebmappOSMFeature {
	protected function init(){ 
		$this->url=$this->base_url.'relation/'.$this->id;
	}
	protected function setFeature() {
		$this->feature = $this->xml->relation;
	}
}
