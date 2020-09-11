<?php // wmcli.php
/**
 * Project: wm-server
 * Author: Webmapp
 * Version 0.1.4
 */
echo "wmcli v0.1.4\n";

require 'autoload.php';
$c = new WebmappCli($argv);
