<?php
class WebmappEmpTask extends WebmappAbstractTask {

    private $categories=array();
    private $source;
    private $source_path;
    private $image_path;
    private $image_url;
    private $report_path;
    private $report_url;


	public function check() {
        if(!array_key_exists('source', $this->options)) {
            throw new WebmappExceptionParameterMandatory ("Parameter source is mandatory", 1);             
        } 
        $this->source = $this->options['source'];
        $this->source_path = '/var/www/html/api.webmapp.it/data/'.$this->source. '/';
        if(!file_exists($this->source_path)) {
            throw new WebmappExceptionParameterError("Directory $this->source_path does not exists", 1);            
        }

        $this->image_path = $this->source_path . '/image';
        if (!file_exists($this->image_path)) {
            echo "Creating image directory $this->image_path \n";
            $cmd = 'mkdir '.$this->image_path;
            system($cmd);
        }
        $this->image_url = 'https://api.webmapp.it/data/'.$this->source.'/image/';
        $this->report_path = $this->source_path .'/report.gojson';
        $this->report_url = 'https://api.webmapp.it/data/'.$this->source.'/report.geojson';

		return TRUE;
	}

    public function process(){

        echo "Processing data from $this->source\n";

        $l=new WebmappLayer('report');

        $d = dir($this->source_path);
        while (false !== ($j = $d->read())) {
           if ($j!='.' && $j != '..' && $j != 'report.geojson') {
            echo "\nProcessing file $j";
            $path = $this->source_path. "/$j";
            // READ JSON
            $ja = json_decode(file_get_contents($path),TRUE);
            $id = $ja['timestamp'];
            $notes = $ja['form_data']['description'];
            $lon = $ja['position'][0];
            $lat = $ja['position'][1];
            if (empty($lat) || empty($lon) || empty($id) ) {
                echo " no data SKIP";
            }
            else {
            echo " id:$id notes:$notes lat:$lat lon:$lon";
            $picture = $ja['form_data']['picture'];
            if(!empty($picture)) {
                echo " picture:yes ";
                $has_picture = "yes";
                $image_path =  $this->image_path . "/$id.jpg";
                $this->writeImage($picture,$image_path);
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
                $image_url = $this->image_url."/$id.jpg";
                echo "Adding image URL to props $image_url \n";
                $poi->addProperty('image',$image_url);
            }
            $l->addFeature($poi);

           }
          }
        }
        $l->write($this->getRoot().'/geojson');
        $d->close();
        echo "\n\nDONE\n\n";
        echo "Process DONE: check geojson at $this->report_url \n\n";
    	return TRUE;
    }

    private function writeImage($sb64,$path) {
        echo "Writing image to $path \n";
        $ifp = fopen( $path, 'wb' ); 
        fwrite( $ifp, base64_decode( $sb64) );
        fclose( $ifp ); 
    }

}
