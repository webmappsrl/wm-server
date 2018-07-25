<?php

class WebmappPNABAlgorabTask extends WebmappAbstractTask {

	private $url;

	public function check() {

		$this->url   = 'http://parcocard.algorab.net/calendario.ashx';
		return TRUE;
	}

	public function process() {
		echo "\n\nProcessing WebmappPNABAlgorabTask\n\n";
		$this->processEvents();
	}

	private function processEvents() {
		echo "Processing ".$this->url."\n\n";
		$items = json_decode(file_get_contents($this->url),TRUE);

		if(!is_array($items) || count($items)<0) {
			throw new Exception("Errore nella lettura dell'URL", 1);
		};
		$first = strtotime("today 00:00");
		$last  = $first+604800;
		// Array degli eventi
		//$events[$giorno][$key]=array(id=ID,partenze=array(15.00,16.00));
		$events=array();
		foreach ($items as $item) {

    // [783] => Array
    //     (
    //         [idDataAttivita] => 856
    //         [idAmbito] => 1
    //         [NomeAmbito] => ApT M. di Campiglio 
    //         [NomeAttivita] => L'Antica Chiesa racconta - 15.00
    //         [DataEvento] => 1538265600000
    //         [OraPartenza] => 15.00
    //         [MinIscritti] => 1
    //         [MaxIscritti] => 50
    //         [Iscritti] => 0
    //     )
			$id=$item['idDataAttivita'];
			$ts=$item['DataEvento']/1000;
			$date=date('d/m/Y H:m:i',$ts);
			$import = false;
			if ($ts>=$first && $ts<=$last) $import = true;

            // coord:
            // https://maps.googleapis.com/maps/api/geocode/json?address=ApT%20M.%20di%20Campiglio&key=AIzaSyAM4pUbcFpXPbk5TO2TIQotR96R9bw15oc

			if ($import) {
				$ambito = $item['NomeAmbito'];
				$id_ambito = $item['idAmbito'];
				$day=date('d/m/Y',$ts);
				$nome_ext = $item['NomeAttivita'];
				$nome_arr=explode('-', $nome_ext);
				$nome=$nome_arr[0];
				$ora = $item['OraPartenza'];
				$key="$id_ambito $nome";
				echo "Attivita DAY=$day NOME=$nome ORA=$ora KEY=$key .. ";
				if(!isset($events[$day][$key])){
					$orari=array();$orari[]=$ora;
					$info = array(
						'id'=>$id,
						'id_ambito'=>$id_ambito,
						'ambito'=>$ambito,
						'orari_partenza'=>$orari,
						'nome' => $nome
						);
					$events[$day][$key]=$info;
					echo "Processing ";
				}
				else {
					echo "Skipping ";

				}
				echo "\n";
			}	
		}

		$l  = new WebmappLayer( 'eventi' );
		foreach ($events as $day => $items) {
			foreach ($items as $ja) {
					$j['id']                  = 'event_'.$ja['id'];
					$j['title']['rendered']   = $ja['nome'];
					$j['content']['rendered'] = '';
					$id_ambito=$ja['id_ambito'];
					// TODO: retrieve lat/lon
					$lat=46.23325699999999;
					$lon=10.8244016;
					$j['n7webmap_coord']['lng'] = $lon;
					$j['n7webmap_coord']['lat'] = $lat;
					$poi = new WebmappPoiFeature( $j );
					$algorab_info = array(
                       'place'=>$ja['ambito'],
                       'day'=> $day,
                       'start' => 'orari_partenza'
						);
					$poi->addProperty('algo',$algorab_info);
					$l->addFeature( $poi );
				}
		};
		$l->write( $this->project_structure->getPathGeojson() );

	}


}