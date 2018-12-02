<?php 
use PHPUnit\Framework\TestCase;
class WebmappPostGisTest extends TestCase {
	public function testSingleton() {
		$pg1 = WebmappPostGis::Instance();
		$res1 = $pg1->getResource();
		$pg2 = WebmappPostGis::Instance();
		$res2 = $pg2->getResource();
		$this->assertEquals(pg_get_pid($res1),pg_get_pid($res2));
	}

	public function testPoi() {
		$lon_pisa = 10.40189;
        $lat_pisa = 43.71586;
		$pg = WebmappPostGis::Instance();
		$pg->clearTables('test');
		$pg->insertPoi('test',1,$lon_pisa,$lat_pisa);
		$res = $pg->select('SELECT * from poi');

		$this->assertEquals('test',$res[0]['instance_id']);
		$this->assertEquals(1,$res[0]['poi_id']);

		// TODO: Test Geometry
	}
}