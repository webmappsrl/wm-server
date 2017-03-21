<?php
use PHPUnit\Framework\TestCase;

class WebmappProjectStructureTest extends TestCase
{

    public function testCheck() {
        // root esistente
        $root = __DIR__.'/../data/api.webmapp.it/example.webmapp.it/';
        $p = new WebmappProjectStructure($root);
        $this->assertTrue($p->check());

        // root Inesistente
        $root = __DIR__.'/../data/api.webmapp.it/NOROOT-XXX/';
        $p = new WebmappProjectStructure($root);
        $this->expectException(Exception::class);
        $p->check();

        // TODO: aggiungere le altre verifiche
    }


    // TODO: eccezione readConf json malformato
    // TODO: eccezione readConf json senza chiave tasks
    // TODO: eccezione readConf factorytask con un tipo non esistente
}