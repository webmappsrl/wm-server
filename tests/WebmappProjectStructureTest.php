<?php
use PHPUnit\Framework\TestCase;

class WebmappProjectStructureTest extends TestCase
{

    private $root = __DIR__.'/../data/api.webmapp.it/example.webmapp.it';

    public function testCheck() {
        $p = new WebmappProjectStructure($this->root);
        $this->assertTrue($p->check());
        $this->assertEquals($this->root,$p->getRoot());
        $this->assertEquals($this->root.'/geojson',$p->getPathGeojson());
        $this->assertEquals($this->root.'/client',$p->getPathClient());
        $this->assertEquals($this->root.'/client/index.html',$p->getPathClientIndex());
        $this->assertEquals($this->root.'/client/config.js',$p->getPathClientConf());
        $this->assertEquals('http://example.webmapp.it',$p->getURLBase());
        $this->assertEquals('http://example.webmapp.it/geojson',$p->getURLGeojson());
        $this->assertEquals('http://example.webmapp.it',$p->getURLClient());
        $this->assertEquals('http://example.webmapp.it/config.js',$p->getURLClientConf());
        $this->assertEquals('http://example.webmapp.it/index.html',$p->getURLClientIndex());


    }

    public function testKoNoRoot() {
        // root Inesistente
        $root = __DIR__.'/../data/api.webmapp.it/NOROOT-XXX/';
        $p = new WebmappProjectStructure($root);
        $this->expectException(Exception::class);
        $p->check();        
    }

    public function testCreate() {

        $root = __DIR__.'/../data/api.webmapp.it/tmp.webmapp.it';
        $cmd = "rm -Rf $root";
        system($cmd);
        $ps = new WebmappProjectStructure($root);
        $ps -> create();

        $this->assertTrue(file_exists($root.'/client'));
        $this->assertTrue(file_exists($root.'/server'));
        $this->assertTrue(file_exists($root.'/geojson'));
        $this->assertTrue(file_exists($root.'/media'));
        $this->assertTrue(file_exists($root.'/media/images'));
        $this->assertTrue(file_exists($root.'/resources'));
        $this->assertTrue(file_exists($root.'/pages'));
        $this->assertTrue(file_exists($root.'/tiles'));
    }
    public function testClean() {

        $root = __DIR__.'/../data/api.webmapp.it/tmp.webmapp.it';
        $cmd = "rm -Rf $root";
        system($cmd);
        $ps = new WebmappProjectStructure($root);
        $ps -> create();

        system("touch $root/client/XXX");
        system("touch $root/geojson/XXX");
        system("touch $root/media/images/XXX");
        system("touch $root/media/XXX");

        $ps->clean();

        $this->assertEquals(2,count(scandir($root.'/client')));
        $this->assertEquals(2,count(scandir($root.'/geojson')));
        $this->assertEquals(2,count(scandir($root.'/media/images')));
        $this->assertEquals(3,count(scandir($root.'/media')));

    }


}