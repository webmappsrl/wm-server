<?php
use PHPUnit\Framework\TestCase;

class WebmappProjectTest extends TestCase
{

    public function testCheck() {
        $root = __DIR__.'/../data/api.webmapp.it/example.webmapp.it/';
        $p = new WebmappProject($root);
        $this->assertTrue($p->check());
    }

    public function testCreate() {
        $root = __DIR__.'/../data/api.webmapp.it/example.webmapp.it/';
        $p = new WebmappProject($root);
        $this->assertTrue($p->create());
    }

    public function testProcess() {
        $root = __DIR__.'/../data/api.webmapp.it/example.webmapp.it/';
        $p = new WebmappProject($root);
        $this->assertTrue($p->process());
    }


}