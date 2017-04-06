<?php
use PHPUnit\Framework\TestCase;

class WebmappTaskFactoryTest extends TestCase
{
    private $name;
    private $options;
    private $project_structure;

    public function __construct() {
        // Init members
        $this->name = 'TaskName';
        $this->options = array ('type'=>'BE');
        $this->project_structure = new WebmappProjectStructure(__DIR__.'/../data/api.webmapp.it/example.webmapp.it/');
    }

    // All parameter ok: check type of Task (WebmappBETask)
    public function testOk() {
        $t = WebmappTaskFactory::Task($this->name,$this->options,$this->project_structure);
        $this->assertEquals('WebmappBETask',get_class($t));
    }

    // Test Eccezioni
    public function testKoOptionsNoArray() {
        $options = 'NoArray';
        $this->expectException(Exception::class);
        $t = WebmappTaskFactory::Task($this->name,$options,$this->project_structure);
    }

    public function testKoNotype() {
        $options = array('notype'=>'XXX');
        $this->expectException(Exception::class);
        $t = WebmappTaskFactory::Task($this->name,$options,$this->project_structure);
    }

    public function testKoWrongType() {
        $options = array('type'=>'wrongType');
        $this->expectException(Exception::class);
        $t = WebmappTaskFactory::Task($this->name,$options,$this->project_structure);
    }

    public function testKoNoProjectStructure() {
        $project_structure = new stdClass();
        $this->expectException(Exception::class);
        $t = WebmappTaskFactory::Task($this->name,$this->options,$project_structure);
    }


}