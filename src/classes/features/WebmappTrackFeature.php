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

            // TODO CALCOLO DEL BBOX in funzione di WIDTH E HEIGHT
            $bbox=$this->properties['bbox_metric'];
            // DEBUG
            $id=$this->getId();
            echo "\n\n======================================\n";
            echo "GENERATING IMAGE for track ID $id\n";
            echo "INPUT W=$width H=$height I=$instance_id P=$path BB1=$bbox\n";

            $bbox_array = explode(',',$bbox);
            $xmin = $bbox_array[0];
            $ymin = $bbox_array[1];
            $xmax = $bbox_array[2];
            $ymax = $bbox_array[3];
            $dx = abs($xmax-$xmin);
            $dy = abs($ymax-$ymin);

            if($dx/$dy > $width/$height) {
                $d=($height/$width*$dx-$dy)*0.5;
                $ymax = $ymax + $d;
                $ymin = $ymin - $d;
            }
            else if ($dx/$dy < $width/$height) {
                $d=($width/$height*$dy-$dy)*0.5;
                $xmax = $xmax + $d;
                $xmin = $xmin + $d;
            }

            // Allargare del 5%
            $delta = 0.05;
            $dx = abs($xmax-$xmin);
            $dy = abs($ymax-$ymin);
            $xmin = $xmin - $dx*$delta;
            $xmax = $xmax + $dx*$delta;
            $ymin = $ymin - $dy*$delta;
            $ymax = $ymax + $dy*$delta;

            $bbox=implode(',', array($xmin,$ymin,$xmax,$ymax));

            // BUILD CURL CALL TO qgs.webmapp.it/track_map.php
            // Crea il file dinamico QGS e restituisce l'URL da chiamare
            $geojson_url = 'https://a.webmapp.it/'.preg_replace('|http://|', '', $instance_id).'/geojson/'.$this->getId().'.geojson';
            $post_data = array(
                'geojson_url' => $geojson_url,
                'bbox' => $bbox,
                'width' => $width,
                'height' => $height
            );

            echo "GEOJSON=$geojson_url BBOX=$bbox\n";

            $ch = curl_init('http://qgs.webmapp.it/track.php');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
            $image_url = curl_exec($ch);
            curl_close($ch);

            $img = $path.'/'.$this->getId().'_map_'.$width.'x'.$height.'.png';
            echo "\n$image_url\n\n";
            echo "\n$img\n";
            echo "================================\n\n";
            file_put_contents($img, file_get_contents($image_url));
        }

    }