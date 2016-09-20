<?php
use PHPUnit\Framework\TestCase;

class ReadConfTest extends TestCase
{
    private $testFileOk = __DIR__.'../data/overpassPoi.example.conf';
    private $testFileKo = __DIR__.'../data/not_existing.conf';
    private $testFileInvalidJson = __DIR__.'../data/not_existing.conf';

    public function testConfFile()
    {
        // Getter
        $a = new ReadConf($this->testFileOk);
        $this->assertEquals($a->getConfFile(), $this->testFileOk);
        $this->assertEquals($a->getError(),'NONE');

        // Configurazione VALIDO
        //$this->assertTrue($a->check());

        // File inesistente
        $b = new ReadConf($this->testFileKo);
        $this->assertFalse($b->check());

        // JSON non valido
    }

}