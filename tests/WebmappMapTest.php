<?php
use PHPUnit\Framework\TestCase;

class WebmappMapTest extends TestCase
{

    private $map;
    private $project_structure;
    private $root;

    private function init() {
        $this->root = __DIR__.'/../data/api.webmapp.it/example.webmapp.it';
        $this-> project_structure = new WebmappProjectStructure($this->root);
        $root = $this->root;

        $cmd = "rm -f $root/client/config.js";system($cmd);
        $cmd = "rm -f $root/client/config.json";system($cmd);
        $cmd = "rm -f $root/config.js";system($cmd);
        $cmd = "rm -f $root/config.json";system($cmd);
        $cmd = "rm -f $root/index.html";system($cmd);
        $cmd = "rm -f $root/client/index.html";system($cmd);
        $cmd = "rm -f $root/client/info.json";system($cmd);
        $cmd = "rm -f $root/resources/icon.png";system($cmd);        
        $cmd = "rm -f $root/resources/splash.png";system($cmd);        
    }

    public function testLoadMetaFromUrl() {

        $this -> init();

        $m = new WebmappMap($this->project_structure);
        $this->assertFalse($m->hasOffline());
        $url = 'http://dev.be.webmapp.it/wp-json/wp/v2/map/408';
        $m->loadMetaFromUrl($url);
        $this->assertTrue($m->hasOffline());

        // Add layers
        $l1 = new WebmappLayer('pois','/');
        $l1->loadMetaFromUrl('http://dev.be.webmapp.it/wp-json/wp/v2/webmapp_category/30');
        $m->addPoisWebmappLayer($l1);
        $l2 = new WebmappLayer('tracks','/');
        $l2->loadMetaFromUrl('http://dev.be.webmapp.it/wp-json/wp/v2/webmapp_category/7');
        $m->addTracksWebmappLayer($l2);

        $this->assertEquals('DEV408 &#8211; MMP',$m->getTitle());
        $this->assertEquals('http://{s}.tile.osm.org/',$m->getTilesUrl());
        //$this->assertEquals('',$m->get());
        $j = $m->getConfJson();
        $ja = json_decode($j,TRUE);

        // OPTIONS
        $this->assertTrue($ja['OPTIONS']['activateZoomControl']);
        $this->assertRegExp('/"VERSION":"0.4"/',$j);
        $this->assertRegExp('/"title":"DEV408 &#8211; MMP"/',$j);
        $this->assertRegExp('/"filterIcon":"wm-icon-layers"/',$j);
        $this->assertRegExp('/"background":"#F3F6E9"/',$j);
        $this->assertRegExp('/"ADVANCED_DEBUG":false/',$j);
        // TODO: migliorare questo controllo
        $this->assertRegExp('|"baseUrl":"http:[^"]*example.webmapp.it"|',$j);
        $this->assertRegExp('/"resourceBaseUrl":"http:[^"]*example.webmapp.it[^"]*geojson"/',$j);
        $this->assertRegExp('/"showAllByDefault":true/',$j);

        // SEZIONE STYLE
        $this->assertRegExp('/"background":"#FAFAFA"/',$j);

        // SEZIONE SEARCH
        $this->assertRegExp('/"indexFields":\["name","description","email","address"\]/',$j);
        
        // SEZIONE MAP
        $this->assertRegExp('/"maxZoom":"16"/',$j);
        $this->assertRegExp('/"minZoom":"10"/',$j);
        $this->assertRegExp('/"defZoom":"13"/',$j);
        // TODO: migliorare questo controllo
        $this->assertRegExp('/"type":"maptile"/',$j);
        $this->assertRegExp('/"tilesUrl":".*tile.osm.org.*"/',$j);

        // DETAIL_MAPPING
        $this->assertRegExp('/"DETAIL_MAPPING":{"default":{/',$j);
        $this->assertRegExp('/"email":"contact:email"/',$j);
        $this->assertRegExp('/"description":"description"/',$j);

        // MENU
        $this->assertRegExp('/"MENU":/',$j);
        $this->assertRegExp('/"label":"Mappa"/',$j);
        $this->assertRegExp('/"type":"map"/',$j);
        

        // PAGES
        $this->assertRegExp('/"label":"Pagina Numero Uno"/',$j);
        $this->assertRegExp('/"label":"Pagina Numero due"/',$j);
        $this->assertRegExp('/"type":"pagina-numero-uno"/',$j);
        $this->assertRegExp('/"isCustom":true/',$j);


        // OVERLAY LAYERS
        $this->assertRegExp('/"type":"poi_geojson"/',$j);
        $this->assertRegExp('/"type":"line_geojson"/',$j);
        $this->assertRegExp('/"label":"Bar"/',$j);
        $this->assertRegExp('/"label":"Ristoranti"/',$j);
        $this->assertRegExp('/"geojsonUrl":"pois.geojson"/',$j);
        $this->assertRegExp('/"geojsonUrl":"tracks.geojson"/',$j);
        $this->assertRegExp('/"icon":"wm-icon-siti-interesse"/',$j);
        $this->assertRegExp('/"icon":"wm-icon-restaurant"/',$j);

        // Additional Overlay Layers
        $labels = array();
        foreach($ja['OVERLAY_LAYERS'] as $layer) {
            $labels[]=$layer['label'];
        }
        $this->assertTrue(in_array('ADD1',$labels));
        $this->assertTrue(in_array('ADD2',$labels));



        // OFFLINE
        $this->assertRegExp('/"resourceBaseUrl":"http:[^"]*example.webmapp.it[^"]*geojson/',$j);
        $this->assertRegExp('/"pagesUrl":"http:[^"]*example.webmapp.it[^"]*pages/',$j);
        $this->assertRegExp('/"urlMbtiles":"http:[^"]*example.webmapp.it[^"]*tiles[^"]*map.mbtiles"/',$j);
        $this->assertRegExp('/"urlImages":"http:[^"]*example.webmapp.it[^"]*media[^"]*images.zip"/',$j);

        // LANGUAGES
        $this->assertRegExp('/"actual":"it"/',$j);
        $this->assertRegExp('/"available":/',$j);
        $this->assertRegExp('/"type":"languages"/',$j);
        
        // MAPTILE
        $this->assertRegExp('/"type":"maptile"/',$j);

        // REPORT
        $this->assertRegExp('/"apiUrl":".*share.php"/',$j);
        $this->assertRegExp('/"default":"alessiopiccioli@webmapp.it"/',$j);
        $this->assertRegExp('/"default":"\+39 328 5360803"/',$j);


        $m->writeConf();

        $this->assertTrue(file_exists($this->root . '/client/config.js'));
        $this->assertTrue(file_exists($this->root . '/client/config.json'));
        $this->assertTrue(file_exists($this->root . '/config.js'));
        $this->assertTrue(file_exists($this->root . '/config.json'));

        $m->writeIndex();
        $this->assertTrue(file_exists($this->root . '/client/index.html'));
        $this->assertTrue(file_exists($this->root . '/index.html'));

        // Controlla la index
/*        

*/

        $index = file_get_contents($this->root . '/client/index.html');
        $this->assertRegExp('/<script src="core\/js\/components\/webmapp\/webmapp.controller.js"><\/script>/',$index);
        $this->assertRegExp('/<script src="core\/js\/components\/attribution\/attribution.controller.js"><\/script>/',$index);
        $this->assertRegExp('/<script src="core\/js\/components\/home\/home.controller.js"><\/script>/',$index);
        $this->assertRegExp('/<script src="core\/lib\/leaflet_plugin\/leaflet-polylinedecorator\/dist\/leaflet.polylineDecorator.js"><\/script>/',$index);
        $this->assertRegExp('/<script src="core\/lib\/ng-country-select\/dist\/ng-country-select.js"><\/script>/',$index);
        $this->assertRegExp('/<script src="core\/js\/components\/languages\/languages.controller.js"><\/script>/',$index);

        $m->writeInfo();
        $this->assertTrue(file_exists($this->root . '/info.json'));
        $this->assertTrue(file_exists($this->root . '/resources/icon.png'));
        $this->assertTrue(file_exists($this->root . '/resources/splash.png'));
        $ja = json_decode(file_get_contents($this->root.'/info.json'),true);
        $this->assertEquals('http://example.webmapp.it/resources/icon.png',$ja['resources']['icon']);
        $this->assertEquals('http://example.webmapp.it/resources/splash.png',$ja['resources']['splash']);

    }

    public function testIndexTitle() {
        $this -> init();
        $m = new WebmappMap($this->project_structure);
        $m->setTitle('MY TITLE');
        $index = $m->getIndex();
        $this->assertEquals(1,preg_match('/<title>MY TITLE<\/title>/',$index));
    }

    public function testAdditionalOverlayLayers() {
        $this->init();
        $m = new WebmappMap($this->project_structure);
        $url = 'https://www.montepisano.travel/wp-json/wp/v2/map/7834';
        $m->loadMetaFromUrl($url);
        $j = $m->getConfJson();
        $ja = json_decode($j,TRUE);
        $this->assertEquals('Sentieri',$ja['OVERLAY_LAYERS'][0]['label']);
        $this->assertEquals('tile_utfgrid_geojson',$ja['OVERLAY_LAYERS'][0]['type']);
        $this->assertEquals('sentieri.geojson',$ja['OVERLAY_LAYERS'][0]['geojsonUrl']);
    }

    public function testAccessibility() {
        $this->init();
        $m = new WebmappMap($this->project_structure);
        $url = 'http://dev.be.webmapp.it/wp-json/wp/v2/map/408';
        $m->loadMetaFromUrl($url);
        $j = $m->getConfJson();
        $ja = json_decode($j,TRUE);
        $this->assertTrue($ja['OPTIONS']['showAccessibilityButtons']);
    } 

    public function testHideInBrowser() {
        $this -> init();
        $m = new WebmappMap($this->project_structure);
        $url = 'http://dev.be.webmapp.it/wp-json/wp/v2/map/408';
        $m->loadMetaFromUrl($url);
        $m->buildStandardMenu();
        $ja = json_decode($m->getConfJson(),TRUE);
        $this->assertTrue($ja['MENU'][2]['hideInBrowser']);
    }

    public function testHidePages() {
        $this -> init();
        $m = new WebmappMap($this->project_structure);
        $url = 'http://dev.be.webmapp.it/wp-json/wp/v2/map/408';
        $m->loadMetaFromUrl($url);
        $m->buildStandardMenu();
        $ja = json_decode($m->getConfJson(),TRUE);
        $this->assertTrue($ja['OPTIONS']['mainMenuHideWebmappPage']);        
        $this->assertTrue($ja['OPTIONS']['mainMenuHideAttributionPage']);        
    }

    public function testBB() {

        $this -> init();
        $m = new WebmappMap($this->project_structure);
        $url = 'http://dev.be.webmapp.it/wp-json/wp/v2/route/772';
        $m->loadMetaFromUrl($url);
        $this->assertTrue(empty($m->getBB()));
        $m->setBB(0,0,2,2);
        $bb=$m->getBB();
        $this->assertEquals($bb['maxZoom'],17);
        $this->assertEquals($bb['minZoom'],7);
        $this->assertEquals($bb['defZoom'],9);
        $this->assertEquals($bb['bounds']['southWest'][0],0);
        $this->assertEquals($bb['bounds']['southWest'][1],0);
        $this->assertEquals($bb['bounds']['northEast'][0],2);
        $this->assertEquals($bb['bounds']['northEast'][1],2);
        $this->assertEquals($bb['center']['lat'],1);
        $this->assertEquals($bb['center']['lng'],1);

        // TODO: verificare anche la configurazione?
        $j=$m->getConfJson();
        $ja=json_decode($j,TRUE);
        $this->assertEquals($ja['MAP']['maxZoom'],17);
        $this->assertEquals($ja['MAP']['minZoom'],7);
        $this->assertEquals($ja['MAP']['defZoom'],9);
        $this->assertEquals($ja['MAP']['bounds']['southWest'][0],0);
        $this->assertEquals($ja['MAP']['bounds']['southWest'][1],0);
        $this->assertEquals($ja['MAP']['bounds']['northEast'][0],2);
        $this->assertEquals($ja['MAP']['bounds']['northEast'][1],2);
        $this->assertEquals($ja['MAP']['center']['lat'],1);
        $this->assertEquals($ja['MAP']['center']['lng'],1);
    }


    public function testLoadMetaFromUrlDefault() {
        $this -> init();
        $m = new WebmappMap($this->project_structure);
        $url = 'http://dev.be.webmapp.it/wp-json/wp/v2/map/414';
        $m->loadMetaFromUrl($url);
        //$this->assertEquals('',$m->get());
        $j = $m->getConfJson();
        // SEZIONE STYLE
        $this->assertRegExp('/"background":"#F3F6E9"/',$j);
    }

    public function testWriteInfo() {
        $this -> init();
        $m = new WebmappMap($this->project_structure);
        $m->setTitle('Titolo di prova');
        $m->writeInfo();
        $this->assertTrue(file_exists($this->root . '/info.json'));
        $ja = json_decode(file_get_contents($this->root.'/info.json'),true);
        $this->assertEquals('http://example.webmapp.it/config.js',$ja['configJs']);
        $this->assertEquals('http://example.webmapp.it/config.json',$ja['configJson']);
        $this->assertEquals('it.webmapp.default',$ja['config.xml']['id']);
        $this->assertEquals('Titolo di prova',$ja['config.xml']['name']);
        $this->assertEquals('App description',$ja['config.xml']['description']);
    }

    public function testLangActualOnRoute() {
        $this->init();
        $m = new WebmappMap($this->project_structure);
        $url = 'http://dev.be.webmapp.it/wp-json/wp/v2/route/686';
        $m->loadMetaFromUrl($url);
        $j = $m->getConfJson();
        $this->assertRegExp('/"LANGUAGES":/',$j);
        $this->assertRegExp('/"actual":"it"/',$j);

    }

    public function testLangDeafultEnNoMulti() {
        $this->init();
        $m = new WebmappMap($this->project_structure);
        $url = 'http://dev.be.webmapp.it/wp-json/wp/v2/map/797';
        $m->loadMetaFromUrl($url);
        $j = $m->getConfJson();
        $this->assertRegExp('/"LANGUAGES":/',$j);
        $this->assertRegExp('/"actual":"en"/',$j);        
    }


}
