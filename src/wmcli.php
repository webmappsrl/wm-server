<?php // wmcli.php
/**
 * Project: wm-server
 * Author: Webmapp
 * Version 0.1.16
 */
echo "wmcli v0.1.16\n";

require 'autoload.php';
$c = new WebmappCli($argv);
