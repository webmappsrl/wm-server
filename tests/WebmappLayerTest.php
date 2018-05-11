<?php // WebmappLayerTest.php

use PHPUnit\Framework\TestCase;

class WebmappLayerTest extends TestCase {

	public function testOk() {
		$path = __DIR__.'/../data';
		$name = 'layerTestOk';
		$filename = $path.'/'.$name.'.geojson';
		system('rm -f '.$filename);

		$layer = new WebmappLayer($name,$path);
		$poi = new WebmappPoiFeature('http://dev.be.webmapp.it/wp-json/wp/v2/poi/522');
		$layer->addFeature($poi);
		$track = new WebmappTrackFeature('http://dev.be.webmapp.it/wp-json/wp/v2/track/580');
		$layer->addFeature($track);

		// TEST STRING
		$json = $layer->getGeoJson();
		$this->verifyBlock($json);

		// TEST WRITING FILE
		$json='';
		$layer->write();
		$json=file_get_contents($filename);
		$this->verifyBlock($json);

		// TEST ALERT FEATURE
		$this->assertFalse($layer->getAlert());
		$layer->setAlert(true);
		$this->assertTrue($layer->getAlert());

	}

	public function testBB() {
		$path = __DIR__.'/../data';
		$name = 'layerTestOk';
		$filename = $path.'/'.$name.'.geojson';
		system('rm -f '.$filename);

		$layer = new WebmappLayer($name,$path);
		$poi = new WebmappPoiFeature('http://dev.be.webmapp.it/wp-json/wp/v2/poi/522');
		$layer->addFeature($poi);

		$poi = new WebmappPoiFeature('http://dev.be.webmapp.it/wp-json/wp/v2/poi/546');
		$layer->addFeature($poi);

		$track = new WebmappTrackFeature('http://dev.be.webmapp.it/wp-json/wp/v2/track/576');
		$layer->addFeature($track);

		$track = new WebmappTrackFeature('http://dev.be.webmapp.it/wp-json/wp/v2/track/576');
		$layer->addFeature($track);

		$bb = $layer->getBB();
		$this->assertEquals($bb['bounds']['northEast'][0],43.72326);
        $this->assertEquals($bb['bounds']['northEast'][1],10.4029019);
        $this->assertEquals($bb['bounds']['southWest'][0],43.70904);
        $this->assertEquals($bb['bounds']['southWest'][1],10.39471);
        $this->assertEquals($bb['center']['lat'],43.71615);
        $this->assertEquals($bb['center']['lng'],10.39880595);

	}

	


	public function testLanguages() {
		$path = __DIR__.'/../data';
		$name = 'layerTestOk';

		$layer = new WebmappLayer($name,$path);
		$poi = new WebmappPoiFeature('http://dev.be.webmapp.it/wp-json/wp/v2/poi/522');
		$layer->addFeature($poi);

		// TEST en
		$json='';
		$filename = $path.'/languages/en/'.$name.'.geojson';
		system('rm -f '.$filename);
		$layer->write('','en');
		$json=file_get_contents($filename);
        $this->assertRegExp('/EN title/',$json);
        $this->assertRegExp('/"description":"<p>English version for Bar San Domenico.</',$json);

		// TEST fr
		$json='';
		$filename = $path.'/languages/fr/'.$name.'.geojson';
		system('rm -f '.$filename);
		$layer->write('','fr');
		$json=file_get_contents($filename);
        $this->assertRegExp('/french version/',$json);
        $this->assertRegExp('/Descrizione in francese./',$json);

	}

	public function testOverlayLanguages() {
		$path = __DIR__.'/../data';
		$name = 'layerTestOk';

		$l = new WebmappLayer($name,$path); 
		// TODO: Impostare IT, EN, FR, DE
		$url = 'http://dev.be.webmapp.it/wp-json/wp/v2/webmapp_category/35';
		$l->setAvailableLanguages(array('it','en','fr','de'));
		$l->loadMetaFromUrl($url);
		$langs = $l->getLanguages();
		$this->assertEquals('test IT',$langs['it']);
		$this->assertEquals('test EN',$langs['en']);
		$this->assertEquals('test FR',$langs['fr']);
		$this->assertEquals('test DE',$langs['de']);

	}

	private function verifyBlock($json) {
		// GENERIC 
		$this->assertRegExp('/"type":"FeatureCollection"/',$json);		
		$this->assertRegExp('/"features":/',$json);		
		// POI
		$this->assertRegExp('/"type":"Feature"/',$json);
		$this->assertRegExp('/"id":522/',$json);
		$this->assertRegExp('/"name":"Bar San Domenico"/',$json);
		$this->assertRegExp('/"description":"<p>Il bar di moda a porta a Lucca</',$json);
		$this->assertRegExp('/"color":"#dd3333"/',$json);
		$this->assertRegExp('/"icon":"wm-icon-generic"/',$json);
		$this->assertRegExp('/"noDetails":false/',$json);
		$this->assertRegExp('/"image":"http:.*dolomites-550349_960_720/',$json);
		$this->assertRegExp('/"src":"http:.*dolomites-550349_960_720/',$json);
		$this->assertRegExp('/"src":"http:.*mountain-1077939_960_720/',$json);
		$this->assertRegExp('/"addr:street":"Largo Parlascio"/',$json);
		$this->assertRegExp('/"addr:housenumber":"1"/',$json);
		$this->assertRegExp('/"addr:postcode":"56127"/',$json);
		$this->assertRegExp('/"addr:city":"Pisa"/',$json);
		$this->assertRegExp('/"contact:email":"info@barsandomenico\.it"/',$json);
		$this->assertRegExp('/"contact:phone":"\+39 050 7846161"/',$json);
		$this->assertRegExp('/"opening_hours":"Sa-Su 00:00-24:00"/',$json);
		$this->assertRegExp('/"capacity":"50"/',$json);
		$this->assertRegExp('/"type":"Point"/',$json);
		$this->assertRegExp('/43\.7223352/',$json);
		$this->assertRegExp('/10\.4015262/',$json);
		// TRACK
		$this->assertRegExp('/"type":"Feature"/',$json);
		$this->assertRegExp('/"id":580/',$json);
		$this->assertRegExp('/"name":"Pisa Tour by Bike"/',$json);
		$this->assertRegExp('/"description":"<p>Un breve ma intenso giro in bicicletta per scoprire tutti i segreti di Pisa\.</',$json);
		$this->assertRegExp('/"image":"http:.*Bici-Pisa\.jpg"/',$json);
		$this->assertRegExp('/"src":"http:.*pisa_stazione_riparazione_gonfiaggio_bici_corso_italia_2\.jpg/',$json);
		$this->assertRegExp('/"src":"http:.*Screenshot-2017-03-01-15\.06\.47/',$json);
		$this->assertRegExp('/"color":"#81d742"/',$json);
		$this->assertRegExp('/"from":"Stazione di Pisa"/',$json);
		$this->assertRegExp('/"to":"Stazione di Pisa"/',$json);
		$this->assertRegExp('/"ref":"001"/',$json);
		$this->assertRegExp('/"ascent":"100"/',$json);
		$this->assertRegExp('/"descent":"100"/',$json);
		$this->assertRegExp('/"distance":"12345"/',$json);
		$this->assertRegExp('/"duration:forward":"11:22"/',$json);
		$this->assertRegExp('/"duration:backward":"22:11"/',$json);
		$this->assertRegExp('/"cai_scale":"E"/',$json);
		$this->assertRegExp('/"type":"LineString"/',$json);
		$this->assertRegExp('/10\.39874/',$json);
		$this->assertRegExp('/10\.39743/',$json);
		$this->assertRegExp('/10\.38968/',$json);
		$this->assertRegExp('/43\.70904/',$json);
		$this->assertRegExp('/43\.71941/',$json);
		$this->assertRegExp('/43\.70904/',$json);

	}

	public function testLoadMetaFromUrl() {
		$l = new WebmappLayer('test','');
		$url = 'http://dev.be.webmapp.it/wp-json/wp/v2/webmapp_category/30';
		$l->loadMetaFromUrl($url);

		$this->assertEquals('Bar',$l->getLabel());
		$this->assertEquals('wm-icon-siti-interesse',$l->getIcon());
		$this->assertEquals('#00ff00',$l->getColor());
		$this->assertTrue($l->getShowByDefault());

		$l = new WebmappLayer('test','');
		$l->loadMetaFromUrl('http://dev.be.webmapp.it/wp-json/wp/v2/webmapp_category/14');
		$this->assertFalse($l->getShowByDefault());
		$this->assertFalse($l->getAlert());

		$l = new WebmappLayer('test','');
		$l->loadMetaFromUrl('http://dev.be.webmapp.it/wp-json/wp/v2/webmapp_category/11');
		$this->assertTrue($l->getShowByDefault());
		$this->assertFalse($l->getAlert());
		$this->assertFalse($l->getExclude());


		$l = new WebmappLayer('test','');
		$l->loadMetaFromUrl('http://dev.be.webmapp.it/wp-json/wp/v2/webmapp_category/34');
		$this->assertTrue($l->getShowByDefault());
		$this->assertTrue($l->getAlert());
		$this->assertTrue($l->getExclude());


	}

}