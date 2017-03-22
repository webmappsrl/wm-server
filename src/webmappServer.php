<?php
require('autoload.php');
// $root = __DIR__.'/../data/api.webmapp.it/example.webmapp.it/';
$root = $argv[1];
$s = new WebmappProject($root);
$s->check();
$s->process();

