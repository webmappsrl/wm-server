    <?php // WebmappTrackFeatureTests.php

use PHPUnit\Framework\TestCase;

class WebmappTrackFeatureTest extends TestCase {
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

        public function testGetGeometry() {
            $t = new WebmappTrackFeature('http://dev.be.webmapp.it/wp-json/wp/v2/track/927');
            $g = $t->getGeometry();
            $this->assertTrue(isset($g['type']));
            $this->assertEquals('LineString',$g['type']);
            $this->assertTrue(isset($g['coordinates']));
            $c=$g['coordinates'];
            $this->assertTrue(count($c)>0);
            $this->assertEquals(10.4016816,$c[0][0]);
            $this->assertEquals(43.715503699999999,$c[0][1]);
        }

        public function testBBox() {
            $pg = WebmappPostGis::Instance();
            $pg->clearTables('test');            
            $t = new WebmappTrackFeature('http://dev.be.webmapp.it/wp-json/wp/v2/track/927');
            $t->writeToPostGis('test');
            $t->addBBox('test');
            $ja=json_decode($t->getJson(),TRUE);
            $this->assertTrue(isset($ja['properties']['bbox']));
            $this->assertTrue(isset($ja['properties']['bbox_metric']));
            $this->assertEquals('10.40167,43.71309,10.40853,43.71627',$ja['properties']['bbox']);
            $this->assertEquals('1157909,5421149,1158672,5421639',$ja['properties']['bbox_metric']);

        }

        public function testGenerateImage(){
            // Prepare TEST
            $img_path = './data/tmp/927_map_491x624.png';
            $cmd = "rm -f $img_path";
            system($cmd);

            // LOAD DATA
            $t = new WebmappTrackFeature('http://dev.be.webmapp.it/wp-json/wp/v2/track/927');

            // PERFORMS OPERATION(S)
            $t->generateImage(491,624,'http://dev.be.webmapp.it','./data/tmp');

            // TEST(S)
            $this->assertTrue(file_exists($img_path));
            $info = getimagesize($img_path);
            $this->assertEquals('image/png',$info['mime']);
            $this->assertEquals(491,$info[0]);
            $this->assertEquals(624,$info[1]);

        }

        public function testGenerateImageVN(){
            // Prepare TEST
            //$img_path = './data/tmp/1300_map_491x624.png';
            $img_path = './data/tmp/12398_map_491x624.png';
            $cmd = "rm -f $img_path";
            system($cmd);

            // LOAD DATA
            $t = new WebmappTrackFeature('http://vn.be.webmapp.it/wp-json/wp/v2/track/12398');
            //$t = new WebmappTrackFeature('http://vn.be.webmapp.it/wp-json/wp/v2/track/1300');

            // PERFORMS OPERATION(S)
            $t->generateImage(491,624,'http://vn.be.webmapp.it','./data/tmp');
            $t->generateLandscapeRBImages('http://vn.be.webmapp.it','./data/tmp');

            // TEST(S)
            $this->assertTrue(file_exists($img_path));
            $info = getimagesize($img_path);
            $this->assertEquals('image/png',$info['mime']);
            $this->assertEquals(491,$info[0]);
            $this->assertEquals(624,$info[1]);

        }

        public function testGenerateAllImages() {
            // Prepare TEST
            $i1 = '/tmp/927_map_491x624.png';
            $i2 = '/tmp/927_map_400x300.png';
            $i3 = '/tmp/927_map_200x200.png';
            $i4 = '/tmp/927_map_1000x1000.png';
            $cmd = "rm -f $i1 $i2 $i3 $i4";
            system($cmd);

            // LOAD DATA
            $t = new WebmappTrackFeature('http://dev.be.webmapp.it/wp-json/wp/v2/track/927');

            // PERFORMS OPERATION(S)
            $t->generateAllImages('http://dev.be.webmapp.it','/tmp');

            // TEST(S)
            $this->assertTrue(file_exists($i1));
            $info = getimagesize($i1);
            $this->assertEquals('image/png',$info['mime']);
            $this->assertEquals(491,$info[0]);
            $this->assertEquals(624,$info[1]);

            $this->assertTrue(file_exists($i2));
            $info = getimagesize($i2);
            $this->assertEquals('image/png',$info['mime']);
            $this->assertEquals(400,$info[0]);
            $this->assertEquals(300,$info[1]);

            $this->assertTrue(file_exists($i3));
            $info = getimagesize($i3);
            $this->assertEquals('image/png',$info['mime']);
            $this->assertEquals(200,$info[0]);
            $this->assertEquals(200,$info[1]);

            $this->assertTrue(file_exists($i4));
            $info = getimagesize($i4);
            $this->assertEquals('image/png',$info['mime']);
            $this->assertEquals(1000,$info[0]);
            $this->assertEquals(1000,$info[1]);

        }

        // public function testGenerateLandscapeRBImages() {

        //     // Prepare TEST
        //     if(!file_exists('/tmp/rbtest')) {
        //         $cmd = 'mkdir /tmp/rbtest';
        //         system($cmd);
        //     }
        //     $cmd = 'rm -Rf /tmp/rbtest/*';
        //     system($cmd);

        //     // LOAD DATA
        //     $t = new WebmappTrackFeature('http://dev.be.webmapp.it/wp-json/wp/v2/track/711');

        //     // PERFORM OPERATIONS
        //     $t->writeToPostGis('http://dev.be.webmapp.it');
        //     $t->generateLandscapeRBImages('http://dev.be.webmapp.it','/tmp/rbtest');

        //     // TEST(S)

        //     // Esistenza di tutti i file che devono essere creati
        //     // Dimensioni in pixel delle immagini

        // }
        public function testSetComputedProperties() {
            // Prepare TEST

            // LOAD DATA
            $t = new WebmappTrackFeature('http://dev.be.webmapp.it/wp-json/wp/v2/track/711');

            // PERFORM OPERATIONS
            $t->setComputedProperties();
            $ja = json_decode($t->getJson(),TRUE);

            // TEST(S)
            $this->assertTrue(isset($ja['properties']['computed']));
            $this->assertTrue(isset($ja['properties']['computed']['distance']));
            $this->assertEquals(54.636123506129003,$ja['properties']['computed']['distance']);


        }

        public function testGetRunningPoints() {
            // Prepare TEST
            $instance_id='http://dev.be.webmapp.it';

            // LOAD DATA
            $t = new WebmappTrackFeature('http://dev.be.webmapp.it/wp-json/wp/v2/track/882');

            // PERFORM OPERATIONS
            $t->writeToPostGis($instance_id);
            $res = $t->getRunningPoints(10,$instance_id);

            // TEST(S)
            $this->assertTrue(is_array($res));
            $this->assertEquals(11,count($res));
        }

        public function testComputeDistance3857() {
            // Prepare TEST
            $instance_id='http://dev.be.webmapp.it';
            $expected_length = 46678;

            // LOAD DATA
            $t = new WebmappTrackFeature('http://dev.be.webmapp.it/wp-json/wp/v2/track/882');

            // TEST
            $this->assertEquals($expected_length,ROUND($t->computeDistance3857($instance_id)));


        }

        public function testWriteRBRelatedPoi() {
            // Prepare TEST
            $file = '/tmp/1300_rb_related_poi.geojson';
            system('rm -f '.$file);

            // LOAD DATA
            $t = new WebmappTrackFeature('http://vn.be.webmapp.it/wp-json/wp/v2/track/1300');

            // PERFORM OPERATIONS
            $t->writeRBRelatedPoi('/tmp','http://vn.be.webmapp.it');

            // TEST(S)
            $ja = json_decode($t->getJson(),TRUE);
            $this->assertTrue(isset($ja['properties']['related']['poi']['related']));
            $this->assertTrue(isset($ja['properties']['related']['poi']['roadbook']));
            $rb_pois = $ja['properties']['related']['poi']['roadbook'];
            $this->assertEquals(1280,$rb_pois[0]);
            $this->assertEquals(1309,$rb_pois[1]);
            $this->assertEquals(1301,$rb_pois[2]);

            //print_r($ja['properties']['related']['poi']['related']);

            // Abbazia di Monte Oliveto Maggiore 1301 seq=1
            // Pieve San Lorenzo 1309 seq=2
            $ja = json_decode(file_get_contents($file),TRUE);
            $this->assertTrue(isset($ja['features']));
            $features = $ja['features'];
            foreach($features as $poi) {
                if ($poi['properties']['id']==1301) {
                    $poi_1301=$poi;
                }
                else if ($poi['properties']['id']==1309) {
                    $poi_1309=$poi;
                }
                else if ($poi['properties']['id']==1280) {
                    $poi_1280=$poi;
                }
            }
            // Ordine corretto; 1280, 1309, 1301

            $this->assertTrue(isset($poi_1280['properties']['sequence']));
            $this->assertTrue(isset($poi_1309['properties']['sequence']));
            $this->assertTrue(isset($poi_1301['properties']['sequence']));

            $this->assertEquals(1,$poi_1280['properties']['sequence']);
            $this->assertEquals(2,$poi_1309['properties']['sequence']);
            $this->assertEquals(3,$poi_1301['properties']['sequence']);
        }

        public function testTranslations() {
            $track = new WebmappTrackFeature('http://dev.be.webmapp.it/wp-json/wp/v2/track/927');
            $j = json_decode($track->getJson(),true);

            $this->assertTrue(isset($j['properties']['translations']));
            $t=$j['properties']['translations'];
            $this->assertTrue(isset($t['en']));
            $this->assertTrue(isset($t['fr']));
            $this->assertTrue(isset($t['de']));
            $en=$t['en'];
            $fr=$t['fr'];
            $de=$t['de'];

            $this->assertEquals(961,$en['id']);
            $this->assertEquals(962,$fr['id']);
            $this->assertEquals(963,$de['id']);

            $this->assertEquals('http://dev.be.webmapp.it//corto-pisano-on-gpsies-com/?lang=en',$en['web']);
            $this->assertEquals('http://dev.be.webmapp.it/wp-json/wp/v2/track/961',$en['source']);
            $this->assertEquals('EN Corto Pisano on GPSies.com',$en['name']);
            $this->assertEquals(1,preg_match('|EN Un tranquillo|',$en['description']));
            $this->assertEquals(1,preg_match('|EN Roadbook|',$en['rb_track_section']));

        }

        public function testAscDesc() {
            // Asciano Mirteto
            $this->AscDescPrivate('Asciano.geojson',5.8,2,16,2,331,380,350);
            // Pania.geojson
            $this->AscDescPrivate('Pania.geojson',2.7,551,1822,551,1822,1271,0);

            // TEST di Marco
            $this->AscDescPrivate('GiroPiano.geojson',6.1,0,0,-3,1,21,21);
            $this->AscDescPrivate('Salita.geojson',1.3,131,891,131,891,760,0);
            $this->AscDescPrivate('SalitaEDiscesa.geojson',2.5,1587,1605,1587,2322,700,716);
            $this->AscDescPrivate('GiroLungo.geojson',71.3,1448,1426,1004,4081,6778,6800);


        }

        private function AscDescPrivate($input,$distance,$ele_from,$ele_to,$ele_min,$ele_max,$ascent,$descent) {
            // Delta%
            $delta_dist = 0.1;
            $delta_ele = 10;
            $delta_asc = 40;

            $pg=WebmappPostGis::Instance();
            $pg->clearTables('test');
            $j['id']=1;
            $j['title']['rendered']='testAscDesc';
            $t=new WebmappTrackFeature($j,true);

            // LOAD DATA
            $geojson = WebmappUtils::getJsonFromApi(dirname(__FILE__).'/fixtures/'.$input);
            $this->assertTrue(isset($geojson['features'][0]['geometry']));
            $t->setGeometryGeoJSON(json_encode($geojson['features'][0]['geometry']));
            $t->writeToPostGis('test');

            // PERFORM OPERATIONS
            $t->setComputedProperties2('test');

            // TEST(S)
            $j=json_decode($t->getJson(),true);
            $this->assertTrue(isset($j['properties']));
            $p=$j['properties'];
            $this->assertTrue(isset($p['computed']));
            $c=$p['computed'];

            $this->assertTrue(isset($c['distance']));
            $this->WMAssertEqualsWithDelta($distance,$c['distance'],$delta_dist);

            $this->assertTrue(isset($c['ele:from']));
            $this->WMAssertEqualsWithDelta($ele_from,$c['ele:from'],$delta_ele);

            $this->assertTrue(isset($c['ele:to']));
            $this->WMAssertEqualsWithDelta($ele_to,$c['ele:to'],$delta_ele);

            $this->assertTrue(isset($c['ele:min']));
            $this->WMAssertEqualsWithDelta($ele_min,$c['ele:min'],$delta_ele);

            $this->assertTrue(isset($c['ele:max']));
            $this->WMAssertEqualsWithDelta($ele_max,$c['ele:max'],$delta_ele);

            $this->assertTrue(isset($c['ascent']));
            $this->WMAssertEqualsWithDelta($ascent,$c['ascent'],$delta_asc);

            $this->assertTrue(isset($c['descent']));
            $this->WMAssertEqualsWithDelta($descent,$c['descent'],$delta_asc);

            $this->assertTrue(isset($p['distance']));
            $this->WMAssertEqualsWithDelta($distance,$p['distance'],$delta_dist);

            $this->assertTrue(isset($p['ele:from']));
            $this->WMAssertEqualsWithDelta($ele_from,$p['ele:from'],$delta_ele);

            $this->assertTrue(isset($p['ele:to']));
            $this->WMAssertEqualsWithDelta($ele_to,$p['ele:to'],$delta_ele);

            $this->assertTrue(isset($p['ele:min']));
            $this->WMAssertEqualsWithDelta($ele_min,$p['ele:min'],$delta_ele);

            $this->assertTrue(isset($p['ele:max']));
            $this->WMAssertEqualsWithDelta($ele_max,$p['ele:max'],$delta_ele);

            $this->assertTrue(isset($p['ascent']));
            $this->WMAssertEqualsWithDelta($ascent,$p['ascent'],$delta_asc);

            $this->assertTrue(isset($p['descent']));
            $this->WMAssertEqualsWithDelta($descent,$p['descent'],$delta_asc);
        }

        public function testAscDescOverride() {

            $input = 'Asciano.geojson';

            $delta_dist = 50;

            $pg=WebmappPostGis::Instance();
            $pg->clearTables('test');
            $j['id']=1;
            $j['title']['rendered']='testAscDesc';
            $j['distance']=10000;
            $t=new WebmappTrackFeature($j,true);

            // LOAD DATA
            $geojson = WebmappUtils::getJsonFromApi(dirname(__FILE__).'/fixtures/'.$input);
            $this->assertTrue(isset($geojson['features'][0]['geometry']));
            $t->setGeometryGeoJSON(json_encode($geojson['features'][0]['geometry']));
            $t->writeToPostGis('test');

            // PERFORM OPERATIONS
            $t->setComputedProperties2('test');

            // TEST(S)
            $j=json_decode($t->getJson(),true);
            $this->assertTrue(isset($j['properties']));
            $p=$j['properties'];
            $this->assertTrue(isset($p['computed']));
            $c=$p['computed'];

            $this->assertTrue(isset($c['distance']));
            $this->WMAssertEqualsWithDelta(5.8,$c['distance'],$delta_dist);

            $this->assertTrue(isset($p['distance']));
            $this->WMAssertEqualsWithDelta(10.0,$p['distance'],$delta_dist);

        }

        private function WMAssertEqualsWithDelta($expected, $actual, $delta) {
            $msg = "Expected: $expected Actual: $actual Delta: $delta";
            $this->assertTrue(abs($expected-$actual)<$delta,$msg);
        } 

        public function testGetRunningPoint() {
            // Prepare TEST
            $pg=WebmappPostGis::Instance();
            $pg->clearTables('test');
            $j['id']=1;
            $j['title']['rendered']='testAscDesc';
            $t=new WebmappTrackFeature($j,true);

            // LOAD DATA
            $geojson = WebmappUtils::getJsonFromApi(dirname(__FILE__).'/fixtures/AscianoMirtetoRagnaieAscdesc.geojson');
            $t->setGeometryGeoJSON(json_encode($geojson['features'][0]['geometry']));
            $t->writeToPostGis('test');

            // INIT
            $vals = $t->getRunningPoint(0,4326,'test');
            $this->assertEquals(10.4628896,$vals[0]);
            $this->assertEquals(43.747847100000001,$vals[1]);

            $vals = $t->getRunningPoint(0.5,4326,'test');
            $this->assertEquals(10.4774185437983,$vals[0]);
            $this->assertEquals(43.7540467918674,$vals[1]);

            $vals = $t->getRunningPoint(1,4326,'test');
            $this->assertEquals(10.46721,$vals[0]);
            $this->assertEquals(43.751759999999997,$vals[1]);

        }

        public function testHasGeometry() {
            $j['id']=1;
            $j['title']['rendered']='testHasGeometry';
            $t = new WebmappTrackFeature($j,true);
            $this->assertFalse($t->hasGeometry());

            $t1 = new WebmappTrackFeature('http://dev.be.webmapp.it/wp-json/wp/v2/track/927');
            $this->assertTrue($t1->hasGeometry());

        }

        public function testHas3D() {
            // CASO 1 no geometria
            $j['id']=1;
            $j['title']['rendered']='testHasGeometry';
            $t = new WebmappTrackFeature($j,true);
            $this->assertFalse($t->has3D());

            // CASO 2 geometria senza 3D
            $t = new WebmappTrackFeature('http://dev.be.webmapp.it/wp-json/wp/v2/track/927');
            $this->assertFalse($t->has3D());

            // CASO 3 calcolo il 3D
            $t->add3D();
            $this->assertTrue($t->has3D());

            // CASO 4 geometria con 3d
            $geojson = WebmappUtils::getJsonFromApi(dirname(__FILE__).'/fixtures/AscianoMirtetoRagnaieAscdesc.geojson');
            $this->assertTrue(isset($geojson['features'][0]['geometry']));
            $t->setGeometryGeoJSON(json_encode($geojson['features'][0]['geometry']));
            $this->assertTrue($t->has3D());
        }

        public function testGetFirst() {
            $t = new WebmappTrackFeature('http://dev.be.webmapp.it/wp-json/wp/v2/track/927');
            $first = $t->getFirst();
            $this->assertEquals(10.4016816,$first[0]);
            $this->assertEquals(43.7155037,$first[1]);
        }

        public function testOSM() {
            // Prepare TEST
            // Delta%
            $delta_dist = 0.1;
            $delta_ele = 10;
            $delta_asc = 40;

            $pg=WebmappPostGis::Instance();
            $pg->clearTables('test');
            $t = new WebmappTrackFeature('http://simap.be.webmapp.it/wp-json/wp/v2/track/1256');
            $t->writeToPostGis('test');
            $t->setComputedProperties2('test');
            // TEST(S)
            $j=json_decode($t->getJson(),true);
            $this->assertTrue(isset($j['properties']));
            $p=$j['properties'];
            $this->assertTrue(isset($p['computed']));
            $c=$p['computed'];

            $distance = 16.1;
            $ele_from = 1583;

            $this->assertTrue(isset($c['distance']));
            $this->WMAssertEqualsWithDelta($distance,$c['distance'],$delta_dist);

            $this->assertTrue(isset($c['ele:from']));
            $this->WMAssertEqualsWithDelta($ele_from,$c['ele:from'],$delta_ele);
        }

        public function testOSMColor() {
            // Prepare TEST
            // Delta%
            $t = new WebmappTrackFeature('http://simap.be.webmapp.it/wp-json/wp/v2/track/1256');
            // TEST(S)
            $j=json_decode($t->getJson(),true);
            $this->assertTrue(isset($j['properties']));
            $p=$j['properties'];
            $this->assertTrue(isset($p['color']));
            $this->assertEquals('#E35234',$p['color']);
        }

        public function testStartEndPoi() {
            $t = new WebmappTrackFeature('http://simap.be.webmapp.it/wp-json/wp/v2/track/1245');
            $j=json_decode($t->getJson(),true);

            // Properties
            $this->assertTrue(isset($j['properties']));
            $p=$j['properties'];

            // FROM_POI
            $this->assertTrue(isset($p['from_poi']));
            $this->assertEquals(4765,isset($p['from_poi']));

            // TO_POI
            $this->assertTrue(isset($p['to_poi']));
            $this->assertEquals(4251,isset($p['to_poi']));
        }

        public function testPrevNext() {

            $id =1420; $next=1421;
            $t = new WebmappTrackFeature('http://simap.be.webmapp.it/wp-json/wp/v2/track/'.$id);
            $j=json_decode($t->getJson(),true);
            $this->assertTrue(isset($j['properties'])); $p=$j['properties'];
            $this->assertTrue(isset($p['next_track']));
            $this->assertEquals($next,isset($p['next_track']));

            $id =1833; $next=3011; $prev=3010;
            $t = new WebmappTrackFeature('http://simap.be.webmapp.it/wp-json/wp/v2/track/'.$id);
            $j=json_decode($t->getJson(),true);
            $this->assertTrue(isset($j['properties'])); $p=$j['properties'];
            $this->assertTrue(isset($p['next_track']));
            $this->assertEquals($next,isset($p['next_track']));
            $this->assertTrue(isset($p['prev_track']));
            $this->assertEquals($prev,isset($p['prev_track']));

            $id =3011; $prev=3010;
            $t = new WebmappTrackFeature('http://simap.be.webmapp.it/wp-json/wp/v2/track/'.$id);
            $j=json_decode($t->getJson(),true);
            $this->assertEquals($next,isset($p['next_track']));
            $this->assertTrue(isset($p['prev_track']));
            $this->assertEquals($prev,isset($p['prev_track']));

        }

    public function testLineDash() {
        // Test sulla track relativa alla relation https://www.openstreetmap.org/relation/9351675
        // (SI V23B) Randazzo - Bivacco Forestale di Monte Scavo (9351675)
        // ID WP: 1728
        $id =1728;
        $t = new WebmappTrackFeature('http://simap.be.webmapp.it/wp-json/wp/v2/track/'.$id);
        $j=json_decode($t->getJson(),true);
        $this->assertTrue(isset($j['properties'])); $p=$j['properties'];
        $this->assertTrue(isset($p['lineDash']));
        $ld = $p['lineDash'];

        $this->assertEquals(2,count($ld));
        $this->assertEquals(12,$ld[0]);
        $this->assertEquals(8,$ld[1]);


    }
    public function testSurface() {
        $t = new WebmappTrackFeature('http://dev.be.webmapp.it/wp-json/wp/v2/track/882');
        $j=json_decode($t->getJson(),true);
        $this->assertTrue(isset($j['properties']));
        $p=$j['properties'];
        $this->assertTrue(isset($p['surface']));
        $s = $p['surface'];

        $this->assertEquals(3,count($s));
        $this->assertTrue(isset($s['asphalt']));
        $this->assertTrue(isset($s['unpaved']));
        $this->assertTrue(isset($s['paved']));

        $this->assertEquals(0.3,$s['asphalt']);
        $this->assertEquals(0.3,$s['unpaved']);
        $this->assertEquals(0.4,$s['paved']);

    }

}
