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
    public function testProcess() {    
         $p = $this->getStructure('dev');
         $p->check();
         $p->process();

         // Altri controlli dopo il Process
         global $wm_config;
         $endpoint = $wm_config['endpoint']['a'].'/dev.be.webmapp.it';
         $root=$p->getRoot();

         // 1. Creare i link simbolici alla directory geojson
         $this->assertTrue(file_exists($root.'/geojson'));
         $this->assertTrue(is_link($root.'/geojson'));
         $this->assertEquals($endpoint.'/geojson',readlink($root.'/geojson'));


         // 2. Pulire le tassonomie della parte comune iniziale /taxonomies/* 
         // rimuovendo la sezione items relativa a POI e TRACK
         $webmapp_category = $root.'/taxonomies/webmapp_category.json';
         $this->assertTrue(file_exists($webmapp_category));
         $ja = json_decode(file_get_contents($webmapp_category),TRUE);
         $this->assertTrue(isset($ja['34']));
         $this->assertTrue(!isset($ja['34']['items']));

         $activity = $root.'/taxonomies/activity.json';
         $this->assertTrue(file_exists($activity));
         $ja = json_decode(file_get_contents($activity),TRUE);
         $this->assertTrue(!isset($ja['40']['items']['track']));
         $this->assertTrue(isset($ja['40']['items']['route']));
         $this->assertTrue(in_array(772, $ja['40']['items']['route']));
         $this->assertTrue(in_array(686, $ja['40']['items']['route']));
         $this->assertTrue(in_array(346, $ja['40']['items']['route']));

         $theme = $root.'/taxonomies/theme.json';
         $this->assertTrue(file_exists($theme));
         $ja = json_decode(file_get_contents($theme),TRUE);
         $this->assertTrue(!isset($ja['41']['items']['track']));
         $this->assertTrue(isset($ja['41']['items']['route']));
         $this->assertTrue(in_array(917, $ja['41']['items']['route']));
         $this->assertTrue(in_array(772, $ja['41']['items']['route']));
         $this->assertTrue(in_array(686, $ja['41']['items']['route']));


         // 3. Creare le directory routes/[route_id]
         $r_ids = array(772,686,346);
         foreach ($r_ids as $rid) {
             $this->assertTrue(file_exists($root.'/routes/'.$rid));
             $this->assertTrue(file_exists($root.'/routes/'.$rid.'/taxonomies'));
         }

         // 4. Creazione del file di tassonomia 
         // /routes/[route_id]/taxonomies/activity.json 
         // deve avere solo la sezione "term_id":"items":"track" 
         // con la lista di tutte le TRACK di quel termine

         // TEST: route_id 346
         // Track_id: 348 activity: 47,40
         $this->assertTrue(file_exists($root.'/routes/346/taxonomies/activity.json'));
         $ja=json_decode(file_get_contents($root.'/routes/346/taxonomies/activity.json'),TRUE);
         $this->assertTrue(isset($ja[47]));
         $this->assertTrue(isset($ja[40]));
         $this->assertTrue(isset($ja[47]['items']));
         $this->assertTrue(isset($ja[40]['items']));
         $this->assertTrue(isset($ja[47]['items']['track']));
         $this->assertTrue(isset($ja[40]['items']['track']));
         $this->assertTrue(in_array(348,$ja[47]['items']['track']));
         $this->assertTrue(in_array(348,$ja[40]['items']['track']));


         // 5. Creazione del file di tassonomia 
         // /routes/[route_id]/taxonomies/webmapp_category.json 
         // deve avere solo la sezione "term_id":"items":"poi" 
         // con la lista di tutti i POI di quel termine

         // TEST: route_id: 346
         // RELATED POI nella related track 348: 443(7),456(7),513(30),514(30),786(34),800(35)
         $this->assertTrue(file_exists($root.'/routes/346/taxonomies/webmapp_category.json'));
         $ja=json_decode(file_get_contents($root.'/routes/346/taxonomies/webmapp_category.json'),TRUE);
         $this->assertTrue(isset($ja[7]));
         $this->assertTrue(isset($ja[30]));
         $this->assertTrue(isset($ja[34]));
         $this->assertTrue(isset($ja[35]));

         $this->assertTrue(isset($ja[7]['items']));
         $this->assertTrue(isset($ja[30]['items']));
         $this->assertTrue(isset($ja[34]['items']));
         $this->assertTrue(isset($ja[35]['items']));

         $this->assertTrue(isset($ja[7]['items']['poi']));
         $this->assertTrue(isset($ja[30]['items']['poi']));
         $this->assertTrue(isset($ja[34]['items']['poi']));
         $this->assertTrue(isset($ja[35]['items']['poi']));

         $this->assertTrue(in_array(443,$ja[7]['items']['poi']));
         $this->assertTrue(in_array(456,$ja[7]['items']['poi']));
         $this->assertTrue(in_array(513,$ja[30]['items']['poi']));
         $this->assertTrue(in_array(514,$ja[30]['items']['poi']));
         $this->assertTrue(in_array(786,$ja[34]['items']['poi']));
         $this->assertTrue(in_array(800,$ja[35]['items']['poi']));


    }

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