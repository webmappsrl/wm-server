<?php

class WebmappTrackFeature extends WebmappAbstractFeature {
    private $lngMin;
    private $lngMax;
    private $latMin;
    private $latMax;
    private $bb_computed = false;
    private $distance = -1;
    private $ascent = -1;
    private $descent = -1;
    private $ele_from = -1;
    private $ele_to = -1;
    private $ele_max = -1;
    private $ele_min = -1;
    private $_relation;
    // Mapping dei meta specifici delle tracks
    //

    public function setGeometry($geometry) {
        $this->geometry = $geometry;
    }

    // Impostazione della geometry a partire da formato API WP

    public function getFirst() {
        if ($this->hasGeometry()) {
            return $this->geometry['coordinates'][0];
        }

        return array();
    }

    public function getLatMax() {
        if (!$this->bb_computed) {
            $this->computeBB();
        }

        return $this->latMax;
    }

    private function computeBB() {
        $coords = $this->geometry['coordinates'];
        $first = true;
        foreach ($coords as $coord) {
            $lng = $coord[0];
            $lat = $coord[1];
            if ($first) {
                $this->lngMin = $lng;
                $this->lngMax = $lng;
                $this->latMin = $lat;
                $this->latMax = $lat;
                $first = false;
            } else {
                if ($lng < $this->lngMin) {
                    $this->lngMin = $lng;
                }

                if ($lng > $this->lngMax) {
                    $this->lngMax = $lng;
                }

                if ($lat < $this->latMin) {
                    $this->latMin = $lat;
                }

                if ($lat > $this->latMax) {
                    $this->latMax = $lat;
                }
            }
        }
        $this->bb_computed = true;
    }

    public function getLatMin() {
        if (!$this->bb_computed) {
            $this->computeBB();
        }

        return $this->latMin;
    }

    public function getLngMax() {
        if (!$this->bb_computed) {
            $this->computeBB();
        }

        return $this->lngMax;
    }

    public function getLngMin() {
        if (!$this->bb_computed) {
            $this->computeBB();
        }

        return $this->lngMin;
    }

    public function setComputedProperties() {
        if (isset($this->geometry['coordinates']) &&
            count($this->geometry['coordinates']) > 0 &&
            count($this->geometry['coordinates'][0]) == 2) {
            $geom = json_encode($this->geometry);
            $pg = WebmappPostGis::Instance();
            $geom_3d = $pg->addEle($geom);
            $this->geometry = json_decode($geom_3d, true);
            // Now set info property
            $first = true;
            $distance = 0;
            $ascent = 0;
            $descent = 0;
            $ele_min = 10000;
            $ele_max = 0;
            foreach ($this->geometry['coordinates'] as $item) {
                if ($first) {
                    $first = false;
                    $ele_from = $item[2];
                } else {
                    $lon = $item[0];
                    $lat = $item[1];
                    $ele = $item[2];
                    $distance += WebmappUtils::distance($lon0, $lat0, $lon, $lat);
                    $delta = $ele - $ele0;
                    if ($delta > 0) {
                        $ascent += $delta;
                    } else {
                        $descent += $delta;
                    }
                }
                $lon0 = $item[0];
                $lat0 = $item[1];
                $ele0 = $item[2];
                $ele_to = $item[2];
                if ($ele0 > $ele_max) {
                    $ele_max = $ele0;
                }

                if ($ele0 < $ele_min) {
                    $ele_min = $ele0;
                }
            }
            $computed = array(
                'distance' => $distance,
                'ascent' => $ascent,
                'descent' => $descent,
                'ele:from' => $ele_from,
                'ele:to' => $ele_to,
                'ele:min' => $ele_min,
                'ele:max' => $ele_max,
            );
            $this->addProperty('computed', $computed);
        }
    }

    public function hasRelation() {
        return isset($this->_relation);
    }

    public function getRelation() {
        return $this->_relation;
    }

    public function setRelation($osmid) {
        $this->_relation = new WebmappOSMRelation($osmid);
    }

    /**
     * @param string $instance_id
     *
     * @throws WebmappExceptionFeaturesNoGeometry
     * @throws WebmappExceptionGeoJsonBadGeomType
     */
    public function setComputedProperties2($instance_id = '') {
        if (!$this->hasGeometry()) {
            throw new WebmappExceptionFeaturesNoGeometry("The track {$this->getId()} is missing the geometry");

            return;
        }
        if (!$this->has3D()) {
            $this->add3D();
        }

        if (empty($instance_id)) {
            $instance_id = WebmappProjectStructure::getInstanceId();
        }
        if (isset($this->geometry['coordinates']) &&
            count($this->geometry['coordinates']) > 0) {
            // Distance
            if ($this->distance == -1) {
                $this->distance = $this->computeDistanceSpheroid($instance_id);
            }
            // Ele from to
            $this->ele_from = $this->geometry['coordinates'][0][2];
            $this->ele_to = $this->geometry['coordinates'][count($this->geometry['coordinates']) - 1][2];
            // Ele min max
            $quotes = array();
            foreach ($this->geometry['coordinates'] as $coordinates) {
                $quotes[] = $coordinates[2];
            }
            $this->ele_min = min($quotes);
            $this->ele_max = max($quotes);
            $this->computeAscDesc($instance_id);

            $dist = round($this->distance / 1000, 1);
            $computed = array(
                'distance' => $dist,
                'ascent' => $this->ascent,
                'descent' => $this->descent,
                'ele:from' => $this->ele_from,
                'ele:to' => $this->ele_to,
                'ele:min' => $this->ele_min,
                'ele:max' => $this->ele_max,
            );
            $this->addProperty('computed', $computed);

            // Set "normal properties"
            //            $this->addProperty('distance', $dist);
            $this->setPropsFromComputed('distance');
            $this->setPropsFromComputed('ele:from');
            $this->setPropsFromComputed('ele:to');
            $this->setPropsFromComputed('ele:min');
            $this->setPropsFromComputed('ele:max');
            $this->setPropsFromComputed('ascent');
            $this->setPropsFromComputed('descent');
        }
    }

    public function has3D() {
        if (empty($this->geometry)) {
            return false;
        }
        if (isset($this->geometry['coordinates']) &&
            is_array($this->geometry['coordinates']) &&
            count($this->geometry['coordinates']) > 0 &&
            is_array($this->geometry['coordinates'][0]) &&
            count($this->geometry['coordinates'][0]) >= 3) {
            return true;
        }

        return false;
    }

    /**
     * @throws WebmappExceptionGeoJsonBadGeomType
     */
    public function add3D() {
        if ($this->hasGeometry()) {
            $pg = WebmappPostGis::Instance();
            $this->geometry = json_decode($pg->addEle(json_encode($this->geometry)), true);
        }
    }

    public function computeDistanceSpheroid($instance_id = '') {
        //ST_Length(ST_Transform(geom,3857))
        $l = 0;
        if (empty($instance_id)) {
            $instance_id = WebmappProjectStructure::getInstanceId();
        }
        $pg = WebmappPostGis::Instance();
        $q = "SELECT ST_LengthSpheroid(geom,'SPHEROID[\"WGS_1984\",6378137,298.257223563]') as l
                 FROM track
                 WHERE track_id={$this->getId()} AND
                       instance_id='$instance_id';";
        $r = $pg->select($q);
        if (count($r) > 0) {
            $l = $r[0]['l'];
        }

        return $l;
    }

    private function computeAscDesc($instance_id = '') {
        // INIT
        $quotes = array();
        $d = 0;
        $h = $this->ele_from;
        $quotes[] = $h;
        // Gestione della ISTANCE ID
        if (empty($instance_id)) {
            $instance_id = WebmappProjectStructure::getInstanceId();
        }

        // LOOP
        while ($d < $this->distance) {
            //$dump="WHILE IN computeAscDesc d=$d h=$h\n";
            //fwrite(STDERR, print_r($dump, TRUE));

            $vals = $this->getNextNormalizedQuote($d, $h, $instance_id);
            $d = $vals[0];
            $h = $vals[1];
            $quotes[] = $h;
        }

        // set ascent / descent
        $n = count($quotes);
        $threshold = 40;
        //$thresholds=array(0,10,20,30,40,50);
        //$ascents = array();
        //foreach ($thresholds as $threshold) {
        if ($n >= 0) {
            $this->ascent = 0;
            $this->descent = 0;
            $q0 = $quotes[0];
            for ($i = 1; $i < $n; $i++) {
                if (abs($quotes[$i] - $q0) > $threshold) {
                    if ($quotes[$i] > $q0) {
                        $this->ascent = $this->ascent + $quotes[$i] - $q0;
                    } else {
                        $this->descent = $this->descent + $q0 - $quotes[$i];
                    }
                    $q0 = $quotes[$i];
                }
            }
            //$ascents[]=$this->ascent;
        }
        //}
        // print_r($ascents);
    }

    private function getNextNormalizedQuote($d, $h, $instance_id = '') {
        // PARAMETER
        $B = $b = 100;

        // INIT
        $d1 = $d + $B - $b;
        $h1 = $h;
        $pg = WebmappPostGis::Instance();
        // Gestione della ISTANCE ID
        if (empty($instance_id)) {
            $instance_id = WebmappProjectStructure::getInstanceId();
        }

        // RUNNING DISTANCE
        while ($h1 == $h) {
            $d1 = $d1 + $b;
            // CHECK DISTANCE
            if ($d1 >= $this->distance) {
                return array($this->distance, $this->ele_to);
            }
            $p = $d1 / $this->distance;
            $vals = $this->getRunningPoint($p, 4326, $instance_id);
            $h1 = $pg->getEle($vals[0], $vals[1]);
        }

        return array($d1, $h1);
    }

    public function getRunningPoint($p, $coord_type, $instance_id = '') {
        // Gestione della ISTANCE ID
        if (empty($instance_id)) {
            $instance_id = WebmappProjectStructure::getInstanceId();
        }
        $pg = WebmappPostGis::Instance();
        $id = $this->getId();
        $q = "SELECT ST_X(ST_Transform(ST_Lineinterpolatepoint(geom,$p),$coord_type)) as x,
                            ST_Y(ST_Transform(ST_Lineinterpolatepoint(geom,$p),$coord_type)) as y
                 FROM track
                 WHERE track_id=$id AND
                  instance_id='$instance_id';";
        $r = $pg->select($q);

        return array($r[0]['x'], $r[0]['y']);
    }

    private function setPropsFromComputed($props_name) {
        if (isset($this->properties['computed'][$props_name]) &&
            (!isset($this->properties[$props_name]) || empty($this->properties[$props_name]))) {
            $this->properties[$props_name] = $this->properties['computed'][$props_name];
        }
    }

    public function addEle() {
        if (isset($this->geometry['coordinates']) &&
            count($this->geometry['coordinates']) > 0 &&
            count($this->geometry['coordinates'][0]) == 2) {
            $geom = json_encode($this->geometry);
            $pg = WebmappPostGis::Instance();
            $geom_3d = $pg->addEle($geom);
            $this->geometry = json_decode($geom_3d, true);
            // Now set info property
            $first = true;
            $distance = 0;
            $ascent = 0;
            $descent = 0;
            $ele_min = 10000;
            $ele_max = 0;
            foreach ($this->geometry['coordinates'] as $item) {
                if ($first) {
                    $first = false;
                    $ele_from = $item[2];
                } else {
                    $lon = $item[0];
                    $lat = $item[1];
                    $ele = $item[2];
                    $distance += WebmappUtils::distance($lon0, $lat0, $lon, $lat);
                    $delta = $ele - $ele0;
                    if ($delta > 0) {
                        $ascent += $delta;
                    } else {
                        $descent += $delta;
                    }
                }
                $lon0 = $item[0];
                $lat0 = $item[1];
                $ele0 = $item[2];
                $ele_to = $item[2];
                if ($ele0 > $ele_max) {
                    $ele_max = $ele0;
                }

                if ($ele0 < $ele_min) {
                    $ele_min = $ele0;
                }
            }
            $this->addProperty('distance', $distance);
            $this->addProperty('ascent', $ascent);
            $this->addProperty('descent', $descent);
            $this->addProperty('ele:from', $ele_from);
            $this->addProperty('ele:to', $ele_to);
            $this->addProperty('ele:min', $ele_min);
            $this->addProperty('ele:max', $ele_max);
            // TODO: Aggiungere duration
        }
    }

    public function writeGPX($path) {
        if (!$this->hasGeometry()) {
            echo "\n\n\n WARNING no geometry found track_id: {$this->getId()}\n\n\n";

            return;
            // throw new WebmappExceptionFeaturesNoGeometry("No Geometry found");
        }
        // TODO: check path
        $path = $path . '/' . $this->getId() . '.gpx';
        $decoder = new Symm\Gisconverter\Decoders\GeoJSON();
        $geometry = $decoder->geomFromText(json_encode($this->geometry));
        $gpx = '<?xml version="1.0"?>
            <gpx version="1.1" creator="GDAL 2.2.2" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:ogr="http://osgeo.org/gdal" xmlns="http://www.topografix.com/GPX/1/1" xsi:schemaLocation="http://www.topografix.com/GPX/1/1 http://www.topografix.com/GPX/1/1/gpx.xsd">
            <trk>' . $geometry->toGPX() . '</trk>
            </gpx>';
        file_put_contents($path, $gpx);
    }

    public function writeKML($path) {
        if (!$this->hasGeometry()) {
            echo "\n\n\n WARNING no geometry found track_id: {$this->getId()}\n\n\n";

            return;
            // throw new WebmappExceptionFeaturesNoGeometry("No Geometry found");
        }
        // TODO: check path
        $path = $path . '/' . $this->getId() . '.kml';
        $decoder = new Symm\Gisconverter\Decoders\GeoJSON();
        $geometry = $decoder->geomFromText(json_encode($this->geometry));
        $kml = '<?xml version="1.0" encoding="UTF-8"?>
            <kml xmlns="http://www.opengis.net/kml/2.2">
            <Document>
            <Placemark><ExtendedData></ExtendedData>' . $geometry->toKML() . '</Placemark>
            </Document>
            </kml>';
        file_put_contents($path, $kml);
    }

    public function addRelated($distance = 5000, $limit = 100) {
        if ($limit > 0) {
            $limit = " LIMIT $limit";
        } else {
            $limit = '';
        }
        $id = $this->properties['id'];
        $q = "SELECT poi_tmp.id as id, track_tmp.id as tid, ST_Distance(poi_tmp.wkb_geometry, ST_Transform(track_tmp.wkb_geometry,3857)) as distance
            FROM  poi_tmp, track_tmp
            WHERE track_tmp.id=$id AND ST_Distance(poi_tmp.wkb_geometry, ST_Transform(track_tmp.wkb_geometry,3857)) < $distance
            ORDER BY distance
            $limit ;";
        $this->addRelatedPoi($q);
    }

    public function generateAllImages($instance_id = '', $path = '') {
        if (empty($instance_id)) {
            $instance_id = WebmappProjectStructure::getInstanceId();
        }

        $sizes = array(
            array(491, 624),
            array(624, 468),
            array(400, 300),
            array(200, 200),
            array(1000, 1000),
        );
        foreach ($sizes as $v) {
            $this->generateImage($v[0], $v[1], $instance_id, $path);
        }
    }

    public function generateImage($width, $height, $instance_id = '', $path = '') {
        // TODO: check parameter
        if (empty($instance_id)) {
            $instance_id = WebmappProjectStructure::getInstanceId();
        }
        if (!isset($this->properties['bbox'])) {
            $this->addBBox($instance_id);
        }

        $img_path = $path . '/' . $this->getId() . '_map_' . $width . 'x' . $height . '.png';
        $geojson_url = 'https://a.webmapp.it/' . preg_replace('|http://|', '', $instance_id) . '/geojson/' . $this->getId() . '.geojson';
        $pois_geojson_url = 'https://a.webmapp.it/' . preg_replace('|http://|', '', $instance_id) . '/track/' . $this->getId() . '_rb_related_poi.geojson';

        if (isset($this->properties['related']['poi']['roadbook']) &&
            count($this->properties['related']['poi']['roadbook']) > 0) {
            WebmappUtils::generateImageWithPois($geojson_url, $pois_geojson_url, $this->properties['bbox_metric'], $width, $height, $img_path);
        } else {
            WebmappUtils::generateImage($geojson_url, $this->properties['bbox_metric'], $width, $height, $img_path);
        }
    }

    public function addBBox($instance_id = '') {
        // Gestione della ISTANCE ID
        if (empty($instance_id)) {
            $instance_id = WebmappProjectStructure::getInstanceId();
        }
        $pg = WebmappPostGis::Instance();
        $bb = $pg->getTrackBBox($instance_id, $this->getId());
        if (empty($bb)) {
            $this->writeToPostGis($instance_id);
            $bb = $pg->getTrackBBox($instance_id, $this->getId());
        }
        $this->addProperty('bbox', $bb);
        $bb = $pg->getTrackBBoxMetric($instance_id, $this->getId());
        $this->addProperty('bbox_metric', $bb);
    }

    public function writeToPostGis($instance_id = '') {
        // Gestione della ISTANCE ID
        if (empty($instance_id)) {
            $instance_id = WebmappProjectStructure::getInstanceId();
        }
        $pg = WebmappPostGis::Instance();
        $pg->insertTrack($instance_id, $this->getId(), $this->geometry);
    }

    public function generatePortraitRBImages($instance_id = '', $path = '') {
        $this->generateRBImages(491, 624, 1300, $instance_id, $path);
    }

    public function generateRBImages($width, $height, $bbox_dx, $instance_id = '', $path = '') {
        // TODO: check PARAMETER
        if (empty($instance_id)) {
            $instance_id = WebmappProjectStructure::getInstanceId();
        }

        // DEBUG INPUT PARAMETER
        WebmappUtils::verbose("Generating roadbook images: W = $width, H = $height, DX = $bbox_dx, INST = $instance_id, PATH = $path");

        // GRANDEZZE DERIVATE
        // 2. DY del BBOX=1300/491*624m
        $bbox_dy = $bbox_dx / $width * $height;
        // 3. d = Lunghezza da percorrere lungo la track per trovare i punti successivi=0.95*MIN(DX,DY)
        $running_d = 0.90 * min(array($bbox_dx, $bbox_dy));
        $l = $this->computeDistance3857($instance_id);
        $n = ceil($l / $running_d) + 1;

        WebmappUtils::verbose("DY = $bbox_dy, d = $running_d, l = $l, n = $n");
        $points = $this->getRunningPoints($n, $instance_id);
        $i = 0;
        if (count($points) > 0) {
            $images = array();
            foreach ($points as $point) {
                $x = $point[0];
                $y = $point[1];
                $xmin = $x - $bbox_dx / 2;
                $xmax = $x + $bbox_dx / 2;
                $ymin = $y - $bbox_dy / 2;
                $ymax = $y + $bbox_dy / 2;
                $bbox = "$xmin,$ymin,$xmax,$ymax";

                $geojson_url = 'https://a.webmapp.it/' . preg_replace('|http://|', '', $instance_id) . '/geojson/' . $this->getId() . '.geojson';
                $pois_geojson_url = 'https://a.webmapp.it/' . preg_replace('|http://|', '', $instance_id) . '/track/' . $this->getId() . '_rb_related_poi.geojson';
                $image_path = $path . '/' . $this->getId() . '_' . $width . 'x' . $height . '_' . $bbox_dx . '_' . $i . '.png';
                if (isset($this->properties['related']['poi']['roadbook']) &&
                    count($this->properties['related']['poi']['roadbook']) > 0) {
                    WebmappUtils::generateImageWithPois($geojson_url, $pois_geojson_url, $bbox, $width, $height, $image_path, false);
                } else {
                    WebmappUtils::generateImage($geojson_url, $bbox, $width, $height, $image_path, false);
                }
                $images[] = $this->getId() . '_' . $width . 'x' . $height . '_' . $bbox_dx . '_' . $i . '.png';

                // Add rotated images
                $r_image_path = $path . '/' . $this->getId() . '_' . $width . 'x' . $height . '_' . $bbox_dx . '_' . $i . '_r.png';
                WebmappUtils::verbose("Running 'imagecreatefrompng' on {$image_path}");
                $valid = true;
                if (file_exists($image_path)) {
                    if (extension_loaded('imagick')) {
                        WebmappUtils::verbose("  Checking image validity with Imagick");
                        try {
                            if (method_exists(Imagick::class, "valid")) {
                                $method = new ReflectionMethod ('Imagick', 'valid');
                                if ($method->isStatic()) {
                                    WebmappUtils::verbose("  Using static method");
                                    $valid = Imagick::valid($image_path);
                                } else {
                                    WebmappUtils::verbose("  Using instance method");
                                    $imagick = new Imagick($image_path);
                                    $valid = $imagick->valid();
                                }
                            }
                        } catch (Exception $e) {
                            $valid = false;
                        }
                    }
                    if ($valid) {
                        WebmappUtils::verbose("  Image is valid or Imagick is not available");
                        $source = @imagecreatefrompng($image_path);
                        if ($source) {
                            $rotated = imagerotate($source, 90, 0);
                            imagepng($rotated, $r_image_path);
                            WebmappUtils::verbose("  File {$r_image_path} written successfully");
                        } else
                            WebmappUtils::warning("File {$image_path} is not a valid png file. {$r_image_path} will be skipped");
                    } else
                        WebmappUtils::warning("File {$image_path} is not a valid png file. {$r_image_path} will be skipped");
                } else WebmappUtils::warning("File {$image_path} does not exists. {$r_image_path} will be skipped");

                $i++;
            }
            $this->addProperty('rb_images', $images);
        }
    }

    public function computeDistance3857($instance_id = '') {
        //ST_Length(ST_Transform(geom,3857))
        $l = 0;
        if (empty($instance_id)) {
            $instance_id = WebmappProjectStructure::getInstanceId();
        }
        $pg = WebmappPostGis::Instance();
        $q = "SELECT ST_Length(ST_Transform(geom,3857)) as l
                 FROM track
                 WHERE track_id={$this->getId()} AND
                       instance_id='$instance_id';";
        $r = $pg->select($q);
        if (count($r) > 0) {
            $l = $r[0]['l'];
        }

        return $l;
    }

    public function getRunningPoints($n, $instance_id = '') {
        // Gestione della ISTANCE ID
        if (empty($instance_id)) {
            $instance_id = WebmappProjectStructure::getInstanceId();
        }
        $pg = WebmappPostGis::Instance();
        $results = array();
        for ($i = 0; $i <= $n; $i++) {
            $p = $i / $n;
            $id = $this->getId();
            $q = "SELECT ST_X(ST_Transform(ST_Lineinterpolatepoint(geom,$p),3857)) as x,
                            ST_Y(ST_Transform(ST_Lineinterpolatepoint(geom,$p),3857)) as y
                     FROM track
                     WHERE track_id=$id AND
                     instance_id='$instance_id';";
            $r = $pg->select($q);
            $results[] = array($r[0]['x'], $r[0]['y']);
        }

        return $results;
    }

    public function generateLandscapeRBImages($instance_id = '', $path = '') {
        $this->generateRBImages(624, 491, 3300, $instance_id, $path);
    }

    public function writeRBRelatedPoi($path, $instance_id = '') {
        if (empty($instance_id)) {
            $instance_id = WebmappProjectStructure::getInstanceId();
        }

        $ids = array();
        if (isset($this->properties['related']['poi']['related'])
            && count($this->properties['related']['poi']['related']) > 0) {
            $l = new WebmappLayer("{$this->getId()}_rb_related_poi");
            foreach ($this->properties['related']['poi']['related'] as $pid) {
                $poi_url = preg_replace('|track/[0-9]*|', '', $this->properties['source']) . 'poi/' . $pid;
                try {
                    $poi = new WebmappPoiFeature($poi_url);
                    $noDetails = $poi->getProperty('noDetails');
                    $noInteraction = $poi->getProperty('noInteraction');
                    if (!$noDetails && !$noInteraction) {
                        $l->addFeature($poi);
                        $ids[] = $poi->getId();
                    }
                } catch (Exception $e) {
                    WebmappUtils::verbose("WARNING Exception thrown " . get_class($e) . ": {$e->getMessage()}");
                }
            }
            if (count($ids) > 0) {
                $l_ordered = new WebmappLayer("{$this->getId()}_rb_related_poi");
                $q_in = implode(',', $ids);
                $track_id = $this->getId();
                $pg = WebmappPostGis::Instance();
                $q = "WITH
                            punti AS ( SELECT * FROM poi WHERE poi_id IN ($q_in) AND instance_id =  '$instance_id' ),
                            traccia as ( SELECT * FROM track WHERE track_id = $track_id AND instance_id = '$instance_id' )
                          SELECT
                            punti.poi_id AS ID,
                            ST_Length(ST_LineSubstring(ST_Transform(traccia.geom,3857),
                                ST_LineLocatePoint(ST_Transform(traccia.geom,3857),ST_StartPoint(ST_Transform(traccia.geom,3857))),
                                ST_LineLocatePoint(ST_Transform(traccia.geom,3857),ST_ClosestPoint(ST_Transform(traccia.geom,3857),ST_Transform(punti.geom,3857))))) AS length
                          FROM traccia, punti
                          ORDER BY length;";
                $res = $pg->select($q);
                $sequence = 1;
                $ordered_ids = array();
                foreach ($res as $item) {
                    $poi = $l->getFeature($item['id']);
                    $ordered_ids[] = $item['id'];
                    $poi->addProperty('sequence', $sequence);
                    $l_ordered->addFeature($poi);
                    $sequence++;
                }
                $this->properties['related']['poi']['roadbook'] = $ordered_ids;
                $l_ordered->write($path);
            }
        }
    }

    public function orderRelatedPois(array $pois) {
        if (count($pois) > 0) {
            $pg = WebmappPostGis::Instance();
            $id = $this->getId();
            $instanceId = WebmappProjectStructure::getInstanceId();
            $poisIds = [];

            foreach ($pois as $poi) {
                if (isset($poi["properties"]) && isset($poi["properties"]["id"])) {
                    $poisIds[] = $poi["properties"]["id"];
                    $pg->insertPoi($instanceId, $poi["properties"]["id"], $poi["geometry"]["coordinates"][0], $poi["geometry"]["coordinates"][1]);
                }
            }

            $queryInput = implode(',', $poisIds);
            $q = "WITH
            punti AS ( SELECT * FROM poi WHERE poi_id IN ($queryInput) AND instance_id =  '$instanceId' ),
            traccia as ( SELECT * FROM track WHERE track_id = $id AND instance_id = '$instanceId' )
          SELECT
            punti.poi_id AS ID,
            ST_Length(ST_LineSubstring(ST_Transform(traccia.geom,3857),
                ST_LineLocatePoint(ST_Transform(traccia.geom,3857),ST_StartPoint(ST_Transform(traccia.geom,3857))),
                ST_LineLocatePoint(ST_Transform(traccia.geom,3857),ST_ClosestPoint(ST_Transform(traccia.geom,3857),ST_Transform(punti.geom,3857))))) AS length
          FROM traccia, punti
          ORDER BY length;";
            $res = $pg->select($q);
            $ordered_ids = array();
            foreach ($res as $item) {
                $ordered_ids[] = intval($item['id']);
            }

            $this->properties['related']['poi']['related'] = $ordered_ids;
            $this->properties['id_pois'] = $ordered_ids;
        }
    }

    protected function mappingSpecific($json_array) {
        $this->setProperty('n7webmapp_track_color', $json_array, 'color');
        $this->setProperty('n7webmap_start', $json_array, 'from');
        $this->setProperty('n7webmap_end', $json_array, 'to');
        $this->setProperty('ref', $json_array);
        $this->setPropertyFloat('ascent', $json_array);
        $this->setPropertyFloat('descent', $json_array);
        $this->setPropertyFloat('ele:min', $json_array);
        $this->setPropertyFloat('ele:max', $json_array);
        $this->setPropertyFloat('ele:from', $json_array);
        $this->setPropertyFloat('ele:to', $json_array);
        $this->setPropertyFloat('distance', $json_array);
        $this->setProperty('duration:forward', $json_array);
        $this->setProperty('duration:backward', $json_array);
        $this->setProperty('cai_scale', $json_array);
        $this->setProperty('stroke_width', $json_array);

        // ADD START AND END POI
        if (isset($json_array['n7webmap_start_poi']) &&
            is_array($json_array['n7webmap_start_poi']) &&
            count($json_array['n7webmap_start_poi']) > 0) {
            $from_id = $json_array['n7webmap_start_poi'][0]['ID'];
            $this->addProperty('from_poi', $from_id);
        }
        if (isset($json_array['n7webmap_end_poi']) &&
            is_array($json_array['n7webmap_end_poi']) &&
            count($json_array['n7webmap_end_poi']) > 0) {
            $from_id = $json_array['n7webmap_end_poi'][0]['ID'];
            $this->addProperty('to_poi', $from_id);
        }

        // PREV AND NEXT TRACK
        if (isset($json_array['prev_track']) &&
            is_array($json_array['prev_track']) &&
            count($json_array['prev_track']) > 0) {
            $this->addProperty('prev_track', $json_array['prev_track'][0]);
        }

        if (isset($json_array['next_track']) &&
            is_array($json_array['next_track']) &&
            count($json_array['next_track']) > 0) {
            $this->addProperty('next_track', $json_array['next_track'][0]);
        }

        // ADD id_pois
        $related_pois_id = $this->getRelatedPoisId();
        $json_array['id_pois'] = $related_pois_id;
        $this->setProperty('id_pois', $json_array);
        if (count($related_pois_id) > 0) {
            $this->properties['related']['poi']['related'] = $related_pois_id;
        }

        // ROADBOOK
        if (isset($json_array['rb_track_section']) && !empty($json_array['rb_track_section'])) {
            $this->addProperty('rb_track_section', $json_array['rb_track_section']);
        }

        // SURFACE
        if (isset($json_array['surface']) && is_array($json_array['surface']) && count($json_array['surface'])) {
            $surface = array();
            foreach ($json_array['surface'] as $item) {
                $surface[$item['surface_type']] = $item['surface_type_percentage'] / 100;
            }
            $this->addProperty('surface', $surface);
        }
    }

    protected function mappingGeometry($json_array) {
        if (isset($json_array['n7webmap_geojson']) && !empty($json_array['n7webmap_geojson'])) {
            $this->geometry = unserialize($json_array['n7webmap_geojson']);
        }
        if ((!isset($this->geometry) || empty($this->geometry)) && $this->hasProperty('osmid')) {
            try {
                $osmid = $this->getProperty('osmid');
                WebmappUtils::verbose("Generating track geometry using osmid {$osmid}");
                $pg = WebmappPostGisOsm::Instance();
                $this->setGeometryGeoJSON($pg->getRelationJsonGeometry($osmid));
                $this->setRelation($osmid);
                //                $this->setOsmProperties();
            } catch (Exception $e) {
                echo "\n\n\nWARNING Exception " . get_class($e) . " thrown. " . $e->getMessage() . "\n";
                echo "Geometry not set\n\n\n";
            }
        }
    }

    public function setOsmProperties(bool $setColor = true) {
        if ($setColor) {
            $red_match = '/red:red:white_stripe:[^:]+:black/';
            $color = '#636363';
            if ($this->_relation->hasTag('source') &&
                $this->_relation->getTag('source') == 'survey:CAI') {
                $color = '#A63FD1';
                if ($this->_relation->hasTag('osmc:symbol') &&
                    preg_match($red_match, $this->_relation->getTag('osmc:symbol'))) {
                    $color = '#E35234';
                }
            }
            if (!$this->hasProperty("color") && $color)
                $this->addProperty("color", $color);
        }

        // ADD lineDash for alternate
        if ($this->_relation->hasTag('state') && $this->_relation->getTag('state') == 'alternate' && !$this->hasProperty('lineDash'))
            $this->addProperty('lineDash', array(12, 8));

        // TODO: Move this code to a mapping specific/mapping standard
        $mapProperties = array(
            "cai_scale",
            "name",
            "from",
            "to",
            "stroke_opacity",
            "stroke_width",
            "line_dash",
            "duration:forward",
            "duration:backward",
            "ref"
        );
        foreach ($mapProperties as $property) {
            if (!$this->hasProperty($property) && $this->_relation->hasTag($property) && !empty($this->_relation->getTag($property)))
                $this->addProperty($property, $this->_relation->getTag($property));
        }
    }

    /**
     * @throws WebmappExceptionFeaturesNoGeometry
     * @throws WebmappExceptionGeoJsonBadGeomType
     */
    public function invertGeometry() {
        if (isset($this->geometry["type"]) && $this->geometry["type"] === "LineString" && isset($this->geometry["coordinates"]) && is_array($this->geometry["coordinates"]))
            $this->geometry["coordinates"] = array_reverse($this->geometry["coordinates"], false);
        elseif (isset($this->geometry["type"]) && $this->geometry["type"] !== "LineString")
            throw new WebmappExceptionGeoJsonBadGeomType("The inversion of geometry type {$this->geometry["type"]} is not supported");
        else
            throw new WebmappExceptionFeaturesNoGeometry("The geometry is not present or is malformed");
    }
}
