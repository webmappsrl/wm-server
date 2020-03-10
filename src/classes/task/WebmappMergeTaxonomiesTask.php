<?php

class WebmappMergeTaxonomiesTask extends WebmappAbstractTask
{
    private $url;
    private $endpoint;

    public function check()
    {
        // Controllo parametro code http://[code].be.webmapp.it
        if (!array_key_exists('url_or_code', $this->options)) {
            throw new WebmappExceptionConfTask("L'array options deve avere la chiave 'url_or_code'", 1);
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

        echo "Check OK\n\n";
        return true;
    }

    public function process()
    {
        // Create symbolic link for geojson and taxonomies folders
        $src = $this->getRoot();
        $trg = $this->endpoint;

        echo "Copying geojsons from {$trg}/geojson/ to {$src}/geojson/";

        $cmd = "cp {$trg}/geojson/*1.geojson {$src}/geojson/";
        exec($cmd);
        $cmd = "cp {$trg}/geojson/*2.geojson {$src}/geojson/";
        exec($cmd);
        $cmd = "cp {$trg}/geojson/*3.geojson {$src}/geojson/";
        exec($cmd);
        $cmd = "cp {$trg}/geojson/*4.geojson {$src}/geojson/";
        exec($cmd);
        $cmd = "cp {$trg}/geojson/*5.geojson {$src}/geojson/";
        exec($cmd);
        $cmd = "cp {$trg}/geojson/*6.geojson {$src}/geojson/";
        exec($cmd);
        $cmd = "cp {$trg}/geojson/*7.geojson {$src}/geojson/";
        exec($cmd);
        $cmd = "cp {$trg}/geojson/*8.geojson {$src}/geojson/";
        exec($cmd);
        $cmd = "cp {$trg}/geojson/*9.geojson {$src}/geojson/";
        exec($cmd);
        $cmd = "cp {$trg}/geojson/*0.geojson {$src}/geojson/";
        exec($cmd);

        echo " OK\n";

        if (array_key_exists('taxonomies', $this->options)) {
            $taxonomies = $this->options['taxonomies'];

            foreach ($taxonomies as $taxonomy) {
                echo "Copying {$taxonomy} from {$trg}/taxonomies/ to {$src}/taxonomies/";

                $cmd = "cp {$trg}/taxonomies/{$taxonomy}.json {$src}/taxonomies/";
                exec($cmd);

                echo " OK\n";
            }
        }

        return true;
    }
}
