<?php

class WebmappTrentinoKTask extends WebmappAbstractTask
{
    private $__aBaseUrl = "/var/www/html/a.webmapp.it/trentino/";
    private $__baseFileNames = array(
        "punti_appoggio.geojson",
        "punti_interesse.geojson",
        "rifugi.geojson",
        "sentieri_localita.geojson",
        "sentieri_tratte.geojson",
        "sentieri_lunga_percorrenza.geojson",
        // "sentieri_lp.geojson",
    );
    private $__endpoint;

    private $__mappingBivacchi = array(
        77 => "http://sat.tn.it/passo-delle-vacche-bivacco-e-segalla/",
        79 => "http://sat.tn.it/cima-sassara-bivacco-fratelli-bonvecchio/",
        80 => "http://sat.tn.it/bivacco-cunella/",
        82 => "http://sat.tn.it/vallaccia-bivacco-d-zeni/",
        98 => "http://sat.tn.it/latemar-bivacco-a-sieff/",
        131 => "http://sat.tn.it/forcella-grande-bivacco-m-rigatti/",
        137 => "http://sat.tn.it/pra-castron-bivacco-c-costanzi/",
        138 => "http://sat.tn.it/presanella-bivacco-v-roberti/",
        149 => "http://sat.tn.it/vigolana-bivacco-g-b-giacomelli/",
        150 => "http://sat.tn.it/cima-presanella-bivacco-orbica/",
        156 => "http://sat.tn.it/sinel-bivacco-g-pedrinolla/",
        300 => "http://sat.tn.it/cima-dasta-capanna-g-cavinato/",
        "boh" => "http://sat.tn.it/crozzon-bivacco-e-castiglioni/",
    );

    private $__mappingRifugi = array(
        1 => "http://sat.tn.it/val-dambiez-rifugio-silvio-agostini/",
        2 => "http://sat.tn.it/altissimo-rifugio-damiano-chiesa/",
        3 => "http://sat.tn.it/rifugio-antermoia/",
        4 => "http://sat.tn.it/bindesi-rifugio-pino-prati/",
        5 => "http://sat.tn.it/col-turond-rifugio-boe/",
        6 => "http://sat.tn.it/cima-dasta-capanna-g-cavinato/",
        7 => "http://sat.tn.it/care-alto-rifugio-dante-ongari/",
        8 => "http://sat.tn.it/l-ciola-rifugio-casarota/",
        9 => "http://sat.tn.it/rifugio-ciampedie/",
        10 => "http://sat.tn.it/rifugio-stavel-francesco-denza/",
        11 => "http://sat.tn.it/saent-rifugio-silvio-dorigoni/",
        12 => "http://sat.tn.it/finonchio-rifugio-f-lli-filzi/",
        13 => "http://sat.tn.it/groste-rifugio-graffer/",
        14 => "http://sat.tn.it/alpe-pozza-rifugio-v-lancia/",
        15 => "http://sat.tn.it/cevedale-rifugio-guido-larcher/",
        16 => "http://sat.tn.it/rifugio-mandron-citta-di-trento/",
        17 => "http://sat.tn.it/rifugio-paludei/",
        18 => "http://sat.tn.it/rifugio-peller/",
        19 => "http://sat.tn.it/bocca-di-trat-rifugio-nino-pernici/",
        20 => "http://sat.tn.it/catinaccio-rifugio-roda-di-vael/",
        21 => "http://sat.tn.it/rifugio-rosetta-g-pedrotti/",
        22 => "http://sat.tn.it/monte-calino-rifugio-san-pietro/",
        23 => "http://sat.tn.it/val-damola-rifugio-giovanni-segantini/",
        24 => "http://sat.tn.it/gruppo-lagorai-rifugio-sette-selle/",
        25 => "http://sat.tn.it/stivo-rifugio-prospero-marchetti/",
        26 => "http://sat.tn.it/monzoni-rifugio-t-taramelli/",
        27 => "http://sat.tn.it/spruggio-g-tonini/",
        28 => "http://sat.tn.it/rifugio-tosa-e-tommaso-pedrotti/",
        29 => "http://sat.tn.it/rifugio-f-f-tuckett-e-quintino-sella/",
        30 => "http://sat.tn.it/vajolet/",
        31 => "http://sat.tn.it/rifugio-val-di-fumo/",
        32 => "http://sat.tn.it/rifugio-velo-della-madonna/",
        33 => "http://sat.tn.it/vioz-rifugio-mantova/",
        34 => "http://sat.tn.it/xii-apostoli-rifugio-fratelli-garbari/",
    );

    public function check()
    {
        echo "Checking file presence...";

        foreach ($this->__baseFileNames as $filename) {
            if (!file_exists($this->__aBaseUrl . "geojson/" . $filename)) {
                throw new WebmappExceptionNoFile("ERROR: Missing file " . $this->__aBaseUrl . $filename, 1);
            }

            echo "...";
        }

        $this->__endpoint = $wm_config['endpoint']['a'] . '/trentino';

        echo "Check OK\n";

        return true;
    }

    public function process()
    {
        $this->generateTaxonomies();

        ini_set('serialize_precision', 6);

        foreach ($this->__baseFileNames as $filename) {
            echo "\nProcessing $filename... \n";
            $file = json_decode(file_get_contents($this->__aBaseUrl . "geojson/" . $filename), true);

            try {
                $file = $this->addId($file);
                $file = $this->addTaxonomy($file, $filename);
                if ($filename == "punti_appoggio.geojson") {
                    $file = $this->mapDrinkingWater($file);
                    $file = $this->mapCapacity($file);
                    $file = $this->mapLocalityToAddress($file);

                    $this->saveBivacchi($file);
                }
                if ($filename == "rifugi.geojson") {
                    $file = $this->mapPictureUrlToImage($file);

                    $mappedFile = $this->mapUrlFromMapping($file, $this->__mappingRifugi);
                    file_put_contents($this->project_structure->getRoot() . "/geojson/" . "rifugi_webapp.geojson", json_encode($mappedFile));
                    $this->saveSingleGeojsons($mappedFile, "rifugi");

                    $file = $this->mapWebsiteToRelatedUrl($file);
                }
                if ($filename == "sentieri_tratte.geojson") {
                    $file = $this->mapImageGalleryUrl($file);
                    $file = $this->mapFromAndTo($file);
                }
                if ($filename == "sentieri_lunga_percorrenza.geojson") {
                    $file = $this->mapWebsiteToRelatedUrl($file);
                    $file = $this->mapFromAndTo($file);
                }

                file_put_contents($this->project_structure->getRoot() . "/geojson/" . $filename, json_encode($file));
                echo "$filename DONE\n";
            } catch (WebmappException $e) {
                echo "\n$filename WARNING: " . $e;
            }
        }

        ini_restore('serialize_precision');

        $src = $this->getRoot();
        $trg = $this->__endpoint;
        $cmd = "rm -Rf {$src}/track";
        system($cmd);
        if (file_exists($trg . '/track')) {
            $cmd = "ln -s {$trg}/track {$src}/track";
            system($cmd);
        }

        return true;
    }

    public function generateTaxonomies()
    {
        $webmappCategory = array(
            "1" => array(
                "id" => "1",
                "name" => "Altri punti di appoggio",
                "taxonomy" => "webmapp_category",
                "is_parent" => false,
                "locale" => "it",
                "color" => "#FF3812",
                "icon" => "wm-icon-wilderness-hut-cai",
                "image" => "https://k.webmapp.it/trentino/media/images/sat_bivacchi.jpg",
            ),
            "2" => array(
                "id" => "2",
                "name" => "Punti di interesse",
                "taxonomy" => "webmapp_category",
                "is_parent" => false,
                "locale" => "it",
                "color" => "#28A7DC",
                "icon" => "wm-icon-star2",
                "image" => "https://k.webmapp.it/trentino/media/images/sat_poi.jpg",
            ),
            "3" => array(
                "id" => "3",
                "name" => "Rifugi SAT",
                "taxonomy" => "webmapp_category",
                "is_parent" => false,
                "locale" => "it",
                "color" => "#FF3812",
                "icon" => "wm-icon-alpine-hut-cai",
                "image" => "https://k.webmapp.it/trentino/media/images/sat_rifugi.jpg",
            ),
            "4" => array(
                "id" => "4",
                "name" => "Località",
                "taxonomy" => "webmapp_category",
                "is_parent" => false,
                "locale" => "it",
                "color" => "#767A71",
                "icon" => "wm-icon-star2",
                "image" => "https://k.webmapp.it/trentino/media/images/sat_localita.jpg",
            ),
        );

        $activity = array(
            "21" => array(
                "id" => "21",
                "name" => "I sentieri della SAT",
                "taxonomy" => "activity",
                "is_parent" => false,
                "locale" => "it",
                "icon" => "wm-icon-hiking-15",
                "image" => "https://k.webmapp.it/trentino/media/images/sat_sentieri.jpg",
            ),
            "22" => array(
                "id" => "22",
                "name" => "Itinerari a tappe",
                "taxonomy" => "activity",
                "is_parent" => false,
                "locale" => "it",
                "color" => "#387EF5",
                "icon" => "wm-icon-trail",
                "image" => "https://k.webmapp.it/trentino/media/images/sat_itinerari.jpg",
            ),
        );

        file_put_contents($this->project_structure->getRoot() . "/taxonomies/webmapp_category.json", json_encode($webmappCategory));
        file_put_contents($this->project_structure->getRoot() . "/taxonomies/activity.json", json_encode($activity));
    }

    public function addId($file)
    {
        $i = 1;
        if ($file["type"] !== "FeatureCollection") {
            throw new WebmappExceptionGeoJson("ERROR: Wrong geojson type");
        }

        if (!array_key_exists('features', $file)) {
            throw new WebmappExceptionGeoJson("ERROR: No features");
        }

        foreach ($file["features"] as $key => $feature) {
            if (!array_key_exists("id", $file["features"][$key]["properties"])) {
                $file["features"][$key]["properties"]["id"] = $i . "";
            }
            if (!array_key_exists("name", $file["features"][$key]["properties"])) {
                $file["features"][$key]["properties"]["name"] = $file["features"][$key]["properties"]["ref"];
            }

            $i++;
        }

        return $file;
    }

    public function addTaxonomy($file, $filename)
    {
        if ($file["type"] !== "FeatureCollection") {
            throw new WebmappExceptionGeoJson("ERROR: Wrong geojson type");
        }

        if (!array_key_exists('features', $file)) {
            throw new WebmappExceptionGeoJson("ERROR: No features");
        }

        $taxonomy = array();

        switch ($filename) {
            case "punti_appoggio.geojson":
                $taxonomy = array("webmapp_category" => array("1"));
                break;
            case "punti_interesse.geojson":
                $taxonomy = array("webmapp_category" => array("2"));
                break;
            case "rifugi.geojson":
                $taxonomy = array("webmapp_category" => array("3"));
                break;
            case "sentieri_localita.geojson":
                $taxonomy = array("webmapp_category" => array("4"));
                break;
            case "sentieri_tratte.geojson":
                $taxonomy = array("activity" => array("21"));
                break;
            case "sentieri_lunga_percorrenza.geojson":
            case "sentieri_lp.geojson":
                $taxonomy = array("activity" => array("22"));
                break;
        }

        foreach ($file["features"] as $key => $feature) {
            $file["features"][$key]["properties"]["taxonomy"] = $taxonomy;
        }

        return $file;
    }

    public function mapDrinkingWater($file)
    {
        foreach ($file["features"] as $key => $feature) {
            if (array_key_exists("drinking_water", $file["features"][$key]["properties"]) && $file["features"][$key]["properties"]["drinking_water"] == "yes") {
                $file["features"][$key]["properties"]["drinking_water"] = true;
            } else {
                unset($file["features"][$key]["properties"]["drinking_water"]);
            }
        }

        return $file;
    }

    public function mapCapacity($file)
    {
        foreach ($file["features"] as $key => $feature) {
            if (array_key_exists("capacity", $file["features"][$key]["properties"]) && $file["features"][$key]["properties"]["capacity"] == null) {
                unset($file["features"][$key]["properties"]["capacity"]);
            }
        }

        return $file;
    }

    public function mapLocalityToAddress($file)
    {
        foreach ($file["features"] as $key => $feature) {
            if (array_key_exists("locality", $file["features"][$key]["properties"])) {
                $file["features"][$key]["properties"]["address"] = $file["features"][$key]["properties"]["locality"];
                unset($file["features"][$key]["properties"]["locality"]);
            }
        }

        return $file;
    }

    public function mapWebsiteToRelatedUrl($file)
    {
        foreach ($file["features"] as $key => $feature) {
            if (array_key_exists("website", $file["features"][$key]["properties"])) {
                $file["features"][$key]["properties"]["related_url"] = array($file["features"][$key]["properties"]["website"]);
                unset($file["features"][$key]["properties"]["website"]);

                if (substr($file["features"][$key]["properties"]["related_url"][0], 0, 3) == "www") {
                    $file["features"][$key]["properties"]["related_url"][0] = "http://" . $file["features"][$key]["properties"]["related_url"][0];
                }
            }
        }

        return $file;
    }

    public function mapPictureUrlToImage($file)
    {
        foreach ($file["features"] as $key => $feature) {
            if (array_key_exists("picture_url", $file["features"][$key]["properties"])) {
                $file["features"][$key]["properties"]["image"] = $file["features"][$key]["properties"]["picture_url"];
                unset($file["features"][$key]["properties"]["picture_url"]);
            }
        }

        return $file;
    }

    public function mapImageGalleryUrl($file)
    {
        foreach ($file["features"] as $key => $feature) {
            if (array_key_exists("image_gallery", $file["features"][$key]["properties"])) {
                $file["features"][$key]["properties"]["imageGalleryUrl"] = $file["features"][$key]["properties"]["image_gallery"];
                unset($file["features"][$key]["properties"]["image_gallery"]);
            }
        }

        return $file;
    }

    public function mapFromAndTo($file)
    {
        foreach ($file["features"] as $key => $feature) {
            if (array_key_exists("start", $file["features"][$key]["properties"])) {
                $file["features"][$key]["properties"]["from"] = $file["features"][$key]["properties"]["start"];
                unset($file["features"][$key]["properties"]["start"]);
            }
            if (array_key_exists("START", $file["features"][$key]["properties"])) {
                $file["features"][$key]["properties"]["from"] = $file["features"][$key]["properties"]["START"];
                unset($file["features"][$key]["properties"]["START"]);
            }

            if (array_key_exists("end", $file["features"][$key]["properties"])) {
                $file["features"][$key]["properties"]["to"] = $file["features"][$key]["properties"]["end"];
                unset($file["features"][$key]["properties"]["end"]);
            }
            if (array_key_exists("END", $file["features"][$key]["properties"])) {
                $file["features"][$key]["properties"]["to"] = $file["features"][$key]["properties"]["END"];
                unset($file["features"][$key]["properties"]["END"]);
            }
        }

        return $file;
    }

    public function mapUrlFromMapping($file, $mapping)
    {
        foreach ($file["features"] as $key => $feature) {
            $id = $file["features"][$key]["properties"]['id'];
            $file["features"][$key]["properties"]["related_url"] = array($mapping[$id]);
            // $file["features"][$key]["properties"]["web"] = $mapping[$id];
        }

        return $file;
    }

    public function saveSingleGeojsons($file, $prefix)
    {
        foreach ($file["features"] as $key => $feature) {
            $id = $file["features"][$key]["properties"]['id'];
            file_put_contents($this->project_structure->getRoot() . "/geojson/poi/" . $prefix . "_" . $id . ".geojson", json_encode($feature));
        }
    }

    public function saveBivacchi($file)
    {
        $mapping = $this->__mappingBivacchi;
        $filteredFile = array();
        $filteredFile["type"] = "FeatureCollection";
        $filteredFile["features"] = array();

        // Filter file
        foreach ($file["features"] as $feature) {
            if (array_key_exists($feature['properties']['id'], $mapping)) {
                $filteredFile["features"][] = $feature;
            }
        }

        $filteredFile = $this->mapUrlFromMapping($filteredFile, $mapping);

        // Save
        file_put_contents($this->project_structure->getRoot() . "/geojson/" . "bivacchi_webapp.geojson", json_encode($filteredFile));
        $this->saveSingleGeojsons($filteredFile, "bivacchi");
    }
}
