<?php
if (count($argv) < 5) {
    die("Usage: php create_map.php [instance_name] [route_id] [max_zoom] [min_zoom]\n");
}

$instance = $argv[1];
$route_id = $argv[2];
$min_zoom = $argv[3];
$max_zoom = $argv[4];
$mbtiles_path = "/root/k.webmapp.it/{$instance}/routes/{$route_id}";
$geojson_path = "/root/k.webmapp.it/{$argv[1]}/geojson";
$route_geojson_url = "{$geojson_path}/{$route_id}.geojson";
$decrypted = false;

echo "Generating map.mbtiles for {$instance}\n";
echo " - Route ID    : $route_id\n";
echo " - Min zoom    : $min_zoom\n";
echo " - Max zoom    : $max_zoom\n";
echo " - MBTiles path: $mbtiles_path\n";
echo " - Geojson path: $geojson_path\n\n";

echo "Cleaning temporary tables... ";
$cmd = 'psql -U webmapp -d offline -h localhost -c "DROP TABLE tmp0;" > /dev/null 2>&1';
system($cmd);
$cmd = 'psql -U webmapp -d offline -h localhost -c "DROP TABLE tmp1;" > /dev/null 2>&1';
system($cmd);
$cmd = 'psql -U webmapp -d offline -h localhost -c "DROP TABLE tmp2;" > /dev/null 2>&1';
system($cmd);
$cmd = 'psql -U webmapp -d offline -h localhost -c "DROP TABLE tmp3;" > /dev/null 2>&1';
system($cmd);
$cmd = 'psql -U webmapp -d offline -h localhost -c "DROP TABLE tmp4;" > /dev/null 2>&1';
system($cmd);
$cmd = 'psql -U webmapp -d offline -h localhost -c "DROP TABLE tmp5;" > /dev/null 2>&1';
system($cmd);
$cmd = 'psql -U webmapp -d offline -h localhost -c "DROP TABLE tmp6;" > /dev/null 2>&1';
system($cmd);
$cmd = 'psql -U webmapp -d offline -h localhost -c "DROP TABLE tmpbuf;" > /dev/null 2>&1';
system($cmd);
$cmd = 'psql -U webmapp -d offline -h localhost -c "DROP TABLE tmpgrid;" > /dev/null 2>&1';
system($cmd);
$cmd = 'psql -U webmapp -d offline -h localhost -c "DROP TABLE tmptracks;" > /dev/null 2>&1';
system($cmd);
echo "OK\n";

/**
 * input: punti e linee del pacchetto
 * da sviluppare: prendere dalle api i files dei percorsi + file dei punti relativi alla route
 * e copiarli nella cartella /root/api.webmapp.it/italia/tiles/temp/ con il nome di:
 * tracks.geojson
 * importo in postgis i file tracks relativi al pacchetto:
 */

/**
 * Find if the geojson is crypted. If it is extract it use it and delete it
 */
echo "Using {$route_geojson_url} file\n";
$geojson = file_get_contents($route_geojson_url);
json_decode($geojson);

if (json_last_error() != JSON_ERROR_NONE) {
    $dest = "{$mbtiles_path}/{$route_id}_temp.geojson";
    echo "Decrypting {$route_geojson_url} into {$dest}... ";

    $conf = __DIR__ . '/../config.json';
    if (!file_exists($conf)) {
        die("\nMissing configuration file {$conf}: abort\n");
    }
    $wm_config = json_decode(file_get_contents($conf), true);
    $method = $wm_config['crypt']['method'];
    $key = $wm_config['crypt']['key'];
    $output = openssl_decrypt($geojson, $method, $key);
    file_put_contents($dest, $output);

    $decrypted = true;
    $route_geojson_url = $dest;
    echo "OK\n";
}

echo "Importing tracks to POSTGRES...";
$cmd = "ogr2ogr -f \"PostgreSQL\" PG:\"host=localhost dbname=offline user=webmapp\" \"{$route_geojson_url}\" -nln tmptracks -t_srs EPSG:3857 -append";

echo "OK\n";

chdir($mbtiles_path);

echo "Creating the tiles buffer...";
// Creo il buffer intorno alle linee e ai tracks:
$cmd = "psql -U webmapp -d offline -h localhost -c \"create table tmp0 AS SELECT ST_Buffer(wkb_geometry, 5000, 'endcap=round join=round') FROM tmptracks;\"";
system($cmd);
// Dissolvo le aree buffer in una sola:
$cmd = "psql -U webmapp -d offline -h localhost -c \"create table tmpbuf AS SELECT ST_Union(tmp0.st_buffer) from tmp0;\"";
system($cmd);

// Ricavo i parametri per creare griglia 1000 m x 1000 m
$cmd = "psql -U webmapp -d offline -h localhost -c \"create table tmp1 as SELECT (round(st_xmin(st_extent(ST_Envelope(wkb_geometry)))/1000)*1000 - 5000) AS x_min, (round(st_ymin(st_extent(ST_Envelope(wkb_geometry)))/1000)*1000 -5000) AS y_min,  (round(ST_Xmax(st_extent(ST_Envelope(wkb_geometry)))/1000)*1000 +5000) AS x_max, (round(st_ymax(st_extent(ST_Envelope(wkb_geometry)))/1000)*1000 + 5000) AS y_max from tmptracks;\"";
system($cmd);
$cmd = "psql -U webmapp -d offline -h localhost -c \"create table tmp2 as SELECT (y_max - y_min)/2000 as rows, (x_max - x_min)/2000 as cols, x_min as x0, y_min as y0 from tmp1;\"";
system($cmd);

// Creo griglia con passo 2000 m x 2000 m
$cmd = "psql -U webmapp -d offline -h localhost -c \"CREATE TABLE tmpgrid AS SELECT ST_SetSRID(cells.geom,3857) as geom FROM tmp2, ST_CreateGrid(tmp2.rows::integer, tmp2.cols::integer, 2000, 2000, tmp2.x0::integer, tmp2.y0::integer) AS cells;\"";
system($cmd);

// Creo selezione delle celle contenute nel buffer:
$cmd = "psql -U webmapp -d offline -h localhost -c \"create table tmp3 as SELECT geom FROM tmpgrid, tmpbuf WHERE ST_Contains(tmpbuf.st_union, tmpgrid.geom);\"";
system($cmd);

// Creo tabella con scritti i comandi di creazioni tiles:
$cmd = "psql -U webmapp -d offline -h localhost -c \"create table tmp4 as SELECT concat('tl copy  -z 14 -Z $4 -b \"', st_xmin(ST_Envelope(ST_transform(geom, 4326))), ' ', st_ymin(ST_Envelope(ST_transform(geom, 4326))), ' ', st_xmax(ST_Envelope(ST_transform(geom, 4326))), ' ', st_ymax(ST_Envelope(ST_transform(geom, 4326))), '\"  file:/root/api.webmapp.it/tiles file://./map') AS bbox from tmp3;\"";
system($cmd);
$cmd = "psql -U webmapp -d offline -h localhost -c \"\COPY tmp4 TO 'tmp4.csv' DELIMITER ',';\"";
system($cmd);
$cmd = "mv tmp4.csv tlcopy.sh";
system($cmd);

$cmd = "psql -U webmapp -d offline -h localhost -c \"create table tmp5 as SELECT (st_xmin(st_extent(ST_Envelope(ST_Transform(st_union,4326))))-0.045) AS x_min, (st_ymin(st_extent(ST_Envelope(ST_Transform(st_union,4326))))-0.045) AS y_min,  (ST_Xmax(st_extent(ST_Envelope(ST_Transform(st_union,4326))))+0.045) AS x_max, (st_ymax(st_extent(ST_Envelope(ST_Transform(st_union,4326))))+0.045) AS y_max from tmpbuf;\"";
system($cmd);

// Creo comando di creazione file corretto metadata.json:
$cmd = "psql -U webmapp -d offline -h localhost -c \"create table tmp6 as SELECT concat('{\"minzoom\":$3,\"maxzoom\":$4,\"bounds\":[', tmp5.x_min, ',', tmp5.y_min, ',', tmp5.x_max, ',', tmp5.y_max, ']}') AS scommand from tmp5;\"";
system($cmd);
$cmd = "psql -U webmapp -d offline -h localhost -c \"\COPY tmp6 TO 'tmp6.csv' DELIMITER '|';\"";
system($cmd);

$cmd = "psql -U webmapp -d offline -h localhost -c \"DROP TABLE tmp6;\"";
system($cmd);
$cmd = "psql -U webmapp -d offline -h localhost -c \"create table tmp6 as SELECT concat('tl copy  -z $3 -Z 13 -b \"',tmp5.x_min, ' ', tmp5.y_min, ' ', tmp5.x_max, ' ', tmp5.y_max, '\" file:/root/api.webmapp.it/tiles file://./map') AS scommand from tmp5;\"";
system($cmd);
$cmd = "psql -U webmapp -d offline -h localhost -c \"\COPY tmp6 TO 'bbox.sh' DELIMITER '|';\"";
system($cmd);

echo "OK\n";

// Eseguo i comandi di creazione mbtile totale, copia dal file mbtile al sistema di cartelle ./map e creazione del nuovo mbtile:
echo "Creating the mbtiles file...\n";
$cmd = "bash tlcopy.sh";
system($cmd);
$cmd = "bash bbox.sh";
system($cmd);
echo "\nOK\n";

echo "Cleaning up...";
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
echo "OK\n";

echo "\nMBTiles generated successfully\n\n";
