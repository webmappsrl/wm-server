<?php

class WebmappPranzosanofuoricasaTask extends WebmappAbstractTask
{

    private $url;
    private $max;
    private $perPage;
    private $types = [];

    public function check()
    {
        // OPTIONS
        if (!array_key_exists('url', $this->options)) {
            throw new Exception("L'opzione URL è obbligatoria", 1);
        }
        if (!array_key_exists('max', $this->options)) {
            throw new Exception("L'opzione max è obbligatoria", 1);
        }
        if (!array_key_exists('types', $this->options)) {
            throw new Exception("L'opzione types è obbligatoria", 1);
        }

        $this->url = $this->options['url'];
        $this->max = $this->options['max'];
        $this->perPage = isset($this->options['per_page']) && is_numeric($this->options['per_page']) ? $this->options['per_page'] : 10;
        $this->types = explode(',', $this->options['types']);

        return true;
    }

    public function process()
    {
        echo "\n\nProcessing WebmappPranzosanofuoricasaTask\n\n";
        foreach ($this->types as $type) {
            $this->processLayer($type);
        }
    }

    private function processLayer($type)
    {
        echo "\nProcessing Layer for $type\n";
        $l = new WebmappLayer($type);
        $page = 0;
        $count = null;
        $total = 0;
        $perPage = $this->perPage;
        do {
            $page++;
            $api = "{$this->url}/{$type}?per_page={$perPage}&page={$page}&orderby=slug&order=asc";
            echo "Getting data form URL $api ...\n";
            $items = WebmappUtils::getJsonFromApi($api);
            if (isset($items['data']['status']) && $items['data']['status'] == 400) {
                $count = 0;
            } else {
                $count = count($items);
            }
            $total += $count;
            if ($count > 0) {
                echo "Found $count items... adding to layer\n";

                foreach ($items as $ja) {
                    $j = [];
                    $j['id'] = $ja['id'];
                    $j['title']['rendered'] = $ja['title']['rendered'];
                    $j['content']['rendered'] = '';

                    if (!empty($ja['acf']['giornochiusura'][0])) {
                        $j['content']['rendered'] .= "<p><span class=\"vt_chiusura\">Giorno di chiusura</span>: " . $ja['acf']['giornochiusura'][0] . "</p>";
                    }
                    $j['content']['rendered'] .= $ja['content']['rendered'];

                    if (!empty($ja['acf']['gallery'])) {
                        $j['n7webmap_media_gallery'] = $ja['acf']['gallery'];
                    }

                    if (!empty($ja['acf']['telefono'])) {
                        $j['contact:phone'] = $ja['acf']['telefono'];
                    }
                    if (!empty($ja['acf']['email'])) {
                        if (is_array($ja['acf']['email']) && count($ja['acf']['email']) > 0)
                            $j['contact:email'] = $ja['acf']['email'][0];
                        else if (is_string($ja['acf']['email']))
                            $j['contact:email'] = $ja['acf']['email'];
                    }

                    $j['opening_hours'] = "";
                    if (!empty($ja['_links']["self"][0])) {
                        $j['content']['rendered'] .= "<p>Vedi tutti i dettagli su: <a href=\"" . $ja['_links']["self"][0] . "\">pranzosanofuoricasa.it</a></p>";
                    }

                    if (isset($ja["acf"])) {
                        foreach ($ja['acf'] as $acfKey => $acfValue) {
                            $j[$acfKey] = $acfValue;
                        }
                    }

                    if (isset($j['indirizzo'])) {
                        if (isset($j['indirizzo']['lat']) && isset($j['indirizzo']['lng'])) {
                            $j['n7webmap_coord'] = [
                                'lat' => floatVal($j['indirizzo']['lat']),
                                'lng' => floatVal($j['indirizzo']['lng'])
                            ];
                        }
                        if (isset($j['indirizzo']['address'])) {
                            $j["address"] = $j['indirizzo']['address'];
                            if (isset($j['indirizzo']['city']))
                                $j["address"] .= ', ' . $j['indirizzo']['city'];
                            if (isset($j['indirizzo']['post_code']))
                                $j["address"] .= ' ' . $j['indirizzo']['post_code'];
                            if (isset($j['indirizzo']['state_short']))
                                $j["address"] .= ', ' . $j['indirizzo']['state_short'];
                        }
                    }

                    if (isset($j['telefono'])) {
                        $j['contact:phone'] = $j['telefono'];
                    }

                    if (isset($j['email'])) {
                        $j['contact:email'] = $j['email'];
                    }

                    if (isset($j['sitoweb'])) {
                        $j['related_url'] = [$j['sitoweb']];
                    }

                    echo "Creating the poi...\n";
                    try {
                        $poi = new WebmappPoiFeature($j);
                    } catch (WebmappExceptionPOINoCoodinates $e) {
                        echo "WARN: skipping coordinates for poi with ID {$j['id']}\n";
                        $poi = new WebmappPoiFeature($j, true);
                    }
                    $provincia = '';
                    if (!empty($ja['acf']['provincia'])) {
                        $provincia = $ja['acf']['provincia'];
                        $poi->addProperty('provincia', $provincia);
                    }
                    if (!empty($ja['acf']['vt_carte'][0])) {
                        $carte = $ja['meta-fields']['vt_carte'][0];
                        $poi->addProperty('carte', $carte);
                    }
                    if (!empty($ja['acf']['facebook'][0])) {
                        $fb = $ja['acf']['facebook'][0];
                        $poi->addProperty('facebook', $fb);
                    }
                    if (!empty($ja['acf']['twitter'][0])) {
                        $tw = $ja['acf']['twitter'][0];
                        $poi->addProperty('twitter', $tw);
                    }
                    if (!empty($ja['acf']['googleplus'][0])) {
                        $gp = $ja['acf']['googleplus'][0];
                        $poi->addProperty('gplus', $gp);
                    }

                    if (!empty($ja['acf']['sitoweb'][0])) {
                        $web = $ja['acf']['sitoweb'][0];
                        $poi->addProperty('web', $web);
                    }

                    if (!empty($ja['meta-fields']['vt_data_inizio'][0])) {
                        $poi->addProperty('date_start', $ja['meta-fields']['vt_data_inizio'][0]);
                    }
                    if (!empty($ja['meta-fields']['vt_data_fine'][0])) {
                        $poi->addProperty('date_stop', $ja['meta-fields']['vt_data_fine'][0]);
                    }

                    if (!empty($ja['vt_featured_image'])) {
                        $poi->setImage($ja['vt_featured_image']);
                    }

                    // TASSONOMIA:
                    $tax = array();
                    $tax['tipo'] = array($type);
                    $tax['localita'] = array(empty($provincia) ? null : $provincia);
                    $tags = array();
                    if (isset($ja['tags']) && is_array($ja['tags'])) {
                        $tags = $ja['tags'];
                    }
                    $tax['tags'] = $tags;
                    $recipeCategories = array();
                    if (isset($ja['categorie-ricette']) && is_array($ja['categorie-ricette'])) {
                        $recipeCategories = $ja['categorie-ricette'];
                    }
                    echo json_encode($recipeCategories) . "\n\n";
                    $tax['categorie-ricette'] = $recipeCategories;
                    $poi->addProperty('taxonomy', $tax);

                    $properties = $poi->getProperties();
                    foreach ($properties as $key => $value) {
                        if (is_string($properties[$key]) && (empty($properties[$key]) || $properties[$key] === 'unknown'))
                            $poi->removeProperty($key);
                    }


                    //$poi->addProperty($key, $value);
                    $l->addFeature($poi);
                }
            } else {
                echo "No more items found.\n";
            }

        } while ($count > 0 && $total < $this->max);

        echo "Writing $total POI\n";
        $l->write($this->project_structure->getPathGeojson());
    }
}
