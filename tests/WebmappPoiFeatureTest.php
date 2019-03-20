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

        public function testFeaturedImage() {
            $poi = new WebmappPoiFeature('http://dev.be.webmapp.it/wp-json/wp/v2/poi/567');
            $j = json_decode($poi->getJson(),true);
            $image = $j['properties']['image'];
            $this->assertEquals('http://dev.be.webmapp.it/wp-content/uploads/2017/03/IMG_0056-768x576.jpg',$image);
        }

        public function testRemoveProperty() {
            $poi = new WebmappPoiFeature('http://dev.be.webmapp.it/wp-json/wp/v2/poi/567');
            $j = json_decode($poi->getJson(),true);
            $props=$j['properties'];
            $this->assertTrue(array_key_exists('noInteraction', $props));

            $poi->removeProperty('noInteraction');
            $j = json_decode($poi->getJson(),true);
            $props=$j['properties'];
            $this->assertFalse(array_key_exists('noInteraction', $props));

            $poi->cleanProperties();
            $j = json_decode($poi->getJson(),true);
            $props=$j['properties'];
            $this->assertFalse(array_key_exists('noInteraction', $props));
            $this->assertFalse(array_key_exists('noDetails', $props));
            $this->assertFalse(array_key_exists('accessibility', $props));
            $this->assertFalse(array_key_exists('id_pois', $props));

        }

        public function testContentFrom() {
            $poi = new WebmappPoiFeature('http://dev.be.webmapp.it/wp-json/wp/v2/poi/890');
            $j = json_decode($poi->getJson(),true);
            $d = $j['properties']['description'];
            $this->assertRegExp('|TESTO DI TEST PER CONTENT FROM|',$d);            
        }

        public function testTaxonomy() {
            $poi = new WebmappPoiFeature('http://dev.be.webmapp.it/wp-json/wp/v2/poi/800');
            $j = json_decode($poi->getJson(),true);
            $this->assertEquals(35,$j['properties']['taxonomy']['webmapp_category'][0]);
        }

        // TEST SU PF per nuovo campo coordinates
        // Entrambe: http://cosmopoli.travel/wp-json/wp/v2/poi/2185
        // Solo vecchio: http://cosmopoli.travel/wp-json/wp/v2/poi/2570 (HA OSM di default)
        // Solo OSM: http://cosmopoli.travel/wp-json/wp/v2/poi/2478

        public function testACFOSM() {
            // Caso di test per il nuovo campo ACF costruito con OSM
            // REF: https://github.com/mcguffin/acf-field-openstreetmap
            // USIAMO IL CASO DI PORTOFERRAIO
            // Entrambe: http://cosmopoli.travel/wp-json/wp/v2/poi/2185
            // Deve prendere le vecchie
            $poi = new WebmappPoiFeature('http://cosmopoli.travel/wp-json/wp/v2/poi/2185');
            $this->assertEquals(42.8140764,$poi->getLat());
            $this->assertEquals(10.329311800000028,$poi->getLng());

            
            // Solo vecchio: http://cosmopoli.travel/wp-json/wp/v2/poi/2570 (HA OSM di default)
            // Deve prendere le vecchie
            $poi = new WebmappPoiFeature('http://cosmopoli.travel/wp-json/wp/v2/poi/2570');
            $this->assertEquals(42.8191423,$poi->getLat());
            $this->assertEquals(10.30749860000003,$poi->getLng());

            // Solo OSM: http://cosmopoli.travel/wp-json/wp/v2/poi/2478
            // deve prendere le nuove
            $poi = new WebmappPoiFeature('http://cosmopoli.travel/wp-json/wp/v2/poi/2478');
            $this->assertEquals(42.8139895,$poi->getLat());
            $this->assertEquals(10.3236765,$poi->getLng());

        }

        public function testLocale() {
            $poi = new WebmappPoiFeature('http://dev.be.webmapp.it/wp-json/wp/v2/poi/800');
            $j = json_decode($poi->getJson(),true);
            $this->assertEquals('it',$j['properties']['locale']);            

        }

        public function testTranslations() {
            $poi = new WebmappPoiFeature('http://dev.be.webmapp.it/wp-json/wp/v2/poi/951');
            $j = json_decode($poi->getJson(),true);

            $this->assertTrue(isset($j['properties']['translations']));
            $t=$j['properties']['translations'];
            $this->assertTrue(isset($t['en']));
            $this->assertTrue(isset($t['fr']));
            $this->assertTrue(isset($t['de']));
            $en=$t['en'];
            $fr=$t['fr'];
            $de=$t['de'];

            $this->assertEquals(955,$en['id']);
            $this->assertEquals(957,$fr['id']);
            $this->assertEquals(958,$de['id']);

            $this->assertEquals('http://dev.be.webmapp.it//en-ponte-di-mezzo-punto-tre/?lang=en',$en['web']);
            $this->assertEquals('http://dev.be.webmapp.it/wp-json/wp/v2/poi/955',$en['source']);
            $this->assertEquals('EN Ponte di Mezzo Punto tre',$en['name']);
            $this->assertEquals(1,preg_match('|EN Tuttavia|',$en['description']));

        }

        public function testSource() {
            $poi = new WebmappPoiFeature('http://dev.be.webmapp.it/wp-json/wp/v2/poi/800');
            $j = json_decode($poi->getJson(),true);
            $this->assertEquals('http://dev.be.webmapp.it/wp-json/wp/v2/poi/800',$j['properties']['source']);            
        }

        public function testWeb() {
            $poi = new WebmappPoiFeature('http://dev.be.webmapp.it/wp-json/wp/v2/poi/800');
            $j = json_decode($poi->getJson(),true);
            $this->assertEquals('http://dev.be.webmapp.it/poi/test-per-languages-overlay-layers/',$j['properties']['web']);                        
        }

        public function testMediumImage() {
            $poi = new WebmappPoiFeature('http://outcropedia.be.webmapp.it/wp-json/wp/v2/poi/1478');
            $j = json_decode($poi->getJson(),true);
            $this->assertTrue(isset($j['properties']['image']));
            $this->assertEquals('http://outcropedia.tectask.org/wp-content/uploads/2018/06/Zimudang-thrust-300x203.jpg',$j['properties']['image']);
        }

        public function testWriteToPostGis() {
            $poi = new WebmappPoiFeature('http://dev.be.webmapp.it/wp-json/wp/v2/poi/522');
            $poi->writeToPostGis('test');

        }

        public function testHasProperty() {
            $wp_url = 'http://dev.be.webmapp.it/wp-json/wp/v2/poi/522';
            $poi = new WebmappPoiFeature($wp_url);
            $this->assertTrue($poi->hasProperty('name'));
            $this->assertFalse($poi->hasProperty('xxx'));
        }

        public function testWPEdit() {
            $wp_url = 'http://dev.be.webmapp.it/wp-json/wp/v2/poi/509';
            $poi = new WebmappPoiFeature($wp_url);
            $this->assertTrue($poi->hasProperty('wp_edit'));
            $this->assertEquals('http://dev.be.webmapp.it/wp-admin/post.php?post=509&action=edit',$poi->getProperty('wp_edit'));
        }

        public function testEle() {
            $wp_url = 'http://dev.be.webmapp.it/wp-json/wp/v2/poi/509';
            $poi = new WebmappPoiFeature($wp_url);
            $poi->addEle();
            $j=json_decode($poi->getJson(),TRUE);
            $this->assertTrue(count($j['geometry']['coordinates'])==3);
        }


}
