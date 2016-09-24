<?php
require('autoload.php');
$json = array("southWest"=>array(43.704367081989325,10.338478088378906),
	      			  "northEast"=>array(43.84839376489157,10.637855529785156));
		$overpass = "43.704367081989325%2C10.338478088378906%2C43.84839376489157%2C10.637855529785156";


		$b = new WebmappBounds($json);

var_dump($b);