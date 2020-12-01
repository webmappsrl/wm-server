<?php

/*
// Esempio di MAPPING per la creazione di un POI
$j=array();

// GENERAL
$j['id']=$ja[''];
$j['title']['rendered']=$ja[''];
$j['content']['rendered']=$ja[''];
$j['color']=$ja[''];
$j['icon']=$ja[''];
$j['noDetails']=$ja[''];
$j['noInteractions']=$ja[''];

// TODO RELATED POI

// IMAGES
$j['n7webmap_media_gallery']=$j[''];

// SPECIFIC POI
$j['addr:street']=$ja[''];
$j['addr:housenumber']=$ja[''];
$j['addr:postcode']=$ja[''];
$j['addr:city']=$ja[''];
$j['contact:phone']=$ja[''];
$j['contact:email']=$ja[''];
$j['opening_hours']=$ja[''];
$j['capacity']=$ja[''];
$j['address']=$ja[''];

// GEOMETRY
$j['n7webmap_coord']['lng']=$ja[''];
$j['n7webmap_coord']['lat']=$ja[''];

// Creazione del POI
$poi = new WebmappPoiFeature($j);
*/

class WebmappPoiFeature extends WebmappAbstractFeature
{
    // Mapping dei meta specifici dei punti
    // http://dev.be.webmapp.local/wp-json/wp/v2/poi/38
    public function setGeometry($lng, $lat)
    {
        $this->geometry['type'] = 'Point';
        $this->geometry['coordinates'] = array((float)$lng, (float)$lat);
    }

    // Impostazione della geometry a partire da formato API WP

    /**
     * {
     * "coordinates": [
     * 10.441684,
     * 43.762954999999998
     * ],
     * "type": "Point"
     * }
     **/

    public function getLon()
    {
        return $this->getLng();
    }

    public function getLng()
    {
        return $this->geometry['coordinates'][0];
    }

    public function getLatMax()
    {
        return $this->getLat();
    }

    public function getLat()
    {
        return $this->geometry['coordinates'][1];
    }

    public function getLatMin()
    {
        return $this->getLat();
    }

    public function getLngMax()
    {
        return $this->getLng();
    }

    public function getLngMin()
    {
        return $this->getLng();
    }

    public function writeToPostGis($instance_id = '')
    {

        // Gestione della ISTANCE ID
        if (empty($instance_id)) {
            $instance_id = WebmappProjectStructure::getInstanceId();
        }
        $id = $this->properties['id'];
        $lon = $this->geometry['coordinates'][0];
        $lat = $this->geometry['coordinates'][1];

        $pg = WebmappPostGis::Instance();
        $pg->insertPoi($instance_id, $id, $lon, $lat);

    }

    public function addRelated($distance = 5000, $limit = 100)
    {
        if ($limit > 0) {
            $limit = " LIMIT $limit";
        } else {
            $limit = '';
        }
        $id = $this->properties['id'];
        $q = "SELECT poi_b.id as id, ST_Distance(poi_a.wkb_geometry, poi_b.wkb_geometry) as distance
              FROM  poi_tmp as poi_a, poi_tmp as poi_b
              WHERE poi_a.id = $id AND poi_b.id <> $id AND ST_Distance(poi_a.wkb_geometry, poi_b.wkb_geometry) < $distance
              ORDER BY distance
              $limit ;";
        $this->addRelatedPoi($q);
    }

    public function getNeighborsByLonLat($distance, $lon, $lat, $instance_id = '')
    {
        // Gestione della ISTANCE ID
        if (empty($instance_id)) {
            $instance_id = WebmappProjectStructure::getInstanceId();
        }
        // Contruzione della query
        echo $q = "SELECT poi_id
              FROM  poi
              WHERE ST_Distance_Sphere(geom, ST_GeomFromText('POINT($lon $lat )', 4326)) < $distance
              AND instance_id='$instance_id';";

        // Esecuzione della query
        $pg = WebmappPostGis::Instance();
        $res = $pg->select($q);
        $ret = array();
        if (is_array($res) && count($res) > 0) {
            foreach ($res as $item) {
                $ret[] = $item['poi_id'];
            }
        }
        return $ret;

    }

    public function addEle()
    {
        if (isset($this->geometry['coordinates']) &&
            count($this->geometry['coordinates']) == 2) {
            $geom = json_encode($this->geometry);
            $pg = WebmappPostGis::Instance();
            $geom_3d = $pg->addEle($geom);
            $this->geometry = json_decode($geom_3d, TRUE);
        }
    }

    // Restituisce gli id dei POI all'interno del cerchio centrato in 
    // lon lat di raggio distance

    public function generateImage($width, $height, $instance_id = '', $path = '')
    {
        echo "\n\nNOT YET IMPLEMENTED!\n\n";
    }

    protected function mappingSpecific($json_array)
    {
        $this->setProperty('addr:street', $json_array);
        $this->setProperty('addr:housenumber', $json_array);
        $this->setProperty('addr:postcode', $json_array);
        $this->setProperty('addr:city', $json_array);
        $this->setProperty('contact:phone', $json_array);
        $this->setProperty('contact:email', $json_array);
        $this->setProperty('opening_hours', $json_array);
        $this->setProperty('capacity', $json_array);
        // Gestione dell'address
        if (isset($json_array['address'])) {
            $this->setProperty('address', $json_array);
        } else if ((isset($json_array['addr:street']) && (!empty($json_array['addr:street'])))
            && (isset($json_array['addr:city']) && (!empty($json_array['addr:city'])))) {
            $num = '';
            if (isset($json_array['addr:housenumber'])) {
                $num = $json_array['addr:housenumber'];
            }
            $address = $json_array['addr:street'] . ', ' . $num . ' ' . $json_array['addr:city'];
            $this->properties['address'] = $address;
        }
    }

    /**
     * @param $json_array
     * @throws WebmappExceptionPOINoCoodinates
     */
    protected function mappingGeometry($json_array)
    {

        $id = $json_array['id'];

        $lat = $lng = '';

        // CASO n7webmap_coord
        if (isset($json_array['n7webmap_coord']) &&
            isset($json_array['n7webmap_coord']['lat']) &&
            isset($json_array['n7webmap_coord']['lng']) &&
            !empty($json_array['n7webmap_coord']['lat']) &&
            !empty($json_array['n7webmap_coord']['lng'])) {
            $lng = $json_array['n7webmap_coord']['lng'];
            $lat = $json_array['n7webmap_coord']['lat'];
        } else if (isset($json_array['coordinates']) &&
            isset($json_array['coordinates']['center_lat']) &&
            isset($json_array['coordinates']['center_lng']) &&
            !empty($json_array['coordinates']['center_lat']) &&
            !empty($json_array['coordinates']['center_lng'])
        ) {
            $lng = $json_array['coordinates']['center_lng'];
            $lat = $json_array['coordinates']['center_lat'];
        } else {
            throw new WebmappExceptionPOINoCoodinates("INVALID POI no id:$id", 1);
        }

        $this->geometry['type'] = 'Point';
        $this->geometry['coordinates'] = array((float)$lng, (float)$lat);
    }
}