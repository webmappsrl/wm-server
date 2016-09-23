<?php
use PHPUnit\Framework\TestCase;

class WebmappOverpassNodeTaskTest extends TestCase {

   public function testOk() {

       $name = 'overpassNode';
       $path = __DIR__.'/../../data/api.webmapp.it/example.webmapp.it/server/overpassNode.conf';
       $json = array(
          'task_type' => 'overpassNode',
          'query' => '"traffic_sign"="IT:Divieto di transito"'
        );

       $encoded_query='%22traffic_sign%22%3D%22IT%3ADivieto%20di%20transito%22';

       $t = new WebmappOverpassNodeTask($name,$path,$json);
       $this->assertEquals('NONE',$t->getError());

       $this->assertEquals('overpassNode',$t->getName());
       $this->assertEquals('overpassNode',$t->getType());
       $this->assertEquals($path,$t->getPath());
       $project_path=__DIR__.'/../../data/api.webmapp.it/example.webmapp.it/';
       $this->assertEquals($project_path,$t->getProjectPath());
       $json_t=$t->getJson();
       $this->assertEquals($json['query'],$json_t['query']);
       $this->assertEquals($json['task_type'],$json_t['task_type']);

       $response = $t->check();
       $this->assertEquals($json['query'],$t->getQuery());
       $this->assertEquals($encoded_query,$t->getEncodedQuery());

       $this->assertEquals('NONE',$t->getError());

       $this->assertTrue($response);

   }

   public function testNoQuery() {
       $name = 'overpassNode';
       $path = __DIR__.'/../../data/api.webmapp.it/example.webmapp.it/server/overpassNode.conf';
       $json = array(
          'task_type' => 'overpassNode',
        );
       $t = new WebmappOverpassNodeTask($name,$path,$json);
       $response = $t->check();
       $this->assertEquals('ERROR: parameter query in json is mandatory for this type of task.',$t->getError());
       $this->assertFalse($response);
   }
}
   	  