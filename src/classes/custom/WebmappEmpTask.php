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

        $l=new WebmappLayer('incendio');

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
            } else {
                echo " picture:no ";
                $has_picture = "no";
            }
            // POI
            $data = date('m/d/Y H:i', $id);
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
            $l->addFeature($poi);

           }
          }
        }
        $l->write($this->getRoot().'/geojson');
        $d->close();
        echo "\n\nDONE\n\n";
    	return TRUE;
    }

}
