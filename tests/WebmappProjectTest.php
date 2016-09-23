<?php
use PHPUnit\Framework\TestCase;

// URL UTILI DEL MANUALE DI PHPUNIT
// https://phpunit.de/manual/current/en/appendixes.assertions.html#appendixes.assertions.assertRegExp
// https://phpunit.de/manual/current/en/textui.html

class WebmappProjectTest extends TestCase
{
    private $pathOk = __DIR__.'/../data/api.webmapp.it/example.webmapp.it/';
    private $pathNoProject = __DIR__.'/../data/api.webmapp.it/example.noproject.webmapp.it/';
    private $pathOnlyProject = __DIR__.'/../data/api.webmapp.it/example.onlyproject.webmapp.it/';

    public function testGetters() {
    	$p = new WebmappProject($this->pathOk);
    	$this->assertEquals($this->pathOk,$p->getPath());
    	$this->assertEquals('example.webmapp.it',$p->getName());

    }

}