<?php
use PHPUnit\Framework\TestCase;

class WebmappMapTest extends TestCase
{

    private $map;
    private $project_structure;

    public function __construct() {
        $this->map = constructMap();
        $root = __DIR__.'/../data/api.webmapp.it/example.webmapp.it';
        $path_base = __DIR__.'/../data/api.webmapp.it';
        $this-> project_structure = new WebmappProjectStructure($root,$path_base);;
    }

    public function testOk() {
       $m = new WebmappMap($this->map,$this->project_structure);
    $this->assertEquals('all',$m->getType());
    $this->assertEquals('TEST ALL',$m->getTitle());
    $this->assertRegExp('/maxZoom: 18/',$m->getBB());
    $this->assertRegExp('/minZoom: 7/',$m->getBB());
    $this->assertRegExp('/defZoom: 9/',$m->getBB());

    $conf = $m->getConf();

    $this->assertRegExp("/title: 'TEST ALL'/",$conf);
    $this->assertRegExp('/maxZoom: 18/',$m->getBB());
    $this->assertRegExp('/minZoom: 7/',$m->getBB());
    $this->assertRegExp('/defZoom: 9/',$m->getBB());

    // Verifica della scrittura del file conf.js
    $conf_path = $this->project_structure->getPathClientConf();
    $cmd = 'rm -f '.$conf_path;
    system($cmd);

    $m->writeConf();
    $this->assertTrue(file_exists($conf_path));

    $conf = file_get_contents($conf_path);
    $this->assertRegExp("/title: 'TEST ALL'/",$conf);
    $this->assertRegExp('/maxZoom: 18/',$conf);
    $this->assertRegExp('/minZoom: 7/',$conf);
    $this->assertRegExp('/defZoom: 9/',$conf);

    }

}

function constructMap() {
// Preso da API DEV: view-source:http://dev.be.webmapp.it/wp-json/wp/v2/map/408
 $map_string = '{"id":408,"date":"2017-04-05T16:35:23","date_gmt":"2017-04-05T16:35:23","guid":{"rendered":"http:\/\/dev.be.webmapp.it\/?post_type=map&#038;p=408"},"modified":"2017-04-05T16:35:59","modified_gmt":"2017-04-05T16:35:59","slug":"test-all","status":"publish","type":"map","link":"http:\/\/dev.be.webmapp.it\/map\/test-all\/","title":{"rendered":"TEST ALL"},"content":{"rendered":"","protected":false},"excerpt":{"rendered":"","protected":false},"author":2,"featured_media":0,"template":"","webmapp_category":[],"n7webmap_type":"all","net7webmap_map_route":null,"layer_poi":null,"n7webmap_map_bbox":"{\r\n    maxZoom: 18,\r\n    minZoom: 7,\r\n    defZoom: 9,\r\n    center: {\r\n        lat: 43.719287828277004,\r\n        lng: 10.39685368537899\r\n    },\r\n    bounds: {\r\n        southWest: [\r\n            43.34116005412307,\r\n            9.385070800781252\r\n        ],\r\n        northEast: [\r\n            44.09547572946637,\r\n            11.4093017578125\r\n        ]\r\n    }\r\n}","wpml_current_locale":"it_IT","wpml_translations":[],"_links":{"self":[{"href":"http:\/\/dev.be.webmapp.it\/wp-json\/wp\/v2\/map\/408"}],"collection":[{"href":"http:\/\/dev.be.webmapp.it\/wp-json\/wp\/v2\/map"}],"about":[{"href":"http:\/\/dev.be.webmapp.it\/wp-json\/wp\/v2\/types\/map"}],"author":[{"embeddable":true,"href":"http:\/\/dev.be.webmapp.it\/wp-json\/wp\/v2\/users\/2"}],"version-history":[{"href":"http:\/\/dev.be.webmapp.it\/wp-json\/wp\/v2\/map\/408\/revisions"}],"wp:attachment":[{"href":"http:\/\/dev.be.webmapp.it\/wp-json\/wp\/v2\/media?parent=408"}],"wp:term":[{"taxonomy":"webmapp_category","embeddable":true,"href":"http:\/\/dev.be.webmapp.it\/wp-json\/wp\/v2\/webmapp_category?post=408"}],"curies":[{"name":"wp","href":"https:\/\/api.w.org\/{rel}","templated":true}]}}';
 return json_decode($map_string,true);
}