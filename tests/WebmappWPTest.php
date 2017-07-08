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
		$this->assertEquals('http://test.be.webmapp.it/wp-json/wp/v2/webmapp_category',$wp->getApiCategories());

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


}