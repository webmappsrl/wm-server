<?php
use PHPUnit\Framework\TestCase;

class WebmappRouteTaskTests extends TestCase
{

    public $name;
    public $root;
    public $project_structure;
    public $geojson_path;
    public $options = array();

    public function __construct() {
        $this->name = 'prova';
        $this->root = __DIR__.'/../data/api.webmapp.it/example.webmapp.it';
        $path_base = __DIR__.'/../data/api.webmapp.it';
        $this->project_structure = new WebmappProjectStructure($this->root,$path_base);
        $this->geojson_path = $this->project_structure->getPathGeojson() ;


        // http://dev.be.webmapp.it/wp-json/wp/v2/route/346
        $this->options = array('code'=>'dev','id'=>346);
    }

    public function clearAll() {
        // Pulizia delle directory
        $cmd = 'rm -f '.$this->geojson_path .'/*.geojson';
        system($cmd);
        $conf_path = $this->project_structure->getPathClientConf();
        $cmd = 'rm -f '.$conf_path;
        system($cmd);
        $conf_index = $this->project_structure->getPathClientIndex();
        $cmd = 'rm -f '.$conf_index;
        system($cmd);        
    }



    public function testOk() {

        $this->clearAll();

        $t = new WebmappRouteTask($this->name,$this->options,$this->project_structure);
        $this->assertTrue($t->check());
        $this->assertEquals('dev',$t->getCode());
        $this->assertEquals('http://dev.be.webmapp.it/wp-json/wp/v2/route/346',$t->getUrl());
        $this->assertTrue($t->process());
        $r = $t->getRoute();
        $this->assertEquals('WebmappRoute',get_class($r));
        $this->assertEquals('346',$r->getId());
        $this->assertEquals('Next to Net7 ROUTE',$r->getTitle());

        $tracks_layer = $t->getTracksLayer();
        $this->assertEquals('WebmappLayer',get_class($tracks_layer));
        $this->assertTrue(file_exists($this->geojson_path.'/tracks.geojson'));

        $this->assertTrue(file_exists($this->geojson_path.'/pois_30.geojson'));
        $this->assertTrue(file_exists($this->geojson_path.'/pois_7.geojson'));

        // Languages
        $this->assertTrue(file_exists($this->geojson_path.'/languages/en_US/pois_30.geojson'));
        $this->assertTrue(file_exists($this->geojson_path.'/languages/fr_FR/pois_30.geojson'));

        $this->assertTrue(file_exists($this->root.'/client/config.json'));
        $ja = json_decode(file_get_contents($this->root.'/client/config.json'),TRUE);
        //$this->assertRegExp('/"label":"Mappa"/',$json);
        $this->assertEquals($ja['MAP']['layers'][0]['label'],'Mappa');
        $this->assertEquals($ja['MAP']['layers'][0]['type'],'mbtiles');
        $this->assertEquals($ja['MENU'][1]['label'],'Mappa');
        $this->assertEquals($ja['MENU'][3]['label'],'Tappe');
        $this->assertEquals($ja['MENU'][0]['label'],'Esci dall\'itinerario');
        $this->assertEquals($ja['routeID'],'346');

        // BOUNDING BOX
        $this->assertEquals($ja['MAP']['bounds']['northEast'][0],43.569839999999999);
        $this->assertEquals($ja['MAP']['bounds']['northEast'][1],10.21466);
        $this->assertEquals($ja['MAP']['bounds']['southWest'][0],43.877560000000003);
        $this->assertEquals($ja['MAP']['bounds']['southWest'][1],10.685499999999999);
        $this->assertEquals($ja['MAP']['center']['lat'],43.744);
        $this->assertEquals($ja['MAP']['center']['lng'],10.531000000000001);

        // SKIP Menu Options
        $this->assertTrue($ja['OPTIONS']['mainMenuHideAttributionPage']);
        $this->assertTrue($ja['OPTIONS']['mainMenuHideWebmappPage']);

        // OVERLAY LAYERS
        $icon = '';
        foreach ($ja['OVERLAY_LAYERS'] as $layer ) {
            if($layer['geojsonUrl']=="tracks.geojson") {
                $icon = $layer['icon'];
            }
        }
        $this->assertEquals('wm-icon-trail',$icon);


    }
    public function testBB() {

        $this->clearAll();
        // http://dev.be.webmapp.it/wp-json/wp/v2/route/772
        $this->options = array('code'=>'dev','id'=>772);

        $t = new WebmappRouteTask('772-BB',$this->options,$this->project_structure);
        $this->assertTrue($t->check());
        $this->assertEquals('dev',$t->getCode());
        $this->assertEquals('http://dev.be.webmapp.it/wp-json/wp/v2/route/772',$t->getUrl());
        $this->assertTrue($t->process());
        $r = $t->getRoute();
        $this->assertEquals('WebmappRoute',get_class($r));
        $this->assertEquals('772',$r->getId());
        $this->assertEquals('COPIA DI Next to Net7 ROUTE',$r->getTitle());

        $tracks_layer = $t->getTracksLayer();
        $this->assertEquals('WebmappLayer',get_class($tracks_layer));
        $this->assertTrue(file_exists($this->geojson_path.'/tracks.geojson'));

        $this->assertTrue(file_exists($this->geojson_path.'/pois_30.geojson'));
        $this->assertTrue(file_exists($this->geojson_path.'/pois_7.geojson'));

        // Languages
        $this->assertTrue(file_exists($this->geojson_path.'/languages/en_US/pois_30.geojson'));
        $this->assertTrue(file_exists($this->geojson_path.'/languages/fr_FR/pois_30.geojson'));

        $this->assertTrue(file_exists($this->root.'/client/config.json'));
        $ja = json_decode(file_get_contents($this->root.'/client/config.json'),TRUE);
        //$this->assertRegExp('/"label":"Mappa"/',$json);
        $this->assertEquals($ja['MAP']['layers'][0]['label'],'Mappa');
        $this->assertEquals($ja['MAP']['layers'][0]['type'],'mbtiles');
        $this->assertEquals($ja['MENU'][1]['label'],'Mappa');
        $this->assertEquals($ja['MENU'][0]['label'],'Esci dall\'itinerario');
        $this->assertEquals($ja['routeID'],'772');

        // BOUNDING BOX
        $this->assertEquals($ja['MAP']['bounds']['northEast'][0],43.7702642);
        $this->assertEquals($ja['MAP']['bounds']['northEast'][1],10.4752324);
        $this->assertEquals($ja['MAP']['bounds']['southWest'][0],43.6627041);
        $this->assertEquals($ja['MAP']['bounds']['southWest'][1],10.3372971);
        $this->assertEquals($ja['MAP']['center']['lat'],43.71648415);
        $this->assertEquals($ja['MAP']['center']['lng'],10.40626475);
        $this->assertEquals($ja['MAP']['maxZoom'],17);
        $this->assertEquals($ja['MAP']['minZoom'],7);
        $this->assertEquals($ja['MAP']['defZoom'],9);
    }

    public function testUrls() {

        $this->clearAll();

        $t = new WebmappRouteTask($this->name,$this->options,$this->project_structure);
        $t->check();
        $t->process();

        // http://dev.be.webmapp.it/wp-json/wp/v2/route/346
        $this->assertEquals('http://dev.be.webmapp.it',$t->getBaseUrl());
        $this->assertEquals('http://dev.be.webmapp.it/wp-json/wp/v2',$t->getApiBaseUrl());
        $this->assertEquals('http://dev.be.webmapp.it/wp-json/wp/v2/route/346',$t->getUrl());

    }


}