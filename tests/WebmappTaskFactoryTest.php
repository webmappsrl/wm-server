<?php
use PHPUnit\Framework\TestCase;

class WebmappTaskFactoryTest extends TestCase {

   public function testOk() {
        $conf = __DIR__.'/../data/api.webmapp.it/example.webmapp.it/server/overpassNode.conf';
        $type = 'overpassNode';


      $tf = new WebmappTaskFactory();
      $t = $tf->Task($conf);
      $this->assertEquals('object',gettype($t));
      $this->assertEquals('NONE',$tf->getError());
      $this->assertEquals('WebmappOverpassNodeTask',get_class($t));
      $this->assertEquals($type,$t->getType());
   }

}