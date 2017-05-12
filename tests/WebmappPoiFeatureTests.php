<?php // WebmappPoiFeatureTests.php

use PHPUnit\Framework\TestCase;

class WebmappPoiFeatureTests extends TestCase {
	public function testOk() {
		$poi = new WebmappPoiFeature('http://dev.be.webmapp.it/wp-json/wp/v2/poi/522');
		$json = $poi->getJson();
        $this->assertRegExp('/"id":522/',$json);
        $this->assertRegExp('/"name":"Bar San Domenico"/',$json);
        $this->assertRegExp('/"description":"<p>Il bar di moda a porta a Lucca</',$json);
        $this->assertRegExp('/"color":"#dd3333"/',$json);
        $this->assertRegExp('/"icon":"wm-icon-generic"/',$json);
        $this->assertRegExp('/"noDetails":false/',$json);
        $this->assertRegExp('/"image":"http:.*dolomites-550349_960_720\.jpg"/',$json);
        $this->assertRegExp('/"src":"http:.*dolomites-550349_960_720\.jpg/',$json);
        $this->assertRegExp('/"src":"http:.*mountain-1077939_960_720\.jpg"/',$json);
        //$this->assertRegExp('/"":""/',$json);
        //$this->assertRegExp('/"":""/',$json);
	}
}
