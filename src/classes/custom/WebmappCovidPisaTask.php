<?php
class WebmappCovidPisaTask extends WebmappAbstractTask {

    private $data;
    private $dataq;
    private $crypt_method = 'aes-128-ecb';
    private $crypt_key ='Q3mc+d:!&qSp;Z6~';

	public function check() {

	    //READ FILE CONTAGIATI
        $csv = 'https://a.webmapp.it/covidpi/resources/Covid-19-SDS%20-%20CSV.csv';
        $this->data=$this->readCSV($csv);

        //READ FILE Quarantene
        $csv = 'https://a.webmapp.it/covidpi/resources/Covid-19-SDS-quarantene%20-%20CSV.csv';
        $this->dataq=$this->readCSV($csv);

        // CHECK CRYPT METHOD
		return TRUE;
	}

	public function process() {
	    $this->processContagiati();
	    $this->processQuarantene();
    }
    private function processContagiati(){


        $fs = array();
        echo "Processing CONTAGIATI ";
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
       // WRITE CRYPT FILE
        $encrypted = openssl_encrypt ($data, $this->crypt_method, $this->crypt_key);
        file_put_contents($this->getRoot().'/geojson/verifica_contagiati.geojson',$encrypted);

        echo " ... done\n";

        return TRUE;
    }
    private function processQuarantene(){

        $fs = array();
        echo "Processing Quarantene ";
        $id = 1;
        foreach ($this->dataq as $p) {
            /*    [99] => Array
        (
            [0] => 43.676438
            [1] => 10.540639
            [2] => VIA S. FRANCESCO ASSISI,10 CASCINA
        )
        */
            echo " $id({$p[1]},{$p[0]})";
            // Properties
            $ps = array();
            $ps['id']=$id;
            $ps['name']=(string) $id;
            $id++;

            $f=array();
            $f['type']='Feature';
            $f['properties']=$ps;
            $f['geometry']['type']='Point';
            $f['geometry']['coordinates']=array((float) $p[1],(float) $p[0]);

            $fs[]=$f;

        }

        // geojson
        $j = array();
        $j['type']='FeatureCollection';
        $j['update']=date('Y-m-d h:i');
        $j['features']=$fs;


        $data = json_encode($j);
        // WRITE CRYPT FILE
        $encrypted = openssl_encrypt ($data, $this->crypt_method, $this->crypt_key);
        file_put_contents($this->getRoot().'/geojson/verifica_quarantene.geojson',$encrypted);

        echo " ... done\n";

        return TRUE;
    }


    private function readCSV($csv) {
	    $ret = array();
        try {
            $row = 1;
            if (($handle = fopen($csv,"r")) !== FALSE) {
                while (($data = fgetcsv($handle, 1000, ",",'"')) !== FALSE) {
                    if($data[0]!='id' && $data[0]!='Lat') {
                        $ret[]=$data;
                    }
                }
                fclose($handle);
            }
        } catch (Exception $e) {
            echo "Error: {$e->getMessage()}\n\n";
        }
        return $ret;
    }

}
