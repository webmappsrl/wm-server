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
        // TODO: aggiungere controlli dettagliati su contenuto del file config

        $json = file_get_contents($this->root.'/client/config.json');
        $this->assertRegExp('/"label":"Mappa"/',$json);
        $this->assertRegExp('/"label":"Cerca"/',$json);
        $this->assertRegExp('/"label":"Esci dall\'itinerario"/',$json);
        $this->assertRegExp('/"type":"mbtiles"/',$json);
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