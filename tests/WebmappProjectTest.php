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
        $this->expectException(Exception::class);
        $p->check();
    }

    public function testProcess() {
        // root esistente
        $root = __DIR__.'/../data/api.webmapp.it/example.webmapp.it/';
        $p = new WebmappProject($root);
        $this->assertTrue($p->check());
        $this->assertTrue($p->process());

        
    }

    public function testReteMontePisano() {
        $root = __DIR__.'/../data/retemontepisano.j.webmapp.it/';
        $conf = $root.'config.json';
        system("rm -f $conf");
        $p = new WebmappProject($root);
        $this->assertTrue($p->check());
        $this->assertTrue($p->process());
        $this->assertTrue(file_exists($conf));
        $ja = json_decode(file_get_contents($conf),TRUE);
        $this->assertEquals('Aree protette e Natura',$ja['OVERLAY_LAYERS'][0]['label']);
        $this->assertEquals('Borghi e Paesi',$ja['OVERLAY_LAYERS'][1]['label']);
    }
}