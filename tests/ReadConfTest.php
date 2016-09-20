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

    public function testConfFile()
    {
        // File VALIDO
        $a = new ReadConf($this->testSimpleJson);
        $this->assertEquals($a->getConfFile(), $this->testSimpleJson);
        $this->assertEquals($a->getError(),'NONE');
        $this->assertTrue($a->check());
        $this->assertEquals($a->getError(),'NONE');
        $simpleJson=array("simple"=>"json");
        $this->assertEquals($a->getJson(),$simpleJson);       
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