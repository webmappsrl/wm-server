<?php 

use PHPUnit\Framework\TestCase;

class WebmappOSMRelationTest extends TestCase {

	public function testSimple() {
		$r = new WebmappOSMRelation("4200445");
		$this->assertTrue($r->load());
	}

	public function test401() {
		$r = new WebmappOSMRelation("1");
		$this->expectException(WebmappExceptionNoOSMRelation::class);
		$r->load();

	}

	public function test404() {
		$r = new WebmappOSMRelation("xxx");
		$this->expectException(WebmappExceptionNoOSMRelation::class);
		$r->load();
	}

}