<?php

class WebmappSingleMapTask extends WebmappAbstractTask
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

        return true;
    }

    public function process()
    {
        // Create symbolic link for geojson and taxonomies folders
        $src = $this->getRoot();
        $trg = $this->endpoint;
        $cmd = "rm -Rf {$src}/geojson";
        exec($cmd);
        $cmd = "ln -s {$trg}/geojson {$src}/geojson";
        exec($cmd);

        $cmd = "rm -Rf {$src}/taxonomies";
        exec($cmd);
        $cmd = "ln -s {$trg}/taxonomies {$src}/taxonomies";
        exec($cmd);

        return true;
    }
}
