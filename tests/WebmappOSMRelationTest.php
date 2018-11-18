<?php 

use PHPUnit\Framework\TestCase;

class WebmappOSMRelationTest extends TestCase {

  	// RELATION (R): https://www.openstreetmap.org/api/0.6/relation/7454121 , https://www.openstreetmap.org/api/0.6/relation/7454121/full
	// <relation id="7454121" visible="true" version="5" changeset="61232947" timestamp="2018-07-31T15:10:03Z" user="Gianfranco2014" uid="1928626">
	public function testKML() {
		$r = new WebmappOSMRelation(7454121);
		$kml = simplexml_load_string($r->getKMLFromWMT());

		$this->assertTrue(isset($kml->Document));
		$this->assertTrue(isset($kml->Document->Placemark));
		$this->assertTrue(isset($kml->Document->Placemark->MultiGeometry));
		$this->assertTrue(isset($kml->Document->Placemark->MultiGeometry->LineString));
		$this->assertTrue(isset($kml->Document->Placemark->MultiGeometry->LineString->coordinates));
	}

	public function testTrack() {
		$r = new WebmappOSMRelation(7454121);
		$track = $r->getTrack();
		$j = $track->getJson();
		$this->assertEquals(7454121,$track->getId());
		$properties = array (
			"id"=>"7454121",
			"visible"=>"true",
			"version"=>"5",
			"changeset"=>"61232947",
			"timestamp"=>"2018-07-31T15:10:03Z",
			"user"=>"Gianfranco2014",
			"uid"=>"1928626",
			"ascent"=>"999",
			"descent"=>"909",
			"duration:backward"=>"08:01",
			"duration:forward"=>"08:10",
			"ele:from"=>"949",
			"ele:max"=>"1392",
			"ele:min"=>"836",
			"ele:to"=>"1039",
			"from"=>"Conca di Monte Alago",
			"name"=>"Sentiero Italia - Tappa N07",
			"network"=>"lwn",
			"osmc:symbol"=>"red:red:white_stripe:SI:black",
			"ref"=>"SI",
			"route"=>"hiking",
			"symbol"=>"red:red:white_stripe:SI:black",
			"to"=>"Valsorda - Rifugio Monte Maggio",
			"type"=>"route",
			"start"=>"Conca di Monte Alago",
			"stop"=>"Valsorda - Rifugio Monte Maggio"
			);
		foreach ($properties as $k => $v ) {
			$this->assertEquals($v,$track->getProperty($k));
		}

		// Check geojson
		$ja = json_decode($j,true);
		$this->assertTrue(isset($ja['type']));
		$this->assertEquals('Feature',$ja['type']);
		$this->assertTrue(isset($ja['properties']));
		foreach($properties as $k => $v ) {
			$this->assertEquals($v,$ja['properties'][$k]);
		}
		$this->assertTrue(isset($ja['geometry']));
		$this->assertRegExp('/LineString/',$ja['geometry']['type']);
		$this->assertTrue(isset($ja['geometry']['coordinates']));
	}

	public function testMembers() {
		$r = new WebmappOSMRelation(7454121);
		$members = array(
			167059866,167059851,331862052,135319690,138545603
			);
		$r_members = $r->getMembers();
		foreach($members as $id) {
			$this->assertTrue(array_key_exists($id,$r_members));
		}
		foreach($r_members as $ref => $info) {
			$this->assertEquals('way',$info['type']);
		}
	}


}