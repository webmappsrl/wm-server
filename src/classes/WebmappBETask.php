<?php
class WebmappBETask extends WebmappAbstractTask {

	public function check() {

		// Controllo parametro list
		if(!array_key_exists('code', $this->options))
			throw new Exception("L'array options deve avere la chiave 'code'", 1);

		// TODO: controllo della risposta delle API http://$code.be.webmapp.it/XXX
			
		return TRUE;
	}

}