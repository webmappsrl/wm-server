<?php
use PHPUnit\Framework\TestCase;

class WebmappTaskFactoryTest extends TestCase {

   public function testOk() {
   	  $type = 'overpassNode';
   	  $tf = new WebmappTaskFactory();
   	  $t = $tf->getTask($type);
   	  $this->assertEquals('NONE',$tf->getError());
   	  $this->assertEquals('object',gettype($t));
   	  $this->assertEquals('WebmappOverpassNodeTask',get_class($t));
   	  $this->assertEquals($type,$t->getType());
   }

   public function testNoClass() {
   	  $type = 'noType';
   	  $tf = new WebmappTaskFactory();
   	  $t = $tf->getTask($type);
   	  $this->assertRegExp('/ERROR/',$tf->getError());
   	  $this->assertTrue(is_null($t));
   }

}
