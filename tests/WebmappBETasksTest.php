<?php
use PHPUnit\Framework\TestCase;

class WebmappBETasksTests extends TestCase
{

    public $name;
    public $root;
    public $project_structure;
    public $options = array();


    private function init($url) {
        $this->name = 'prova';
        $this->root = __DIR__.'/../data/api.webmapp.it/'.$url;
        $this->project_structure = new WebmappProjectStructure($this->root);
        // Pulizia delle directory
        $cmd = 'rm -f '.$this->project_structure->getPathGeojson().'/*.geojson';
        system($cmd);
        $conf_path = $this->project_structure->getPathClientConf();
        $cmd = 'rm -f '.$conf_path;
        system($cmd);
        $conf_index = $this->project_structure->getPathClientIndex();
        $cmd = 'rm -f '.$conf_index;
        system($cmd);
        system('rm -f '.$this->root.'/info.json');


    }

    public function testOk() {
        $this->init('example.webmapp.it');
        $conf_path = $this->project_structure->getPathClientConf();
        $conf_index = $this->project_structure->getPathClientIndex();
        // La mappa con ID 408 ha n7webmap_type='all';
        $this->options = array('code'=>'dev','id'=>408);        

        $this->assertEquals('http://example.webmapp.it',$this->project_structure->getUrlBase());

        $t = new WebmappBETask($this->name,$this->options,$this->project_structure);
        $this->assertTrue($t->check());
        $this->assertEquals('dev',$t->getCode());
        $this->assertTrue($t->process());
        
        // File di POIS
        $this->assertTrue(file_exists($this->project_structure->getPathGeojson().'/pois_30.geojson'));
        $this->assertTrue(file_exists($this->project_structure->getPathGeojson().'/pois_7.geojson'));

        // 30 BAR: http://dev.be.webmapp.it/wp-json/wp/v2/poi?webmapp_category=30
        $pois_30 = file_get_contents($this->project_structure->getPathGeojson().'/pois_30.geojson');
        $this->assertRegExp('/0000ff/',$pois_30);
        $this->assertRegExp('/"noDetails":true/',$pois_30);
        $this->assertRegExp('/"icon":"wm-icon-mappalo"/',$pois_30);
        $this->assertRegExp('/"image":/',$pois_30);

        // File di Tracks
        $this->assertTrue(file_exists($this->project_structure->getPathGeojson().'/tracks_14.geojson'));

        // Tour della cat 11: http://dev.be.webmapp.it/wp-admin/post.php?post=580&action=edit&lang=it
        $tracks_11 = file_get_contents($this->project_structure->getPathGeojson().'/tracks_14.geojson');
        $this->assertRegExp('/81d742/',$tracks_11);
        $this->assertRegExp('/Pisa Tour by Bike/',$tracks_11);
        $this->assertRegExp('/imageGallery/',$tracks_11);
        $this->assertRegExp('/Screenshot-2017-03-01-15/',$tracks_11);
        $this->assertRegExp('/ref/',$tracks_11);
        $this->assertRegExp('/descent/',$tracks_11);
        $this->assertRegExp('/ascent/',$tracks_11);
        $this->assertRegExp('/duration:forward/',$tracks_11);
        $this->assertRegExp('/duration:backward/',$tracks_11);
        $this->assertRegExp('/distance/',$tracks_11);
        $this->assertRegExp('/cai_scale/',$tracks_11);
        // "image":"http:\/\/dev.be.webmapp.it\/wp-content\/uploads\/2017\/03\/Screenshot-2017-03-01-15.06.47.png"
        $this->assertRegExp('/"image":/',$tracks_11);



        // Controllo sui file del client di configurazione e index.html
        $this->assertTrue(file_exists($conf_path));
        $this->assertTrue(file_exists($conf_index));
        $conf = file_get_contents($conf_path);
        $index = file_get_contents($conf_index);
        $this->assertRegExp('/"title":"DEV408 &#8211; MMP"/',$conf);
        $this->assertRegExp('/"maxZoom":"16"/',$conf);
        $this->assertRegExp('/"minZoom":"10"/',$conf);
        $this->assertRegExp('/"defZoom":"13"/',$conf);
        $this->assertRegExp('/"label":"Bar"/',$conf);
        $this->assertRegExp('/"color":"#00ff00"/',$conf);
        $this->assertRegExp('/"icon":"wm-icon-generic",/',$conf);
        $this->assertRegExp('/"icon":"wm-icon-restaurant",/',$conf);

        // Controllo Menu Standard (Mappa + layer)
        $this->assertRegExp('/"label":"Punti di interesse",/',$conf);
        // TODO: $this->assertRegExp('/"type":"layer",/',$conf);
        $this->assertRegExp('/"type":"layerGroup",/',$conf);
        $this->assertRegExp('/"items":\["/',$conf);

        $this->assertRegExp('/"label":"Informazioni"/',$conf);
        $this->assertRegExp('/"type":"pageGroup"/',$conf);
        $this->assertRegExp('/"items":\["Pagina Numero Uno","Pagina Numero due"\]/',$conf);
        $this->assertRegExp('/"color":"#dd3333"/',$conf);
        $this->assertRegExp('/"icon":"wm-icon-manor"/',$conf);

        $this->assertRegExp('/"label":"Mappa Offline"/',$conf);
        $this->assertRegExp('/"type":"settings"/',$conf);
        // MAPTILE
        $this->assertRegExp('/"type":"maptile"/',$conf);

        // OFFLINE
        $this->assertRegExp('/"resourceBaseUrl":"http:[^"]*example.webmapp.it[^"]*geojson/',$conf);
        $this->assertRegExp('/"pagesUrl":"http:[^"]*example.webmapp.it[^"]*pages/',$conf);
        $this->assertRegExp('/"urlMbtiles":"http:[^"]*example.webmapp.it[^"]*tiles[^"]*map.mbtiles"/',$conf);
        $this->assertRegExp('/"urlImages":"http:[^"]*example.webmapp.it[^"]*media[^"]*images.zip"/',$conf);

        // Languages
        $this->assertRegExp('/Ristoranti/',$conf);
        $this->assertRegExp('/"it":"Ristoranti"/',$conf);
        $this->assertRegExp('/"en":"Restaurants"/',$conf);

        // Controllo file info.json
        $this->assertTrue(file_exists($this->root . '/info.json'));
        $ja = json_decode(file_get_contents($this->root . '/info.json'),true);
        $this->assertEquals('http://example.webmapp.it/config.js',$ja['configJs']);
        $this->assertEquals('http://example.webmapp.it/config.json',$ja['configJson']);
        $this->assertEquals('it.webmapp.testpisa',$ja['config.xml']['id']);
        $this->assertRegExp('/DEV408/',$ja['config.xml']['name']);
        $this->assertEquals('Descrizione di test per la APP',$ja['config.xml']['description']);

        // config.json
        $config_json=$this->root."/config.json";
        $this->assertTrue(file_exists($config_json));
        $ja = json_decode(file_get_contents($config_json),TRUE);
        $labels = array();
        foreach($ja['OVERLAY_LAYERS'] as $layer) {
            $labels[]=$layer['label'];
        }
        $this->assertFalse(in_array('attenzione', $labels));

        // ADvanced Options
        // additional overlay Layers
        $this->assertTrue(in_array('ADD1',$labels));
        $this->assertTrue(in_array('ADD2',$labels));
     
    }

    public function testBB() {
        $this->init('example.webmapp.it');
        $conf_path = $this->project_structure->getPathClientConf();
        $conf_index = $this->project_structure->getPathClientIndex();
        // La mappa con ID 408 ha n7webmap_type='all';
        $this->options = array('code'=>'dev','id'=>408);        

        // Pulizia delle directory
        $cmd = 'rm -f '.$this->project_structure->getPathGeojson().'/*.geojson';
        system($cmd);
        $conf_path = $this->project_structure->getPathClientConf();
        $cmd = 'rm -f '.$conf_path;
        system($cmd);
        $conf_index = $this->project_structure->getPathClientIndex();
        $cmd = 'rm -f '.$conf_index;
        system($cmd);
        system('rm -f '.$this->root.'/info.json');
        $json_conf_file = $this->root.'/config.json';
        system('rm -f '.$json_conf_file);

        $this->assertEquals('http://example.webmapp.it',$this->project_structure->getUrlBase());
        $options = array('code'=>'dev','id'=>780);

        $t = new WebmappBETask($this->name,$options,$this->project_structure);
        $this->assertTrue($t->check());
        $this->assertEquals('dev',$t->getCode());
        $this->assertTrue($t->process());
        
        $ja = json_decode(file_get_contents($json_conf_file),TRUE);
        // BOUNDING BOX
        $this->assertEquals($ja['MAP']['bounds']['northEast'][0],46.08409);
        $this->assertEquals($ja['MAP']['bounds']['northEast'][1],12.781930847168);
        $this->assertEquals($ja['MAP']['bounds']['southWest'][0],38.046336607512);
        $this->assertEquals($ja['MAP']['bounds']['southWest'][1],6.61582);
        $this->assertEquals($ja['MAP']['center']['lat'],42.065213303756);
        $this->assertEquals($ja['MAP']['center']['lng'],9.698875423584);
        $this->assertEquals($ja['MAP']['maxZoom'],17);
        $this->assertEquals($ja['MAP']['minZoom'],7);
        $this->assertEquals($ja['MAP']['defZoom'],9);

    }

    public function testHTTPS() {
        $root=__DIR__.'/../data/api.webmapp.it/examplesecure.webmapp.it';
        $confPath= $root.'/config.json';
        $s = new WebmappProject($root);
        $s->check();
        $s->process();
        $j = json_decode(file_get_contents($confPath),TRUE);
        $this->assertRegExp('/https/',$j['MAP']['layers'][0]['tilesUrl']);
        $this->assertRegExp('/https/',$j['COMMUNICATION']['baseUrl']);
        $this->assertRegExp('/https/',$j['COMMUNICATION']['resourceBaseUrl']);
        $this->assertRegExp('/https/',$j['OFFLINE']['pagesUrl']);
        $this->assertRegExp('/https/',$j['OFFLINE']['urlMbtiles']);
        $this->assertRegExp('/https/',$j['OFFLINE']['urlImages']);
    }
}