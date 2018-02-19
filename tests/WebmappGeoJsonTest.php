<?php 

use PHPUnit\Framework\TestCase;

class WebmappGeoJsonTest extends TestCase {

	public function testaddLineString() {
		$monte_bianco = array(6.864688,45.832905);
		$monte_rosa = array(7.86676,45.93689);
		$points = array($monte_bianco,$monte_rosa);
		$gj = new WebmappGeoJson();
		$this->assertTrue($gj->addLineString($points));
		$ja = json_decode($gj->__toString(),TRUE);
		$this->assertEquals('FeatureCollection',$ja['type']);
		$this->assertEquals(6.864688,$ja['features'][0]['geometry']['coordinates'][0][0]);
		$this->assertEquals(45.832905,$ja['features'][0]['geometry']['coordinates'][0][1]);
	}

	public function testaddLineStringByListExceptionOdd() {
		$list = '45.832905,6.864688,45.93689';
		$gj = new WebmappGeoJson();
		$this->expectException(WebmappExceptionGeoJsonOddList::class);
		$gj->addLineStringByList($list);

	}

	public function testaddLineStringByList() {
		$list = '45.832905,6.864688,45.93689,7.86676';
		$gj = new WebmappGeoJson();
		$this->assertTrue($gj->addLineStringByList($list));
		$ja = json_decode($gj->__toString(),TRUE);
		$this->assertEquals('FeatureCollection',$ja['type']);
		$this->assertEquals(6.864688,$ja['features'][0]['geometry']['coordinates'][0][0]);
		$this->assertEquals(45.832905,$ja['features'][0]['geometry']['coordinates'][0][1]);
	}
}