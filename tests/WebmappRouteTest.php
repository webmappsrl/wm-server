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

	public function testTaxonomy() {
		$r = new WebmappRoute('http://dev.be.webmapp.it/wp-json/wp/v2/route/772');
		$ja=json_decode($r->getJson(),TRUE);
		$this->assertTrue(isset($ja['properties']['taxonomy']));
		$this->assertTrue(isset($ja['properties']['taxonomy']['activity']));

		// ACTIVITY
		$this->assertTrue(is_array($ja['properties']['taxonomy']['activity']));
		$this->assertTrue(count($ja['properties']['taxonomy']['activity'])>0);
		$this->assertTrue(in_array(47,$ja['properties']['taxonomy']['activity']));
		$this->assertTrue(in_array(40,$ja['properties']['taxonomy']['activity']));

		// THEME
		$this->assertTrue(is_array($ja['properties']['taxonomy']['theme']));
		$this->assertTrue(count($ja['properties']['taxonomy']['theme'])>0);
		$this->assertTrue(in_array(45,$ja['properties']['taxonomy']['theme']));
		$this->assertTrue(in_array(41,$ja['properties']['taxonomy']['theme']));


	}



}