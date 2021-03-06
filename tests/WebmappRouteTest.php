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

	public function testBBoxAmalfi() {
		$pg = WebmappPostGis::Instance();
		$pg->clearTables('test');

		$tracks = array(1864,1868,1875,1878,1881,1872,1884,1887,1899,1890,1904);
		foreach($tracks as $tid) {
			$turl = "http://merlot.be.webmapp.it/wp-json/wp/v2/track/$tid";
			$t1 = new WebmappTrackFeature($turl);
			$t1->writeToPostGis('test');			
		} 

		$rurl = "http://merlot.be.webmapp.it/wp-json/wp/v2/route/1907";
		$r = new WebmappRoute($rurl);
		$r->writeToPostGis('test');

		$bb = $r->addBBox('test');

		$ja=json_decode($r->getJson(),TRUE);

		$this->assertTrue(isset($ja['properties']['bbox']));
		$this->assertTrue(isset($ja['properties']['bbox_metric']));

		$this->assertEquals('13.964479992911,40.494779999182,14.684010009468,40.904405239224',$ja['properties']['bbox']);
		$this->assertEquals('1555085,4940427,1634051,4995890',$ja['properties']['bbox_metric']);

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

	public function testRouteWeb() {
		$wp_url = 'http://selfguided-toscana.it/wp-json/wp/v2/route/4586';
        $r = new WebmappRoute($wp_url);
        $j = json_decode($r->getJson(),TRUE);
        $this->assertTrue(isset($j['properties']['web']));
        $this->assertEquals('https://selfguided-toscana.it/route/mtb-in-alta-versilia/',$j['properties']['web']);
	}

	public function testEle() {
        $r = new WebmappRoute('http://selfguided-toscana.it/wp-json/wp/v2/route/4586');
        $ja=json_decode($r->getJson(),TRUE);
        $this->assertTrue(isset($ja['features']));
        $this->assertTrue(count($ja['features'])>0);
        $this->assertTrue(isset($ja['features'][0]['geometry']['coordinates']));
        $this->assertTrue(count($ja['features'][0]['geometry']['coordinates'])>0);
    }

    public function testTaxonomyRoute() {
        $r = new WebmappRoute('https://cyclando.com/wp-json/wp/v2/route/80296');
        $ja = json_decode($r->getJson(),true);
        $this->assertTrue(isset($ja['features'][0]['properties']['taxonomy']));
        $this->assertTrue(isset($ja['features'][0]['properties']['taxonomy']['activity']));
        $this->assertEquals(84,isset($ja['features'][0]['properties']['taxonomy']['activity'][0]));
    }

    public function testAscent() {
        $r = new WebmappRoute('https://cyclando.com/wp-json/wp/v2/route/80394');
        $ja = json_decode($r->getJson(),true);
        $this->assertTrue(isset($ja['features'][0]['properties']['ascent']));
        $this->assertEquals(451,$ja['features'][0]['properties']['ascent']);
    }


    public function testDistAscentDescent()
    {

        // Route composta dalle seguenti TRACK:

        // 835: https://a.webmapp.it/dev.be.webmapp.it/geojson/835.geojson
        // Distance: 6.4
        // D+: 699
        // D-: 728

        // https://a.webmapp.it/dev.be.webmapp.it/geojson/927.geojson
        // Distance: 0.8
        // D+: 0
        // D-: 0

        // DI conseguenza i valori attesi della route sono:
        // Distance: 7.2
        // D+: 699
        // D-: 728

        $r = new WebmappRoute("http://dev.be.webmapp.it/wp-json/wp/v2/route/992");
        $ja = json_decode($r->getJson(),true);
        $this->assertEquals(7.2,$ja['properties']['distance']);
        $this->assertEquals(699,$ja['properties']['ascent']);
        $this->assertEquals(728,$ja['properties']['descent']);

    }
    public function testEncrypt()
    {

        $r = new WebmappRoute("http://dev.be.webmapp.it/wp-json/wp/v2/route/992");
        $r->write('/tmp',true);

        global $wm_config;
        $method = $wm_config['crypt']['method'];
        $key = $wm_config['crypt']['key'];

        $in = file_get_contents('/tmp/992.geojson');
        $in = openssl_decrypt($in,$method,$key);
        $ja = json_decode($in,true);
        $this->assertTrue(isset($ja['features'][0]['properties']['ascent']));
        $this->assertEquals(699,$ja['features'][0]['properties']['ascent']);
    }

}