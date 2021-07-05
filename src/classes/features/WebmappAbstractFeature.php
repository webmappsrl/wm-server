<?php

abstract class WebmappAbstractFeature {
    // Array geometria della feature
    protected $geometry;
    // Array associativo con la sezione properties
    protected $properties;
    // Array del json restituito dalle API
    protected $json_array;
    // Array degli ID delle categorie webmapp
    protected $webmapp_category_ids = array();
    // Array per la gestione delle traduzioni
    protected $languages = array();
    // WP URL
    private $wp_url = '';
    // GEOJSON FNAME (usato per il write)
    private $geojson_path;
    protected $debug = false;
    // Il costruttore prende in ingresso un array che rispecchia le API di WP
    // della singola feature oppure direttamente l'URL di un singolo POI
    /**
     * WebmappAbstractFeature constructor.
     *
     * @param      $array_or_url
     * @param bool $skip_geometry
     *
     * @throws WebmappExceptionHttpRequest
     */
    public function __construct($array_or_url, bool $skip_geometry = false) {
        declare(ticks = 1);
        global $wm_config;

        $this->debug = !!$wm_config['debug'];

        if (!is_array($array_or_url)) {
            try {
                $json_array = WebmappUtils::getJsonFromApi($array_or_url);
            } catch (WebmappExceptionHttpRequest $e) {
                throw new WebmappExceptionHttpRequest("The instance is unreachable or the feature does not exists");
            }
            $this->wp_url = $array_or_url;
        } else {
            $json_array = $array_or_url;
        }

        if (isset($json_array['wpml_translations']) &&
            is_array($json_array['wpml_translations']) &&
            count($json_array['wpml_translations']) > 0) {
            $url = $json_array['_links']['self'][0]['href'];
            foreach ($json_array['wpml_translations'] as $t) {
                $lang = $t['locale'];
                $lang = preg_replace('|_.*$|', '', $lang);
                $id = $t['id'];
                $lang_url = preg_replace('|\d+$|', $id, $url);
                try {
                    $json_t = WebmappUtils::getJsonFromApi($lang_url);
                    // TODO: estendere oltre a name e description (variabile globale?)
                    if (isset($json_t['title']['rendered'])) {
                        $this->translate($lang, 'name', $json_t['title']['rendered']);
                    }

                    if (isset($json_t['content']['rendered'])) {
                        $this->translate($lang, 'description', $json_t['content']['rendered']);
                    }
                } catch (WebmappExceptionHttpRequest $e) {
                    WebmappUtils::warning("The feature {$lang} language is not available at the url {$lang_url}. This could be due to the translation being in a draft state. HttpError: " . $e->getMessage());
                }
            }
        }
        $this->json_array = $json_array;

        // TODO: non passare $json_array ma usare la proprietà
        $this->mappingStandard($json_array);
        $this->mappingSpecific($json_array);
        if (!$skip_geometry) {
            $this->mappingGeometry($json_array);
        }
    }

    // Simple Getters

    private function translate($lang, $key, $val) {
        $this->languages[$lang][$key] = $val;
    }

    // GEOJSON FNAME

    private function mappingStandard($json_array) {
        $this->setProperty('id', $json_array);
        if (isset($json_array['title']['rendered'])) {
            $this->setProperty('rendered', $json_array['title'], 'name');
        } elseif (isset($json_array['title'])) {
            $this->setProperty('title', $json_array, 'name');
        }

        if (isset($json_array['content']['rendered'])) {
            $this->setProperty('rendered', $json_array['content'], 'description');
        } elseif (isset($json_array['description'])) {
            $this->setProperty('description', $json_array, 'description');
        }

        $this->setProperty('modified', $json_array);
        $this->setProperty('color', $json_array);
        $this->setProperty('icon', $json_array);
        $this->setPropertyBool('noDetails', $json_array);
        $this->setPropertyBool('noInteraction', $json_array);
        $this->setProperty('related_pois', $json_array);
        $this->setProperty('osmid', $json_array);
        $this->setProperty('zindex', $json_array);
        if (isset($json_array['acf'])) {
            $this->setProperty('adopted', $json_array['acf']);
            $this->setProperty('adoption_date', $json_array['acf']);
        }

        // Gestione delle immagini
        // TODO: migliorare la gestione unificando il nome per POI e track
        if (isset($json_array['n7webmap_media_gallery'])) {
            $this->mappingImage($json_array['n7webmap_media_gallery']);
        }
        if (isset($json_array['n7webmap_track_media_gallery'])) {
            $this->mappingImage($json_array['n7webmap_track_media_gallery']);
        }
        if (isset($json_array['n7webmap_route_media_gallery'])) {
            $this->mappingImage($json_array['n7webmap_route_media_gallery']);
        }

        // Se è presente la featured image viene messa come immagine principale
        if (isset($json_array['featured_media'])
            && !is_null($json_array['featured_media'])
            && $json_array['featured_media'] != 0
            && isset($json_array['_links']['wp:featuredmedia'][0]['href'])
        ) {
            try {
                $jm = WebmappUtils::getJsonFromApi($json_array['_links']['wp:featuredmedia'][0]['href']);
                if (isset($jm['media_details']['sizes']))
                    $this->setImage($this->_getImageUrlFromSizes($jm['media_details']['sizes']));
            } catch (WebmappExceptionHttpRequest $e) {
                WebmappUtils::warning("Featured media unreachable at url {$json_array['_links']['wp:featuredmedia'][0]['href']}: " + $e->getMessage());
            }
        } elseif (isset($json_array['image']['id'])
            && !is_null($json_array['image']['id'])
            && $json_array['image']['id'] != 0) {
            if (isset($json_array['image']['sizes']) && is_array($json_array['image']['sizes']) && count($json_array['image']['sizes']) > 0)
                $this->setImage($this->_getImageUrlFromSizes($json_array['image']['sizes']));
        }

        // FILE AUDIO
        if (isset($json_array['audio']) && is_array($json_array['audio']))
            $this->addProperty('audio', $json_array['audio']['url']);

        // Gestione delle categorie WEBMAPP
        // http://dev.be.webmapp.it/wp-json/wp/v2/poi/610
        // http://dev.be.webmapp.it/wp-json/wp/v2/track/580
        if (isset($this->json_array['webmapp_category']) &&
            is_array($this->json_array['webmapp_category']) &&
            count($this->json_array['webmapp_category']) > 0) {
            $this->webmapp_category_ids = $json_array['webmapp_category'];
        }

        // Taxonomies
        $this->addTaxonomy('webmapp_category');
        $this->addTaxonomy('activity');
        $this->addTaxonomy('theme');
        $this->addTaxonomy('where');
        $this->addTaxonomy('when');
        $this->addTaxonomy('who');

        // set Accessibility
        $this->setAccessibility($json_array);
        // set Reachability
        $this->setReachability($json_array);

        $this->setNotAccessible($json_array);

        // Related URL
        $this->setRelatedUrl($json_array);

        // CONTENT FROM
        if (isset($json_array['content_from']) && is_array($json_array['content_from']) && count($json_array['content_from']) > 0) {
            $this->setProperty('description', array('description' => nl2br($json_array['content_from'][0]['post_content'])));
        }

        // LOCALE
        if (isset($json_array['wpml_current_locale']) && !empty($json_array['wpml_current_locale'])) {
            $this->addProperty('locale', preg_replace('|_.*$|', '', $json_array['wpml_current_locale']));
        }

        // SOURCE and WP_EDIT
        $source = null;
        $wp_edit = null;
        if (isset($json_array['_links']['self'][0]['href'])) {
            $source = $json_array['_links']['self'][0]['href'];
            // ADD wp_edit
            $parse = parse_url($source);
            $host = $parse['host'];
            // http://dev.be.webmapp.it/wp-admin/post.php?post=509&action=edit
            $wp_edit = 'http://' . $host . '/wp-admin/post.php?post=' . $this->getId() . '&action=edit';
        }
        if (!is_null($source))
            $this->addProperty('source', $source);
        if (!is_null($wp_edit))
            $this->addProperty('wp_edit', $wp_edit);

        // TRANSLATIONS
        if (isset($json_array['wpml_translations'])) {
            $t = $json_array['wpml_translations'];
            if (is_array($t) && count($t) > 0) {
                $tp = array();
                foreach ($t as $item) {
                    $locale = preg_replace('|_.*$|', '', $item['locale']);
                    $val = array();
                    $val['id'] = $item['id'];
                    $val['name'] = $item['post_title'];
                    $val['web'] = $item['href'];
                    $val['source'] = preg_replace('|/[0-9]*$|', '/' . $val['id'], $this->properties['source']);
                    try {
                        $ja = WebmappUtils::getJsonFromApi($val['source']);
                        if (isset($ja['content'])) {
                            $val['description'] = $ja['content']['rendered'];
                        }
                        if (isset($ja['rb_track_section'])) {
                            $val['rb_track_section'] = $ja['rb_track_section'];
                        }
                        if (isset($ja['audio']) && is_array($ja['audio']) && isset($ja['audio']['url']) && $ja['audio']['url'] !== $this->getProperty('audio')) {
                            $val['audio'] = $ja['audio']['url'];
                        }

                        $tp[$locale] = $val;
                    } catch (WebmappExceptionHttpRequest $e) {
                        WebmappUtils::warning("The feature {$locale} language is not available at the url {$val['source']}. This could be due to the translation being in a draft state. HttpError: " . $e->getMessage());
                    }
                }
                $this->addProperty('translations', $tp);
            }
        }

        // LINK WEB
        if (isset($json_array['link']) && !empty($json_array['link']))
            $this->addProperty('web', $json_array['link']);
        elseif (isset($json_array['url']) && !empty($json_array['url']))
            $this->addProperty('web', $json_array['url']);
    }

    public function setProperty($key, $json_array, $key_map = '') {
        if (isset($json_array[$key]) && !is_null($json_array[$key])) {
            if ($key_map == '') {
                $key_map = $key;
            }

            $this->properties[$key_map] = $json_array[$key];
        }
    }

    public function setPropertyFloat($key, $json_array, $key_map = '') {
        if (isset($json_array[$key]) && !is_null($json_array[$key])) {
            if ($key_map == '')
                $key_map = $key;

            $this->properties[$key_map] = floatval($json_array[$key]);
        }
    }

    protected function setPropertyBool($key, $json_array, $key_map = '') {
        if ($key_map == '') {
            $key_map = $key;
        }

        $val = false;
        if (isset($json_array[$key]) && !is_null($json_array[$key])) {
            $json_val = $json_array[$key];
            if ($json_val == true or $json_val == 'true' or $json_val == '1') {
                $val = true;
            }
        }
        $this->properties[$key_map] = $val;
    }

    private function mappingImage($gallery) {
        if (is_array($gallery) && count($gallery) > 0) {
            $images = array();
            foreach ($gallery as $item) {
                // TODO: usare una grandezza standard
                //$images[]=array('src'=>$item['url']);
                if (isset($item['sizes'])) {
                    $src = $this->_getImageUrlFromSizes($item['sizes']);
                    $images[] = array(
                        'src' => $src,
                        'id' => $item['id'],
                        'caption' => $item['caption']);
                } else
                    WebmappUtils::warning("The image sizes are not available. Image item: " . json_encode($item));
            }
            $this->properties['imageGallery'] = $images;
            $this->setImage($images[0]['src']);
        }
    }

    /**
     * @param array $sizes
     *
     * @return string|null
     */
    protected function _getImageUrlFromSizes(array $sizes): ?string {
        $url = null;
        if (isset($sizes)) {
            if (isset($sizes['large']['source_url']))
                $url = $sizes['large']['source_url'];
            elseif (isset($sizes['large']['url']))
                $url = $sizes['large']['url'];
            elseif (isset($sizes['large']) && is_string($sizes['large']))
                $url = $sizes['large'];
            elseif (isset($sizes['medium_large']['source_url']))
                $url = $sizes['medium_large']['source_url'];
            elseif (isset($sizes['medium_large']['url']))
                $url = $sizes['medium_large']['url'];
            elseif (isset($sizes['medium_large']) && is_string($sizes['medium_large']))
                $url = $sizes['medium_large'];
            elseif (isset($sizes['medium']['source_url']))
                $url = $sizes['medium']['source_url'];
            elseif (isset($sizes['medium']['url']))
                $url = $sizes['medium']['url'];
            elseif (isset($sizes['medium']) && is_string($sizes['medium']))
                $url = $sizes['medium'];
            elseif (is_array($sizes) && isset($sizes[array_key_first($sizes)]['source_url']))
                $url = $sizes[array_key_first($sizes)]['source_url'];
            elseif (is_array($sizes) && isset($sizes[array_key_first($sizes)]['url']))
                $url = $sizes[array_key_first($sizes)]['url'];
            elseif (isset($sizes[array_key_first($sizes)]) && is_string($sizes[array_key_first($sizes)]))
                $url = $sizes[array_key_first($sizes)];
        }

        return $url;
    }

    public function setImage($url) {
        if (isset($url))
            $this->properties['image'] = $url;
    }

    public function addProperty($key, $val) {
        $this->properties[$key] = $val;
    }

    private function addTaxonomy($name) {
        if (isset($this->json_array[$name]) &&
            is_array($this->json_array[$name]) &&
            count($this->json_array[$name]) > 0) {
            $this->properties['taxonomy'][$name] = $this->json_array[$name];
        }
    }

    // Setters

    /**
     * @param array $json_array
     * @param array $types
     *
     * @return array
     */
    private function _getObjectWithCheckAndDescription(array $json_array = [], array $types = [], string $prefix): array {
        $result = [];
        foreach ($types as $type) {
            $check = $prefix . '_' . $type . '_check';
            $description = $prefix . '_' . $type . '_description';
            $level = $prefix . '_' . $type . '_level';
            $result[$type]['check'] = isset($json_array[$check]) ? !!$json_array[$check] : false;
            $result[$type]['description'] = isset($json_array[$description]) ? $json_array[$description] : "";
            if (isset($json_array[$level]))
                $result[$type]['level'] = $json_array[$level];
        }

        return $result;
    }

    protected function setAccessibility($json_array) {
        // ACCESSIBILITA'
        // TODO: GESTIRE IL CASO VUOTO
        $types = array('mobility', 'hearing', 'vision', 'cognitive', 'food');
        $this->properties['accessibility'] = $this->_getObjectWithCheckAndDescription(is_null($json_array) ? [] : $json_array, $types, 'access');
    }

    protected function setReachability($json_array) {
        // TODO: GESTIRE IL CASO VUOTO
        $types = array('by_bike', 'on_foot', 'by_car', 'by_public_transportation');
        $this->properties['reachability'] = $this->_getObjectWithCheckAndDescription(is_null($json_array) ? [] : $json_array, $types, 'reachability');
    }

    protected function setNotAccessible($json_array) {
        if (isset($json_array['not_accessible']) && !!$json_array['not_accessible']) {
            $this->properties['not_accessible'] = [
                "check" => true,
            ];
            if (isset($json_array['not_accessible_description']) && !empty($json_array['not_accessible_description']))
                $this->properties["not_accessible"]["description"] = $json_array['not_accessible_description'];
        }
    }

    private function setRelatedUrl($ja) {
        if (isset($ja['n7webmap_rpt_related_url']) && is_array($ja['n7webmap_rpt_related_url'])) {
            $urls = array();
            foreach ($ja['n7webmap_rpt_related_url'] as $item) {
                $urls[] = $item['net7webmap_related_url'];
            }
            $this->properties['related_url'] = $urls;
        } elseif (isset($ja['website']) && is_string($ja['website']))
            $this->properties['related_url'] = [$ja['website']];
    }

    // Restituisce l'array con l'id WP delle categorie

    public function getId() {
        if (isset($this->properties['id'])) {
            return $this->properties['id'];
        }

        return '';
    }

    abstract protected function mappingSpecific($json_array);

    abstract protected function mappingGeometry($json_array);

    public function getWPUrl(): string {
        return $this->wp_url;
    }

    public function getProperties() {
        return $this->properties;
    }

    public function getGeometry() {
        return $this->geometry;
    }

    public function getGeometryType(): string {
        if (!$this->hasGeometry()) {
            return 'no-geometry';
        }

        if (!isset($this->geometry['type'])) {
            return 'bad-geometry';
        }
        if (empty($this->geometry)) {
            return 'empty-geomtrytype';
        }

        return $this->geometry['type'];
    }

    public function hasGeometry(): bool {
        return !empty($this->geometry);
    }

    public function getWebmappCategoryIds(): array {
        return $this->webmapp_category_ids;
    }

    public function getIcon(): string {
        if (isset($this->properties['icon'])) {
            return $this->properties['icon'];
        }

        return '';
    }

    public function getColor() {
        if (isset($this->properties['color'])) {
            return $this->properties['color'];
        }

        return '';
    }

    public function mapCustomProperties($custom_array) {
        foreach ($custom_array as $key => $property) {
            $this->customSetProperty(is_numeric($key) ? $property : $key, $this->json_array, $property);
        }
    }

    protected function customSetProperty($key, $json_array, $key_map = '') {
        $val = null;
        if (isset($json_array["acf"]) && isset($json_array["acf"][$key]) && !is_null($json_array["acf"][$key])) {
            $val = $json_array['acf'][$key];
        } else if (isset($json_array[$key]) && !is_null($json_array[$key])) {
            $val = $json_array[$key];
        }

        if (!is_null($val)) {
            if ($key_map == '') {
                $key_map = $key;
            }

            if ($val === true || $val === 'true') {
                $val = true;
            } else if ($val === false || $val === 'false') {
                $val = false;
            }

            $this->properties[$key_map] = $val;
        }
    }

    public function addImageToGallery($src, $caption = '', $id = '') {
        $this->properties['imageGallery'][] = array('src' => $src, 'caption' => $caption, 'id' => $id);
    }

    public function cleanProperties() {
        $this->removeProperty('noInteraction');
        $this->removeProperty('noDetails');
        $this->removeProperty('accessibility');
        $this->removeProperty('id_pois');
    }

    public function removeProperty($key) {
        unset($this->properties[$key]);
    }

    // Map properties

    public function map($a) {
        foreach ($a as $key => $val) {
            $this->addProperty($key, $val);
        }
    }

    public function setDescription($val) {
        $this->addProperty('description', $val);
    }

    // Mapping della gallery e della imagine di base

    public function setGeometryGeoJSON($geom) {
        $this->geometry = json_decode($geom, true);
    }

    // Mapping delle proprietà specifiche di una feature esclusa la geometria

    public function getRelatedPois() {
        // Retrieve POIS URL
        if (!empty($this->wp_url)) {
            if (($parsed_url = parse_url($this->wp_url)) !== null) {
                $pois_url = $parsed_url['scheme'] . '://' . $parsed_url['host'] . '/wp-json/wp/v2/poi';
            } else {
                throw new WebmappExceptionsFeaturesTracksRelatedPoisBadWPURL("Bad WP URL {$this->wp_url} Feature_id: {$this->getId()}");
            }
        } else if ($this->hasProperty('source')) {
            if (($parsed_url = parse_url($this->getProperty('source'))) !== null) {
                $pois_url = $parsed_url['scheme'] . '://' . $parsed_url['host'] . '/wp-json/wp/v2/poi';
            } else {
                throw new WebmappExceptionsFeaturesTracksRelatedPoisBadSource("Bad source URL {$this->getProperty('source')} Feature_id: {$this->getId()}");
            }
        } else {
            throw new WebmappExceptionsFeaturesTracksRelatedPoisNoWPNOSource("NO WP_URL or source Feature_id: {$this->getId()}");
        }

        echo "\n\n\n$pois_url\n\n\n";

        $pois = array();
        if (isset($this->json_array['n7webmap_related_poi']) &&
            is_array($this->json_array['n7webmap_related_poi']) &&
            count($this->json_array['n7webmap_related_poi']) > 0) {
            foreach ($this->json_array['n7webmap_related_poi'] as $poi) {
                $guid = $poi['guid'];
                $id = $poi['ID'];
                // http://dev.be.webmapp.it/poi/bar-pasticceria-lilli/
                // $code = str_replace('http://', '', $guid);
                // $code = preg_replace('|\..*|', '', $code);
                //$poi_url = "http://$code.be.webmapp.it/wp-json/wp/v2/poi/$id";
                $poi_url = $pois_url . "/$id";
                $pois[] = new WebmappPoiFeature($poi_url);
            }
        }

        return $pois;
    }

    // Mapping della geometria

    public function hasProperty($k) {
        return array_key_exists($k, $this->properties) && isset($this->properties[$k]) && !empty($this->properties[$k]);
    }

    // Force $geometry with geoJson geometry (string)

    public function getProperty($k) {
        return $this->properties[$k];
    }

    // Restituisce un array di oggetti WebmappPoiFeature con i relatedPoi

    public function getBB() {
        $bb = array();
        $bb['bounds']['southWest'] = array($this->getLatMin(), $this->getLngMin());
        $bb['bounds']['northEast'] = array($this->getLatMax(), $this->getLngMax());
        $bb['center']['lat'] = ($this->getLatMin() + $this->getLatMax()) / 2;
        $bb['center']['lng'] = ($this->getLngMin() + $this->getLngMax()) / 2;

        return $bb;
    }

    abstract public function getLatMin();

    // Lat e Lng Max  e MIN (usate per il BB)

    abstract public function getLngMin();

    abstract public function getLatMax();

    abstract public function getLngMax();

    public function write($path, $encrypt = false) {
        $id = $this->properties['id'];
        $this->geojson_path = $path . "/$id.geojson";
        if (!file_exists($path)) {
            $cmd = "mkdir $path";
            system($cmd);
        }
        $out = $this->getJson();
        if ($encrypt) {
            $out = WebmappUtils::encrypt($out);
        }
        file_put_contents($this->geojson_path, $out);
    }

    // ARRAY pronto per essere convertito in json
    // ['bounds']['southWest']array(lat,lng)
    // ['bounds']['northEast']array(lat,lng)
    // ['center']array(lat,lng)
    // NON FARLA ASTRATTA MA IN FUNZIONE DELLE PRECEDENTI

    public function getJson($lang = '') {
        return json_encode($this->getArrayJson($lang));
    }

    public function getArrayJson($lang = '') {
        $meta = $this->properties;
        // manage translations
        if ($lang != '') {
            if (array_key_exists($lang, $this->languages)) {
                $t = $this->languages[$lang];
                foreach ($t as $key => $value) {
                    if (isset($meta[$key])) {
                        $meta[$key] = $value;
                    }
                }
            }
        }

        $json = array();
        $json['type'] = 'Feature';
        $json['properties'] = $meta;
        $json['geometry'] = $this->geometry;

        return $json;
    }

    abstract public function writeToPostGis($instance_id = '');

    abstract public function addRelated($distance = 5000, $limit = 100);

    abstract public function addEle();

    public function writeRelated($path) {
        $this->writeRelatedSpecific($path, 'poi', 'related');
        $this->writeRelatedSpecific($path, 'poi', 'neighbors');
        $this->writeRelatedSpecific($path, 'track', 'related');
        $this->writeRelatedSpecific($path, 'track', 'neighbors');
        $this->writeRelatedSpecific($path, 'route', 'related');
        $this->writeRelatedSpecific($path, 'route', 'neighbors');
    }

    private function writeRelatedSpecific($path, $ftype, $rtype) {
        if (isset($this->properties['related'][$ftype][$rtype])) {
            $rel = $this->properties['related'][$ftype][$rtype];
            if (is_array($rel) && count($rel) > 0) {
                $fname = $path . '/' . $this->getId() . '_' . $ftype . '_' . $rtype . '.geojson';
                $features = array();
                $j = array();
                foreach ($rel as $id) {
                    $features[] = json_decode(file_get_contents($path . '/' . $id . '.geojson'), true);
                }
                $j['type'] = 'FeatureCollection';
                $j['features'] = $features;
                file_put_contents($fname, json_encode($j));
            }
        }
    }

    // La query POSTGIS deve essere costruita in modo tale da avere i parametri ID del POI e distance POI / OGGETTO

    abstract public function generateImage($width, $height, $instance_id = '', $path = '');

    // TODO: remove this PATCH

    protected function addRelatedPoi($q) {
        // PATH per recuperare i geojson dei POI
        $path = $this->getGeoJsonPath();
        if (preg_match('|/track/|', $path)) {
            $new_path = preg_replace('|track/.*$|', '', $path);
        } else {
            $new_path = preg_replace('|poi/.*$|', '', $path);
        }

        // Neighbors
        $d = pg_connect("host=46.101.124.52 port=5432 dbname=webmapptest user=webmapp password=T1tup4atmA");
        $r = pg_query($d, $q);
        $neighbors = array();
        while ($row = pg_fetch_array($r)) {
            $id = $row['id'];
            // $poi_path = $new_path."/poi/$id.geojson";
            // $poi_path=$this->checkPoiPath($poi_path);
            // $neighbors[$id]=$this->getPoiInfoArray($poi_path,$row['distance']);
            $neighbors[] = $id;
        }
        $this->properties['related']['poi']['neighbors'] = $neighbors;

        // Related inserted by user (related - poi - related)
        $related = array();

        $id_pois = $this->getRelatedPoisId();

        if (isset($id_pois) && count($id_pois) > 0) {
            foreach ($id_pois as $id) {
                //$poi_path = $new_path."/poi/$id.geojson";
                //$poi_path=$this->checkPoiPath($poi_path);
                //$related[$id]=$this->getPoiInfoArray($poi_path);
                $related[] = $id;
            }
        }
        $this->properties['related']['poi']['related'] = $related;
    }

    // Recupero INFO dal file related (suppongo che i file POI già esistano)

    public function getGeoJsonPath() {
        return $this->geojson_path;
    }

    public function getRelatedPoisId() {
        $pois = array();
        if (isset($this->json_array['n7webmap_related_poi']) &&
            is_array($this->json_array['n7webmap_related_poi']) &&
            count($this->json_array['n7webmap_related_poi']) > 0) {
            foreach ($this->json_array['n7webmap_related_poi'] as $poi) {
                $pois[] = $poi['ID'];
            }
        }

        return $pois;
    }

    private function checkPoiPath($path) {
        if (file_exists($path)) {
            return $path;
        } else {
            echo "ADDRELATED POI PATCH TODO: remove this PATCH!!!\n";
            ////[...]/data/tmp/1532853048/geojson/800.geojson/poi/449.geojson
            ////[...]/data/tmp/1532853048/geojson/449.geojson
            $new_path = preg_replace('|/geojson/.*/poi/|', '/geojson/', $path);
            echo "OLD: $path NEW:$new_path";

            return $new_path;
        }
    }

    private function getPoiInfoArray($poi_path, $distance = -1) {
        $j = WebmappUtils::getJsonFromApi($poi_path);
        $name = '';
        if (isset($j['properties']) && isset($j['properties']['name'])) {
            $name = $j['properties']['name'];
        }
        $web = '';
        if (isset($j['properties']) && isset($j['properties']['web'])) {
            $web = $j['properties']['web'];
        }
        $lat = $lon = '';
        if (isset($j['geometry']) && isset($j['geometry']['coordinates'])) {
            $lon = $j['geometry']['coordinates'][0];
            $lat = $j['geometry']['coordinates'][1];
        }
        $wmc = array();
        if (isset($j['properties']) &&
            isset($j['properties']['taxonomy']) &&
            isset($j['properties']['taxonomy']['webmapp_category'])) {
            $wmc = $j['properties']['taxonomy']['webmapp_category'];
        }
        $r = array();
        $r['distance'] = $distance;
        $r['webmapp_category'] = $wmc;
        $r['name'] = $name;
        $r['web'] = $web;
        $r['lat'] = $lat;
        $r['lon'] = $lon;

        return $r;
    }
}

/** Esempio di classe concreta che estende la classe astratta
 * class WebmappPoiFeature extends WebmappAbstractFeature {
 * protected function mappingSpecific($json_array) {}
 * protected function mappingGeometry($json_array) {}
 * }
 **/
