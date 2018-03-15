<?php

class WebmappVetrinaToscanaTask extends WebmappAbstractTask {

	private $url;
	private $max;
	private $types = [];

	public function check() {

		// OPTIONS
		if ( ! array_key_exists( 'url', $this->options ) ) {
			throw new Exception( "L'opzione URL è obbligatoria", 1 );
		}
		if ( ! array_key_exists( 'max', $this->options ) ) {
			throw new Exception( "L'opzione max è obbligatoria", 1 );
		}
		if ( ! array_key_exists( 'types', $this->options ) ) {
			throw new Exception( "L'opzione types è obbligatoria", 1 );
		}

		$this->url   = $this->options['url'];
		$this->max   = $this->options['max'];
		$this->types = explode( ',', $this->options['types'] );

		// $a = get_headers($this->url);
		// $match = (preg_match('/301/', $a[0]));
		// if($match==0) {
		// 	echo "Error connecting to server: \n";
		// 	print_r($a);
		// 	return false;
		// }
		return TRUE;
	}

	public function process() {
		echo "\n\nProcessing WebmappVetrinaToscanaTask\n\n";
		foreach ( $this->types as $type ) {
			$this->processLayer( $type );
		}
	}

	private function processLayer( $type ) {
		echo "\nProcessing Layer for $type\n";
		$l     = new WebmappLayer( $type );
		$page  = 0;
		$count = 0;
		$total = 0;
		do {
			$page ++;
			$api = $this->url . '/' . $type . "?per_page=10&page=$page";
			echo "Getting data form URL $api ... ";
			$items = WebmappUtils::getJsonFromApi( $api );
			if (isset($items['data']['status']) && $items['data']['status'] == 400 ) {
				$count = 0;
			} else 
			{
				$count = count($items);
			}
			$total += $count;
			if ( $count > 0 ) {
				echo "Found $count items... adding to layer\n";

				foreach ( $items as $ja ) {
					//if (empty($ja['id'])){
					//	break;
					//}
					$j                        = [];
					$j['id']                  = $ja['id'];
					$j['title']['rendered']   = $ja['title']['rendered'];

				    if ($type == 'event'){

						if ( !empty( $ja['meta-fields']['subtitle'][0] ) ) {
							$j['content']['rendered'] = "<h4 class=\"subtitle\">" . $ja['meta-fields']['subtitle'][0] . "</h4>";
						}

					    if ( !empty( $ja['meta-fields']['vt_data_inizio'][0] ) ) {
						    $j['content']['rendered'] .= "<h5 class=\"event-date\">Dal " . $ja['meta-fields']['vt_data_inizio'][0] . "</h5> ";
					    }
					    if ( !empty( $ja['meta-fields']['vt_data_fine'][0] ) ) {
						    $j['content']['rendered'] .= "<h5 class=\"event-date\">Al " . $ja['meta-fields']['vt_data_fine'][0] . "</h5> ";
					    }
					    if ( !empty( $ja['meta-fields']['orari'][0] ) ) {
						    $j['content']['rendered'] .= "<h5 class=\"event-date\">Orari " . $ja['meta-fields']['orari'][0] . "</h5>";
					    }

					    $j['content']['rendered'] .= $ja['content']['rendered'];

				    } else {
					    $j['content']['rendered'] = $ja['content']['rendered'];
				    }


					if ( !empty( $ja['acf']['vt_gallery'] ) ) {
						$j['n7webmap_media_gallery'] = $ja['acf']['vt_gallery'];
					}
					if ( !empty( $ja['acf']['vt_google_map']['address'] ) ) {
						$j['address'] = $ja['acf']['vt_google_map']['address'];
					}
					if ( !empty( $ja['acf']['vt_google_map']['lng'] ) ) {
						$j['n7webmap_coord']['lng'] = $ja['acf']['vt_google_map']['lng'];
					}
					if ( !empty( $ja['acf']['vt_google_map']['lat'] ) ) {
						$j['n7webmap_coord']['lat'] = $ja['acf']['vt_google_map']['lat'];
					}
					if ( !empty( $ja['meta-fields']['vt_telefono'][0] ) ) {
						$j['contact:phone'] = $ja['meta-fields']['vt_telefono'][0];
					}
					if ( !empty( $ja['meta-fields']['vt_email'][0] ) ) {
						$j['contact:email'] = $ja['meta-fields']['vt_email'][0];
					}

					$j['opening_hours'] = "";
					if ( $type == 'restaurant' || $type == 'shop' ) {
						if ( !empty( $ja['meta-fields']['vt_dalleorepranzo'][0] ) ) {
							$j['opening_hours'] .= "Dalle " . $ja['meta-fields']['vt_dalleorepranzo'][0] . " ";
						}
						if ( !empty( $ja['meta-fields']['vt_alleorepranzo'][0] ) ) {
							$j['opening_hours'] .= "Alle " . $ja['meta-fields']['vt_alleorepranzo'][0] . " ";
						}
						if ( !empty( $ja['meta-fields']['vt_dalleorecena'][0] ) ) {
							$j['opening_hours'] .= "Dalle " . $ja['meta-fields']['vt_dalleorecena'][0] . " ";
						}
						if ( !empty( $ja['meta-fields']['vt_alleorecena'][0] ) ) {
							$j['opening_hours'] .= "Alle " . $ja['meta-fields']['vt_alleorecena'][0] . " - ";
						}
					} else if ( $type == 'producer' ) {

						if ( !empty( $ja['meta-fields']['vt_aperturainizioda'][0] ) ) {
							$j['opening_hours'] .= "Da " . $ja['meta-fields']['vt_aperturainizioda'][0] . " ";
						}
						if ( !empty( $ja['meta-fields']['vt_aperturainizioa'][0] ) ) {
							$j['opening_hours'] .= "A " . $ja['meta-fields']['vt_aperturainizioa'][0] . " ";
						}
						if ( !empty( $ja['meta-fields']['vt_aperturafineda'][0] ) ) {
							$j['opening_hours'] .= "Da " . $ja['meta-fields']['vt_aperturafineda'][0] . " ";
						}
						if ( !empty( $ja['meta-fields']['vt_aperturafinea'][0] ) ) {
							$j['opening_hours'] .= "A " . $ja['meta-fields']['vt_aperturafinea'][0] . " - ";
						}

					}

					if ( !empty( $ja['meta-fields']['vt_chiusura'][0] ) ) {
						$j['opening_hours'] .= "Giorno di chiusura " . $ja['meta-fields']['vt_chiusura'][0];
					}

					if ( !empty( $ja['meta-fields']['vt_carte'][0] ) ) {
						$j['content']['rendered'] .= "Carte accettate: " . $ja['meta-fields']['vt_carte'][0];
					}

					if ( !empty( $ja['meta-fields']['vt_facebook'][0] ) ) {
						$j['content']['rendered'] .= "<br /><a href=" . $ja['meta-fields']['vt_facebook'][0] . " class=\"social-link-2\"><span class=\"icon\"><img src=\"http://www.vetrina.toscana.it/wp-content/themes/vetrinatoscana/images/social-icon-fb.svg\" width=\"35px\"></span></a> ";
					}

					if ( !empty( $ja['meta-fields']['vt_twitter'][0] ) ) {
						$j['content']['rendered'] .= "<a href=" . $ja['meta-fields']['vt_twitter'][0] . " class=\"social-link-2\"><span class=\"icon\"><img src=\"http://www.vetrina.toscana.it/wp-content/themes/vetrinatoscana/images/social-icon-tw.svg\" width=\"35px\"></span></a> ";
					}

					if ( !empty( $ja['meta-fields']['vt_googleplus'][0] ) ) {
						$j['content']['rendered'] .= "<a href=" . $ja['meta-fields']['vt_googleplus'][0] . " class=\"social-link-2\"><span class=\"icon\"><img src=\"http://www.vetrina.toscana.it/wp-content/themes/vetrinatoscana/images/social-icon-gplus.svg\" width=\"35px\"></span></a> ";
					}


					if ( !empty( $ja['meta-fields']['vt_website'][0] ) ) {
						$j['content']['rendered'] .= "<br />Sito Web: <a href=\"" . $ja['meta-fields']['vt_website'][0] . "\">" . $ja['meta-fields']['vt_website'][0] . "</a>";
					}

					if ( !empty( $ja['link'] ) ) {
						$j['content']['rendered'] .= "<br />Vedi tutti i dettagli su: <a href=\"" . $ja['link'] . "\">VetrinaToscana.it</a>";
					}

					$poi = new WebmappPoiFeature( $j );
					$l->addFeature( $poi );
				}
			} else {
				echo "No more items found.\n";
			}

		} while ( $count > 0 && $total < $this->max );
		echo "Writing $total POI\n";
		$l->write( $this->project_structure->getPathGeojson() );

	}


}