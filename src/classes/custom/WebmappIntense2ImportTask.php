<?php
class WebmappIntense2ImportTask extends WebmappAbstractTask {

	private $token;

	public function check() {
		$this->getToken();
		echo "TOKEN: {$this->token}\n";
		return TRUE;
	}

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

	public function process() {
		return TRUE;
	}

}
