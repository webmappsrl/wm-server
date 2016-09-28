<?php
use PHPUnit\Framework\TestCase;

class WebmappOverpassNodeTaskTest extends TestCase {

   public function testOk() {

       $name = 'overpassNode';
       $project_root = __DIR__.'/../data/api.webmapp.it/example.webmapp.it/';
       $project_structure = new WebmappProjectStructure($project_root);
       $geojson = $project_root .'geojson/poi/'.$name.'.geojson';

       $json = array(
          'task_type' => 'overpassNode',
          'query' => '"traffic_sign"="IT:Divieto di transito"',
          'bounds' => array("southWest"=>array(43.704367081989,10.338478088378),
                      "northEast"=>array(43.84839376489,10.637855529785))
        );

       $encoded_query='%22traffic_sign%22%3D%22IT%3ADivieto%20di%20transito%22';

       $t = new WebmappOverpassNodeTask($name,$project_root,$json);

       $this->assertEquals('overpassNode',$t->getName());
       $this->assertEquals('overpassNode',$t->getType());

       $bounds = $t->getBounds();
       $this->assertEquals('WebmappBounds',get_class($bounds));
       $this->assertEquals($json['bounds']['southWest'],$bounds->getSouthWest());

       $this->assertEquals($project_structure,$t->getProjectStructure());

       $this->assertEquals($geojson,$t->getPoiFile());

       $response = $t->check();
       $this->assertEquals($encoded_query,$t->getEncodedQuery());

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
   	  