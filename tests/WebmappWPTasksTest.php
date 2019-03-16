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
        $p->getStructure()->setInstanceId('http://dev.be.webmapp.it');
        $this->assertTrue($p->check());
        $this->assertTrue($p->process());

        $path = $p->getStructure()->getRoot().'/geojson';

        // Check POI Single file
        $ids = array(800,786,753,751,752,750,609);
        foreach ($ids as $id) {
            $poi_file = $path.'/'.$id.'.geojson';
            $this->assertTrue(file_exists($poi_file));
        }

        // Open first and check geometry
        $poi = json_decode(file_get_contents($path.'/800.geojson'),TRUE);
        $this->assertTrue(isset($poi['type']));
        $this->assertTrue(isset($poi['properties']));
        $this->assertTrue(isset($poi['geometry']));
        $this->assertTrue(isset($poi['geometry']['type']));
        $this->assertEquals('Point',$poi['geometry']['type']);

        // CHECK TRACK Single file
        $ids = array(835,769,711,688,683,580);
        foreach ($ids as $id) {
            $poi_file = $path.'/'.$id.'.geojson';
            $this->assertTrue(file_exists($poi_file));
        }

        // Open first and check geometry
        $track = json_decode(file_get_contents($path.'/835.geojson'),TRUE);
        $this->assertTrue(isset($track['type']));
        $this->assertTrue(isset($track['properties']));
        $this->assertTrue(isset($track['geometry']));
        $this->assertTrue(isset($track['geometry']['type']));
        $this->assertEquals('LineString',$track['geometry']['type']);

        // TODO: riaccendere CHeck nieghbours
        // $poi = json_decode(file_get_contents($path.'/426.geojson'),TRUE);
        // $this->assertTrue(count($poi['properties']['related']['poi']['neighbors'])>0);
        // $track = json_decode(file_get_contents($path.'/348.geojson'),TRUE);
        // $this->assertTrue(count($track['properties']['related']['poi']['neighbors'])>0);

        // Check icon and color
        // POI 546 non ha icon e color -> prende quelli di BAR color=#00ff00 icon=wm-icon-siti-interesse        
        $item=json_decode(file_get_contents($path.'/546.geojson'),TRUE);
        $this->assertTrue(isset($item['properties']['color']));
        $this->assertTrue(isset($item['properties']['icon']));
        $this->assertEquals('#00ff00',$item['properties']['color']);
        $this->assertEquals('wm-icon-siti-interesse',$item['properties']['icon']);

        // POI 540 pur essendo bar ha i propri => color=#0000ff  icon=wm-icon-mappalo
        $item=json_decode(file_get_contents($path.'/540.geojson'),TRUE);
        $this->assertTrue(isset($item['properties']['color']));
        $this->assertTrue(isset($item['properties']['icon']));
        $this->assertEquals('#0000ff',$item['properties']['color']);
        $this->assertEquals('wm-icon-mappalo',$item['properties']['icon']);

        // TRACK 580 color=#81d742
        $item=json_decode(file_get_contents($path.'/580.geojson'),TRUE);
        $this->assertTrue(isset($item['properties']['color']));
        $this->assertEquals('#81d742',$item['properties']['color']);

        // TRACK 580's relted POI: geojson/580_poi_related.geojson
        $this->assertTrue(file_exists($path.'/580_poi_related.geojson'));

        // TRACK 711 color=#262163
        $item=json_decode(file_get_contents($path.'/711.geojson'),TRUE);
        $this->assertTrue(isset($item['properties']['color']));
        $this->assertEquals('#262163',$item['properties']['color']);
        $this->assertTrue(isset($item['properties']['bbox']));
        $this->assertTrue(isset($item['properties']['bbox_metric']));

        $taxs = array('webmapp_category','theme','activity','who','where','when');
        $tax_path = $p->getStructure()->getRoot().'/taxonomies';
        foreach ($taxs as $tax) {
            $file = $tax_path.'/'.$tax.'.json';
            $this->assertTrue(file_exists($file));
        }

        // ROUTES 772 686 346
        $this->assertTrue(file_exists($path.'/772.geojson'));     
        $this->assertTrue(file_exists($path.'/686.geojson'));     
        $this->assertTrue(file_exists($path.'/346.geojson')); 

        $item=json_decode(file_get_contents($path.'/346.geojson'),TRUE);
        $this->assertEquals('FeatureCollection',$item['type']);
        $this->assertTrue(is_array($item['properties']['related']['track']['related']));
        $related_track = $item['properties']['related']['track']['related'];
        $this->assertTrue(in_array(348, $related_track));
        $this->assertTrue(in_array(576, $related_track));
        $this->assertTrue(is_array($item['features']));
        foreach ($item['features'] as $track) {
            $this->assertEquals('Feature',$track['type']);
            $this->assertTrue(isset($track['properties']));
            $this->assertTrue(isset($track['geometry']));
        }
        $this->assertTrue(isset($item['properties']['bbox']));
        $this->assertTrue(isset($item['properties']['bbox_metric']));


        // Route index
        $this->assertTrue(file_exists($path.'/route_index.geojson'));
        $j=WebmappUtils::getJsonFromApi($path.'/route_index.geojson');
        $features=$j['features'];
        $values=array(
            '772'=>array(10.396649837494,43.716170598881),
            '686'=>array(6.86924,45.92367),
            '346'=>array(10.396649837494,43.716170598881)
            );
        foreach($features as $f) {
            $id = $f['properties']['id'];
            $lon = $f['geometry']['coordinates'][0];
            $lat = $f['geometry']['coordinates'][1];
            $this->assertEquals($lon,$values[$id][0]);
            $this->assertEquals($lat,$values[$id][1]);
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
