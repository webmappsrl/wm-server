<?php
class WebmappMaratonaDiPisaTask extends WebmappAbstractTask {

	public function check() {
		return TRUE;
	}

    public function process(){

        $l = new WebmappLayer('runners');

        for ($i=1; $i <= 5000; $i++) { 
            echo "Runner $i\n";
            
        }
        return TRUE;
    }

}
