<?php

class WebmappGenerateMbtilesJob extends WebmappAbstractJob
{
    /**
     * WebmappGenerateMbtilesJob constructor.
     * @param string $instanceUrl containing the instance url
     * @param string $params containing an encoded JSON with the poi ID
     * @param boolean $verbose
     */
    public function __construct(string $instanceUrl, string $params, $verbose = false)
    {
        parent::__construct("generate_mbtiles", $instanceUrl, $params, $verbose);
    }

    protected function process()
    {
        $id = intval($this->params['id']);
        if (is_null($id)) {
            throw new WebmappExceptionParameterError("The id must be set, null given");
            return;
        }
        $kCodes = [];
        global $wm_config;
        if (isset($wm_config["a_k_instances"]) && is_array($wm_config["a_k_instances"]) && isset($wm_config["a_k_instances"][$this->instanceName])) {
            foreach ($wm_config["a_k_instances"][$this->instanceName] as $kName) {
                $kCodes[] = $kName;
            }
        }

        foreach ($kCodes as $kCode) {
            $this->_generateMbtiles($kCode, $id);
        }

        $this->_success("MBTiles generated successfully");
    }

    private function _generateMbtiles(string $instanceName, int $id, int $maxZoom = 16, int $minZoom = 4)
    {
        $kBaseUrl = isset($wm_config["endpoint"]) && isset($wm_config["endpoint"]["k"])
            ? "{$wm_config["endpoint"]["k"]}"
            : "/var/www/html/k.webmapp.it";
        $mbtilesPath = "{$kBaseUrl}/{$instanceName}/routes/{$id}";
        $geojsonPath = "{$kBaseUrl}/{$instanceName}/geojson";
        $routeGeojsonUrl = "{$geojsonPath}/{$id}.geojson";
        $decrypted = false;

        if ($this->verbose) {
            $this->_verbose("Generating map.mbtiles for {$instanceName}");
            $this->_verbose(" - Route ID    : $id");
            $this->_verbose(" - Min zoom    : $minZoom");
            $this->_verbose(" - Max zoom    : $maxZoom");
            $this->_verbose(" - MBTiles path: $mbtilesPath");
            $this->_verbose(" - Geojson path: $geojsonPath");
        }

        if ($this->verbose) {
            $this->_verbose("Cleaning temporary tables");
        }
        $cmd = 'psql -U webmapp -d offline -h localhost -c "DROP TABLE tmp0;"';
        system($cmd . " > /dev/null 2>&1");
        $cmd = 'psql -U webmapp -d offline -h localhost -c "DROP TABLE tmp1;"';
        system($cmd . " > /dev/null 2>&1");
        $cmd = 'psql -U webmapp -d offline -h localhost -c "DROP TABLE tmp2;"';
        system($cmd . " > /dev/null 2>&1");
        $cmd = 'psql -U webmapp -d offline -h localhost -c "DROP TABLE tmp3;"';
        system($cmd . " > /dev/null 2>&1");
        $cmd = 'psql -U webmapp -d offline -h localhost -c "DROP TABLE tmp4;"';
        system($cmd . " > /dev/null 2>&1");
        $cmd = 'psql -U webmapp -d offline -h localhost -c "DROP TABLE tmp5;"';
        system($cmd . " > /dev/null 2>&1");
        $cmd = 'psql -U webmapp -d offline -h localhost -c "DROP TABLE tmp6;"';
        system($cmd . " > /dev/null 2>&1");
        $cmd = 'psql -U webmapp -d offline -h localhost -c "DROP TABLE tmpbuf;"';
        system($cmd . " > /dev/null 2>&1");
        $cmd = 'psql -U webmapp -d offline -h localhost -c "DROP TABLE tmpgrid;"';
        system($cmd . " > /dev/null 2>&1");
        $cmd = 'psql -U webmapp -d offline -h localhost -c "DROP TABLE tmptracks;"';
        system($cmd . " > /dev/null 2>&1");

        if ($this->verbose) {
            $this->_verbose("Using {$routeGeojsonUrl} file");
        }
        $geojson = file_get_contents($routeGeojsonUrl);
        json_decode($geojson);

        if (json_last_error() != JSON_ERROR_NONE) {
            $dest = "{$mbtilesPath}/{$id}_temp.geojson";
            if ($this->verbose) {
                $this->_verbose("Decrypting {$routeGeojsonUrl} into {$dest}");
            }

            global $wm_config;
            $method = $wm_config['crypt']['method'];
            $key = $wm_config['crypt']['key'];
            $output = openssl_decrypt($geojson, $method, $key);
            file_put_contents($dest, $output);

            $decrypted = true;
            $routeGeojsonUrl = $dest;
        }

        if ($this->verbose) {
            $this->_verbose("Importing tracks to POSTGRES");
        }
        $cmd = "ogr2ogr -f \"PostgreSQL\" PG:\"host=localhost dbname=offline user=webmapp\" \"{$routeGeojsonUrl}\" -nln tmptracks -t_srs EPSG:3857 -append";
        system($cmd . " > /dev/null");

        chdir($mbtilesPath);

        if ($this->verbose) {
            $this->_verbose("Creating the tiles buffer");
        }
        // Creo il buffer intorno alle linee e ai tracks:
        $cmd = "psql -U webmapp -d offline -h localhost -c \"create table tmp0 AS SELECT ST_Buffer(wkb_geometry, 5000, 'endcap=round join=round') FROM tmptracks;\"";
        system($cmd . " > /dev/null");
        // Dissolvo le aree buffer in una sola:
        $cmd = "psql -U webmapp -d offline -h localhost -c \"create table tmpbuf AS SELECT ST_Union(tmp0.st_buffer) from tmp0;\"";
        system($cmd . " > /dev/null");

        // Ricavo i parametri per creare griglia 1000 m x 1000 m
        $cmd = "psql -U webmapp -d offline -h localhost -c \"create table tmp1 as SELECT (round(st_xmin(st_extent(ST_Envelope(wkb_geometry)))/1000)*1000 - 5000) AS x_min, (round(st_ymin(st_extent(ST_Envelope(wkb_geometry)))/1000)*1000 -5000) AS y_min,  (round(ST_Xmax(st_extent(ST_Envelope(wkb_geometry)))/1000)*1000 +5000) AS x_max, (round(st_ymax(st_extent(ST_Envelope(wkb_geometry)))/1000)*1000 + 5000) AS y_max from tmptracks;\"";
        system($cmd . " > /dev/null");
        $cmd = "psql -U webmapp -d offline -h localhost -c \"create table tmp2 as SELECT (y_max - y_min)/2000 as rows, (x_max - x_min)/2000 as cols, x_min as x0, y_min as y0 from tmp1;\"";
        system($cmd . " > /dev/null");

        // Creo griglia con passo 2000 m x 2000 m
        $cmd = "psql -U webmapp -d offline -h localhost -c \"CREATE TABLE tmpgrid AS SELECT ST_SetSRID(cells.geom,3857) as geom FROM tmp2, ST_CreateGrid(tmp2.rows::integer, tmp2.cols::integer, 2000, 2000, tmp2.x0::integer, tmp2.y0::integer) AS cells;\"";
        system($cmd . " > /dev/null");

        // Creo selezione delle celle contenute nel buffer:
        $cmd = "psql -U webmapp -d offline -h localhost -c \"create table tmp3 as SELECT geom FROM tmpgrid, tmpbuf WHERE ST_Contains(tmpbuf.st_union, tmpgrid.geom);\"";
        system($cmd . " > /dev/null");

        // Creo tabella con scritti i comandi di creazioni tiles:
        $cmd = "psql -U webmapp -d offline -h localhost -c \"create table tmp4 as SELECT concat('tl copy  -z 14 -Z {$maxZoom} -b \\\"', st_xmin(ST_Envelope(ST_transform(geom, 4326))), ' ', st_ymin(ST_Envelope(ST_transform(geom, 4326))), ' ', st_xmax(ST_Envelope(ST_transform(geom, 4326))), ' ', st_ymax(ST_Envelope(ST_transform(geom, 4326))), '\\\"  file:/root/api.webmapp.it/tiles file://./map') AS bbox from tmp3;\"";
        system($cmd . " > /dev/null");
        $cmd = "psql -U webmapp -d offline -h localhost -c \"\COPY tmp4 TO 'tmp4.csv' DELIMITER ',';\"";
        system($cmd . " > /dev/null");
        $cmd = "mv tmp4.csv tlcopy.sh";
        system($cmd . " > /dev/null");

        $cmd = "psql -U webmapp -d offline -h localhost -c \"create table tmp5 as SELECT (st_xmin(st_extent(ST_Envelope(ST_Transform(st_union,4326))))-0.045) AS x_min, (st_ymin(st_extent(ST_Envelope(ST_Transform(st_union,4326))))-0.045) AS y_min,  (ST_Xmax(st_extent(ST_Envelope(ST_Transform(st_union,4326))))+0.045) AS x_max, (st_ymax(st_extent(ST_Envelope(ST_Transform(st_union,4326))))+0.045) AS y_max from tmpbuf;\"";
        system($cmd . " > /dev/null");

        // Creo comando di creazione file corretto metadata.json:
        $cmd = "psql -U webmapp -d offline -h localhost -c \"create table tmp6 as SELECT concat('{\\\"minzoom\\\":{$minZoom},\\\"maxzoom\\\":{$maxZoom},\\\"bounds\\\":[', tmp5.x_min, ',', tmp5.y_min, ',', tmp5.x_max, ',', tmp5.y_max, ']}') AS scommand from tmp5;\"";
        system($cmd . " > /dev/null");
        $cmd = "psql -U webmapp -d offline -h localhost -c \"\COPY tmp6 TO 'tmp6.csv' DELIMITER '|';\"";
        system($cmd . " > /dev/null");

        $cmd = "psql -U webmapp -d offline -h localhost -c \"DROP TABLE tmp6;\"";
        system($cmd . " > /dev/null");
        $cmd = "psql -U webmapp -d offline -h localhost -c \"create table tmp6 as SELECT concat('tl copy  -z {$minZoom} -Z 13 -b \\\"',tmp5.x_min, ' ', tmp5.y_min, ' ', tmp5.x_max, ' ', tmp5.y_max, '\\\" file:/root/api.webmapp.it/tiles file://./map') AS scommand from tmp5;\"";
        system($cmd . " > /dev/null");
        $cmd = "psql -U webmapp -d offline -h localhost -c \"\COPY tmp6 TO 'bbox.sh' DELIMITER '|';\"";
        system($cmd . " > /dev/null");

        // Eseguo i comandi di creazione mbtile totale, copia dal file mbtile al sistema di cartelle ./map e creazione del nuovo mbtile:
        if ($this->verbose) {
            $this->_verbose("Creating the mbtiles file");
        }
        $cmd = "bash tlcopy.sh";
        system($cmd);
        $cmd = "bash bbox.sh";
        system($cmd);

        if ($this->verbose) {
            $this->_verbose("Cleaning up");
        }
        $cmd = "mv tmp6.csv map/metadata.json";
        system($cmd);
        $cmd = "rm -rf map.mbtiles";
        system($cmd);
        $cmd = "tl copy file://./map mbtiles://./map.mbtiles";
        system($cmd);
        $cmd = "rm -rf map/";
        system($cmd);
        $cmd = "rm tlcopy.sh";
        system($cmd);
        $cmd = "rm bbox.sh";
        system($cmd);
        if ($decrypted) {
            $cmd = "rm {$routeGeojsonUrl}";
            system($cmd);
        }
    }
}