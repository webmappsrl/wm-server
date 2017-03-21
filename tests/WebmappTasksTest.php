<?php
use PHPUnit\Framework\TestCase;

class WebmappTasksTests extends TestCase
{
    // OSMLIST
    public function testOSMListTask() {
        $name = 'prova';
    	$root = __DIR__.'/../data/api.webmapp.it/example.webmapp.it/';

    	//TEST OK
    	$options = array('list'=>'/server/list');
    	$t = new WebmappOSMListTask($name,$options,$root);
    	$this->assertTrue($t->check());
        
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
}