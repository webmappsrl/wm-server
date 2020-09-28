<?php // wmcli.php
/**
 * Project: wm-server
 * Author: Webmapp
 * Version 0.1.7
 */
echo "wmcli v0.1.7\n";

require 'autoload.php';
$c = new WebmappCli($argv);
