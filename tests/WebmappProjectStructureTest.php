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
        $this->assertEquals($this->root.'/client/conf.js',$p->getPathClientConf());

    }

    public function testKoNoRoot() {
        // root Inesistente
        $root = __DIR__.'/../data/api.webmapp.it/NOROOT-XXX/';
        $p = new WebmappProjectStructure($root);
        $this->expectException(Exception::class);
        $p->check();        
    }

    // TODO: eccezione readConf json malformato
    // TODO: eccezione readConf json senza chiave tasks
    // TODO: eccezione readConf factorytask con un tipo non esistente
}