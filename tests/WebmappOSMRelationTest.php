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

}