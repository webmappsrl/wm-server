<?php
/**
Questo TASK crea i geojson e le TILES utfgrid a partire dai dati del Trentino
forniti dalla SAT nella directory API A
**/
class WebmappTrentinoATask extends WebmappAbstractTask {

  private $tmp_path='';

  public function check() {
      return TRUE;
  }

  public function process(){
    // Creating tmp dir
    $this->tmp_path=$this->getRoot().'/tmp';
    if (file_exists($this->tmp_path)) {
        system("rm -rf ".$this->tmp_path);
    }
    system("mkdir ".$this->tmp_path);

    // Start script

    // DOWNLOAD and UNZIP DATA
    $zip=$this->tmp_path.'sentieri_tratte.json.zip';
    $cmd="curl -o $zip -g https://sentieri.sat.tn.it/download/webmapp/sentierisat.json.zip";
    system ($cmd);
    $cmd="unzip $zip -d {$this->tmp_path}";
    system($cmd);

    // UPDATE POSTGRES
    // ogr2ogr -t_srs EPSG:3857 -f "PostgreSQL" PG:"dbname=sat user=pgadmin host=localhost" "punti_appoggio.json" -nln punti_appoggio -overwrite
    $options = "-t_srs EPSG:3857 -f 'PostgreSQL' PG:'dbname=sat user=pgadmin host=46.101.124.52' -overwrite";
    echo "Upload punti_appoggio.json to postgis\n";
    $cmd = "ogr2ogr $options '{$this->tmp_path}/punti_appoggio.json' -nln 'punti_appoggio'"; system($cmd);
    
    echo "Upload punti_interesse.json to postgis\n";
    $cmd = "ogr2ogr $options '{$this->tmp_path}/punti_interesse.json' -nln 'punti_interesse'"; system($cmd);
    
    echo "Upload sentieri_localita.json to postgis\n";
    $cmd = "ogr2ogr $options '{$this->tmp_path}/sentieri_localita.json' -nln 'sentieri_localita'"; system($cmd);
    
    echo "Upload sentieri_lunga_percorrenza.json to postgis\n";
    $cmd = "ogr2ogr $options '{$this->tmp_path}/sentieri_lunga_percorrenza.json' -nln 'sentieri_lp'"; system($cmd);
    
    echo "Upload sentieri_sottotratte.json to postgis\n";
    $cmd = "ogr2ogr $options '{$this->tmp_path}/sentieri_sottotratte.json' -nln 'sentieri_sottotratte'"; system($cmd);
    
    echo "Upload sentieri_tratte.json to postgis\n";
    $cmd = "ogr2ogr $options '{$this->tmp_path}/sentieri_tratte.json' -nln 'sentieri_tratte'"; system($cmd);
    
    echo "Upload sentieri_luoghi.json to postgis\n";
    $cmd = "ogr2ogr $options '{$this->tmp_path}/sentieri_luoghi.json' -nln 'sentieri_luoghi'"; system($cmd);

    // PSQL 
    $psql_conn = "-U pgadmin -d sat -h 46.101.124.52";
    echo "Adding status to sentieri_sottotratte";
    $cmd = "psql $psql_conn -c 'alter table sentieri_sottotratte add column stato varchar(10);'"; system($cmd);


    $options = "-nlt LINESTRING 'PG:host=46.101.124.52 dbname=sat user=pgadmin'";
// NO ogr2ogr -f GeoJSON sentieri_tratte.geojson -nlt LINESTRING "PG:host=localhost dbname=sat user=pgadmin" -sql "SELECT  ST_Transform (ST_Simplify(wkb_geometry, 1000), 4326) as geom, ogc_fid as id, numero as ref, descr as description, dataagg, competenza, concat(numero, ' ', denominaz) as \"name\", difficolta, loc_inizio, loc_fine, quota_iniz, quota_fine, quota_min, quota_max, concat(lun_planim,' m') as distance, lun_inclin, t_andata, t_ritorno, gr_mont, comuni_toc, concat('sentieri.sat.tn.it/schede-sentieri?numero=', numero) as url_scheda, concat('sentieri.sat.tn.it/imgviewer?numero=', numero) as url_foto from sentieri_tratte  order by ref"
// NO ogr2ogr -f GeoJSON sentieri_lunga_percorrenza.geojson -nlt LINESTRING "PG:host=localhost dbname=sat user=pgadmin" -sql "SELECT ST_Union(ST_Transform (ST_Simplify(wkb_geometry, 8), 4326)) as geom, concat(sentiero, ' - ', tappe) as ref, label from sentieri_lp group by ref, label order by ref"
// NO ogr2ogr -f GeoJSON sentieri_luoghi.geojson -nlt LINESTRING "PG:host=localhost dbname=sat user=pgadmin" -sql "SELECT ST_Transform(wkb_geometry, 4326) as geom, numero as ref, luogo as place_code, concat('N ', nord_geo, ', E ', est_geo) as coordinates, concat('N ', nord_utm, ', E ', est_utm) as utm_coordinates from sentieri_luoghi order by ref"

// SI ogr2ogr -f GeoJSON sentieri_localita.geojson -nlt LINESTRING "PG:host=localhost dbname=sat user=pgadmin" -sql "SELECT ST_Transform(wkb_geometry, 4326) as geom, localita as name, quota as ele, concat('N ', round(ST_Y(ST_Transform(wkb_geometry, 4326)::geometry)::numeric,5), ', E ', round(ST_X(ST_Transform(wkb_geometry, 4326)::geometry)::numeric,5)) as coordinates, concat('N ', round(ST_Y(ST_Transform(wkb_geometry, 25832)::geometry)::numeric,0), ', E ', round(ST_X(ST_Transform(wkb_geometry, 25832)::geometry)::numeric,0)) as utm_coordinates  from sentieri_localita order by name"

// SI ogr2ogr -f GeoJSON punti_interesse.geojson -nlt LINESTRING "PG:host=localhost dbname=sat user=pgadmin" -sql "SELECT ST_Transform(wkb_geometry, 4326) as geom, nome as name, categoria as category, descrizione as description, concat('N ', round(ST_Y(ST_Transform(wkb_geometry, 4326)::geometry)::numeric,5), ', E ', round(ST_X(ST_Transform(wkb_geometry, 4326)::geometry)::numeric,5)) as coordinates, concat('N ', round(ST_Y(ST_Transform(wkb_geometry, 25832)::geometry)::numeric,0), ', E ', round(ST_X(ST_Transform(wkb_geometry, 25832)::geometry)::numeric,0)) as utm_coordinates from punti_interesse order by name"

// SI ogr2ogr -f GeoJSON punti_appoggio.geojson -nlt LINESTRING "PG:host=localhost dbname=sat user=pgadmin" -sql "SELECT ST_Transform(wkb_geometry, 4326) as geom, nome as name, localita as locality, quota as ele, descrizione as description, posti as capacity, CASE WHEN acqua = 'Sì' THEN 'yes' ELSE 'no' END as drinking_water, concat('N ', round(ST_Y(ST_Transform(wkb_geometry, 4326)::geometry)::numeric,5), ', E ', round(ST_X(ST_Transform(wkb_geometry, 4326)::geometry)::numeric,5)) as coordinates, concat('N ', round(ST_Y(ST_Transform(wkb_geometry, 25832)::geometry)::numeric,0), ', E ', round(ST_X(ST_Transform(wkb_geometry, 25832)::geometry)::numeric,0)) as utm_coordinates from punti_appoggio order by name"

// SI ogr2ogr -f GeoJSON sentieri_tratte_sy.geojson -nlt LINESTRING "PG:host=localhost dbname=sat user=pgadmin" 
    $select = <<<EOS
SELECT  ST_Transform (ST_Simplify(wkb_geometry, 20), 4326) as geom, 
        ogc_fid as id, 
        numero as ref, 
        descr as description, 
        dataagg as modified, 
        competenza, 
        concat(numero, ' ', denominaz) as \"name\", 
        difficolta as scale, 
        loc_inizio as start, 
        loc_fine as end, 
        quota_iniz as ele_from, 
        quota_fine as ele_to, 
        quota_min as ele_min, 
        quota_max as ele_max, 
        concat(lun_planim,' m') as distance, 
        lun_inclin , 
        t_andata as duration_forward, 
        t_ritorno as duration_backword, 
        gr_mont, 
        comuni_toc, 
        concat('sentieri.sat.tn.it/schede-sentieri?numero=', numero) as web, 
        concat('sentieri.sat.tn.it/imgviewer?numero=', numero) as image, 
        concat('sentieri.sat.tn.it/gpx-sentieri?numero=', numero) as gpx 
FROM sentieri_tratte  order by ref
EOS;
    $cmd = "rm -f {$this->getRoot()}/geojson/sentieri_tratte_sy.geojson"; system($cmd);
    $cmd = "ogr2ogr -f GeoJSON {$this->getRoot()}/geojson/sentieri_tratte_sy.geojson $options -sql \"$select\"";
    system($cmd);
    // TODO: aggiornare con sed i nomi dei campi di timo XXX:YYY
    $to_change = array(
        'ele_from'=>'ele:from',
        'ele_to'=>'ele:to',
        'ele_min'=>'ele:min',
        'ele_max'=>'ele:max',
        'duration_forward'=>'duration:forward',
        'duration_backword'=>'duration_backword'
        );
    $in = file_get_contents($this->getRoot().'/geojson/sentieri_tratte_sy.geojson');
    foreach($to_change as $old => $new) {
        $in = preg_replace("/$old/",$new,$in);
    }
    file_put_contents($this->getRoot().'/geojson/sentieri_tratte_sy.geojson', $in);

    //system("rm -rf ".$this->tmp_path);
    return TRUE;
  }

}
