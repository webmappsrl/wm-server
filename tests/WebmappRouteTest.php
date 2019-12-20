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

		$this->assertEquals('10.34471,43.65904,10.4489,43.77326',$ja['properties']['bbox']);
		$this->assertEquals('1152134,5415525,1162600,5427715',$ja['properties']['bbox_metric']);

	}

	public function testGenerateRBHTML($instance_id='') {
		$file = '/tmp/346_rb.html';
		system("rm -Rf $file");
		$r = new WebmappRoute('http://dev.be.webmapp.it/wp-json/wp/v2/route/346');
		$r->generateRBHTML('/tmp','http://dev.be.webmapp.it');
		$this->assertTrue(file_exists($file));
		echo "\n\n\n FILE $file created!\n\n\n";
	}

	public function testTranslations() {
		$route = new WebmappRoute('http://dev.be.webmapp.it/wp-json/wp/v2/route/346');
		$j = json_decode($route->getJson(),true);

		$this->assertTrue(isset($j['properties']['locale']));
		$this->assertEquals('it',$j['properties']['locale']);

		$this->assertTrue(isset($j['properties']['translations']));
		$t=$j['properties']['translations'];
		$this->assertTrue(isset($t['en']));
		$this->assertTrue(isset($t['fr']));
		$this->assertTrue(isset($t['de']));
		$en=$t['en'];
		$fr=$t['fr'];
		$de=$t['de'];

		$this->assertEquals(358,$en['id']);
		$this->assertEquals(737,$fr['id']);
		$this->assertEquals(806,$de['id']);

		$this->assertEquals('http://dev.be.webmapp.it//next-to-net7/?lang=en',$en['web']);
		$this->assertEquals('http://dev.be.webmapp.it/wp-json/wp/v2/route/358',$en['source']);
		$this->assertEquals('Next to Net7',$en['name']);
		$this->assertEquals(1,preg_match('|Description in english|',$en['description']));

	}

	public function testModified() {
		$wp_url = 'http://dev.be.webmapp.it/wp-json/wp/v2/route/917';
        $ja = WebmappUtils::getJsonFromApi($wp_url);
        $modified = $ja['modified'];

        $r = new WebmappRoute($wp_url);
        $j = json_decode($r->getJson(),TRUE);
        $this->assertEquals($modified,$j['properties']['modified']);

	}

	public function testCode() {
		$wp_url = 'http://dev.be.webmapp.it/wp-json/wp/v2/route/772';
        $r = new WebmappRoute($wp_url);
        $j = json_decode($r->getJson(),TRUE);
        $this->assertEquals('PIPPO2',$j['properties']['code']);
	}
	public function testDifficulty() {
		$wp_url = 'http://dev.be.webmapp.it/wp-json/wp/v2/route/772';
        $r = new WebmappRoute($wp_url);
        $j = json_decode($r->getJson(),TRUE);
        $this->assertEquals(2.5,$j['properties']['difficulty']);
	}
	public function testStages() {
		$wp_url = 'http://dev.be.webmapp.it/wp-json/wp/v2/route/772';
        $r = new WebmappRoute($wp_url);
        $j = json_decode($r->getJson(),TRUE);
        $this->assertEquals(2,$j['properties']['stages']);
	}
	public function testBugImageRouteVN() {
		$wp_url = 'http://vn.be.webmapp.it/wp-json/wp/v2/route/1123';
        $r = new WebmappRoute($wp_url);
        $j = json_decode($r->getJson(),TRUE);
        $this->assertTrue(isset($j['properties']['image']));
        $this->assertEquals('http://vn.be.webmapp.it/wp-content/uploads/2017/10/L004-copertina-300x300.jpg',$j['properties']['image']);
        $this->assertTrue(isset($j['properties']['imageGallery']));
	}

	public function testIsPublic() {
		$wp_url = 'http://dev.be.webmapp.it/wp-json/wp/v2/route/686';
        $r = new WebmappRoute($wp_url);
        $j = json_decode($r->getJson(),TRUE);
        $this->assertTrue(isset($j['properties']['isPublic']));		
        $this->assertTrue($j['properties']['isPublic']);		

		$wp_url = 'http://dev.be.webmapp.it/wp-json/wp/v2/route/917';
        $r = new WebmappRoute($wp_url);
        $j = json_decode($r->getJson(),TRUE);
        $this->assertTrue(isset($j['properties']['isPublic']));		
        $this->assertFalse($j['properties']['isPublic']);		
	}

	public function testRoutePassword() {
		$wp_url = 'http://develop.be.webmapp.it/wp-json/wp/v2/route/262';
        $r = new WebmappRoute($wp_url);
        $j = json_decode($r->getJson(),TRUE);
        $this->assertTrue(isset($j['properties']['use_password']));
        $this->assertTrue($j['properties']['use_password']);
        $this->assertTrue(isset($j['properties']['route_password']));
        $pwd = 'pippo234';
        $pk = '2K7TGxm98QBTga7DB6kDh4YSAv39rYN5';
        $md5 = md5($pk.$pwd);
        $this->assertEquals($md5,$j['properties']['route_password']);
	}


}