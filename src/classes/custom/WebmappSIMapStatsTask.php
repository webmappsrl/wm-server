<?php

// Task per la realizzazione di statistiche e file di insieme del Sentiero Italia

class WebmappSIMapStatsTask extends WebmappAbstractTask {
    private $fr = null;
    private $zip_gpx = null;
    private $zip_geojson = null;
    private $zip_kml = null;

    private $regioni_osm = 
array(
    "7011030" => "Sardegna",
    "7011950" => "Sicilia",
    "7125614" => "Calabria",
    "7164643" => "Basilicata",
    "7186477" => "Campania",
    "9290765" => "Puglia",
    "7220974" => "Molise",
    "7401588" => "Abruzzo",
    "7246181" => "Lazio",
    "7448629" => "Umbria",
    "7458976" => "Marche",
    "7468319" => "Toscana Emilia Romagna",
    "7561168" => "Liguria",
    "9521613" => "Valle d'Aosta",
    "7029511" => "Piemonte",
    "7029512" => "Lombardia",
    "7029513" => "Trentino Alto Adige",
    "7029514" => "Veneto",
    "7332771" => "Friuli Venezia Giulia"
);

	public function check() {
        return TRUE;
    }

    public function process() {
        echo "Processing Sentiero Italia \n\n";
        $path = $this->getRoot().'/geojson';

        $italia = new WebmappOSMSuperRelation(1021025);
        $this->fr = fopen($this->getRoot().'/resources/SI.csv','w');
        $fields = array(
            'ID','REGIONE','NAME','REF','STATO',
            'FROM','TO','DIFFICOLTA',
            'DISTANCE','ASCENT','DESCENT','ELE:FROM','ELE:TO','ELE:MAX','ELE:MIN',
            'SIMAP','GEOJSON','GPX','KML','OSM','WMT');
        fputcsv($this->fr,$fields);
        $this->openZip();
        foreach ($italia->getMembers() as $ref => $member ) {
           $this->processRegion($ref);
        }
        echo "Closing CSV\n";
        fclose($this->fr);
        echo "Closing ZIP\n";
        $this->zip_gpx->close();
        $this->zip_kml->close();
        $this->zip_geojson->close();

        return TRUE;
    }

    private function openZip() {
        $this->zip_gpx=new ZipArchive();
        $zipname = $this->getRoot().'/resources/SI_allgpx.zip';
        if($this->zip_gpx->open($zipname,ZIPARCHIVE::CREATE)!==TRUE){
            throw new WebmappException("Impossibile aprire file $zipname", 1);
            
        }
        echo "\n\nFILE $zipname OPEN\n\n";

        $this->zip_kml=new ZipArchive();
        $zipname = $this->getRoot().'/resources/SI_allkml.zip';
        if($this->zip_kml->open($zipname,ZIPARCHIVE::CREATE)!==TRUE){
            throw new WebmappException("Impossibile aprire file $zipname", 1);
            
        }
        echo "\n\nFILE $zipname OPEN\n\n";

        $this->zip_geojson=new ZipArchive();
        $zipname = $this->getRoot().'/resources/SI_allgeojson.zip';
        if($this->zip_geojson->open($zipname,ZIPARCHIVE::CREATE)!==TRUE){
            throw new WebmappException("Impossibile aprire file $zipname", 1);
            
        }
        echo "\n\nFILE $zipname OPEN\n\n";

    }

    private function processRegion($id) {
        $color=array('#636363'=>'grigio','#A63FD1'=>'viola','#E35234'=>'rosso');
        $name = $this->regioni_osm[$id];
        echo "\nProcessing Regione $name\n";
        $regione = new WebmappOSMSuperRelation($id);
        $count = 0 ;
        foreach ($regione->getMembers() as $ref => $member) {
            if ($member['type']=='relation') {
                echo "Processing tappa $ref ";
                $geojson = $this->getRoot().'/geojson/'.$ref.'.geojson';
                if (file_exists($geojson)) {
                    $item = array();
                    echo "OK ..";
                    $j = WebmappUtils::getJsonFromApi($geojson);
                    $p = $j['properties'];
                    //'ID','REGIONE','NAME','REF','STATO',
                    $item[]=$ref;
                    $item[]=$name;
                    $item[]=isset($p['name'])?$p['name']:"";
                    $item[]=isset($p['ref'])?$p['ref']:"";
                    $item[]=isset($p['color'])?$color[$p['color']]:"";
                    //'FROM','TO','DIFFICOLTA',
                    $item[]=isset($p['from'])?$p['from']:"";
                    $item[]=isset($p['to'])?$p['to']:"";
                    $item[]=isset($p['cai_scale'])?$p['cai_scale']:"";
                    //'DISTANCE','ASCENT','DESCENT','ELE:FROM','ELE:TO','ELE:MAX','ELE:MIN'
                    $item[]=isset($p['distance'])?$p['distance']:"";
                    $item[]=isset($p['ascent'])?$p['ascent']:"";
                    $item[]=isset($p['descent'])?$p['descent']:"";
                    $item[]=isset($p['ele:from'])?$p['ele:from']:"";
                    $item[]=isset($p['ele:to'])?$p['ele:to']:"";
                    $item[]=isset($p['ele:max'])?$p['ele:max']:"";
                    $item[]=isset($p['ele:min'])?$p['ele:min']:"";
                    //'SIMAP','GEOJSON','GPX','KML','OSM','WMT';
                    $item[]='http://simap.j.webmapp.it/#/layer/'.$name.'/'.$ref;
                    $item[]='http://simap.j.webmapp.it/geojson/'.$ref.'.geojson';
                    $item[]='http://simap.j.webmapp.it/resources/'.$ref.'.gpx';
                    $item[]='http://simap.j.webmapp.it/resources/'.$ref.'.kml';
                    $item[]='https://www.openstreetmap.org/relation/'.$ref;
                    $item[]='https://hiking.waymarkedtrails.org/#route?id='.$ref;
                    echo " .. write CSV .. ";
                    fputcsv($this->fr,$item);

                    // ADDING RESOURCES TO ZIP
                    echo " .. adding GPX to zip .. ";
                    $filename = $this->getRoot().'/resources/'.$ref.'.gpx';
                    $newfilename = basename($filename);
                    $this->zip_gpx->addFile($filename,$newfilename);
                    
                    echo " .. adding KML to zip .. ";
                    $filename = $this->getRoot().'/resources/'.$ref.'.kml';
                    $newfilename = basename($filename);
                    $this->zip_kml->addFile($filename,$newfilename);

                    echo " .. adding GEOJSON to zip .. ";
                    $filename = $this->getRoot().'/geojson/'.$ref.'.geojson';
                    $newfilename = basename($filename);
                    $this->zip_geojson->addFile($filename,$newfilename);

                } else {
                    echo "No geojson file... skipping.";
                }
                echo "\n";
            } else {
                echo "  ===> WARNING MEMBER IS NOT RELATION ($ref) ... SKIP \n";
            }
        }
        echo "\n\n";
    }

}
