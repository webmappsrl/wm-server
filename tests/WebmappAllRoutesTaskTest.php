<?php
use PHPUnit\Framework\TestCase;

class WebmappAllRoutesTaskTests extends TestCase
{

    private $data=__DIR__.'/../data/tmp/';



    public function testNoUrlOrCode() {
        $p = $this->getBadStructure('dev');
        $this->expectException(WebmappExceptionConfTask::class);
        $p->check();

    }

    public function testNoSpecificDev() {
        $p = $this->getStructure('devX');
        $this->expectException(WebmappExceptionAllRoutesTaskNoEndpoint::class);
        $p->check();

    }

    public function testCheck() {
         $p = $this->getStructure('dev');
         $this->assertTrue($p->check());
    }
    // public function testProcess() {    }

    // LASCIARE X ULTIMA
    public function testNoEndpoint() {
        global $wm_config;
        if(isset($wm_config['endpoint']['a'])) {
            unset($wm_config['endpoint']);
        }

        $p = $this->getStructure('dev');
        $this->expectException(WebmappExceptionConfEndpoint::class);
        $p->check();

    }
    ////////////////////////////////////////////

    private function getStructure($url) {
        if(!file_exists($this->data)){
            echo "Creating dir $this->data\n";
            system("mkdir -p $this->data");
        }
        $root = $this->data.time();
        while (file_exists($root)) {
            $root = $this->data.time();
        }
        echo "\n\nCreating dir $root\n";

        $conf=$root.'/server/project.conf';
        echo "Creating file $conf\n";
        $task = array("type"=>"allroutes","url_or_code"=>"$url");
        $tasks = array("TMPALLROUTES"=>$task);
        $options = array("tasks"=>$tasks);
        $s = new WebmappProjectStructure($root);
        $s->create();
        file_put_contents($conf,json_encode($options));

        echo "Creating project\n";
        return new WebmappProject($root);
    }

    private function getBadStructure($url) {
        if(!file_exists($this->data)){
            echo "Creating dir $this->data\n";
            system("mkdir -p $this->data");
        }
        $root = $this->data.time();
        while (file_exists($root)) {
            $root = $this->data.time();
        }
        echo "\n\nCreating dir $root\n";

        $conf=$root.'/server/project.conf';
        echo "Creating file $conf\n";
        $task = array("type"=>"allroutes");
        $tasks = array("TMPALLROUTES"=>$task);
        $options = array("tasks"=>$tasks);
        $s = new WebmappProjectStructure($root);
        $s->create();
        file_put_contents($conf,json_encode($options));

        echo "Creating project\n";
        return new WebmappProject($root);
    }

}