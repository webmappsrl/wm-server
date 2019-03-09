<?php // WebmappWPTEst.php

use PHPUnit\Framework\TestCase;

class WebmappWPTest extends TestCase
{
	public function testOk() {
		$wp = new WebmappWP('test');
		$this->assertEquals('test',$wp->getCode());
		$this->assertEquals('http://test.be.webmapp.it',$wp->getBaseUrl());
		$this->assertEquals('http://test.be.webmapp.it/wp-json/wp/v2',$wp->getApiUrl());

		$this->assertEquals('http://test.be.webmapp.it/wp-json/wp/v2/poi',$wp->getApiPois());
		$this->assertEquals('http://test.be.webmapp.it/wp-json/wp/v2/track',$wp->getApiTracks());
		$this->assertEquals('http://test.be.webmapp.it/wp-json/wp/v2/route',$wp->getApiRoutes());
		$this->assertEquals('http://test.be.webmapp.it/wp-json/wp/v2/area',$wp->getApiAreas());
		$this->assertEquals('http://test.be.webmapp.it/wp-json/wp/v2/map',$wp->getApiMaps());
		$this->assertEquals('http://test.be.webmapp.it/wp-json/wp/v2/webmapp_category?per_page=100',$wp->getApiCategories());

		$this->assertEquals('http://test.be.webmapp.it/wp-json/wp/v2/poi?webmapp_category=1',$wp->getApiPois(1));
		$this->assertEquals('http://test.be.webmapp.it/wp-json/wp/v2/track?webmapp_category=1',$wp->getApiTracks(1));
		$this->assertEquals('http://test.be.webmapp.it/wp-json/wp/v2/route?webmapp_category=1',$wp->getApiRoutes(1));
		$this->assertEquals('http://test.be.webmapp.it/wp-json/wp/v2/area?webmapp_category=1',$wp->getApiAreas(1));

		$this->assertEquals('http://test.be.webmapp.it/wp-json/wp/v2/poi/10',$wp->getApiPoi(10));
		$this->assertEquals('http://test.be.webmapp.it/wp-json/wp/v2/track/10',$wp->getApiTrack(10));
		$this->assertEquals('http://test.be.webmapp.it/wp-json/wp/v2/route/10',$wp->getApiRoute(10));
		$this->assertEquals('http://test.be.webmapp.it/wp-json/wp/v2/area/10',$wp->getApiArea(10));
		$this->assertEquals('http://test.be.webmapp.it/wp-json/wp/v2/map/10',$wp->getApiMap(10));
		$this->assertEquals('http://test.be.webmapp.it/wp-json/wp/v2/webmapp_category/10',$wp->getApiCategory(10));

		// HTTP
		$wp = new WebmappWP('http://www.external.it/');
		$this->assertEquals('http://www.external.it/',$wp->getCode());
		$this->assertEquals('http://www.external.it/',$wp->getBaseUrl());

		// HTTPS
		$wp = new WebmappWP('https://www.external.it/');
		$this->assertEquals('https://www.external.it/',$wp->getCode());
		$this->assertEquals('https://www.external.it/',$wp->getBaseUrl());


	}

	public function testGettersAndSetters() {
		$wp = new WebmappWP('test');
		$this->assertEquals(100,$wp->getPerPage());
		$wp->setPerPage(5);
		$this->assertEquals(5,$wp->getPerPage());
	}

	public function testCheck() {
		$wp = new WebmappWP('notvalid');
		$this->assertFalse($wp->check());

		$wp = new WebmappWP('dev');
		$this->assertTrue($wp->check());
		$this->assertTrue($wp->checkPoi(567));
		$this->assertTrue($wp->checkTrack(580));
		$this->assertTrue($wp->checkRoute(346));
		// TODO: test area $this->assertTrue($wp->checkArea());
		$this->assertTrue($wp->checkMap(414));
		$this->assertTrue($wp->checkCategory(14));

	}

	public function testGetCategoriesArray() {
		$wp = new WebmappWP('dev');
		$cats = $wp -> getCategoriesArray();
		$this->assertTrue(in_array(14, $cats)); 
		$this->assertTrue(in_array(30, $cats)); 
		$this->assertTrue(in_array(11, $cats)); 
		$this->assertTrue(in_array(12, $cats)); 
		$this->assertTrue(in_array(13, $cats)); 
		$this->assertTrue(in_array(10, $cats)); 
		$this->assertTrue(in_array(4, $cats)); 
	}

	public function testGetPoiLayers() {
		$wp = new WebmappWP('dev');
		$wp->setPerPage(2);
		$layers = $wp->getPoiLayers();
		// print_r($layers);die();
		$this->assertTrue(is_array($layers));
		foreach ($layers as $layer) {
			$this->assertEquals('WebmappLayer',get_class($layer));
			$features = $layer -> getFeatures();
			$this->assertTrue(count($features)>0);
			foreach ($features as $feature) {
				$this->assertEquals('WebmappPoiFeature',get_class($feature));
			}
		}
	}

	public function testGetTrackLayers() {
		$wp = new WebmappWP('dev');
		$wp->setPerPage(2);
		$layers = $wp->getTrackLayers();
		// print_r($layers);die();
		$this->assertTrue(is_array($layers));
		foreach ($layers as $layer) {
			$this->assertEquals('WebmappLayer',get_class($layer));
			$features = $layer -> getFeatures();
			$this->assertTrue(count($features)>0);
			foreach ($features as $feature) {
				$this->assertEquals('WebmappTrackFeature',get_class($feature));
			}
		}
	}

	public function testExternalAPI() {
		// Usiamo montepisanotree
		$wp = new WebmappWP('http://www.montepisanotree.org');
		$this->assertEquals($wp->getBaseUrl(),'http://www.montepisanotree.org');
		//$this->assertTrue($wp->checkMap(954));

	}

	// public function testgetImageLayer() {
	// 	$wp = new WebmappWP('dev');
	// 	$l = $wp->getImageLayer();
	// 	$path=__DIR__.'/../data';
	// 	$l->write($path);

	// 	$o=$path.'/image.geojson';
	// 	$j=WebmappUtils::getJsonFromApi($o);
	// 	$this->assertTrue(is_array($j));
	// 	$this->assertTrue(isset($j['type']));
	// 	$this->assertEquals('FeatureCollection',$j['type']);

	// 	// TODO: decommentare dopo implementazione
	// 	// $this->assertTrue(isset($j['features']));

	// }

	public function testGetAllRoutesLayer() {
		$wp = new WebmappWp('dev');
		$l = $wp->getAllRoutesLayer('/tmp');
		$this->assertTrue(count($l->getFeatures())>0);
		foreach($l->getFeatures() as $route) {
			$this->assertEquals('WebmappRoute',get_class($route));
		}
	}

	public function testLoadTaxonomies() {
		$wp = new WebmappWP('dev');
		$wp->loadTaxonomies();
		$tax=$wp->getTaxonomies();

		$this->assertTrue(is_array($tax));

		$this->assertTrue(is_array($tax['webmapp_category']));
		$this->assertTrue(is_array($tax['activity']));
		$this->assertTrue(is_array($tax['theme']));
		$this->assertTrue(is_array($tax['who']));
		$this->assertTrue(is_array($tax['when']));
		$this->assertTrue(is_array($tax['where']));

		$this->assertTrue(count($tax['webmapp_category'])>0);
		$this->assertTrue(count($tax['activity'])>0);
		$this->assertTrue(count($tax['theme'])>0);
		$this->assertTrue(count($tax['who'])>0);
		$this->assertTrue(count($tax['where'])>0);
		$this->assertTrue(count($tax['when'])>0);

		// Check a caso
		$this->assertEquals('#dd3333',$tax['webmapp_category'][14]['color']);
		$this->assertEquals('#262163',$tax['activity'][40]['color']);
		$this->assertEquals('#d63767',$tax['theme'][45]['color']);
		$this->assertEquals('#e03599',$tax['who'][44]['color']);
		$this->assertEquals('#5b55d1',$tax['where'][46]['color']);
		$this->assertEquals('#997a00',$tax['when'][43]['color']);

	}

	public function testWriteTaxonomies() {
		$taxs = array('webmapp_category','theme','activity','who','where','when');
		// CLEAN
		foreach($taxs as $tax) {
			$file = '/tmp/'.$tax.'.json';
			$cmd = "rm -f $file";
			system($cmd);
		}
		$wp = new WebmappWP('dev');
		$wp->loadTaxonomies();
		$wp->writeTaxonomies('/tmp');

		foreach ($taxs as $tax) {
			$file = '/tmp/'.$tax.'.json';
			$this->assertTrue(file_exists($file));
		}		
	}

	public function testPoisInTaxonomy() {
		$wp = new WebmappWP('dev');
		$wp->loadTaxonomies();
		$l=$wp->getAllPoisLayer('all_poi');
		$t=$wp->getTaxonomies();

		// WEBMAPP CATEGORY
		$this->assertTrue(isset($t['webmapp_category'][35]));
		$t_35 = $t['webmapp_category'][35];
		$this->assertTrue(isset($t_35['items']['poi']));
		$this->assertTrue(count($t_35['items']['poi'])>0);
		$this->assertTrue(in_array(800, $t_35['items']['poi']));

	}

	public function testTracksInTaxonomy() {
		$wp = new WebmappWP('dev');
		$wp->loadTaxonomies();
		$l=$wp->getAllTracksLayer('all_track');
		$t=$wp->getTaxonomies();

		// ACTIVITY
		$this->assertTrue(isset($t['activity'][40]));
		$t_40 = $t['activity'][40];
		$this->assertTrue(isset($t_40['items']['track']));
		$this->assertTrue(count($t_40['items']['track'])>0);
		$this->assertTrue(in_array(882, $t_40['items']['track']));
	}

	public function testRoutesInTaxonomy() {
		$wp = new WebmappWP('dev');
		$wp->loadTaxonomies();
		$l=$wp->getAllRoutesLayer('all_route');
		$t=$wp->getTaxonomies();

		// ACTIVITY
		$this->assertTrue(isset($t['activity'][40]));
		$t_40 = $t['activity'][40];
		$this->assertTrue(isset($t_40['items']['route']));
		$this->assertTrue(count($t_40['items']['route'])>0);
		$this->assertTrue(in_array(346, $t_40['items']['route']));

		// THEME
		$this->assertTrue(isset($t['theme'][41]));
		$t_41 = $t['theme'][41];
		$this->assertTrue(isset($t_41['items']['route']));
		$this->assertTrue(count($t_41['items']['route'])>0);
		$this->assertTrue(in_array(917, $t_41['items']['route']));


	}
}