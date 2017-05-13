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
		$this->assertRegExp('/"image":"http:.*dolomites-550349_960_720\.jpg"/',$json);
		$this->assertRegExp('/"src":"http:.*dolomites-550349_960_720\.jpg/',$json);
		$this->assertRegExp('/"src":"http:.*mountain-1077939_960_720\.jpg"/',$json);
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
		$this->assertRegExp('/"src":"http:.*Screenshot-2017-03-01-15\.06\.47\.png/',$json);
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
}