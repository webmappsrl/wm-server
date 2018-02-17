<?php 

use PHPUnit\Framework\TestCase;

class WebmappUtilsTests extends TestCase {
	public function test3D() {
		$gpx = __DIR__.'/fixtures/3DandPoints.gpx';
		$info = WebmappUtils::GPXAnalyze($gpx);
		$this->assertEquals(1,$info['tracks']);
		$this->assertEquals(1881,$info['trackpoints']);
		$this->assertEquals(27542,floor($info['distance']));
		$this->assertEquals(1029,floor($info['ele_max']));
		$this->assertEquals(192,floor($info['ele_min']));
		$this->assertEquals(194,floor($info['ele_start']));
		$this->assertEquals(721,floor($info['ele_end']));
		$this->assertEquals(1886,floor($info['ele_gain_positive']));
		$this->assertEquals(1331,floor($info['ele_gain_negative']));

	}
	public function testSimple() {
		$gpx = __DIR__.'/fixtures/simple.gpx';
		$info = WebmappUtils::GPXAnalyze($gpx);
		$this->assertEquals(1,$info['tracks']);
		$this->assertEquals(359,$info['trackpoints']);
		$this->assertEquals(5147,floor($info['distance']));
		$this->assertEquals(0,floor($info['ele_max']));
		$this->assertEquals(0,floor($info['ele_min']));
	}

}