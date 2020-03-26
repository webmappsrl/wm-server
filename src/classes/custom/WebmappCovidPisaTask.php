<?php
class WebmappCovidPisaTask extends WebmappAbstractTask {

    private $data;
    private $crypt_method = 'aes-128-ecb';
    private $crypt_key ='Q3mc+d:!&qSp;Z6~';

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

        // CHECK CRYPT METHOD
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


	    $data = json_encode($j);
        // Write FILE
	    file_put_contents($this->getRoot().'/geojson/verifica_contagiati.geojson',$data);

	    // WRITE CRYPT FILE
        $encrypted = openssl_encrypt ($data, $this->crypt_method, $this->crypt_key);
        file_put_contents($this->getRoot().'/geojson/verifica_contagiati_crypted.geojson',$encrypted);

        echo " ... done\n";

    	return TRUE;
    }

}
