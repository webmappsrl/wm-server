<?php // WebmappTrackFeatureTests.php

use PHPUnit\Framework\TestCase;

class WebmappTrackFeatureTests extends TestCase {
	public function testOk() {
		$track = new WebmappTrackFeature('http://dev.be.webmapp.it/wp-json/wp/v2/track/580');
		$json = $track->getJson();
                // TODO: testare con ja
                $ja = json_decode($json,TRUE);

                // MAPPING STANDARD
                $this->assertRegExp('/"type":"Feature"/',$json);
                $this->assertRegExp('/"id":580/',$json);
                $this->assertRegExp('/"name":"Pisa Tour by Bike"/',$json);
                $this->assertRegExp('/"description":"<p>Un breve ma intenso giro in bicicletta per scoprire tutti i segreti di Pisa\.</',$json);
                $this->assertRegExp('/"image":"http:.*Bici-Pisa\.jpg"/',$json);
                $this->assertRegExp('/"src":"http:.*pisa_stazione_riparazione_gonfiaggio_bici_corso_italia_2\.jpg/',$json);
                $this->assertRegExp('/"src":"http:.*Screenshot-2017-03-01-15\.06\.47/',$json);

                // MAPPING SPECIFIC TRACK
                $this->assertRegExp('/"color":"#81d742"/',$json);
                $this->assertRegExp('/"from":"Stazione di Pisa"/',$json);
                $this->assertRegExp('/"to":"Stazione di Pisa"/',$json);
                $this->assertRegExp('/"ref":"001"/',$json);
                $this->assertRegExp('/"ascent":"100"/',$json);
                $this->assertRegExp('/"descent":"100"/',$json);
                $this->assertRegExp('/"distance":"12345"/',$json);
                $this->assertRegExp('/"duration:forward":"11:22"/',$json);
                $this->assertRegExp('/"duration:backward":"22:11"/',$json);
                $this->assertRegExp('/"cai_scale":"E"/',$json);

                // MAPPING GEOMETRY
                $this->assertRegExp('/"type":"LineString"/',$json);
                $this->assertRegExp('/10\.39874/',$json);
                $this->assertRegExp('/10\.39743/',$json);
                $this->assertRegExp('/10\.38968/',$json);
                $this->assertRegExp('/43\.70904/',$json);
                $this->assertRegExp('/43\.71941/',$json);
                $this->assertRegExp('/43\.70904/',$json);

                //$this->assertRegExp('/"":""/',$json);
                //LngMin: 10.38781 LngMax: 10.41875 LatMin: 43.70904 LatMax: 43.72496
                $this->assertEquals(10.38781,$track->getLngMin());
                $this->assertEquals(10.41875,$track->getLngMax());
                $this->assertEquals(43.70904,$track->getLatMin());
                $this->assertEquals(43.72496,$track->getLatMax());

                $bb=$track->getBB();
                $this->assertEquals($bb['bounds']['southWest'][0],43.70904);
                $this->assertEquals($bb['bounds']['southWest'][1],10.38781);
                $this->assertEquals($bb['bounds']['northEast'][0],43.72496);
                $this->assertEquals($bb['bounds']['northEast'][1],10.41875);
                $this->assertEquals($bb['center']['lat'],(43.72496+43.70904)/2);
                $this->assertEquals($bb['center']['lng'],(10.38781+10.41875)/2);

                // RELATED POIS
                $ids = $ja['properties']['id_pois'];
                $this->assertTrue(in_array(487, $ids));
                $this->assertTrue(in_array(488, $ids));
                $this->assertTrue(in_array(465, $ids));
                $this->assertTrue(in_array(510, $ids));
        }

        public function testGetRelatedPois() {
                $track = new WebmappTrackFeature('http://dev.be.webmapp.it/wp-json/wp/v2/track/348');
                $pois = $track->getRelatedPois();
                $this->assertTrue(is_array($pois));
                $this->assertEquals(6,count($pois));
                $this->assertEquals('WebmappPoiFeature',get_class($pois[0]));
        }

        // public function testGetWebmappCategoryIds() {
        //         $poi = new WebmappTrackFeature('http://dev.be.webmapp.it/wp-json/wp/v2/track/580');
        //         $ids = $poi->getWebmappCategoryIds();
        //         $this->assertTrue(is_array($ids));
        //         $this->assertEquals(2,count($ids));
        //         $this->assertEquals(14,$ids[0]);
        // }

        public function testRemoveProperty() {
            $t = new WebmappTrackFeature('http://dev.be.webmapp.it/wp-json/wp/v2/track/580');
            $j = json_decode($t->getJson(),true);
            $props=$j['properties'];
            $this->assertTrue(array_key_exists('noInteraction', $props));

            $t->removeProperty('noInteraction');
            $j = json_decode($t->getJson(),true);
            $props=$j['properties'];
            $this->assertFalse(array_key_exists('noInteraction', $props));

            $t->cleanProperties();
            $j = json_decode($t->getJson(),true);
            $props=$j['properties'];
            $this->assertFalse(array_key_exists('noInteraction', $props));
            $this->assertFalse(array_key_exists('noDetails', $props));
            $this->assertFalse(array_key_exists('accessibility', $props));
            $this->assertFalse(array_key_exists('id_pois', $props));

        }

        public function testTaxonomy() {
            $poi = new WebmappTrackFeature('http://dev.be.webmapp.it/wp-json/wp/v2/track/769');
            $j = json_decode($poi->getJson(),true);
            $this->assertEquals(40,$j['properties']['taxonomy']['activity'][0]);
        }

        public function testWriteGPX() {
            $track = new WebmappTrackFeature('http://dev.be.webmapp.it/wp-json/wp/v2/track/580');
            $path='/tmp/'.$track->getId().'.gpx';
            $cmd="rm -f $path"; system($cmd);
            $track->writeGPX('/tmp');
            $this->assertTrue(file_exists($path));
        }

        public function testWriteKML() {
            $track = new WebmappTrackFeature('http://dev.be.webmapp.it/wp-json/wp/v2/track/580');
            $path='/tmp/'.$track->getId().'.kml';
            $cmd="rm -f $path"; system($cmd);
            $track->writeKML('/tmp');
            $this->assertTrue(file_exists($path));
        }

        public function testaddEle() {
            $track = new WebmappTrackFeature('http://dev.be.webmapp.it/wp-json/wp/v2/track/580');
            $track->addEle();
            $j=json_decode($track->getJson(),TRUE);
            $this->assertTrue(isset($j['geometry']));
            $this->assertTrue(isset($j['geometry']['coordinates']));
            $this->assertTrue(count($j['geometry']['coordinates'][0])==3);

            $this->assertTrue(isset($j['properties']['distance']));
            $this->assertTrue(isset($j['properties']['ele:from']));
            $this->assertTrue(isset($j['properties']['ele:to']));
            $this->assertTrue(isset($j['properties']['ele:max']));
            $this->assertTrue(isset($j['properties']['ele:min']));
            $this->assertTrue(isset($j['properties']['ascent']));
            $this->assertTrue(isset($j['properties']['descent']));
            $this->assertTrue(isset($j['properties']['duration:forward']));
            $this->assertTrue(isset($j['properties']['duration:backward']));

        }

}
