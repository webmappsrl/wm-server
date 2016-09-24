<?php
use PHPUnit\Framework\TestCase;

class WebmappBoundsTest extends TestCase {

	public function testOk() {
		$json = array("southWest"=>array(43.704367081989,10.338478088378),
	      			  "northEast"=>array(43.84839376489,10.637855529785));
		$overpass = "43.704367081989%2C10.338478088378%2C43.84839376489%2C10.637855529785";


		$b = new WebmappBounds($json);

		$this->assertEquals($json['southWest'],$b->getSouthWest());
		$this->assertEquals($json['southWest'][1],$b->getSouthWestLon());
		$this->assertEquals($json['southWest'][0],$b->getSouthWestLat());
		$this->assertEquals($json['northEast'],$b->getNorthEast());
		$this->assertEquals($json['northEast'][1],$b->getNorthEastLon());
		$this->assertEquals($json['northEast'][0],$b->getNorthEastLat());
		$this->assertEquals($overpass,$b->getForOverpass());
	}

	// TODO: test sui casi di errore

}