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

	public function testWriteToPostGis() {
		// ROUTE ID = 346
		// RELATED_TRACKS = 348 576
		$r = new WebmappRoute('http://dev.be.webmapp.it/wp-json/wp/v2/route/346');
		$id = $r->getId();
		$pg = WebmappPostGis::Instance();
		$pg->clearTables('test');
		$r->writeToPostGis('test');

		$q = "SELECT * from related_track WHERE instance_id='test' AND route_id=$id";
		$a = $pg->select($q);
		$this->assertEquals(2,count($a));

		$q = "SELECT * from related_track WHERE instance_id='test' AND track_id='348'";
		$a = $pg->select($q);
		$this->assertEquals(1,count($a));

		$q = "SELECT * from related_track WHERE instance_id='test' AND track_id='576'";
		$a = $pg->select($q);
		$this->assertEquals(1,count($a));
	}

	public function testBBox() {
		$pg = WebmappPostGis::Instance();
		$pg->clearTables('test');

		$t1 = new WebmappTrackFeature('http://dev.be.webmapp.it/wp-json/wp/v2/track/348');
		$t1->writeToPostGis('test');
		$t2 = new WebmappTrackFeature('http://dev.be.webmapp.it/wp-json/wp/v2/track/576');
		$t2->writeToPostGis('test');
		$r = new WebmappRoute('http://dev.be.webmapp.it/wp-json/wp/v2/route/346');
		$r->writeToPostGis('test');

		$bb = $r->addBBox('test');

		$ja=json_decode($r->getJson(),TRUE);

		$this->assertTrue(isset($ja['properties']['bbox']));
		$this->assertTrue(isset($ja['properties']['bbox_metric']));

		$this->assertEquals('10.39471,43.70904,10.3989,43.72326',$ja['properties']['bbox']);
		$this->assertEquals('1157134,5420525,1157600,5422715',$ja['properties']['bbox_metric']);

	}



}