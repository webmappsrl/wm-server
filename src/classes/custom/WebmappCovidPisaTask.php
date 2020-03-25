<?php
class WebmappCovidPisaTask extends WebmappAbstractTask {

    private $data;

	public function check() {

	    //READ FILE
        $csv = 'https://a.webmapp.it/covidpi/resources/Covid-19-SDS%20-%20CSV.csv';
        try {
            $row = 1;
            if (($handle = fopen($csv,"r")) !== FALSE) {
                while (($data = fgetcsv($handle, 1000, ",",'"')) !== FALSE) {
                    if($data[0]!='id') {
                        $this->data[]=$data;
                    }
                }
                fclose($handle);
            }
        } catch (Exception $e) {
            echo "Error: {$e->getMessage()}\n\n";
        }
		return TRUE;
	}

    public function process(){
	    $fs = array();
	    echo "Processing ";
	    foreach ($this->data as $p) {
	        /*    [99] => Array
        (
            [0] => 100
            [1] => 43.676438
            [2] => 10.540639
            [3] => VIA S. FRANCESCO ASSISI,10 CASCINA
        )
        */
	        echo ".{$p[0]}";
	        // Properties
	        $ps = array();
	        $ps['id']=$p[0];
	        $ps['name']=$p[0];


	        $f=array();
	        $f['type']='Feature';
	        $f['properties']=$ps;
	        $f['geometry']['type']='Point';
	        $f['geometry']['coordinates']=array((float) $p[2],(float) $p[1]);

	        $fs[]=$f;

        }

        // geojson
        $j = array();
	    $j['type']='FeatureCollection';
	    $j['update']=date('Y-m-d h:i');
	    $j['features']=$fs;

        // Write FILE
	    file_put_contents($this->getRoot().'/geojson/verifica_contagiati.geojson',json_encode($j));

        echo " ... done\n";

    	return TRUE;
    }

}
