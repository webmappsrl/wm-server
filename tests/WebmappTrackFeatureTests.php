<?php // WebmappTrackFeatureTests.php

use PHPUnit\Framework\TestCase;

class WebmappTrackFeatureTests extends TestCase {
	public function testOk() {
		$track = new WebmappTrackFeature('http://dev.be.webmapp.it/wp-json/wp/v2/track/580');
		$json = $track->getJson();
                $this->assertRegExp('/"type":"Feature"/',$json);
                $this->assertRegExp('/"id":580/',$json);
                $this->assertRegExp('/"name":"Pisa Tour by Bike"/',$json);
                $this->assertRegExp('/"description":"<p>Un breve ma intenso giro in bicicletta per scoprire tutti i segreti di Pisa\.</',$json);
                $this->assertRegExp('/"image":"http:.*Bici-Pisa\.jpg"/',$json);
                $this->assertRegExp('/"src":"http:.*pisa_stazione_riparazione_gonfiaggio_bici_corso_italia_2\.jpg/',$json);
                $this->assertRegExp('/"src":"http:.*Screenshot-2017-03-01-15\.06\.47\.png/',$json);

                // $this->assertRegExp('/"":""/',$json);
                // $this->assertRegExp('/"color":"#dd3333"/',$json);
        
        }
}
