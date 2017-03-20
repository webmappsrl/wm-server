<?php
require('autoload.php');
$root = __DIR__.'/../data/api.webmapp.it/example.webmapp.it/';
$s = new WebmappProjectStructure($root);
$s->open();
$s->process();

