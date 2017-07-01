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
		$this->assertEquals('http://test.be.webmapp.it/wp-json/wp/v2/route',$wp->getApiRoutes());
		$this->assertEquals('http://test.be.webmapp.it/wp-json/wp/v2/area',$wp->getApiAreas());
		$this->assertEquals('http://test.be.webmapp.it/wp-json/wp/v2/map',$wp->getApiMaps());
		$this->assertEquals('http://test.be.webmapp.it/wp-json/wp/v2/webmapp_category',$wp->getApiCategories());

		$this->assertEquals('http://test.be.webmapp.it/wp-json/wp/v2/poi/10',$wp->getApiPoi(10));
		$this->assertEquals('http://test.be.webmapp.it/wp-json/wp/v2/route/10',$wp->getApiRoute(10));
		$this->assertEquals('http://test.be.webmapp.it/wp-json/wp/v2/area/10',$wp->getApiArea(10));
		$this->assertEquals('http://test.be.webmapp.it/wp-json/wp/v2/map/10',$wp->getApiMap(10));
		$this->assertEquals('http://test.be.webmapp.it/wp-json/wp/v2/webmapp_category/10',$wp->getApiCategory(10));

	}
}