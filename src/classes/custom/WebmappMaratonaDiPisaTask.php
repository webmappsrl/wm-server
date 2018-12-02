<?php
class WebmappMaratonaDiPisaTask extends WebmappAbstractTask {

	public function check() {
		return TRUE;
	}

    public function process(){
        // PISA https://www.openstreetmap.org/?mlat=43.71586&mlon=10.40189#map=19/43.71586/10.40189
        $lon_pisa = 10.40189;
        $lat_pisa = 43.71586;
        $rho = 0.03;

        $l = new WebmappLayer('runners');

        for ($i=1; $i <= 500; $i++) { 
            echo "Runner $i\n";
            $pos = WebmappUtils::getRandomPoint($lon_pisa,$lat_pisa,$rho);
            $lon = $pos[0];
            $lat = $pos[1];
            // GENERAL
            $j['id']=$i;
            $j['title']['rendered']='Number - '.$i;
            $j['content']['rendered']="Pettorale $i <br/>Ultimo aggiornamento XX </br>- Posizione ($lat,$lon)";
            $j['n7webmap_coord']['lng']=$lon;
            $j['n7webmap_coord']['lat']=$lat;
            $poi = new WebmappPoiFeature($j);
            $l->addFeature($poi);
        }
        $l->write($this->getRoot().'/geojson/');
        return TRUE;
    }

}
