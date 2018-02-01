<?php // WebmappTrackFeatureTests.php

use PHPUnit\Framework\TestCase;

class WebmappTrackFeatureTests extends TestCase {
	public function testOk() {
		$track = new WebmappTrackFeature('http://dev.be.webmapp.it/wp-json/wp/v2/track/580');
		$json = $track->getJson();

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

                

        }

        public function testGetRelatedPois() {
                $track = new WebmappTrackFeature('http://dev.be.webmapp.it/wp-json/wp/v2/track/348');
                $pois = $track->getRelatedPois();
                $this->assertTrue(is_array($pois));
                $this->assertEquals(4,count($pois));
                $this->assertEquals('WebmappPoiFeature',get_class($pois[0]));
        }

        public function testGetWebmappCategoryIds() {
                $poi = new WebmappTrackFeature('http://dev.be.webmapp.it/wp-json/wp/v2/track/580');
                $ids = $poi->getWebmappCategoryIds();
                $this->assertTrue(is_array($ids));
                $this->assertEquals(1,count($ids));
                $this->assertEquals(14,$ids[0]);
        }
}
