<?php 

use PHPUnit\Framework\TestCase;

class WebmappUtilsTest extends TestCase {
	private $delta_ele = 20;

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
		$this->assertFalse($info['has_multi_segments']);

	}
	public function testSimple() {
		$gpx = __DIR__.'/fixtures/simple.gpx';
		$info = WebmappUtils::GPXAnalyze($gpx);
		$this->assertEquals(1,$info['tracks']);
		$this->assertEquals(359,$info['trackpoints']);
		$this->assertEquals(5.1,$info['distance']);
		$this->assertFalse($info['has_ele']);
		$this->assertFalse($info['has_multi_segments']);
	}
	public function testMultiSegments() {
		$gpx = __DIR__.'/fixtures/singletrack_multiseg.gpx';
		$info = WebmappUtils::GPXAnalyze($gpx);
		$this->assertTrue($info['has_multi_segments']);
	}
	public function testMultiTracks() {
		$gpx = __DIR__.'/fixtures/multitrack.gpx';
		$info = WebmappUtils::GPXAnalyze($gpx);
		$this->assertTrue($info['tracks']>1);
	}

	public function testFormatDuration() {
		$this->assertEquals('0:00',WebmappUtils::formatDuration(0));
		$this->assertEquals('0:05',WebmappUtils::formatDuration(0.01));
		$this->assertEquals('0:15',WebmappUtils::formatDuration(0.25));
		$this->assertEquals('0:30',WebmappUtils::formatDuration(0.5));
		$this->assertEquals('0:40',WebmappUtils::formatDuration(0.75));
		$this->assertEquals('1:00',WebmappUtils::formatDuration(1));
		$this->assertEquals('1:10',WebmappUtils::formatDuration(1.25));
		$this->assertEquals('1:30',WebmappUtils::formatDuration(1.5));
	}

	public function testBingEle() {
		// Monte Bianco ele 4808
		$monte_bianco = array(45.832905,6.864688);
		// Monte Rosa ele 4634
		$monte_rosa = array(45.93689,7.86676);
		$points = array(
			$monte_bianco,
			$monte_rosa
			);
		$ele = WebmappUtils::getBingElevations($points);
		$this->assertEquals(4821,$ele[0]);
		$this->assertEquals(4465,$ele[1]);
	}

	public function testEle() {
		$serra = array(43.7510,10.5536);
		$pania_della_croce = array(44.0344,10.3241);
		$points = array(
			$serra,
			$pania_della_croce
			);
		$ele = WebmappUtils::getElevations($points);

		$this->assertGreaterThan($ele[0]-$this->delta_ele,902);
		$this->assertLessThan($ele[0]+$this->delta_ele,902);
		$this->assertGreaterThan($ele[1]-$this->delta_ele,1770);
		$this->assertLessThan($ele[1]+$this->delta_ele,1770);
	}

	public function testEleLong() {
		$max = 10;
		$serra = array(43.7510,10.5536);
		$points=array();
		for ($i=0; $i < $max; $i++) { 
			$points[]=$serra;
		}
		$ele = WebmappUtils::getElevations($points);
		$this->assertGreaterThan($ele[0]-$this->delta_ele,902);
		$this->assertLessThan($ele[0]+$this->delta_ele,902);
	}
	public function testBingEleExceptionArray() {
		$points_error = 'error';
		$this->expectException(Exception::class);
		WebmappUtils::getBingElevations($points_error);
	}
	public function testBingEleExceptionEmptyArray() {
		$points_error = array();
		$this->expectException(Exception::class);
		WebmappUtils::getBingElevations($points_error);
	}
	public function testBinSingleEle(){
        // Monte Bianco
		$this->assertEquals(4824,WebmappUtils::getBingElevation(45.832905,6.864688));
		// Monte Rosa
		$this->assertEquals(4465,WebmappUtils::getBingElevation(45.93689,7.86676));
	}

	/**
	* @group WebmappUtils_GPXAddEle
	**/
	public function testGPXAddEle() {
		$in_ele = __DIR__.'/fixtures/3DandPoints.gpx';
		$in_no_ele = __DIR__.'/fixtures/simple_5.gpx';
		$out = '/tmp/out.gpx';

		// FILE con ele: esegue la copia
		$cmd = "rm -f $out";
		system($cmd);
		$this->assertFalse(file_exists($out));
		$this->assertTrue(WebmappUtils::GPXAddEle($in_ele,$out));
		$this->assertEquals(file_get_contents($in_ele),file_get_contents($out));
		$this->assertTrue(WebmappUtils::GPXAddEle($in_no_ele,$out));

		// Controlli sul risultato ottenuto
		$info = WebmappUtils::GPXAnalyze($out);
		$this->assertEquals(1,$info['tracks']);
		$this->assertEquals(5,$info['trackpoints']);
		$this->assertEquals(0.1,$info['distance']);
		//print_r($info);
		$this->assertTrue($info['has_ele']);
		$this->delta(1478,$info['ele_max']);
		$this->delta(1475,$info['ele_min']);
		$this->delta(1477,$info['ele_start']);
		$this->delta(1478,$info['ele_end']);

		// TODO: rivedere questi valori
		// $this->assertEquals(0,$info['ele_gain_positive']);
		// $this->assertEquals(0,$info['ele_gain_negative']);
		// $this->assertEquals("0:05",$info['duration_forward']);
		// $this->assertEquals("0:05",$info['duration_backward']);
		// $this->assertFalse($info['has_multi_segments']);

	}

	private function delta($exp,$actual,$d=100) {
		$this->assertTrue($exp-$d<=$actual);
		$this->assertTrue($exp+$d>=$actual);
	}

	/**
	* @group WebmappUtils_GPXAddEle
	**/
	public function testGPXAddEleExceptionNoFile() {
		$in ='/nowhere/track.pgx';
		$out = '/tmp/out.gpx';
		$this->expectException(Exception::class);
		WebmappUtils::GPXAddEle($in,$out);		
	}
	/**
	* @group WebmappUtils_GPXAddEle
	**/
	public function testGPXAddEleExceptionMultipleSegments() {
		$in =__DIR__.'/fixtures/singletrack_multiseg.gpx';
		$out = '/tmp/out.gpx';
		$this->expectException(ExceptionWebmappUtilsGPXAddEleMultipleSegments::class);
		WebmappUtils::GPXAddEle($in,$out);		
	}
	/**
	* @group WebmappUtils_GPXAddEle
	**/
	public function testGPXAddEleExceptionMultipleTracks() {
		$in =__DIR__.'/fixtures/multitrack.gpx';
		$out = '/tmp/out.gpx';
		$this->expectException(ExceptionWebmappUtilsGPXAddEleMultipleTracks::class);
		WebmappUtils::GPXAddEle($in,$out);		
	}

	/**
	* @group WebmappUtils_GPXGenerateProfile
	**/
	public function testGPXGenerateProfileNoFile() {
		$in ='/nowhere/multitrack.gpx';
		$out = '/tmp/out.gpx';
		$this->expectException(WebmappExceptionNofile::class);
		WebmappUtils::GPXGenerateProfile($in,$out);		
	}

	/**
	* @group WebmappUtils_GPXGenerateProfile
	**/
	public function testGPXGenerateProfileNoDirectory() {
		$in ='/nowhere/multitrack.gpx';
		$out = '/nowhere/out.gpx';
		$this->expectException(WebmappExceptionNofile::class);
		WebmappUtils::GPXGenerateProfile($in,$out);		
	}

	/**
	* @group WebmappUtils_GPXGenerateProfile
	**/
	public function testGPXGenerateProfileNoEle() {
		$in =__DIR__.'/fixtures/simple_5.gpx';
		$out = '/tmp/out.gpx';
		$this->expectException(WebmappUtilsExceptionsGPXNoEle::class);
		WebmappUtils::GPXGenerateProfile($in,$out);		
	}


	public function testgetMultipleJsonFromApi() {
		$url="http://dev.be.webmapp.it/wp-json/wp/v2/media";
		$r=WebmappUtils::getMultipleJsonFromApi($url);
		$this->assertTrue(is_array($r));
		$this->assertTrue(count($r)>0);
	}


	public function testRouteIndex() {
		$api_url = "http://dev.be.webmapp.it/wp-json/wp/v2/route";
		$l = WebmappUtils::createRouteIndexLayer($api_url);
		$this->assertTrue($l->count()>0);
		$j=json_decode($l->getGeoJson(),TRUE);
		$features=$j['features'];
		$values=array(
			'772'=>array(10.396649837494,43.716170598881),
			'686'=>array(10.40169,43.71554),
			'346'=>array(10.396649837494,43.716170598881),
            '992'=>array(10.3323411,44.0394793)
			);

		foreach($features as $f) {
			$id = $f['properties']['id'];
			$lon = $f['geometry']['coordinates'][0];
			$lat = $f['geometry']['coordinates'][1];
			$this->assertEquals($lon,$values[$id][0]);
			$this->assertEquals($lat,$values[$id][1]);
		}

	}

	public function testGetRandomPoint() {
		$data = array (
			array(0,0,1),
			array(0,0,0.1),
			array(0,0,0.01),
			array(0,0,0.001),
			array(40,10,1),
			array(40,10,0.1),
			array(40,10,0.01),
			array(40,10,0.001),
			array(-40,10,1),
			array(-40,10,0.1),
			array(-40,10,0.01),
			array(-40,10,0.001),
			array(40,-10,1),
			array(40,-10,0.1),
			array(40,-10,0.01),
			array(40,-10,0.001),
			array(-40,-10,1),
			array(-40,-10,0.1),
			array(-40,-10,0.01),
			array(-40,-10,0.001)
			); 
		foreach ($data as $item) {
			$lon0=$item[0];
			$lat0=$item[1];
			$rho=$item[2];
			$res = WebmappUtils::getRandomPoint($lon0,$lat0,$rho);
			$lon = $res[0];
			$lat = $res[1];
			$this->assertTrue(sqrt(($lon0-$lon)*($lon0-$lon)+($lat0-$lat)*($lat0-$lat))<=$rho);
		}
	}

	public function testGetOptimalBBox() {
        // INPUT W=491 H=624 BB=1157909,5421149,1158672,5421639 PERC=0.05
        // OUTPUT BB=1157870.85,5420860.6769857,1158710.15,5421927.3230143
        $bbin = '1157909,5421149,1158672,5421639';
        $bbout = explode(',', WebmappUtils::getOptimalBBox($bbin,491,624,0.05));
        $this->assertEquals(1157870.85,$bbout[0]);
        $this->assertEquals(5420860.6769857,$bbout[1]);
        $this->assertEquals(1158710.15,$bbout[2]);
        $this->assertEquals(5421927.3230143,$bbout[3]);

	}

	public function testUrlExists() {
		$url = 'http://google.com';
		$this->assertTrue(WebmappUtils::urlExists($url));

		$url = 'https://google.com';
		$this->assertTrue(WebmappUtils::urlExists($url));

		$url = 'https://www.terredipisa.it/wp-json/wp/v2/percorso/?page=1';
		$this->assertTrue(WebmappUtils::urlExists($url));

		$url = 'https://www.terredipisa.it/xxx';
		$this->assertFalse(WebmappUtils::urlExists($url));

		$url = 'http://aaa.aaa.aaa/';
		$this->assertFalse(WebmappUtils::urlExists($url));
	}


	public function testGetJsonFromApiCrypt() {
	    // Build test
        $a = array("test"=>"test");
        global $wm_config;
        $method = $wm_config['crypt']['method'];
        $key = $wm_config['crypt']['key'];
        $tmp = tempnam('/tmp','testGetJsonFromApiCrypt_');
        file_put_contents($tmp,openssl_encrypt (json_encode($a), $method, $key));

        $a = WebmappUtils::getJsonFromApi($tmp,true);

        $this->assertTrue(isset($a['test']));
        $this->assertEquals('test',$a['test']);
    }

}