<?php
class WebmappIntense2ImportTask extends WebmappAbstractTask {

	private $token;

	public function check() {
		$this->getToken();
		return TRUE;
	}

    /**
     * Effettua una query per recuperare il token da utilizzare nelle chiamate
     * successive
     */
	private function getToken() {
	    // CHECK API and get token
		$post = [
			'grant_type' => 'password',
			'scope' => 'ost_editor',
			'username' => 'webmapp',
			'password' => 'webmapp',
			'client_id' => 'f49353d7-6c84-41e7-afeb-4ddbd16e8cdf',
			'client_secret' => '123'
		];

		$ch = curl_init('https://api-intense.stage.sardegnaturismocloud.it/oauth/token');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
		$response = curl_exec($ch);
		curl_close($ch);

		$j=json_decode($response,TRUE);
		$this->token=$j['access_token'];
	}

    /**
     * @return bool
     */
	public function process() {
        $data = $this->querySimpleOst();
		$data = $this->querySimpleIntense();
		return TRUE;
	}

    /**
     *
     * Questo metodo restituisce un array con tutti i risultati di una query GraphQL
     * effettua una chiamata aumentando la pagina da 0 a n+1 fino a raggiungere il risultato
     * vuoto
     *
     * @param $query_name
     * @param $query_object
     * @param $query
     * @param string $vars
     * @return array
     */
	private function queryGraphQL($query_name,$query_object,$query,$vars='') {
        $ret = array();
        // PAGE LOOP
        $page=0;
        do {
            $q = $this->buildQueryGraphQl($page,$query_name,$query_object,$query,$vars);
            echo "$q \n";
            $page++;
        } while ($page<10);

        return $ret;
    }

    /**
     *
     * Questo metodo costruisce la stringa completa per la query
     *
     * Example: '{queryOst(page: 0, locale: \"it\", type: \"ost_sentiero_percorso\") { ost {id} }}'
     *
     * @param $page
     * @param $query_name
     * @param $query_object
     * @param $query
     * @param string $vars
     * @return string
     */
    private function buildQueryGraphQl($page,$query_name,$query_object,$query,$vars='') {
        $query_full='';
        if(!empty($vars)) {
            $vars = '(page: '.$page.','.$vars.')';
        }
        else {
            $vars = '(page: '.$page.')';
        }
        $query_full ='{'.$query_name.$vars.' {'.$query_object.'{'.$query.'}}';
        return $query_full;
    }

    /**
     *
     * Questo metodo effettua la chiamata GRAPHQL usando il token che deve essere
     * istanziato precedentemete
     *
     * Example: '{queryOst(page: 0, locale: \"it\", type: \"ost_sentiero_percorso\") { ost {id} }}'
     *
     * @param $query
     * @return mixed
     */
	private function queryGraphQLSingle ($query) {

		$endpoint = "https://api-intense.stage.sardegnaturismocloud.it/api/v1.0/graphql";
		$authToken = $this->token;
		$qry = '{"query":"'.$query.'"}';
		$headers = array();
		$headers[] = 'Content-Type: application/json';
		$headers[] = 'Authorization: Bearer '.$authToken;

		$ch = curl_init();

		curl_setopt($ch, CURLOPT_URL, $endpoint);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $qry);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

		$result = curl_exec($ch);

		if (curl_errno($ch)) {
			echo 'Error:' . curl_error($ch);
		}

		return json_decode($result,TRUE);
	}

	private function querySimpleOst() {
		//'{queryOst(page: 0, locale: \"it\", type: \"ost_sentiero_percorso\") { ost {id} }}';
        $query_name = 'queryOst';
        $query_object = 'ost';
        $query = 'id';
        $vars = 'locale: \"it\", type: \"ost_sentiero_percorso\"';
		return $this->queryGraphQL($query_name,$query_object,$query,$vars);
	}

	private function querySimpleIntense() {
		// $q = '{querySchedeIntense (count:150) { schedeIntense {id} }}';
        $query_name = 'querySchedeIntense';
        $query_object = 'schedeIntense';
        $query = 'id';
        return $this->queryGraphQL($query_name,$query_object,$query);
	}

}
