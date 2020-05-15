<?php

class WebmappTrentinoKTask extends WebmappAbstractTask
{
    private $aBaseUrl = "/var/www/html/a.webmapp.it/trentino/";
    private $baseFileNames = array(
        "punti_appoggio.geojson",
        "punti_interesse.geojson",
        "rifugi.geojson",
        "sentieri_localita.geojson",
        "sentieri_tratte.geojson",
        "sentieri_lunga_percorrenza.geojson",
        // "sentieri_lp.geojson",
    );
    private $endpoint;

    public function check()
    {
        echo "Checking file presence...";

        foreach ($this->baseFileNames as $filename) {
            if (!file_exists($this->aBaseUrl . "geojson/" . $filename)) {
                throw new WebmappExceptionNoFile("ERROR: Missing file " . $this->aBaseUrl . $filename, 1);
            }

            echo "...";
        }

        $this->endpoint = $wm_config['endpoint']['a'] . '/trentino';

        echo "Check OK\n";

        return true;
    }

    public function process()
    {
        $this->generateTaxonomies();

        foreach ($this->baseFileNames as $filename) {
            echo "\nProcessing $filename... \n";
            $file = json_decode(file_get_contents($this->aBaseUrl . "geojson/" . $filename), true);

            try {
                $file = $this->addId($file);
                $file = $this->addTaxonomy($file, $filename);
                if ($filename == "punti_appoggio.geojson") {
                    $file = $this->mapDrinkingWater($file);
                    $file = $this->mapCapacity($file);
                    $file = $this->mapLocalityToAddress($file);
                }
                if ($filename == "rifugi.geojson") {
                    $file = $this->mapWebsiteToRelatedUrl($file);
                    $file = $this->mapPictureUrlToImage($file);
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

        $src = $this->getRoot();
        $trg = $this->endpoint;
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
}
