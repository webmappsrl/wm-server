<?php

class WebmappAllRoutesTask extends WebmappAbstractTask
{

    private $url;
    private $endpoint;

    private $process_route_index = false;
    private $routes_id = array();
    private $mbtiles_route_ids = array();

    private $encrypt = false;

    public function check()
    {

        // Controllo parametro code http://[code].be.webmapp.it
        if (!array_key_exists('url_or_code', $this->options)) {
            throw new WebmappExceptionConfTask("L'array options deve avere la chiave 'url_or_code'", 1);
        }

        if (array_key_exists('routes', $this->options)) {
            $this->routes_id = $this->options['routes'];
            if (is_array($this->routes_id) && count($this->routes_id) > 0) {
                $this->process_route_index = true;
            }
        }

        $code = $this->options['url_or_code'];
        if (preg_match('|^http://|', $code) || preg_match('|^https://|', $code)) {
            $this->url = $code;
        } else {
            $this->url = "http://$code.be.webmapp.it";
        }

        global $wm_config;
        if (!isset($wm_config['endpoint']['a'])) {
            throw new WebmappExceptionConfEndpoint("No ENDPOINT section in conf.json", 1);
        }

        $this->endpoint = $wm_config['endpoint']['a'] . '/' . preg_replace("(^https?://)", "", $this->url);

        if (!file_exists($this->endpoint)) {
            throw new WebmappExceptionAllRoutesTaskNoEndpoint("Directory {$this->endpoint} does not exists", 1);
        }

        if (array_key_exists('encrypt', $this->options)) {
            $this->encrypt = $this->options['encrypt'];
        }

        return true;
    }

    public function process()
    {

        // 1. Creare i link simbolici alla directory geojson
        $this->processSymLinks();

        // 2. Pulire le tassonomie della parte comune iniziale /taxonomies/*
        // rimuovendo la sezione items relativa a POI e TRACK
        $this->processMainTaxonomies();

        // 3. Creare le directory routes/[route_id]
        // 4. Creazione del file di tassonomia
        // /routes/[route_id]/taxonomies/activity.json
        // deve avere solo la sezione "term_id":"items":"track"
        // con la lista di tutte le TRACK di quel termine

        // 5. Creazione del file di tassonomia
        // /routes/[route_id]/taxonomies/webmapp_category.json
        // deve avere solo la sezione "term_id":"items":"poi"
        // con la lista di tutti i POI di quel termine
        $this->processRoutes();

        $this->processRouteIndex();

        $this->reportMbtiles();

        return true;
    }

    private function processRouteIndex()
    {
        if ($this->process_route_index) {
            // Load a/xxx/geojson/route_index.geojson
            $a_route_index = $this->endpoint . '/geojson/route_index.geojson';
            if (!file_exists($a_route_index)) {
                throw new WebmappExceptionAllRoutesTaskNoRouteIndex();
            }

            $all_routes = file_get_contents($a_route_index);
            if ($this->encrypt) {
                $all_routes = WebmappUtils::decrypt($all_routes);
            }
            $all_routes = json_decode($all_routes, true);
            $features = $all_routes['features'];
            $new_features = array();
            foreach ($features as $feature) {
                $id = $feature['properties']['id'];
                if (in_array((int) $id, $this->routes_id)) {
                    $new_features[] = $feature;
                }
            }
            $j = array();
            $j['type'] = 'FeatureCollection';
            $j['features'] = $new_features;
            $out = $this->getRoot() . '/routes/index.geojson';
            $j = json_encode($j);
            if ($this->encrypt) {
                $j = WebmappUtils::encrypt($j);
            }
            file_put_contents($out, $j);

            $a_route_index = $this->endpoint . '/geojson/full_geometry_route_index.geojson';
            if (file_exists($a_route_index)) {
                $all_routes = file_get_contents($a_route_index);
                if ($this->encrypt) {
                    $all_routes = WebmappUtils::decrypt($all_routes);
                }
                $all_routes = json_decode($all_routes, true);
                $features = $all_routes['features'];
                $new_features = array();
                foreach ($features as $feature) {
                    $id = $feature['properties']['id'];
                    if (in_array((int) $id, $this->routes_id)) {
                        $new_features[] = $feature;
                    }
                }
                $j = array();
                $j['type'] = 'FeatureCollection';
                $j['features'] = $new_features;
                $out = $this->getRoot() . '/routes/full_geometry_route_index.geojson';
                $j = json_encode($j);
                if ($this->encrypt) {
                    $j = WebmappUtils::encrypt($j);
                }
                file_put_contents($out, $j);
            }
        }
    }

    private function processSymLinks()
    {
        $src = $this->getRoot() . '/geojson';
        $trg = $this->endpoint . '/geojson';
        $cmd = "rm -Rf $src";
        system($cmd);
        $cmd = "ln -s $trg $src";
        system($cmd);

        $tax_dir = $this->getRoot() . '/taxonomies';
        if (!file_exists($tax_dir)) {
            $cmd = "mkdir $tax_dir";
            system($cmd);
        }

        $src = $this->getRoot() . '/track';
        $trg = $this->endpoint . '/track';
        $cmd = "rm -Rf $src";
        system($cmd);
        if (file_exists($trg)) {
            $cmd = "ln -s $trg $src";
            system($cmd);
        }

        // Route images link
        $media_dir = $this->getRoot() . '/media';
        if (!file_exists($media_dir)) {
            $cmd = "mkdir $media_dir";
            system($cmd);
        }

        $src = $this->getRoot() . '/media/route_images';
        $trg = $this->endpoint . '/route';
        if (!file_exists($src) && file_exists($trg)) {
            $cmd = "ln -s $trg $src";
            system($cmd);
        }
    }

    private function processMainTaxonomies()
    {
        // webmapp_category: tolgo items
        $src = $this->endpoint . '/taxonomies/webmapp_category.json';
        $trg = $this->getRoot() . '/taxonomies/webmapp_category.json';
        $ja = json_decode(file_get_contents($src), true);
        $ja_new = array();
        foreach ($ja as $id => $term) {
            if (isset($term['items'])) {
                unset($term['items']);
            }

            $ja_new[$id] = $term;
        }
        file_put_contents($trg, json_encode($ja_new));

        // activity
        $src = $this->endpoint . '/taxonomies/activity.json';
        $trg = $this->getRoot() . '/taxonomies/activity.json';
        $ja = json_decode(file_get_contents($src), true);
        $ja_new = array();
        foreach ($ja as $id => $term) {
            if (isset($term['items']['poi'])) {
                unset($term['items']['poi']);
            }

            if (isset($term['items']['track'])) {
                unset($term['items']['track']);
            }

            $ja_new[$id] = $term;
        }
        file_put_contents($trg, json_encode($ja_new));

        // theme
        $src = $this->endpoint . '/taxonomies/theme.json';
        $trg = $this->getRoot() . '/taxonomies/theme.json';
        $cmd = "cp -f $src $trg";
        system($cmd);

        // when
        $src = $this->endpoint . '/taxonomies/when.json';
        $trg = $this->getRoot() . '/taxonomies/when.json';
        $cmd = "cp -f $src $trg";
        system($cmd);

        // where
        $src = $this->endpoint . '/taxonomies/where.json';
        $trg = $this->getRoot() . '/taxonomies/where.json';
        $cmd = "cp -f $src $trg";
        system($cmd);

        // who
        $src = $this->endpoint . '/taxonomies/who.json';
        $trg = $this->getRoot() . '/taxonomies/who.json';
        $cmd = "cp -f $src $trg";
        system($cmd);

    }

    private function processRoutes()
    {
        $route_index = $this->endpoint . '/geojson/route_index.geojson';
        if (!file_exists($this->getRoot() . '/routes')) {
            $cmd = 'mkdir ' . $this->getRoot() . '/routes';
            system($cmd);
        }
        if (file_exists($route_index)) {
            $ja = file_get_contents($this->endpoint . '/geojson/route_index.geojson');
            if ($this->encrypt) {
                $ja = WebmappUtils::decrypt($ja);
            }
            $ja = json_decode($ja, true);
            if (isset($ja['features']) && count($ja['features']) > 0) {
                foreach ($ja['features'] as $route) {
                    $this->processRoute($route['properties']['id']);
                }
            }
        }
    }

    private function processRoute($id)
    {

        // SKIP IF is not in routes_id (only if parameter is set)
        if ($this->process_route_index && !in_array($id, $this->routes_id)) {
            echo "\n\nProcess_route_index TRUE, ROUTEID($id) not in routes_id (routes parameter in /server/config.json)... SKIPPING \n\n";
            return;
        }
        $route_path = $this->getRoot() . '/routes/' . $id;
        $route_tax_path = $this->getRoot() . '/routes/' . $id . '/taxonomies';
        if (!file_exists($route_path)) {
            $cmd = "mkdir $route_path";
            system($cmd);
        }
        if (!file_exists($route_tax_path)) {
            $cmd = "mkdir $route_tax_path";
            system($cmd);
        }

        // LOAD ROUTE FILE
        $ja = file_get_contents($this->endpoint . '/geojson/' . $id . '.geojson');
        if ($this->encrypt) {
            $ja = WebmappUtils::decrypt($ja);
        }
        $ja = json_decode($ja, true);

        // LOOP ON RELATED TRACK
        $activities = array();
        $webmapp_categories = array();
        if (isset($ja['features']) && count($ja['features']) > 0) {
            foreach ($ja['features'] as $track) {
                if (isset($track['properties']['taxonomy']) &&
                    isset($track['properties']['taxonomy']['activity']) &&
                    count($track['properties']['taxonomy']['activity']) > 0) {
                    foreach ($track['properties']['taxonomy']['activity'] as $term_id) {
                        $activities[$term_id]['items']['track'][] = $track['properties']['id'];
                    }
                }
                if (isset($track['properties']['related']) &&
                    isset($track['properties']['related']['poi']) &&
                    isset($track['properties']['related']['poi']['related']) &&
                    count($track['properties']['related']['poi']['related']) > 0) {
                    foreach ($track['properties']['related']['poi']['related'] as $pid) {
                        $poi = file_get_contents($this->endpoint . '/geojson/' . $pid . '.geojson');
                        if ($this->encrypt) {
                            $poi = WebmappUtils::decrypt($poi);
                        }
                        $poi = json_decode($poi, true);
                        if (isset($poi['properties']['taxonomy']) &&
                            isset($poi['properties']['taxonomy']['webmapp_category']) &&
                            count($poi['properties']['taxonomy']['webmapp_category']) > 0) {
                            foreach ($poi['properties']['taxonomy']['webmapp_category'] as $term_id) {
                                $webmapp_categories[$term_id]['items']['poi'][] = $poi['properties']['id'];
                            }
                        }
                    }
                }
            }
        }
        if (count($activities) > 0) {
            file_put_contents($route_tax_path . '/activity.json', json_encode($activities));
        } else {
            file_put_contents($route_tax_path . '/activity.json', '{}');
        }
        if (count($webmapp_categories) > 0) {
            file_put_contents($route_tax_path . '/webmapp_category.json', json_encode($webmapp_categories));
        } else {
            file_put_contents($route_tax_path . '/webmapp_category.json', '{}');
        }

        // Generazione del file map.json
        // Al momento si deve distinguere il caso presente nelle API e non
        $map = array();
        $jb = WebmappUtils::getJsonFromApi($ja['properties']['source']);
        if (isset($jb['n7webmapp_route_bbox']) && !empty($jb['n7webmapp_route_bbox'])) {
            echo "Building map.json info from WP\n";
            $bb = json_decode($jb['n7webmapp_route_bbox'], true);
            if (is_array($bb)) {
                $map['maxZoom'] = $bb['maxZoom'];
                $map['minZoom'] = $bb['minZoom'];
                $map['defZoom'] = $bb['defZoom'];
                $map['center'][0] = $bb['center']['lng'];
                $map['center'][1] = $bb['center']['lat'];
                $map['bbox'][0] = $bb['bounds']['southWest'][1];
                $map['bbox'][1] = $bb['bounds']['southWest'][0];
                $map['bbox'][2] = $bb['bounds']['northEast'][1];
                $map['bbox'][3] = $bb['bounds']['northEast'][0];
            } else {
                echo "Building map.json info from route bbox (NO ARRAY)\n";
                $map['maxZoom'] = 16;
                $map['minZoom'] = 8;
                $map['defZoom'] = 9;
                $bb = explode(',', $ja['properties']['bbox']);
                $map['center'][0] = ($bb[0] + $bb[2]) / 2.0;
                $map['center'][1] = ($bb[1] + $bb[3]) / 2.0;
                $map['bbox'][0] = (float) $bb[0];
                $map['bbox'][1] = (float) $bb[1];
                $map['bbox'][2] = (float) $bb[2];
                $map['bbox'][3] = (float) $bb[3];
            }
        } else {
            echo "Building map.json info from route bbox\n";
            $map['maxZoom'] = 16;
            $map['minZoom'] = 8;
            $map['defZoom'] = 9;
            $bb = explode(',', $ja['properties']['bbox']);
            $map['center'][0] = ($bb[0] + $bb[2]) / 2.0;
            $map['center'][1] = ($bb[1] + $bb[3]) / 2.0;
            $map['bbox'][0] = (float) $bb[0];
            $map['bbox'][1] = (float) $bb[1];
            $map['bbox'][2] = (float) $bb[2];
            $map['bbox'][3] = (float) $bb[3];
        }

        $needUpdate = false;

        if (file_exists($route_path . '/map.json')) {
            $currentMap = file_get_contents($route_path . '/map.json');
            $newMap = json_encode($map);
            $needUpdate = $currentMap != $newMap;
        } else {
            $needUpdate = true;
        }

        if ($needUpdate || !file_exists($route_path . '/map.mbtiles')) {
            file_put_contents($route_path . '/map.json', json_encode($map));
            $this->mbtiles_route_ids[] = $id;
            // $this->updateMbtiles($id);
        }
    }

    private function updateMbtiles($id)
    {
        // echo "Checking mbtiles for route $id... ";
        // $root_base_url = $this->getRoot();
        // $split = preg_split("/\//", $baseUrl);
        // $instance = '';

        // if ($root_base_url[count($root_base_url) - 1] == '/') {
        //     $instance = $split[count($split) - 2];
        // } else {
        //     $instance = $split[count($split) - 1];
        // }

        // if (file_exists($route_path))
    }

    private function reportMbtiles()
    {
        if (count($this->mbtiles_route_ids) > 0) {
            echo "Some routes need to be generated...\n";
            global $wm_config;

            $root_base_url = $this->getRoot();
            $split = preg_split("/\//", $root_base_url);
            $instance = '';
            if ($root_base_url[strlen($root_base_url) - 1] == '/') {
                $instance = $split[count($split) - 2];
            } else {
                $instance = $split[count($split) - 1];
            }

            $content = "<p>Ci sono alcune route di {$instance} che hanno bisogno di attenzione</p>";

            $imploded = implode(' ', $this->mbtiles_route_ids);
            $content .= "<p>Le route {$imploded} hanno bisogno che le mbtiles siano generate</p>";
            echo "{$imploded} have no mbtiles. Generation needed\n";

            $mail = new PHPMailer;
            $mail->isSMTP();
            $mail->Host = $wm_config["email"]["host"];
            $mail->Port = $wm_config["email"]["port"];
            $mail->SMTPSecure = $wm_config["email"]["smtpSecure"];
            $mail->SMTPAuth = true;
            $mail->Username = $wm_config["email"]["username"];
            $mail->Password = $wm_config["email"]["password"];
            $mail->setFrom('noreply@webmapp.it', 'Server mbtiles');
            // $mail->addAddress('alessiopiccioli@webmapp.it');
            // $mail->addAddress('davidepizzato@webmapp.it');
            // $mail->addAddress('gianmarcogagliardi@webmapp.it@webmapp.it');
            // $mail->addAddress('marcobarbieri@webmapp.it');
            // $mail->addAddress('pedramkatanchi@webmapp.it');
            $mail->addAddress('team@webmapp.it');
            $mail->Subject = "MBTiles {$instance}";
            $mail->msgHTML($content);

            $mail->send();
        }
    }
}
