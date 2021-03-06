<?php

class WebmappATask extends WebmappAbstractTask
{

    private $wp;
    private $distance = 5000;
    private $limit = 10;

    private $path;
    private $track_path;
    private $route_path;
    private $tax_path;

    // Array utilizzato nelle configurazioni per forzare la rigenerazione di tipi specici
    private $force_type = array();

    private $taxonomies = array();

    private $encrypt = false;

    private $mapping;

    public function check()
    {

        // Controllo parametro code http://[code].be.webmapp.it
        if (!array_key_exists('url_or_code', $this->options)) {
            throw new Exception("'url_or_code' option is mandatory", 1);
        }

        // Controllo parametro code http://[code].be.webmapp.it
        if (array_key_exists('force_type', $this->options)) {
            $this->force_type = $this->options['force_type'];
        }

        // Controllo parametro code http://[code].be.webmapp.it
        if (array_key_exists('encrypt', $this->options)) {
            $this->encrypt = $this->options['encrypt'];
        }

        if (array_key_exists('mapping', $this->options) && is_array($this->options["mapping"])) {
            $this->mapping = $this->options['mapping'];
        }

        $wp = new WebmappWP($this->options['url_or_code']);
        // Controlla esistenza della piattaforma
        if (!$wp->check()) {
            throw new Exception("ERRORE: La piattaforma {$wp->getBaseUrl()} non risponde o non esiste.", 1);
        }
        $this->wp = $wp;
        return true;
    }

    public function process()
    {

        $this->path = $this->project_structure->getRoot() . '/geojson';
        $this->track_path = $this->project_structure->getRoot() . '/track';
        $this->route_path = $this->project_structure->getRoot() . '/route';
        $this->tax_path = $this->project_structure->getRoot() . '/taxonomies';
        if (!file_exists($this->tax_path)) {
            if (!is_writable($this->project_structure->getRoot())) {
                throw new Exception("Error Processing Request", 1);
            }
            $cmd = "mkdir {$this->tax_path}";
            system($cmd);
        }

        if (!file_exists($this->track_path)) {
            $cmd = "mkdir {$this->track_path}";
            system($cmd);
        }
        if (!file_exists($this->route_path)) {
            $cmd = "mkdir {$this->route_path}";
            system($cmd);
        }

        $this->processPois();
        $this->processTracks();
        $this->processRoutes();
        $this->processTaxonomies();
        $this->processPoiIndex();
        $this->processRouteIndex();

        return true;

    }

    private function processPois()
    {
        $this->processFeatures('poi');
    }

    private function processTracks()
    {
        $this->processFeatures('track');
    }

    private function processRoutes()
    {
        $this->processFeatures('route');
    }

    private function processTaxonomies()
    {
        $this->wp->loadTaxonomies();
        $this->wp->addItemsAndPruneTaxonomies($this->taxonomies);
        $this->wp->writeTaxonomies($this->tax_path);

        // Creates /taxonomies/[term_id].geojson
        $this->writeTaxonomyCollection('webmapp_category', 'poi');
        $this->writeTaxonomyCollection('activity', 'track');
        $this->writeTaxonomyCollection('theme', 'track');
        $this->writeTaxonomyCollection('when', 'track');
        $this->writeTaxonomyCollection('where', 'track');
        $this->writeTaxonomyCollection('who', 'track');

    }

    private function writeTaxonomyCollection($taxn, $feature_type)
    {
        // Controlla esistenza del file di tassonomia con i singoli termini -> load
        $taxf = $this->tax_path . '/' . $taxn . '.json';
        if (!file_exists($taxf)) {
            echo "WARN: $taxf does not exists\n";
            return;
        }
        echo "Writing taxonomy collection $taxf\n";
        $taxj = WebmappUtils::getJsonFromAPI($taxf);
        if (is_array($taxj) && count($taxj) > 0) {
            // Loop sui singoli termini
            foreach ($taxj as $termid => $term) {
                // Controlla esistenza degli items del tipo richiesto (count >0) -> aggiungi feature al layer
                echo "Processing term $termid ... ";
                $features = array();
                if (isset($term['items'][$feature_type]) &&
                    is_array($term['items'][$feature_type]) &&
                    count($term['items'][$feature_type]) > 0) {
                    echo "adding features ";
                    foreach ($term['items'][$feature_type] as $fid) {
                        $features[] = WebmappUtils::getJsonFromApi($this->path . '/' . $fid . '.geojson');
                    }
                } else {
                    echo "no features found ";
                }
                // Scrivi Layer
                echo " ... writing ";
                $l = array();
                $l['type'] = 'FeatureCollection';
                $l['features'] = $features;
                file_put_contents($this->tax_path . '/' . $termid . '.geojson', json_encode($l));
                echo " DONE!\n";
            }
        }
    }

    private function processFeatures($type)
    {
        $pois = $this->getListByType($type);
        if (is_array($pois) && count($pois) > 0) {
            foreach ($pois as $id => $mod) {
                echo "Checking $type $id ... ";
                $to_process = false;
                $geojson = $this->path . '/' . $id . '.geojson';
                if (!file_exists($geojson)) {
                    echo "NO Geojson ";
                    $to_process = true;
                } else {
                    $j = WebmappUtils::getJsonFromAPI($geojson, $this->encrypt);
                    if (in_array($type, $this->force_type)) {
                        $to_process = true;
                    } else if (isset($j['properties']['modified'])) {
                        $poi_mod = $j['properties']['modified'];
                        if (strtotime($mod) > strtotime($poi_mod)) {
                            echo " $type need to be updated ($mod VS $poi_mod)";
                            $to_process = true;
                        } else {
                            echo "$type updated ($mod VS $poi_mod). Skipping ";
                            $this->taxonomies[$type][$id] = $j['properties']['taxonomy'];
                            $to_process = false;
                        }
                    } else {
                        echo "Property MODIFIED missing ... updating ";
                        $to_process = true;
                    }
                }
                if ($to_process) {
                    $this->processFeature($type, $id);
                }
                echo "... DONE.\n\n";
            }
        }
    }

    private function processFeature($type, $id)
    {
        switch ($type) {
            case 'poi':
                $this->processPoi($id);
                break;
            case 'track':
                $this->processTrack($id);
                break;
            case 'route':
                $this->processRoute($id);
                break;
        }
    }

    private function processPoi($id)
    {
        $poi = new WebmappPoiFeature($this->wp->getApiPoi($id));
        $poi_properties = $this->getCustomProperties("poi");
        if (isset($poi_properties) && is_array($poi_properties)) {
            echo "CustomProperties.";
            $poi->mapCustomProperties($poi_properties);
        }

        $j = json_decode($poi->getJson(), true);
        if (isset($j['properties']['taxonomy'])) {
            $this->taxonomies['poi'][$id] = $j['properties']['taxonomy'];
        }

        $poi->write($this->path, $this->encrypt);
    }

    private function processTrack($id)
    {
        $t = new WebmappTrackFeature($this->wp->getApiTrack($id), false, $this->mapping);
        echo "related.";
        $t->addRelated($this->distance, $this->limit);
        echo "postgis.";
        $t->writeToPostGis();
        if ($t->getGeometryType() == 'LineString') {
            echo "3d.";
            $t->add3D();
            echo "computedProps";
            $t->setComputedProperties2();
            $track_properties = $this->getCustomProperties("track");
            if (isset($track_properties) && is_array($track_properties)) {
                echo "CustomProperties.";
                $t->mapCustomProperties($track_properties);
            }
            echo "bbox.";
            $t->addBBox();
            echo "RBpois.";
            $t->writeRBRelatedPoi($this->track_path);
            echo "Images.";
            $t->generateAllImages('', $this->track_path);
            $t->generateLandscapeRBImages('', $this->track_path);

            echo "GPX.";
            $t->writeGPX($this->track_path);
            echo "KML.";
            $t->writeKML($this->track_path);
        } else {
            echo "\n\n\nWARNING. Track with invalid geometry track id: {$t->getId()} geometry type: {$t->getGeometryType()}\n\n\n";
        }
        echo "write.";
        $j = json_decode($t->getJson(), true);
        if (isset($j['properties']['taxonomy'])) {
            $this->taxonomies['track'][$id] = $j['properties']['taxonomy'];
        }

        $t->write($this->path, $this->encrypt);
    }

    private function processRoute($id)
    {
        $r = new WebmappRoute($this->wp->getApiRoute($id));
        // SKIP NO TRACK
        if (count($r->getTracks()) > 0) {
            $r->writeToPostGis();
            $r->addBBox();
            $r->generateAllImages('', $this->route_path);
            $route_properties = $this->getCustomProperties("route");
            if (isset($route_properties) && is_array($route_properties)) {
                echo "CustomProperties.";
                $r->mapCustomProperties($route_properties);
            }
            $j = json_decode($r->getJson(), true);
            if (isset($j['properties']['taxonomy'])) {
                $this->taxonomies['route'][$id] = $j['properties']['taxonomy'];
            }

            $r->write($this->path, $this->encrypt);
        } else {
            echo "NO TRACKS FOUND: skipping route\n";
        }

    }

    private function getListByType($type)
    {
        $j = WebmappUtils::getJsonFromAPI($this->wp->getBaseUrl() . '/wp-json/webmapp/v1/list?type=' . $type);
        if (isset($j['code']) && $j['code'] == 'rest_no_route') {
            echo "OLD VERSION: downloading all POIS\n\n";
            $new_j = array();
            // build j from usual
            $fs = WebmappUtils::getMultipleJsonFromApi($this->wp->getBaseUrl() . '/wp-json/wp/v2/' . $type);
            if (is_array($fs) && count($fs) > 0) {
                foreach ($fs as $f) {
                    $id = $f['id'];
                    $date = $f['modified'];
                    $new_j[$id] = $date;
                }
            }
            $j = $new_j;
        }
        return $j;
    }

    private function processRouteIndex()
    {
        // Lista delle route:
        $routes = $this->getListByType('route');
        $features = array();
        $fullGeometryFeatures = array();
        $to_prune = array();

        if (count($routes) > 0) {
            foreach ($routes as $rid => $date) {
                $skip = false;
                echo "\n\n\n Processing route $rid\n";
                $feature = array();
                $trackGeometry = null;
                $r = WebmappUtils::getJsonFromAPI($this->path . '/' . $rid . '.geojson', $this->encrypt);
                $feature['properties'] = $r['properties'];
                // CAMBIA QUI il TYPE
                $feature['type'] = 'Feature';
                // Geometry (solo se ha le related track)
                if (isset($r['properties']['related']['track']['related']) &&
                    count($r['properties']['related']['track']['related']) > 0) {
                    $first_track_id = $r['properties']['related']['track']['related'][0];
                    $t = WebmappUtils::getJsonFromAPI($this->path . '/' . $first_track_id . '.geojson', $this->encrypt);
                    if (isset($t['geometry']['coordinates'])) {
                        $lon = $t['geometry']['coordinates'][0][0];
                        $lat = $t['geometry']['coordinates'][0][1];
                        $feature['geometry']['type'] = 'Point';
                        $feature['geometry']['coordinates'] = array($lon, $lat);
                        $trackGeometry = $this->simplifyGeometry($t['geometry']);
                    } else {
                        echo "Warning no GEOMETRY In first track $first_track_id ... SKIP!\n";
                        $skip = true;
                    }
                } else {
                    echo "Warning no RELATED TRACK\n";
                    $skip = true;
                }
                if (!$skip) {
                    $features[] = $feature;
                    if (isset($trackGeometry) && !empty($trackGeometry)) {
                        $fullFeature = array(
                            'properties' => $feature['properties'],
                            'type' => 'Feature',
                            'geometry' => $trackGeometry,
                        );
                        $fullGeometryFeatures[] = $fullFeature;
                    } else {
                        $fullGeometryFeatures[] = $feature;
                    }
                }

                if ($skip) {
                    $to_prune[] = $rid;
                }

            }
            $j = array();
            $j['type'] = 'FeatureCollection';
            $j['features'] = $features;
            $j = json_encode($j);
            if ($this->encrypt) {
                $j = WebmappUtils::encrypt($j);
            }

            file_put_contents($this->path . '/route_index.geojson', $j);

            $j = array();
            $j['type'] = 'FeatureCollection';
            $j['features'] = $fullGeometryFeatures;
            $j = json_encode($j);
            if ($this->encrypt) {
                $j = WebmappUtils::encrypt($j);
            }

            file_put_contents($this->path . '/full_geometry_route_index.geojson', $j);

            // // PRUNE taxonomies items
            // if(count($to_prune)>0){
            //     $this->pruneTax('activity',$to_prune);
            //     $this->pruneTax('theme',$to_prune);
            //     $this->pruneTax('who',$to_prune);
            //     $this->pruneTax('when',$to_prune);
            //     $this->pruneTax('where',$to_prune);
            // }
        }
    }

    private function simplifyGeometry($geometry)
    {
        $interval = 5;
        $pos = 0;
        $newCoordinates = array();

        if ($geometry["type"] === 'LineString') {
            while ($pos < count($geometry['coordinates'])) {
                if (isset($geometry['coordinates'][$pos][1]) && !empty($geometry['coordinates'][$pos][1])) {
                    $newCoordinates[] = array(
                        round($geometry['coordinates'][$pos][0], 3),
                        round($geometry['coordinates'][$pos][1], 3),
                        round($geometry['coordinates'][$pos][2], 0),
                    );
                } else {
                    $newCoordinates[] = array(
                        round($geometry['coordinates'][$pos][0], 3),
                        round($geometry['coordinates'][$pos][1], 3),
                    );
                }

                $pos += $interval;
            }
        }

        $geometry['coordinates'] = $newCoordinates;

        return $geometry;
    }

    private function pruneTax($name, $to_prune)
    {
        $tax_file = $this->tax_path . '/' . $name . '.json';
        if (file_exists($tax_file)) {
            $ja = WebmappUtils::getJsonFromApi($tax_file);
            foreach ($ja as $id => $term) {
                if (isset($term['items']['route']) && count($term['items']['route']) > 0) {
                    $ja[$id]['items']['route'] = array_diff($term['items']['route'], $to_prune);
                }
            }
            file_put_contents($tax_file, json_encode($ja));
        }
    }

    private function processPoiIndex()
    {
        // Lista delle route:
        $pois = $this->getListByType('poi');
        $features = array();
        if (count($pois) > 0) {
            foreach ($pois as $pid => $date) {
                $skip = false;
                echo "\n\n\n Processing POI $pid\n";
                $p = WebmappUtils::getJsonFromAPI($this->path . '/' . $pid . '.geojson', $this->encrypt);
                if (!isset($p['geometry'])) {
                    echo "Warning no GEOMETRY: SKIPPING POI\n";
                    $skip = true;
                }
                if (!$skip) {
                    $features[] = $p;
                }

            }
            $j = array();
            $j['type'] = 'FeatureCollection';
            $j['features'] = $features;
            $j = json_encode($j);
            if ($this->encrypt) {
                $j = WebmappUtils::encrypt($j);
            }

            file_put_contents($this->path . '/poi_index.geojson', $j);
        }
    }

    /**
     * Return an associative array with the key as the property in the json and the value the property to map it in
     *
     * @param $type the geometry type
     * @return null | array with the property mapping
     */
    private function getCustomProperties($type)
    {
        if (($type !== 'poi' && $type !== 'track' && $type !== 'route') || !array_key_exists('custom_mapping', $this->options)) {
            return null;
        }

        $properties = array();
        $custom_mapping = $this->options["custom_mapping"];

        // Map the global properties
        foreach ($custom_mapping as $key => $property) {
            if ($key !== 'poi' && $key !== 'track' && $key !== 'route') {
                if (is_numeric($key)) {
                    $properties[$property] = $property;
                } else {
                    $properties[$key] = $property;
                }
            }
        }

        // Map the properties specific for the geometry type
        if (array_key_exists($type, $custom_mapping)) {
            foreach ($custom_mapping[$type] as $key => $property) {
                if (is_numeric($key)) {
                    $properties[$property] = $property;
                } else {
                    $properties[$key] = $property;
                }
            }
        }

        return $properties;
    }
}
