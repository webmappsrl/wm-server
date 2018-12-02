<?php

final class WebmappPostGis {

    private $resource;

    public static function Instance()
    {
        static $inst = null;
        if ($inst === null) {
            $inst = new WebmappPostGis();
        }
        return $inst;
    }

    private function __construct()
    {
        global $wm_config;
        if(!isset($wm_config['postgis'])) {
            throw new WebmappExceptionConfPostgis("No Postgist section in conf.json", 1);  
        }

        // TODO: check other parametr
        $pgconf = $wm_config['postgis'];

        $username = $pgconf['username'];
        $database = $pgconf['database'];
        $host = $pgconf['host'];
        $password= $pgconf['password'];

        // TODO: gestire l'errore con una eccezione
        $this->resource = pg_connect("host=$host dbname=$database user=$username password=$password")
        or die('Could not connect: ' . pg_last_error());

    }

    public function getResource() {
    	return $this->resource;
    }
}