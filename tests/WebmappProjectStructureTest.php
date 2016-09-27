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

}
