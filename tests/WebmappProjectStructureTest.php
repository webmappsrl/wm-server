<?php
use PHPUnit\Framework\TestCase;

class WebmappProjectStructureTest extends TestCase { 

    private $root = '/tmp/testWebmapp/';
    private $server = '/tmp/testWebmapp/server/';
    private $conf = '/tmp/testWebmapp/server/project.conf';
    private $geojson = '/tmp/testWebmapp/geojson/';
    private $poi = '/tmp/testWebmapp/geojson/poi/';	
	private $poi_single = '/tmp/testWebmapp/geojson/poi/id/';

	public function testOk() {


        $s = new WebmappProjectStructure($this->root);

        $s->create();

        $this->assertEquals($this->root,$s->getRoot());
        $this->assertEquals($this->server,$s->getServer());
        $this->assertEquals($this->conf,$s->getConf());
        $this->assertEquals($this->geojson,$s->getGeojson());
        $this->assertEquals($this->poi,$s->getPoi());
        $this->assertEquals($this->poi_single,$s->getPoiSingle());
        $this->assertTrue(file_exists($this->root));
        $this->assertTrue(file_exists($this->geojson));
        $this->assertTrue(file_exists($this->poi));
        $this->assertTrue(file_exists($this->poi_single));

        $this->assertTrue($s->checkPoi());

	}

    public function testTaskFiles() {
        $root = __DIR__.'/../data/api.webmapp.it/example.webmapp.it/';
        $task = $root . 'server/overpassNode.conf'; 
        $task1 = $root . 'server/overpassNode1.conf';
        $project = $root . 'server/project.conf';
  
        $s = new WebmappProjectStructure($root);
        $files = $s -> getTaskConfFiles();
        $this->assertContains($task,$files);
        $this->assertContains($task1,$files);
        $this->assertNotContains($project,$files);
    }

}
