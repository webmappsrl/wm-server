<?php 

use PHPUnit\Framework\TestCase;

class WebmappUtilsTests extends TestCase {
	public function test3D() {
		$gpx = __DIR__.'/fixtures/3DandPoints.gpx';
		$info = WebmappUtils::GPXAnalyze($gpx);
		$this->assertEquals(1,$info['tracks']);
		$this->assertEquals(1881,$info['trackpoints']);
		$this->assertEquals(27.5,$info['distance']);
		$this->assertTrue($info['has_ele']);
		$this->assertEquals(1029,$info['ele_max']);
		$this->assertEquals(192,$info['ele_min']);
		$this->assertEquals(194,$info['ele_start']);
		$this->assertEquals(721,$info['ele_end']);
		$this->assertEquals(1886,$info['ele_gain_positive']);
		$this->assertEquals(1331,$info['ele_gain_negative']);
		$this->assertEquals("13:10",$info['duration_forward']);
		$this->assertEquals("11:30",$info['duration_backward']);
	}
	public function testSimple() {
		$gpx = __DIR__.'/fixtures/simple.gpx';
		$info = WebmappUtils::GPXAnalyze($gpx);
		$this->assertEquals(1,$info['tracks']);
		$this->assertEquals(359,$info['trackpoints']);
		$this->assertEquals(5.1,$info['distance']);
		$this->assertFalse($info['has_ele']);
	}

	public function testFormatDuraion() {
		$this->assertEquals('0:00',WebmappUtils::formatDuration(0));
		$this->assertEquals('0:05',WebmappUtils::formatDuration(0.01));
		$this->assertEquals('0:15',WebmappUtils::formatDuration(0.25));
		$this->assertEquals('0:30',WebmappUtils::formatDuration(0.5));
		$this->assertEquals('0:40',WebmappUtils::formatDuration(0.75));
		$this->assertEquals('1:00',WebmappUtils::formatDuration(1));
		$this->assertEquals('1:10',WebmappUtils::formatDuration(1.25));
		$this->assertEquals('1:30',WebmappUtils::formatDuration(1.5));
	}

}