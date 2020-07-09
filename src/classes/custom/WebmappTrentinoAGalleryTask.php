<?php
/**
 * Questo TASK crea i geojson e le TILES utfgrid a partire dai dati del Trentino
 * forniti dalla SAT nella directory API A
 **/
class WebmappTrentinoAGalleryTask extends WebmappAbstractTask
{

    public function check()
    {
        return true;
    }

    public function process() {
        $in = file_get_contents($this->getRoot() . '/geojson/sentieri_tratte.geojson');
        $ja = json_decode($in,TRUE);
        $ja_new=array();
        $ja_new['type']='FeatureCollection';
        $features = $ja['features'];
        $features_new = array();
        // Loop to ADD Images
        foreach($features as $feature) {
            $ref = $feature['properties']['ref'];
            echo "Processing sentiero $ref ... ";
            $feature_new = $feature;
            // Get image list
            $url="https://sentieri.sat.tn.it/webapps/imgall?numero=$ref&dim=me";
            $imgs = WebmappUtils::getJsonFromApi($url);
            if (is_array($imgs) && count($imgs)>0) {

                echo count($imgs);
                $feature_new['properties']['imageGallery']=array();
                foreach($imgs as $img) {
                    $feature_new['properties']['imageGallery'][]=array('src'=>$img);
                }
            }

            else echo "NO IMAGES";

            $features_new[]=$feature_new;
            echo " DONE!\n";
        }
        $ja_new['features']=$features_new;
        $in = json_encode($ja_new);
        file_put_contents($this->getRoot() . '/geojson/sentieri_tratte.geojson', $in);
    }

}
