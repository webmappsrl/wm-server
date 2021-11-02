<?php // wmcli.php
/**
 * Project: wm-server
 * Author: Webmapp
 * Version 0.1.26
 */
echo "wmcli v0.1.26\n";

require 'autoload.php';
$c = new WebmappCli($argv);
