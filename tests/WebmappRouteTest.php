<?php // WebmappRouteTest.php

use PHPUnit\Framework\TestCase;

class WebmappRouteTest extends TestCase {

	public function testOk() {
		$r = new WebmappRoute('http://dev.be.webmapp.it/wp-json/wp/v2/route/346');
		$this->assertEquals('346',$r->getId());
		$this->assertEquals('Next to Net7 ROUTE',$r->getTitle());
		$tracks = $r->getTracks();
		$this->assertGreaterThan(0,count($tracks));
		foreach ($tracks as $track) {
			$this->assertEquals('WebmappTrackFeature',get_class($track));
		}
	}

	public function testGetLanguages() {
		$r = new WebmappRoute('http://dev.be.webmapp.it/wp-json/wp/v2/route/346');
		$ls = $r->getLanguages();
		$this->assertContains('en',$ls);
		$this->assertContains('fr',$ls);

	}

	public function testJson() {
		$r = new WebmappRoute('http://dev.be.webmapp.it/wp-json/wp/v2/route/346');
		$ja=json_decode($r->getJson(),TRUE);
        $this->assertTrue(isset($ja['type']));
        $this->assertEquals('FeatureCollection',$ja['type']);
        $this->assertTrue(isset($ja['features']));
        $this->assertTrue(count($ja['features'])>0);
        $this->assertTrue(isset($ja['properties']));
	}



}