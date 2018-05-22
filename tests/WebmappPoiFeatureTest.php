<?php // WebmappPoiFeatureTests.php

use PHPUnit\Framework\TestCase;

class WebmappPoiFeatureTest extends TestCase {
        public function testOk() {
                $wp_url = 'http://dev.be.webmapp.it/wp-json/wp/v2/poi/522';
                $poi = new WebmappPoiFeature($wp_url);
                $this->assertEquals($wp_url,$poi->getWPUrl());
                $json = $poi->getJson();
                $this->assertRegExp('/"type":"Feature"/',$json);
                $this->assertRegExp('/"id":522/',$json);
                $this->assertRegExp('/"name":"Bar San Domenico"/',$json);
                $this->assertRegExp('/"description":"<p>Il bar di moda a porta a Lucca</',$json);
                $this->assertRegExp('/"color":"#dd3333"/',$json);
                $this->assertRegExp('/"icon":"wm-icon-generic"/',$json);
                $this->assertRegExp('/"noDetails":false/',$json);
                $this->assertRegExp('/"noInteraction":true/',$json);
                $this->assertRegExp('/"image":"http:.*dolomites-550349_960_720/',$json);
                $this->assertRegExp('/"src":"http:.*dolomites-550349_960_720/',$json);
                $this->assertRegExp('/"src":"http:.*mountain-1077939_960_720/',$json);
                $this->assertRegExp('/"addr:street":"Largo Parlascio"/',$json);
                $this->assertRegExp('/"addr:housenumber":"1"/',$json);
                $this->assertRegExp('/"addr:postcode":"56127"/',$json);
                $this->assertRegExp('/"addr:city":"Pisa"/',$json);
                $this->assertRegExp('/"address":"Largo Parlascio, 1 Pisa"/',$json);
                $this->assertRegExp('/"contact:email":"info@barsandomenico\.it"/',$json);
                $this->assertRegExp('/"contact:phone":"\+39 050 7846161"/',$json);
                $this->assertRegExp('/"opening_hours":"Sa-Su 00:00-24:00"/',$json);
                $this->assertRegExp('/"capacity":"50"/',$json);
                $this->assertRegExp('/"type":"Point"/',$json);
                $this->assertRegExp('/43\.7223352/',$json);
                $this->assertRegExp('/10\.4015262/',$json);

                $this->assertEquals($poi->getLatMax(),43.7223352);
                $this->assertEquals($poi->getLngMax(),10.4015262);
                $this->assertEquals($poi->getLatMin(),43.7223352);
                $this->assertEquals($poi->getLngMin(),10.4015262);

                $bb=$poi->getBB();
                $this->assertEquals($bb['bounds']['southWest'][0],43.7223352);
                $this->assertEquals($bb['bounds']['southWest'][1],10.4015262);
                $this->assertEquals($bb['bounds']['northEast'][0],43.7223352);
                $this->assertEquals($bb['bounds']['northEast'][1],10.4015262);
                $this->assertEquals($bb['center']['lat'],43.7223352);
                $this->assertEquals($bb['center']['lng'],10.4015262);
        }

       public function testLanguagesEn() {
                $poi = new WebmappPoiFeature('http://dev.be.webmapp.it/wp-json/wp/v2/poi/522');
                $json = $poi->getJson('en');
                $this->assertRegExp('/EN title/',$json);
                $this->assertRegExp('/"description":"<p>English version for Bar San Domenico.</',$json);
        //$this->assertRegExp('/"":""/',$json);
        }

       public function testLanguagesFr() {
                $poi = new WebmappPoiFeature('http://dev.be.webmapp.it/wp-json/wp/v2/poi/522');
                $json = $poi->getJson('fr');
                $this->assertRegExp('/french version/',$json);
                $this->assertRegExp('/Descrizione in francese./',$json);
        //$this->assertRegExp('/"":""/',$json);
        }

        public function testGetWebmappCategoryIds() {
                $poi = new WebmappPoiFeature('http://dev.be.webmapp.it/wp-json/wp/v2/poi/610');
                $ids = $poi->getWebmappCategoryIds();
                $this->assertTrue(is_array($ids));
                $this->assertEquals(1,count($ids));
                $this->assertEquals(30,$ids[0]);
        }

        public function testAccessibility() {
                $poi = new WebmappPoiFeature('http://dev.be.webmapp.it/wp-json/wp/v2/poi/800');
                $j = json_decode($poi->getJson(),true);
                $types = array('mobility','hearing','vision','cognitive','food');
                foreach($types as $type) {
                   $this->assertTrue($j['properties']['accessibility'][$type]['check']);
                   $this->assertRegExp('|<p>test</p>|',$j['properties']['accessibility'][$type]['description']);                         
                }
        }

        public function testRelatedUrl() {
                $poi = new WebmappPoiFeature('http://dev.be.webmapp.it/wp-json/wp/v2/poi/800');
                $j = json_decode($poi->getJson(),true);
                $this->assertTrue(isset($j['properties']['related_url']));
                $urls=$j['properties']['related_url'];
                $this->assertTrue(in_array('http://www.google.it',$urls));
                $this->assertTrue(in_array('http://www.webmapp.it',$urls));
        }

        public function testImageCaption() {
            $poi = new WebmappPoiFeature('http://dev.be.webmapp.it/wp-json/wp/v2/poi/567');
            $j = json_decode($poi->getJson(),true);
            $g = $j['properties']['imageGallery'];
            $has_572 = false;
            foreach($g as $image) {
                if($image['id']==572) {
                    $has_572=true;
                    $caption = $image['caption'];
                }
            }
            $this->assertTrue($has_572);
            $this->assertEquals('CAPTION TEST',$caption);
        }
}
