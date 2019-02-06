<?php 
use PHPUnit\Framework\TestCase;
class WebmappLogTest extends TestCase {

	private $index_file;
	private $log_file;

	public function __construct() {
		// INIT create dir or empty
		global $wm_config;
		$path = $wm_config['log']['path'];
        if (file_exists($path)) {
        	$cmd = "rm -Rf $path/*";
        }
        else {
        	$cmd = "mkdir $path";
        }
        system($cmd);
        $d = $path.'/'.date('Ymd');
        $this->index_file="$d/index.json";
        $this->log_file="$d/00001.log";
	}

	public function testSingleton() {
		$log1 = WebmappLog::Instance();
		$id1 = $log1->getId();
		$this->assertEquals(1,$id1);
		$log2 = WebmappLog::Instance();
		$id2 = $log2->getId();
		$this->assertEquals($id1,$id2);
	}

	public function testOpen() {
		$l = WebmappLog::Instance();
		$l->open("test open");
		// Test vari
		$this->assertTrue(file_exists($this->index_file));
		$this->assertTrue(file_exists($this->log_file));
		$i = json_decode(file_get_contents($this->index_file),TRUE);
		$this->assertTrue(count($i)==1);
		$this->assertEquals($i[1]['desc'],'test open');
		$this->assertEquals($i[1]['stop'],'ND');

		$log_content = file_get_contents($this->log_file);
		$this->assertEquals(1,preg_match('/test open/',$log_content));
		$this->assertEquals(1,preg_match('/LOG START/',$log_content));
	}

	public function testLog() {
		$l = WebmappLog::Instance();
		$l->open("test open");
		for ($i=0; $i < 100 ; $i++) { 
			$l->log("Message $i");
		}
		$this->assertTrue(file_exists($this->index_file));
		$this->assertTrue(file_exists($this->log_file));
		$i = json_decode(file_get_contents($this->index_file),TRUE);
		$this->assertTrue(count($i)==1);
		$this->assertEquals($i[1]['desc'],'test open');
		$this->assertEquals($i[1]['stop'],'ND');

		$log_content = file_get_contents($this->log_file);
		$this->assertEquals(1,preg_match('/test open/',$log_content));
		$this->assertEquals(1,preg_match('/LOG START/',$log_content));
		$this->assertEquals(1,preg_match('/Message 0/',$log_content));
		$this->assertEquals(1,preg_match('/Message 1/',$log_content));
		$this->assertEquals(1,preg_match('/Message 98/',$log_content));
		$this->assertEquals(1,preg_match('/Message 99/',$log_content));

	}

	public function testClose() {
		$l = WebmappLog::Instance();
		$l->open("test open");
		for ($i=0; $i < 100 ; $i++) { 
			$l->log("Message $i");
		}
		$l->close();

		$this->assertTrue(file_exists($this->index_file));
		$this->assertTrue(file_exists($this->log_file));
		$i = json_decode(file_get_contents($this->index_file),TRUE);
		$this->assertTrue(count($i)==1);
		$this->assertEquals($i[1]['desc'],'test open');

		$log_content = file_get_contents($this->log_file);
		$this->assertEquals(1,preg_match('/test open/',$log_content));
		$this->assertEquals(1,preg_match('/LOG START/',$log_content));
		$this->assertEquals(1,preg_match('/Message 0/',$log_content));
		$this->assertEquals(1,preg_match('/Message 1/',$log_content));
		$this->assertEquals(1,preg_match('/Message 98/',$log_content));
		$this->assertEquals(1,preg_match('/Message 99/',$log_content));
		$this->assertEquals(1,preg_match('/LOG STOP/',$log_content));
	}

}
