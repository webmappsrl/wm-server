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

    public function testLoadMetaFromUrl() {
        $m = new WebmappMap($this->project_structure);
        $url = 'http://dev.be.webmapp.it/wp-json/wp/v2/map/408';
        $m->loadMetaFromUrl($url);

        // Add layers
        $l1 = new WebmappLayer('pois','/');
        $l1->loadMetaFromUrl('http://dev.be.webmapp.it/wp-json/wp/v2/webmapp_category/30');
        $m->addPoisWebmappLayer($l1);
        $l2 = new WebmappLayer('tracks','/');
        $l2->loadMetaFromUrl('http://dev.be.webmapp.it/wp-json/wp/v2/webmapp_category/7');
        $m->addTracksWebmappLayer($l2);

        $this->assertEquals('DEV408 &#8211; MMP',$m->getTitle());
        $this->assertEquals('https://api.mappalo.org/mappadeimontipisani_new/tiles/map/',$m->getTilesUrl());
        //$this->assertEquals('',$m->get());
        $j = $m->getConfJson();
        $this->assertRegExp('/"VERSION":"0.4"/',$j);
        $this->assertRegExp('/"title":"DEV408 &#8211; MMP"/',$j);
        $this->assertRegExp('/"filterIcon":"wm-icon-layers"/',$j);
        $this->assertRegExp('/"background":"#F3F6E9"/',$j);
        $this->assertRegExp('/"ADVANCED_DEBUG":false/',$j);
        // TODO: migliorare questo controllo
        $this->assertRegExp('/"resourceBaseUrl":".*geojson"/',$j);
        $this->assertRegExp('/"showAllByDefault":true/',$j);
        $this->assertRegExp('/"MENU":/',$j);
        $this->assertRegExp('/"label":"Esci dall\'itinerario"/',$j);
        $this->assertRegExp('/"label":"Mappa"/',$j);
        $this->assertRegExp('/"label":"Cerca"/',$j);

        // SEZIONE MAP
        $this->assertRegExp('/"maxZoom":"16"/',$j);
        $this->assertRegExp('/"minZoom":"10"/',$j);
        $this->assertRegExp('/"defZoom":"13"/',$j);
        // TODO: migliorare questo controllo
        $this->assertRegExp('/"tilesUrl":".*mappadeimontipisani_new/',$j);

        // DETAIL_MAPPING
        $this->assertRegExp('/"DETAIL_MAPPING":{"default":{/',$j);
        $this->assertRegExp('/"email":"mail"/',$j);
        $this->assertRegExp('/"description":"description"/',$j);

        // PAGES
        $this->assertRegExp('/"PAGES":\[\]/',$j);

        // OVERLAY LAYERS
        $this->assertRegExp('/"type":"poi_geojson"/',$j);
        $this->assertRegExp('/"type":"line_geojson"/',$j);
        $this->assertRegExp('/"label":"Bar"/',$j);
        $this->assertRegExp('/"label":"Ristoranti"/',$j);
        $this->assertRegExp('/"geojsonUrl":"pois.geojson"/',$j);
        $this->assertRegExp('/"geojsonUrl":"tracks.geojson"/',$j);
        $this->assertRegExp('/"icon":"wm-icon-siti-interesse"/',$j);
        $this->assertRegExp('/"icon":"wm-icon-restaurant"/',$j);

        $m->writeConf();

    }

    public function testOk() {

    }

}

function constructMap() {
// Preso da API DEV: view-source:http://dev.be.webmapp.it/wp-json/wp/v2/map/408
$map_string = '{"id":408,"date":"2017-04-05T16:35:23","date_gmt":"2017-04-05T16:35:23","guid":{"rendered":"http:\/\/dev.be.webmapp.it\/?post_type=map&#038;p=408"},"modified":"2017-04-18T14:53:42","modified_gmt":"2017-04-18T14:53:42","slug":"test-all","status":"publish","type":"map","link":"http:\/\/dev.be.webmapp.it\/map\/test-all\/","title":{"rendered":"TEST ALL"},"content":{"rendered":"","protected":false},"excerpt":{"rendered":"","protected":false},"author":2,"featured_media":0,"template":"","webmapp_category":[],"n7webmap_type":"all","net7webmap_map_route":null,"layer_poi":null,"tiles":"https:\/\/api.mappalo.org\/mappadeimontipisani_new\/tiles\/map\/","n7webmap_map_bbox":"{\r\n    maxZoom: 18,\r\n    minZoom: 7,\r\n    defZoom: 9,\r\n    center: {\r\n        lat: 43.719287828277004,\r\n        lng: 10.39685368537899\r\n    },\r\n    bounds: {\r\n        southWest: [\r\n            43.34116005412307,\r\n            9.385070800781252\r\n        ],\r\n        northEast: [\r\n            44.09547572946637,\r\n            11.4093017578125\r\n        ]\r\n    }\r\n}","wpml_current_locale":"it_IT","wpml_translations":[],"_links":{"self":[{"href":"http:\/\/dev.be.webmapp.it\/wp-json\/wp\/v2\/map\/408"}],"collection":[{"href":"http:\/\/dev.be.webmapp.it\/wp-json\/wp\/v2\/map"}],"about":[{"href":"http:\/\/dev.be.webmapp.it\/wp-json\/wp\/v2\/types\/map"}],"author":[{"embeddable":true,"href":"http:\/\/dev.be.webmapp.it\/wp-json\/wp\/v2\/users\/2"}],"version-history":[{"href":"http:\/\/dev.be.webmapp.it\/wp-json\/wp\/v2\/map\/408\/revisions"}],"wp:attachment":[{"href":"http:\/\/dev.be.webmapp.it\/wp-json\/wp\/v2\/media?parent=408"}],"wp:term":[{"taxonomy":"webmapp_category","embeddable":true,"href":"http:\/\/dev.be.webmapp.it\/wp-json\/wp\/v2\/webmapp_category?post=408"}],"curies":[{"name":"wp","href":"https:\/\/api.w.org\/{rel}","templated":true}]}}';
return json_decode($map_string,true);
}