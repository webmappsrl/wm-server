<?php 

use PHPUnit\Framework\TestCase;

class WebmappOSMFeatureTest extends TestCase {


	private function process($url,$feature,$properties=array(),$tags=array()) {
		$this->assertEquals($url,$feature->getUrl());
		if (count($properties)>0) {
			foreach($properties as $k => $v) {
				$this->assertEquals($v,$feature->getProperty($k));
			}
		}
		if (count($tags)>0) {
			foreach($tags as $k => $v) {
				$this->assertEquals($v,$feature->getTag($k));
			}
		}
	} 

	// SUPERRELATION (SR): https://www.openstreetmap.org/api/0.6/relation/1021025
	// <relation id="1021025" visible="true" version="57" changeset="61232947" timestamp="2018-07-31T14:58:22Z" user="Gianfranco2014" uid="1928626">
	public function testSuperRelation() {
		$feature = new WebmappOSMSuperRelation(1021025);
		$url = 'https://www.openstreetmap.org/api/0.6/relation/1021025';
		$this->process($url,$feature);
	}
	// WAY (W): https://www.openstreetmap.org/api/0.6/way/167059866 , https://www.openstreetmap.org/api/0.6/way/167059866/full  
	// <way id="167059866" visible="true" version="3" changeset="29333411" timestamp="2015-03-08T16:18:31Z" user="arcanma" uid="1211510">
	public function testWay() {
	 	$feature= new WebmappOSMWay(167059866);
	 	$url = 'https://www.openstreetmap.org/api/0.6/way/167059866';
		$this->process($url,$feature);
	}
	
	// NODE (N): https://www.openstreetmap.org/api/0.6/node/1486233694 (senza TAGS) 
	// <node id="1486233694" visible="true" version="2" changeset="14555749" timestamp="2013-01-06T21:03:25Z" user="Eraclitus" uid="196103" lat="43.1358956" lon="12.8297080"/>
	public function testNodeNoTag() {
	 	$feature = new WebmappOSMNode(1486233694);
	 	$url = 'https://www.openstreetmap.org/api/0.6/node/1486233694';
		$this->process($url,$feature);
	}

	
	// NODE (N): https://www.openstreetmap.org/api/0.6/node/1950330571 (con TAGS)
	// <node id="1950330571" visible="true" version="4" changeset="20582530" timestamp="2014-02-15T18:27:12Z" user="dforsi" uid="24126" lat="40.0146760" lon="9.2313480">
	public function testNodeWithTag() {
	 	$feature = new WebmappOSMNode(1950330571);
	 	$url = 'https://www.openstreetmap.org/api/0.6/node/1950330571';
		$this->process($url,$feature);
	}

	

	public function testExceptions() {
		$this->expectException(WebmappExceptionNoOSMFeature::class);
		$r = new WebmappOSMRelation("1");

		$this->expectException(WebmappExceptionNoOSMFeature::class);
		$r = new WebmappOSMRelation("xxx");
	}

	public function testTrackMeta() {
	    $t = new WebmappTrackFeature('http://dev.be.webmapp.it/wp-json/wp/v2/track/1007');
	    $ja = json_decode($t->getJson(),true);
        $this->assertEquals('E',$ja['properties']['cai_scale']);
        $this->assertEquals('Percorso 825',$ja['properties']['from']);
        $this->assertEquals('Passo della Tabella',$ja['properties']['to']);
        $this->assertEquals('Percorso 825 - Monte Pelpi - Passo di Monte Vacca\' - Tasola - Monte Orocco - Passo della Tabella',$ja['properties']['name']);
    }

}