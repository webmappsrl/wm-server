<?php
use PHPUnit\Framework\TestCase;

class WebmappTaskFactoryTest extends TestCase {

   public function testOk() {
        $type = 'overpassNode';
        $name = 'overpassNode';
        $path = __DIR__.'/../data/api.webmapp.it/example.webmapp.it/server/overpassNode.conf';
        $json = array(
          'task_type' => 'overpassNode',
          'query' => '"traffic_sign"="IT:Divieto di transito"'
        );


   	  $tf = new WebmappTaskFactory();
   	  $t = $tf->getTask($type,$name,$path,$json);
   	  $this->assertEquals('NONE',$tf->getError());
   	  $this->assertEquals('object',gettype($t));
   	  $this->assertEquals('WebmappOverpassNodeTask',get_class($t));
   	  $this->assertEquals($type,$t->getType());
   }

   public function testNoClass() {
   	  $type = 'noType';
        $name = 'overpassNode';
        $path = __DIR__.'/../data/api.webmapp.it/example.webmapp.it/server/overpassNode.conf';
        $json = array(
          'task_type' => 'overpassNode',
          'query' => '"traffic_sign"="IT:Divieto di transito"'
        );
   	  $tf = new WebmappTaskFactory();
   	  $t = $tf->getTask($type,$name,$path,$json);
   	  $this->assertRegExp('/ERROR/',$tf->getError());
   	  $this->assertTrue(is_null($t));
   }

}
