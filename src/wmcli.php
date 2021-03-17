<?php // wmcli.php
/**
 * Project: wm-server
 * Author: Webmapp
 * Version 0.1.22
 */
echo "wmcli v0.1.22\n";

require 'autoload.php';
$c = new WebmappCli($argv);
