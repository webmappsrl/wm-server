<?php
use PHPUnit\Framework\TestCase;

// URL UTILI DEL MANUALE DI PHPUNIT
// https://phpunit.de/manual/current/en/appendixes.assertions.html#appendixes.assertions.assertRegExp
// https://phpunit.de/manual/current/en/textui.html

class ReadConfTest extends TestCase
{
    private $testFileOk = __DIR__.'/../data/overpassPoi.example.conf';
    private $testSimpleJson = __DIR__.'/../data/simpleJson.conf';
    private $testFileKo = __DIR__.'/../data/not_existing.conf';
    private $testFileInvalidJson = __DIR__.'/../data/invalidJson.conf';

    public function testOk() {
        $a = new ReadConf($this->testFileOk);
        $this->assertEquals($a->getConfFile(), $this->testFileOk);
        $this->assertEquals($a->getError(),'NONE');
        $result = $a->check();
        $this->assertEquals($a->getError(),'NONE');
        $this->assertTrue($result);
    }

    public function testJson()
    {
        $a = new ReadConf($this->testSimpleJson);
        $this->assertEquals($a->getConfFile(), $this->testSimpleJson);
        $this->assertEquals($a->getError(),'NONE');
        $this->assertFalse($a->check());
        $this->assertRegExp('/Mandatory/',$a->getError());
        $simpleJson=array("simple"=>"json");
        $this->assertEquals($a->getJson(),$simpleJson);       
    }

    public function testMandatoryInfo() {
        // File VALIDO
        $a = new ReadConf($this->testSimpleJson);
        $this->assertEquals($a->getConfFile(), $this->testSimpleJson);
        $this->assertFalse($a->check());
        $this->assertRegExp('/project_name/',$a->getError());
        $this->assertRegExp('/file_name/',$a->getError());
        $this->assertRegExp('/file_type/',$a->getError());
        $this->assertRegExp('/bounds/',$a->getError());

    }

    public function testNotExistingFile () {
        // File inesistente
        $b = new ReadConf($this->testFileKo);
        $this->assertFalse($b->check());
        $this->assertRegExp('/file does not exist/',$b->getError());        
    }

    public function testNotValidJson () {
        // JSON non valido
        $c = new ReadConf($this->testFileInvalidJson);
        $this->assertFalse($c->check());
        $this->assertRegExp('/json is not valid/',$c->getError());        

    }

}