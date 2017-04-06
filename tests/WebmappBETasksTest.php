<?php
use PHPUnit\Framework\TestCase;

class WebmappBETasksTests extends TestCase
{

    public $name;
    public $root;
    public $options = array();

    public function __construct() {
        $this->name = 'prova';
        $this->root = __DIR__.'/../data/api.webmapp.it/example.webmapp.it/';

        // La mappa con ID 408 ha n7webmap_type='all';
        $this->options = array('code'=>'dev','id'=>408);
    }
    public function testOk() {
        $t = new WebmappBETask($this->name,$this->options,$this->root);
        $this->assertTrue($t->check());
        $this->assertEquals('dev',$t->getCode());
        $this->assertEquals('http://dev.be.webmapp.it/wp-json/wp/v2/map',$t->getAPI('wp','map'));
        $this->assertEquals('http://dev.be.webmapp.it/wp-json/wp/v2/map/'.$this->options['id'],$t->getMapAPI());
        $this->assertEquals('http://dev.be.webmapp.it/wp-json/webmapp/v1/pois.geojson',$t->getAPI('wm','pois.geojson'));
        $this->assertTrue($t->process());
        $this->expectException(Exception::class);
        $t->getAPI('XX','');

    }

    public function testLoadMap() {
        $t = new WebmappBETask($this->name,$this->options,$this->root);
        $this->assertTrue($t->check());
        $map=$t->loadAPI($t->getMapAPI());
        $this->assertEquals($this->options['id'],$map['id']);
    }

    // Eccezioni
    public function testKoNocode() {
              // No code nell'array
        $options = array('nocode'=>'dev');
        $t = new WebmappBETask($this->name,$options,$this->root);
        $this->expectException(Exception::class);
        $t->check();
    }

    public function testKoNoID() {
              // No code nell'array
        $options = array('code'=>'dev');
        $t = new WebmappBETask($this->name,$options,$this->root);
        $this->expectException(Exception::class);
        $t->check();
    }

    public function testKoNoMap() {
              // No code nell'array
        $options = array('code'=>'dev','id'=>1);
        $t = new WebmappBETask($this->name,$options,$this->root);
        $t->check();
        $this->expectException(Exception::class);
        $t->process();
    }




}