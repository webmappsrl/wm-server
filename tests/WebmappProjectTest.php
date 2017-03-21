<?php
use PHPUnit\Framework\TestCase;

class WebmappProjectTest extends TestCase
{

    public function testCheck() {
        // root esistente
        $root = __DIR__.'/../data/api.webmapp.it/example.webmapp.it/';
        $p = new WebmappProject($root);
        $this->assertTrue($p->check());

        // root inesistente
        $root = __DIR__.'/../data/api.webmapp.it/XXX-NO-ROOT-XXX/';
        $p = new WebmappProject($root);
        $this->assertFalse($p->check());
    }

}