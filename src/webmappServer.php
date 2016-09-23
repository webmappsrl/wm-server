<?php
require('autoload.php');
$p = new WebmappProject($argv[1]);
if($p->open()) {
	echo "OK";
}
else {
	echo $p->getError();
}

var_dump($p->getConfFiles());
