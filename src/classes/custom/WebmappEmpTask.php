<?php
class WebmappEmpTask extends WebmappAbstractTask {

    private $categories=array();

	public function check() {
		return TRUE;
	}

    public function process(){
        $rpath = $this->getRoot().'/geojson/raw';
        $bpath = $this->getRoot().'/geojson/backup';
        echo "\nRAW: $rpath";
        echo "\nBAK: $bpath";

        $l=new WebmappLayer('report');

        $d = dir($rpath);
        while (false !== ($j = $d->read())) {
           if ($j!='.' && $j != '..') {
            echo "\nProcessing file $j";
            $path = "$rpath/$j";
            // READ JSON
            $ja = json_decode(file_get_contents($path),TRUE);
            $id = $ja['timestamp'];
            $notes = $ja['notes'];
            $lat = $ja['lat'];
            $lon = $ja['lng'];
            if (empty($lat) || empty($lon) || empty($id) ) {
                echo " no data SKIP";
            }
            else {
            echo " id:$id notes:$notes lat:$lat lon:$lon";
            $picture = $ja['picture'];
            if(!empty($picture)) {
                echo " picture:yes ";
                $has_picture = "yes";
                $path = $this->getRoot()."/media/images/$id.jpg";
                $this->writeImage($picture,$path);
            } else {
                echo " picture:no ";
                $has_picture = "no";
            }
            // POI
            $data = date('m/d/Y H:i', (int) $id/1000);
            $title = $data;
            $content = <<<EOF
Segnalazione del $data <br />
ID: $id <br/>
Posizione (LAT,LON): $lat,$lon <br/>
Note: $notes <br/>
Picture: $has_picture <br>
EOF;
            $jp = array();
            $jp['id']=$ja['timestamp'];
            $jp['title']['rendered']=$title;
            $jp['content']['rendered']=$content;
            $jp['color']='#ff0000';
            $jp['icon']='wm-icon-alert-circled';
            $jp['n7webmap_coord']['lng']=$ja['lng'];
            $jp['n7webmap_coord']['lat']=$ja['lat'];

            $poi = new WebmappPoiFeature($jp);
            if ($has_picture=='yes') {
                $poi->addProperty('image',"http://emp.j.webmapp.it/media/images/$id.jpg");
            }
            $l->addFeature($poi);

           }
          }
        }
        $l->write($this->getRoot().'/geojson');
        $d->close();
        echo "\n\nDONE\n\n";
    	return TRUE;
    }

    private function writeImage($sb64,$path) {
        $ifp = fopen( $path, 'wb' ); 
        fwrite( $ifp, base64_decode( $sb64) );
        fclose( $ifp ); 
    }


}
