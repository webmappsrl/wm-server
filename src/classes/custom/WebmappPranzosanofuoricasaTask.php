<?php

class WebmappPranzosanofuoricasaTask extends WebmappAbstractTask
{

    private $url;
    private $max;
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
        $count = 0;
        $total = 0;
        $perPage = 10;
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

                    if ($type == 'event') {
                        if (!empty($ja['meta-fields']['subtitle'][0])) {
                            $j['content']['rendered'] = "<h4 class=\"subtitle\">" . $ja['meta-fields']['subtitle'][0] . "</h4>";
                        }

                        if (!empty($ja['meta-fields']['orari'][0])) {
                            $j['content']['rendered'] .= "<h5 class=\"event-date\">Orari " . $ja['meta-fields']['orari'][0] . "</h5>";
                        }

                        $j['content']['rendered'] .= $ja['content']['rendered'];

                        if (!empty($ja['acf']['location']['address'])) {
                            $j['address'] = $ja['acf']['location']['address'];
                        }
                        if (!empty($ja['acf']['location']['lng'])) {
                            $j['n7webmap_coord']['lng'] = $ja['acf']['location']['lng'];
                        }
                        if (!empty($ja['acf']['location']['lat'])) {
                            $j['n7webmap_coord']['lat'] = $ja['acf']['location']['lat'];
                        }

                    } else {
                        if (!empty($ja['meta-fields']['vt_chiusura'][0])) {
                            $j['content']['rendered'] .= "<p><span class=\"vt_chiusura\">Giorno di chiusura</span>: " . $ja['meta-fields']['vt_chiusura'][0] . "</p>";
                        }
                        $j['content']['rendered'] .= $ja['content']['rendered'];

                        if (!empty($ja['acf']['vt_google_map']['address'])) {
                            $j['address'] = $ja['acf']['vt_google_map']['address'];
                        }
                        if (!empty($ja['acf']['vt_google_map']['lng'])) {
                            $j['n7webmap_coord']['lng'] = $ja['acf']['vt_google_map']['lng'];
                        } elseif (!empty($ja['meta-fields']['_tmp_lon'])) {
                            $j['n7webmap_coord']['lng'] = $ja['meta-fields']['_tmp_lon'];
                        }
                        if (!empty($ja['acf']['vt_google_map']['lat'])) {
                            $j['n7webmap_coord']['lat'] = $ja['acf']['vt_google_map']['lat'];
                        } elseif (!empty($ja['meta-fields']['_tmp_lat'])) {
                            $j['n7webmap_coord']['lat'] = $ja['meta-fields']['_tmp_lat'];
                        }
                    }

                    if (!empty($ja['acf']['vt_gallery'])) {
                        $j['n7webmap_media_gallery'] = $ja['acf']['vt_gallery'];
                    }

                    if (!empty($ja['meta-fields']['vt_telefono'][0])) {
                        $j['contact:phone'] = $ja['meta-fields']['vt_telefono'][0];
                    }
                    if (!empty($ja['meta-fields']['vt_email'][0])) {
                        $j['contact:email'] = $ja['meta-fields']['vt_email'][0];
                    }

                    $j['opening_hours'] = "";
                    if ($type == 'restaurant' || $type == 'shop') {
                        if (!empty($ja['meta-fields']['vt_dalleorepranzo'][0])) {
                            $j['opening_hours'] .= "Dalle " . $ja['meta-fields']['vt_dalleorepranzo'][0] . " ";
                        }
                        if (!empty($ja['meta-fields']['vt_alleorepranzo'][0])) {
                            $j['opening_hours'] .= "Alle " . $ja['meta-fields']['vt_alleorepranzo'][0] . " ";
                        }
                        if (!empty($ja['meta-fields']['vt_dalleorecena'][0])) {
                            $j['opening_hours'] .= "Dalle " . $ja['meta-fields']['vt_dalleorecena'][0] . " ";
                        }
                        if (!empty($ja['meta-fields']['vt_alleorecena'][0])) {
                            $j['opening_hours'] .= "Alle " . $ja['meta-fields']['vt_alleorecena'][0] . " - ";
                        }
                    } else if ($type == 'producer') {

                        if (!empty($ja['meta-fields']['vt_aperturainizioda'][0])) {
                            $j['opening_hours'] .= "Da " . $ja['meta-fields']['vt_aperturainizioda'][0] . " ";
                        }
                        if (!empty($ja['meta-fields']['vt_aperturainizioa'][0])) {
                            $j['opening_hours'] .= "A " . $ja['meta-fields']['vt_aperturainizioa'][0] . " ";
                        }
                        if (!empty($ja['meta-fields']['vt_aperturafineda'][0])) {
                            $j['opening_hours'] .= "Da " . $ja['meta-fields']['vt_aperturafineda'][0] . " ";
                        }
                        if (!empty($ja['meta-fields']['vt_aperturafinea'][0])) {
                            $j['opening_hours'] .= "A " . $ja['meta-fields']['vt_aperturafinea'][0] . " - ";
                        }
                        if (!empty($ja['link'])) {
                            $j['content']['rendered'] .= "<p>Vedi tutti i dettagli su: <a href=\"" . $ja['link'] . "\">vetrina.toscana.it</a></p>";
                        }

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
                    if (!empty($ja['meta-fields']['vt_provincia'][0])) {
                        $provincia = $ja['meta-fields']['vt_provincia'][0];
                        $poi->addProperty('provincia', $provincia);
                    }
                    if (!empty($ja['meta-fields']['vt_carte'][0])) {
                        $carte = $ja['meta-fields']['vt_carte'][0];
                        $poi->addProperty('carte', $carte);
                    }
                    if (!empty($ja['meta-fields']['vt_facebook'][0])) {
                        $fb = $ja['meta-fields']['vt_facebook'][0];
                        $poi->addProperty('facebook', $fb);
                    }
                    if (!empty($ja['meta-fields']['vt_twitter'][0])) {
                        $tw = $ja['meta-fields']['vt_twitter'][0];
                        $poi->addProperty('twitter', $tw);
                    }
                    if (!empty($ja['meta-fields']['vt_googleplus'][0])) {
                        $gp = $ja['meta-fields']['vt_googleplus'][0];
                        $poi->addProperty('gplus', $gp);
                    }

                    if (!empty($ja['meta-fields']['vt_website'][0])) {
                        $web = $ja['meta-fields']['vt_website'][0];
                        $poi->addProperty('web', $web);
                    }
                    if (!empty($ja['meta-fields']['vt_antipasto'][0])) {
                        $antipasto = $ja['meta-fields']['vt_antipasto'][0];
                        $poi->addProperty('antipasto', $antipasto);
                    }
                    if (!empty($ja['meta-fields']['vt_primopiatto'][0])) {
                        $primopiatto = $ja['meta-fields']['vt_primopiatto'][0];
                        $poi->addProperty('primopiatto', $primopiatto);
                    }
                    if (!empty($ja['meta-fields']['vt_carnipesce'][0])) {
                        $carnipesce = $ja['meta-fields']['vt_carnipesce'][0];
                        $poi->addProperty('carnipesce', $carnipesce);
                    }
                    if (!empty($ja['meta-fields']['vt_contorno'][0])) {
                        $contorno = $ja['meta-fields']['vt_contorno'][0];
                        $poi->addProperty('contorno', $contorno);
                    }
                    if (!empty($ja['meta-fields']['vt_dessert'][0])) {
                        $dessert = $ja['meta-fields']['vt_dessert'][0];
                        $poi->addProperty('dessert', $dessert);
                    }
                    if (!empty($ja['meta-fields']['vt_cantina'][0])) {
                        $cantina = $ja['meta-fields']['vt_cantina'][0];
                        $poi->addProperty('cantina', $cantina);
                    }
                    if (!empty($ja['acf']['vt_menu'][0])) {
                        $menu = $ja['acf']['vt_menu'];
                        $poi->addProperty('menu', $menu);
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
                    $tax['localita'] = array($provincia);
                    $tags = array();
                    if (isset($ja['tags']) && is_array($ja['tags'])) {
                        $tags = $ja['tags'];
                    }
                    $tax['specialita'] = $tags;
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
