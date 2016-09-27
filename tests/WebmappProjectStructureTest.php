<?php
use PHPUnit\Framework\TestCase;

class WebmappProjectStructureTest extends TestCase { 

    private $root = '/tmp/testWebmapp/';
    private $geojson = '/tmp/testWebmapp/geojson/';
    private $poi = '/tmp/testWebmapp/geojson/poi/';	
	private $poi_single = '/tmp/testWebmapp/geojson/poi/id/';

	public function testOk() {


        $s = new WebmappProjectStructure($this->root);

        $s->create();

        $this->assertEquals($this->root,$s->getRoot());
        $this->assertEquals($this->geojson,$s->getGeojson());
        $this->assertEquals($this->poi,$s->getPoi());
        $this->assertEquals($this->poi_single,$s->getPoiSingle());
        $this->assertTrue(file_exists($this->root));
        $this->assertTrue(file_exists($this->geojson));
        $this->assertTrue(file_exists($this->poi));
        $this->assertTrue(file_exists($this->poi_single));

        $this->assertTrue($s->checkPoi());

	}

	public static function delTree($dir) { 
		$files = array_diff(scandir($dir), array('.','..'));
		foreach ($files as $file) { 
			(is_dir("$dir/$file")) ? self::delTree("$dir/$file") : unlink("$dir/$file");
		}
		return rmdir($dir);
	}

	public function testCheckPoiNoPoiSingle() {
		$s = new WebmappProjectStructure($this->root);
		$s->create();
		self::delTree($this->poi_single);
		// TODO: controllare lo specifico messaggio di errore con il metodo expectExceptionMessage ( che non funziona )
		// $this->expectExceptionMessage('Error: directory poi_single does not exist');
		$this->expectException(Exception::class);
		$s->checkPoi();
	}

	public function testCheckPoiNoPoi() {
		$s = new WebmappProjectStructure($this->root);
		$s->create();
		self::delTree($this->poi);
		// TODO: controllare lo specifico messaggio di errore con il metodo expectExceptionMessage ( che non funziona )
		$this->expectException(Exception::class);
		$s->checkPoi();
	}

	public function testCheckPoiNoGeojson() {
		$s = new WebmappProjectStructure($this->root);
		$s->create();
		self::delTree($this->geojson);
		// TODO: controllare lo specifico messaggio di errore con il metodo expectExceptionMessage ( che non funziona )
		$this->expectException(Exception::class);
		$s->checkPoi();
	}
}
