<?php
use PHPUnit\Framework\TestCase;

class WebmappBETasksTests extends TestCase
{
    public function testSimple() {
        $name = 'prova';
        $root = __DIR__.'/../data/api.webmapp.it/example.webmapp.it/';

        //TEST OK
        $options = array('code'=>'dev');
        $t = new WebmappBETask($name,$options,$root);
        $this->assertTrue($t->check());
        $this->assertEquals('dev',$t->getCode());
        $this->assertEquals('http://dev.be.webmapp.it/wp-json/wp/v2/map',$t->getAPI('wp','map'));
        $this->assertEquals('http://dev.be.webmapp.it/wp-json/wp/v2/map',$t->getMapAPI());
        $this->assertEquals('http://dev.be.webmapp.it/wp-json/webmapp/v1/pois.geojson',$t->getAPI('wm','pois.geojson'));
        $this->assertTrue($t->process());
        $this->expectException(Exception::class);
        $t->getAPI('XX','');

        // ECCEZIONI
        // No code nell'array
        $options = array('nocode'=>'dev');
        $t = new WebmappBETask($name,$options,$root);
        $this->expectException(Exception::class);
        $t->check();
    }

    public function testLoadMaps() {
        $name = 'prova';
        $root = __DIR__.'/../data/api.webmapp.it/example.webmapp.it/';
        //TEST OK
        $options = array('code'=>'dev');
        $t = new WebmappBETask($name,$options,$root);
        $this->assertTrue($t->check());
        $maps=$t->loadAPI($t->getMapAPI());
        $this->assertGreaterThan(0,count($maps));
        foreach($maps as $map) {
            $this->assertTrue(array_key_exists('n7webmap_type', $map));
            $this->assertTrue(array_key_exists('layer_poi', $map));
        }
    }
}