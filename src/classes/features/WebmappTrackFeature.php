<?php 

class WebmappTrackFeature extends WebmappAbstractFeature {

    private $lngMin;
    private $lngMax;
    private $latMin;
    private $latMax;
    private $bb_computed = false;

	// Mapping dei meta specifici delle tracks
    // 
    protected function mappingSpecific($json_array) {

        $this->setProperty('n7webmapp_track_color',$json_array,'color');
        $this->setProperty('n7webmap_start',$json_array,'from');
        $this->setProperty('n7webmap_end',$json_array,'to');
        $this->setProperty('ref',$json_array);
        $this->setProperty('ascent',$json_array);
        $this->setProperty('descent',$json_array);
        $this->setProperty('distance',$json_array);
        $this->setProperty('duration:forward',$json_array);
        $this->setProperty('duration:backward',$json_array);
        $this->setProperty('cai_scale',$json_array);
        // ADD id_pois
        $json_array['id_pois']=$this->getRelatedPoisId();
        $this->setProperty('id_pois',$json_array);

        // ROADBOOK
        if(isset($json_array['rb_track_section']) && !empty($json_array['rb_track_section'])) {
            $this->addProperty('rb_track_section',$json_array['rb_track_section']);
        }

    }

    // Impostazione della geometry a partire da formato API WP
    /**
    SERIALIZED: a:2:{s:4:"type";s:10:"LineString";s:11:"coordinates";a:42:{i:0;a:2:{i:0;d:5.0802517309784996;i:1;d:52.019237307
        JSON: { "type" : "LineString" ,
                "coordinates": [
                       [ 11.238753551237847, 43.55744054805567],
              }
    **/
        protected function mappingGeometry($json_array) {
        // TODO: controllo esistenza coordinate
                if (isset($json_array['n7webmap_geojson'])) {
                 $this->geometry=unserialize($json_array['n7webmap_geojson']);            
             }
         }
         public function setGeometry($geometry){$this->geometry=$geometry;}

         private function computeBB() {
            $coords = $this->geometry['coordinates'];
            $first = true;
            foreach ($coords as $coord) {
                $lng = $coord[0];
                $lat = $coord[1];
                if($first) {
                    $this->lngMin = $lng;
                    $this->lngMax = $lng;
                    $this->latMin = $lat;
                    $this->latMax = $lat;
                    $first = false;
                }
                else {
                    if ($lng<$this->lngMin) $this->lngMin = $lng;
                    if ($lng>$this->lngMax) $this->lngMax = $lng;
                    if ($lat<$this->latMin) $this->latMin = $lat;
                    if ($lat>$this->latMax) $this->latMax = $lat;       
                }
            }
            $this->bb_computed = true;
        }

        public function getLatMax(){if(!$this->bb_computed) $this->computeBB(); return $this->latMax;}
        public function getLatMin(){if(!$this->bb_computed) $this->computeBB(); return $this->latMin;}
        public function getLngMax(){if(!$this->bb_computed) $this->computeBB(); return $this->lngMax;}
        public function getLngMin(){if(!$this->bb_computed) $this->computeBB(); return $this->lngMin;}

    public function writeToPostGis($instance_id='') {

        // Gestione della ISTANCE ID
        if(empty($instance_id)) {
            $instance_id = WebmappProjectStructure::getInstanceId();
        }
        $pg = WebmappPostGis::Instance();
        $pg->insertTrack($instance_id,$this->getId(),$this->geometry);

    }

    public function addBBox($instance_id='') {
        // Gestione della ISTANCE ID
        if(empty($instance_id)) {
            $instance_id = WebmappProjectStructure::getInstanceId();
        }
        $pg = WebmappPostGis::Instance();
        $bb = $pg->getTrackBBox($instance_id,$this->getId());
        if(empty($bb)) {
            $this->writeToPostGis($instance_id);
            $bb = $pg->getTrackBBox($instance_id,$this->getId());
        }
        $this->addProperty('bbox',$bb);
        $bb = $pg->getTrackBBoxMetric($instance_id,$this->getId());
        $this->addProperty('bbox_metric',$bb);
    }

    public function setComputedProperties() {
            if(isset($this->geometry['coordinates']) &&
                count($this->geometry['coordinates'])>0 &&
                count($this->geometry['coordinates'][0])==2) {
                $geom = json_encode($this->geometry);
                $pg = WebmappPostGis::Instance();
                $geom_3d = $pg->addEle($geom);
                $this->geometry=json_decode($geom_3d,TRUE);
                // Now set info property
                $first=true;
                $distance=0;
                $ascent=0;
                $descent=0;
                $ele_min=10000;
                $ele_max=0;
                foreach ($this->geometry['coordinates'] as $item) {
                    if($first) {
                        $first=false;
                        $ele_from=$item[2];
                    }
                    else {
                        $lon=$item[0];
                        $lat=$item[1];
                        $ele=$item[2];
                        $distance += WebmappUtils::distance($lon0,$lat0,$lon,$lat);
                        $delta = $ele - $ele0;
                        if($delta>0) {
                            $ascent += $delta;
                        } else {
                            $descent += $delta;
                        }
                    }
                    $lon0=$item[0];
                    $lat0=$item[1];
                    $ele0=$item[2];
                    $ele_to=$item[2];
                    if($ele0>$ele_max) $ele_max=$ele0;
                    if($ele0<$ele_min) $ele_min=$ele0;
                }
                $computed = array(
                    'distance' => $distance,
                    'ascent' => $ascent,
                    'descent' => $descent,
                    'ele:from' => $ele_from,
                    'ele:to' => $ele_to,
                    'ele:min' => $ele_min,
                    'ele:max' => $ele_max
                    );
                $this->addProperty('computed',$computed);
    }
}


        // COnvert geom to 3d geom (only if needed)
        public function addEle() {
            if(isset($this->geometry['coordinates']) &&
                count($this->geometry['coordinates'])>0 &&
                count($this->geometry['coordinates'][0])==2) {
                $geom = json_encode($this->geometry);
                $pg = WebmappPostGis::Instance();
                $geom_3d = $pg->addEle($geom);
                $this->geometry=json_decode($geom_3d,TRUE);
                // Now set info property
                $first=true;
                $distance=0;
                $ascent=0;
                $descent=0;
                $ele_min=10000;
                $ele_max=0;
                foreach ($this->geometry['coordinates'] as $item) {
                    if($first) {
                        $first=false;
                        $ele_from=$item[2];
                    }
                    else {
                        $lon=$item[0];
                        $lat=$item[1];
                        $ele=$item[2];
                        $distance += WebmappUtils::distance($lon0,$lat0,$lon,$lat);
                        $delta = $ele - $ele0;
                        if($delta>0) {
                            $ascent += $delta;
                        } else {
                            $descent += $delta;
                        }
                    }
                    $lon0=$item[0];
                    $lat0=$item[1];
                    $ele0=$item[2];
                    $ele_to=$item[2];
                    if($ele0>$ele_max) $ele_max=$ele0;
                    if($ele0<$ele_min) $ele_min=$ele0;
                }
                $this->addProperty('distance',$distance);
                $this->addProperty('ascent',$ascent);
                $this->addProperty('descent',$descent);
                $this->addProperty('ele:from',$ele_from);
                $this->addProperty('ele:to',$ele_to);
                $this->addProperty('ele:min',$ele_min);
                $this->addProperty('ele:max',$ele_max);

                // TODO: Aggiungere duration
            }


        }

        public function writeGPX($path) {
        // TODO: check path
            $path = $path.'/'.$this->getId().'.gpx';
            $decoder = new Symm\Gisconverter\Decoders\GeoJSON();
            $geometry = $decoder->geomFromText(json_encode($this->geometry));
            $gpx='<?xml version="1.0"?>
            <gpx version="1.1" creator="GDAL 2.2.2" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:ogr="http://osgeo.org/gdal" xmlns="http://www.topografix.com/GPX/1/1" xsi:schemaLocation="http://www.topografix.com/GPX/1/1 http://www.topografix.com/GPX/1/1/gpx.xsd">
            <trk>'.$geometry->toGPX().'</trk>
            </gpx>';
            file_put_contents($path,$gpx);
        }

        public function writeKML($path) {
        // TODO: check path
            $path = $path.'/'.$this->getId().'.kml';
            $decoder = new Symm\Gisconverter\Decoders\GeoJSON();
            $geometry = $decoder->geomFromText(json_encode($this->geometry));
            $kml ='<?xml version="1.0" encoding="UTF-8"?>
            <kml xmlns="http://www.opengis.net/kml/2.2">
            <Document>
            <Placemark><ExtendedData></ExtendedData>'.$geometry->toKML().'</Placemark>
            </Document>
            </kml>';
            file_put_contents($path,$kml);
        }


        public function addRelated($distance=5000,$limit=100) {
            if($limit>0) {
                $limit = " LIMIT $limit";
            } else {
                $limit='';
            }
            $id = $this->properties['id'];
            $q = "SELECT poi_tmp.id as id, track_tmp.id as tid, ST_Distance(poi_tmp.wkb_geometry, ST_Transform(track_tmp.wkb_geometry,3857)) as distance
            FROM  poi_tmp, track_tmp
            WHERE track_tmp.id=$id AND ST_Distance(poi_tmp.wkb_geometry, ST_Transform(track_tmp.wkb_geometry,3857)) < $distance
            ORDER BY distance
            $limit ;";
            $this->addRelatedPoi($q);
        }

        public function generateAllImages($instance_id='',$path='') {

            // Gestione della ISTANCE ID
            if(empty($instance_id)) {
                $instance_id = WebmappProjectStructure::getInstanceId();
            }

            $sizes = array(
                    array(491,624),
                    array(400,300),
                    array(200,200),
                    array(1000,1000)
                );
            foreach ($sizes as $v) {
                $this->generateImage($v[0],$v[1],$instance_id,$path);
            }

        }

        public function generateImage($width,$height,$instance_id='',$path='') {
            // TODO: check parameter

            // Gestione della ISTANCE ID
            if(empty($instance_id)) {
                $instance_id = WebmappProjectStructure::getInstanceId();
            }           
            if(!isset($this->properties['bbox'])) {
                $this->addBBox($instance_id);
            }

            $geojson_url = 'https://a.webmapp.it/'.preg_replace('|http://|', '', $instance_id).'/geojson/'.$this->getId().'.geojson';
            $img_path = $path.'/'.$this->getId().'_map_'.$width.'x'.$height.'.png';
 
            WebmappUtils::generateImage($geojson_url,$this->properties['bbox_metric'],$width,$height,$img_path);
        }

        public function generatePortraitRBImages($instance_id='',$path='') {
            $this->generateRBImages(491,624,1300,$instance_id,$path);
        }

        public function generateLandscapeRBImages($instance_id='',$path='') {
            $this->generateRBImages(624,491,3300,$instance_id,$path);
        }

        // TODO: gestire output coordinate
        public function getRunningPoints($n,$instance_id='') {
            // Gestione della ISTANCE ID
            if(empty($instance_id)) {
                $instance_id = WebmappProjectStructure::getInstanceId();
            }
            $pg = WebmappPostGis::Instance();
            $results = array();
            for ($i=0; $i <= $n; $i++) { 
                $p = $i/$n;
                $id=$this->getId();
                $q= "SELECT ST_X(ST_Transform(ST_Lineinterpolatepoint(geom,$p),3857)) as x,
                            ST_Y(ST_Transform(ST_Lineinterpolatepoint(geom,$p),3857)) as y 
                     FROM track 
                     WHERE track_id=$id AND 
                     instance_id='$instance_id';";
                $r = $pg->select($q);
                $results[]=array($r[0]['x'],$r[0]['y']);
            }
            return $results;
        }

        public function computeDistance3857($instance_id='') {
            //ST_Length(ST_Transform(geom,3857))
            $l = 0;
            if(empty($instance_id)) {
                $instance_id = WebmappProjectStructure::getInstanceId();
            }
            $pg = WebmappPostGis::Instance();
            $q= "SELECT ST_Length(ST_Transform(geom,3857)) as l
                     FROM track 
                     WHERE track_id={$this->getId()} AND 
                     instance_id='$instance_id';";
            $r=$pg->select($q);
            if(count($r)>0){
                $l=$r[0]['l'];
            }
            return $l;
        }

        public function generateRBImages($width,$height,$bbox_dx,$instance_id='',$path=''){

            // TODO: check PARAMETER

            // Gestione della ISTANCE ID
            if(empty($instance_id)) {
                $instance_id = WebmappProjectStructure::getInstanceId();
            }

            // DEBUG INPUT PARAMETER
            echo "\n\n\n=====================\n";
            echo "generateRBImages(W=$width,H=$height,DX=$bbox_dx,INST=$instance_id,PATH=$path)\n";

            // GRANDEZZE DERIVATE
            // 2. DY del BBOX=1300/491*624m
            $bbox_dy = $bbox_dx / $width * $height;
            // 3. d = Lunghezza da percorrere lungo la track per trovare i punti successivi=0.95*MIN(DX,DY)
            $running_d = 0.90*min(array($bbox_dx,$bbox_dy));
            $l = $this->computeDistance3857($instance_id);
            $n = ceil($l/$running_d)+1;

            echo "DY=$bbox_dy,d=$running_d l=$l n=$n\n";
            $points = $this->getRunningPoints($n,$instance_id);
            $i=0;
            if(count($points)>0) {
                $images=array();
                foreach ($points as $point) {
                    $x = $point[0]; $y = $point[1];
                    $xmin = $x-$bbox_dx/2;
                    $xmax = $x+$bbox_dx/2;
                    $ymin = $y-$bbox_dy/2;
                    $ymax = $y+$bbox_dy/2;
                    $bbox="$xmin,$ymin,$xmax,$ymax";

                    $geojson_url = 'https://a.webmapp.it/'.preg_replace('|http://|', '', $instance_id).'/geojson/'.$this->getId().'.geojson';
                    $image_path=$path.'/'.$this->getId().'_'.$width.'x'.$height.'_'.$bbox_dx.'_'.$i.'.png';
                    WebmappUtils::generateImage($geojson_url,$bbox,$width,$height,$image_path,false);
                    $i++;
                    $images[]=$this->getId().'_'.$width.'x'.$height.'_'.$bbox_dx.'_'.$i.'.png';
                }
                $this->addProperty('rb_images',$images);
            }

        }

    }








