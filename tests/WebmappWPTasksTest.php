<?php
use PHPUnit\Framework\TestCase;

class WebmappWPTasksTests extends TestCase {
    private $data=__DIR__.'/../data/tmp/';
    public function testCheck() {

        $p = $this->getStructure('dev');
        $this->assertTrue($p->check());

        $p = $this->getStructure('https://cosmopoli.travel');
        $this->assertTrue($p->check());

    }

    public function testProcess() {
        $p = $this->getStructure('dev');
        $this->assertTrue($p->check());
        $this->assertTrue($p->process());

        // Check POI Single file
        $path = $p->getStructure()->getRoot().'/geojson';
        $ids = array(800,786,753,751,752,750,609);
        foreach ($ids as $id) {
            $poi_file = $path.'/'.$id.'.geojson';
            $this->assertTrue(file_exists($poi_file));
        }
    }

    private function getStructure($url) {
        if(!file_exists($this->data)){
            echo "Creating dir $this->data\n";
            system("mkdir -p $this->data");
        }
        $root = $this->data.time();
        while (file_exists($root)) {
            $root = $this->data.time();
        }
        echo "Creating dir $root\n";

        $conf=$root.'/server/project.conf';
        echo "Creating file $conf\n";
        $task = array("type"=>"wp","url_or_code"=>"$url");
        $tasks = array("WP"=>$task);
        $options = array("tasks"=>$tasks);
        $s = new WebmappProjectStructure($root);
        $s->create();
        file_put_contents($conf,json_encode($options));

        echo "Creating project\n";
        return new WebmappProject($root);
    }
}
