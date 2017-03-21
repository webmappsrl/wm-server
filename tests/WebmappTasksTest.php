<?php
use PHPUnit\Framework\TestCase;

class WebmappTasksTests extends TestCase
{
    // OSMLIST
    public function testOSMListTask() {
        $name = 'prova';
        $root = __DIR__.'/../data/api.webmapp.it/example.webmapp.it/';
        $path_osm = $root.'/server/osm';

        //TEST OK
        // Svuota la directory /server/osm se esiste
        if(file_exists($path_osm)) {
            $files = glob($path_osm);
            foreach ($files as $file) {
                if(is_file($file)) unlink($file);
            }
        }
        $options = array('list'=>'/server/list');
        $t = new WebmappOSMListTask($name,$options,$root);
        $this->assertTrue($t->check());
        $this->assertTrue($t->process());
        $this->assertTrue(file_exists($path_osm.'/353049774.node.osm'));
        $this->assertTrue(file_exists($path_osm.'/284449666.way.osm'));
        $this->assertTrue(file_exists($path_osm.'/4174475.relation.osm'));
        // TODO: Controlla il contenuto dei file XML (Attenzione che potrebbe cambiare su OSM e quindi non essere più valido)
        
        
        // ECCEZIONI
        // No list nell'array
        $options = array('nolist'=>'/server/list');
        $t = new WebmappOSMListTask($name,$options,$root);
        $this->expectException(Exception::class);
        $t->check();

        // No list file
        $options = array('list'=>'/server/nolist');
        $t = new WebmappOSMListTask($name,$options,$root);
        $this->expectException(Exception::class);
        $t->check();
    }
    public function testBETask() {
        $name = 'prova';
        $root = __DIR__.'/../data/api.webmapp.it/example.webmapp.it/';

        //TEST OK
        $options = array('code'=>'dev');
        $t = new WebmappBETask($name,$options,$root);
        $this->assertTrue($t->check());
        
        // ECCEZIONI
        // No code nell'array
        $options = array('nocode'=>'dev');
        $t = new WebmappBETask($name,$options,$root);
        $this->expectException(Exception::class);
        $t->check();

    }
}