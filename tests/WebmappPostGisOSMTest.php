<?php 
use PHPUnit\Framework\TestCase;
class WebmappPostGisOSMTest extends TestCase {

	public function testSingleton() {
		$pg1 = WebmappPostGisOSM::Instance();
		$res1 = $pg1->getResource();
		$pg2 = WebmappPostGisOSM::Instance();
		$res2 = $pg2->getResource();
		$this->assertEquals(pg_get_pid($res1),pg_get_pid($res2));
	}

	public function testRelationGeoJson() {
		$osm_id = 7006731;
		$pg = WebmappPostGisOSM::Instance();
		$geo = $pg->getRelationJsonGeometry($osm_id);
		// TEST ON
		// {"type":"LineString",
		// "coordinates":[
		//         [1046628.24,4810823.25],
		//         [1046630.34,4810826.51],
		//         ...
		$j=json_decode($geo,TRUE);
		$this->assertTrue(isset($j['type']));
		$this->assertEquals($j['type'],'LineString');
		$this->assertTrue(isset($j['coordinates']));
		$coord = $j['coordinates'];
		$this->assertTrue(is_array($coord));
		$this->assertTrue(count($coord)>0);
		$this->assertTrue(is_array($coord[0]));
		$pair=$coord[0];
		$this->assertTrue(count($pair)==2);
	}

	public function testRelationInverted() {
		// Da invertire
		$osm_id = 7468547;

		// Da non invertire
		// $osm_id = 7635772;
		// 

		$pg = WebmappPostGisOSM::Instance();
		$geo = $pg->getRelationJsonGeometry($osm_id);
		$j=json_decode($geo,TRUE);
		$this->assertTrue(isset($j['type']));
		$this->assertEquals($j['type'],'LineString');
		$this->assertTrue(isset($j['coordinates']));
		$coord = $j['coordinates'];
		$p0 = $coord[0];
		$this->assertEquals(11.876578850466,$p0[0]);
		$this->assertEquals(43.794309788881,$p0[1]);
	}
	public function testRelationNotInverted() {
		// Da non invertire
		$osm_id = 7635772;

		$pg = WebmappPostGisOSM::Instance();
		$geo = $pg->getRelationJsonGeometry($osm_id);
		$j=json_decode($geo,TRUE);
		$this->assertTrue(isset($j['type']));
		$this->assertEquals($j['type'],'LineString');
		$this->assertTrue(isset($j['coordinates']));
		$coord = $j['coordinates'];
		$p0 = $coord[0];
		$this->assertEquals(7.0549472011010499,$p0[0]);
		$this->assertEquals(44.798997003949701,$p0[1]);
	}

	public function testGetWayMeta() {
		$osm_id = 43320999;
		$pg = WebmappPostGisOSM::Instance();
		$meta = $pg->getWayMeta($osm_id);

		$this->assertEquals('tertiary',$meta['highway']);
	}

}