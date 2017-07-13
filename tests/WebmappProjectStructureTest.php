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
        
    }




}