<?php 
use PHPUnit\Framework\TestCase;
class WebmappPostGisTest extends TestCase {

	// Elevation tolerance
	private $delta_ele = 20;

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
		$q="SELECT * from poi where instance_id='test';";
		$res = $pg->select($q);

		$this->assertEquals('test',$res[0]['instance_id']);
		$this->assertEquals(1,$res[0]['poi_id']);

		$ja = json_decode($pg->getPoiGeoJsonGeometry('test',1),TRUE);
		$this->assertEquals('Point',$ja['type']);
		$this->assertEquals($lon_pisa,$ja['coordinates'][0]);
		$this->assertEquals($lat_pisa,$ja['coordinates'][1]);
	}

	public function testSamePoi() {
		$lon_pisa = 10.40189;
        $lat_pisa = 43.71586;
		$pg = WebmappPostGis::Instance();
		$pg->clearTables('test');
		$pg->insertPoi('test',1,$lon_pisa,$lat_pisa);
		$pg->insertPoi('test',1,$lon_pisa,$lat_pisa);
		$q="SELECT * from poi where instance_id='test';";
		$a = $pg->select($q);
		$this->assertTrue(count($a)>0);
	}

	public function testGetEle() {
		$pg = WebmappPostGis::Instance();
		$data = array (
			array(10.40189,43.71586,6), // Pisa
		    array(10.5536,43.7510,902), // Monte Serra
		    array(10.3241,44.0344,1770) // Pania della Croce
			);
		foreach ($data as $p) {
			$a = $pg->getEle($p[0],$p[1]);
			$e = $p[2];
			$d = $this->delta_ele;
			$this->assertTrue($e-$d<=$a); 
			$this->assertTrue($e+$d>=$a); 
		}
	}
	public function testAddEleTrack() {
		$geom = '{
        "type": "LineString",
        "coordinates": [
          [
            10.46499252319336,
            43.74790912709143
          ],
          [
            10.458126068115234,
            43.766135280960974
          ],
          [
            10.46773910522461,
            43.76725098757973
          ],
          [
            10.480613708496094,
            43.75944060423704
          ],
          [
            10.495719909667969,
            43.7580767819468
          ],
          [
            10.501041412353516,
            43.753737140573186
          ],
          [
            10.52541732788086,
            43.76142429025697
          ]
        ]
      }';
	$pg = WebmappPostGis::Instance();
	$geom_ele = $pg->addEle($geom);
	$j=json_decode($geom_ele,TRUE);
	$this->assertTrue($j['type']=='LineString');
	$this->assertTrue(count($j['coordinates'])==7);
	$this->assertTrue(count($j['coordinates'][0])==3);
	$this->assertTrue(count($j['coordinates'][1])==3);
	$this->assertTrue(count($j['coordinates'][2])==3);
	$this->assertTrue(count($j['coordinates'][3])==3);
	$this->assertTrue(count($j['coordinates'][4])==3);
	$this->assertTrue(count($j['coordinates'][5])==3);
	$this->assertTrue(count($j['coordinates'][6])==3);
    }

	public function testAddElePoi() {
		$geom = '{
        "type": "Point",
        "coordinates": [
          10.520095825195312,
          43.77667168029756
        ]
      }';
	$pg = WebmappPostGis::Instance();
	$geom_ele = $pg->addEle($geom);
	$j=json_decode($geom_ele,TRUE);
	$this->assertTrue($j['type']=='Point');
	$this->assertTrue(count($j['coordinates'])==1);
	$this->assertTrue(count($j['coordinates'][0])==3);
    }
}
