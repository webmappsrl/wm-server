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
    private $pathNoServer = __DIR__.'/../data/api.webmapp.it/example.noserver.webmapp.it/';
    private $pathInvalid = __DIR__.'/../data/api.webmapp.it/example.invalid.webmapp.it/';
    private $pathNotValid = '/invalid/path';

    public function testGetters() {
    	$p = new WebmappProject($this->pathOk);
    	$this->assertEquals('NONE',$p->getError());
    	$this->assertEquals($this->pathOk,$p->getPath());
    	$p->open();
    	// $this->assertEquals('NONE',$p->getError());
    	$this->assertEquals($this->pathOk.'server/',$p->getConfPath());
    	$this->assertEquals('example.webmapp.it',$p->getName());
    	$this->assertTrue(in_array('project.conf', $p->getConfFiles()));
    	$this->assertTrue(in_array('overpassNode.conf', $p->getConfFiles()));
    	$this->assertTrue(array_key_exists('overpassNode', $p->getTasks()));
    	$this->assertTrue(array_key_exists('overpassNode1', $p->getTasks()));
    }

    public function testError() {
    	// Invalid path
    	$p = new WebmappProject($this->pathNotValid);
    	$this->assertFalse($p->open());
    	$this->assertRegExp('/ERROR/',$p->getError());
    	$this->assertRegExp('/is not valid path/',$p->getError());

    	// No project.conf
    	$p = new WebmappProject($this->pathNoProject);
    	$this->assertFalse($p->open());
    	$this->assertRegExp('/ERROR/',$p->getError());
    	$this->assertRegExp('/has no project.conf file./',$p->getError());

    	// No server dir
    	$p = new WebmappProject($this->pathNoServer);
    	$this->assertFalse($p->open());
     	$this->assertRegExp('/ERROR/',$p->getError());
    	$this->assertRegExp('/has no subdir server with configuration files/',$p->getError());

        // Invalid task configuration file
        $p = new WebmappProject($this->pathInvalid);
        $this->assertFalse($p->open());
     	$this->assertRegExp('/ERROR/',$p->getError());
    	$this->assertRegExp('/reading configuration files/',$p->getError());

    }

}