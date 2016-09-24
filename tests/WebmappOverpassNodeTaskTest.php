<?php
use PHPUnit\Framework\TestCase;

class WebmappOverpassNodeTaskTest extends TestCase {

   public function testOk() {

       $name = 'overpassNode';
       $path = __DIR__.'/../data/api.webmapp.it/example.webmapp.it/server/overpassNode.conf';
       $project_path=__DIR__.'/../data/api.webmapp.it/example.webmapp.it/';
       $geojson_path = __DIR__.'/../data/api.webmapp.it/example.webmapp.it/geojson/';
       $geojson_file = $geojson_path.'overpassNode.geojson';

       $json = array(
          'task_type' => 'overpassNode',
          'query' => '"traffic_sign"="IT:Divieto di transito"',
          'bounds' => array("southWest"=>array(43.704367081989,10.338478088378),
                      "northEast"=>array(43.84839376489,10.637855529785))
        );

       $encoded_query='%22traffic_sign%22%3D%22IT%3ADivieto%20di%20transito%22';

       $t = new WebmappOverpassNodeTask($name,$path,$json);
       $this->assertEquals('NONE',$t->getError());

       $this->assertEquals('overpassNode',$t->getName());
       $this->assertEquals('overpassNode',$t->getType());
       $this->assertEquals($path,$t->getPath());
       $this->assertEquals($project_path,$t->getProjectPath());
       $this->assertEquals($geojson_path,$t->getGeojsonPath());
       $this->assertEquals($geojson_file,$t->getGeojsonFile());
       $bounds = $t->getBounds();
       $this->assertEquals('WebmappBounds',get_class($bounds));
       $this->assertEquals($json['bounds']['southWest'],$bounds->getSouthWest());



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
   	  