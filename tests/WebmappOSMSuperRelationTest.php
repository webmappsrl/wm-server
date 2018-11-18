<?php 

use PHPUnit\Framework\TestCase;

class WebmappOSMSuperRelationTest extends TestCase {
 	// SUPERRELATION (SR): https://www.openstreetmap.org/api/0.6/relation/1021025
	// <relation id="1021025" visible="true" version="57" changeset="61232947" timestamp="2018-07-31T14:58:22Z" user="Gianfranco2014" uid="1928626">
	public function testMembers() {
		$sr = new WebmappOSMSuperRelation(1021025);
		$members = array(
			7011030,7011950,7125614,7164643,7186477,7220974,
			7401588,7246181,7448629,7458976,7468319,7561168,
			7029511,7029512,7029513,7029514,7332771);
		$sr_members = $sr->getMembers();
		foreach($members as $id) {
			$this->assertTrue(array_key_exists($id,$sr_members));
		}
		foreach($sr_members as $ref => $info) {
			$this->assertEquals('relation',$info['type']);
		}
	}

}