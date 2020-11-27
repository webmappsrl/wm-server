<?php // wmcli.php
/**
 * Project: wm-server
 * Author: Webmapp
 * Version 0.1.7
 */
require 'autoload.php';

WebmappUtils::title("Running wmcli v0.1.7");

if (!file_exists(__DIR__ . "/node/node_modules")) {
//    installDependencies();
}

declare(ticks=1);
$c = new WebmappCli($argv);

function installDependencies()
{
    global $wm_config;

    WebmappUtils::message("Installing dependencies...");
    chdir(__DIR__ . "/node");

    $npmCmd = "npm";
    if (isset($wm_config["node"]["npm"])) {
        $npmCmd = $wm_config["node"]["npm"];
    }
    $cmd = "{$npmCmd} i";

    $descriptorspec = array(
        0 => array("pipe", "r"),   // stdin is a pipe that the child will read from
        1 => array("pipe", "w"),   // stdout is a pipe that the child will write to
        2 => array("pipe", "w")    // stderr is a pipe that the child will write to
    );
    flush();
    $process = proc_open($cmd, $descriptorspec, $pipes, realpath('./'), array());
    if (is_resource($process)) {
        while ($s = fgets($pipes[1])) {
            if ($wm_config["debug"]) {
                WebmappUtils::verbose($s);
            }
            flush();
        }

        if ($s = fgets($pipes[2])) {
            WebmappUtils::error("An error occurred installing the project dependencies: {$s}");
            WebmappUtils::error("The WMCLI server could have problem executing some jobs due to some dependencies missing. This could be due to missing node.npm property in the config.json");
            return;
        }
    }
    WebmappUtils::success("Dependencies successfully installed");
}