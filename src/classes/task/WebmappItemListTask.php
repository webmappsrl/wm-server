<?php
class WebmappItemListTask extends WebmappAbstractTask {


 public function check() {

     // API Url for items
     if(!array_key_exists('url', $this->options))
         throw new Exception("'url' option is mandatory", 1);

     // Output file name
     if(!array_key_exists('out', $this->options))
         throw new Exception("'out' option is mandatory", 1);

    return TRUE;
}


public function process(){
    echo "Hello Process OK!";
    return true;

}

}
