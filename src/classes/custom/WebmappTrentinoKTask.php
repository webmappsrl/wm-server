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
                "name" => "Punti d'appoggio",
                "taxonomy" => "webmapp_category",
                "is_parent" => false,
                "locale" => "it",
            ),
            "2" => array(
                "id" => "2",
                "name" => "Punti d'interesse",
                "taxonomy" => "webmapp_category",
                "is_parent" => false,
                "locale" => "it",
            ),
            "3" => array(
                "id" => "3",
                "name" => "Rifugi SAT",
                "taxonomy" => "webmapp_category",
                "is_parent" => false,
                "locale" => "it",
            ),
        );

        $activity = array(
            "20" => array(
                "id" => "20",
                "name" => "Località",
                "taxonomy" => "activity",
                "is_parent" => false,
                "locale" => "it",
            ),
            "21" => array(
                "id" => "21",
                "name" => "Sentieri SAT",
                "taxonomy" => "activity",
                "is_parent" => false,
                "locale" => "it",
            ),
            "22" => array(
                "id" => "22",
                "name" => "Itinerari",
                "taxonomy" => "activity",
                "is_parent" => false,
                "locale" => "it",
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
            $file["features"][$key]["properties"]["id"] = $i . "";
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
                $taxonomy = array("activity" => array("20"));
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
