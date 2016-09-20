<?php
require('autoload.php');
$confFile = $argv[1];
$conf=new ReadConf($confFile);

if($conf->check()) {
	echo "\n\nOK -> processing file $confFile\n\n";
}
else {
	echo $conf->getError();
}
