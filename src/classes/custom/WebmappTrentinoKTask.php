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
    );

    public function check()
    {
        echo "Checking file presence...";

        foreach ($this->baseFileNames as $filename) {
            if (!file_exists($this->aBaseUrl . "geojson/" . $filename)) {
                throw new WebmappExceptionNoFile("ERROR: Missing file " . $this->aBaseUrl . $filename, 1);
            }

            echo "...";
        }

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
                file_put_contents($this->project_structure->getRoot() . "/geojson/" . $filename, json_encode($file));
                echo "$filename DONE\n";
            } catch (WebmappException $e) {
                echo "\n$filename WARNING: " . $e;
            }
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
                "icon" => "wm-icon-generic",
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
                "color" => "#588248",
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
                $taxonomy = array("activity" => array("22"));
                break;
        }

        foreach ($file["features"] as $key => $feature) {
            $file["features"][$key]["properties"]["taxonomy"] = $taxonomy;
        }

        return $file;
    }
}
