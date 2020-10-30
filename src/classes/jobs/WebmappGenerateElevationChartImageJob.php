<?php

class WebmappGenerateElevationChartImageJob extends WebmappAbstractJob
{
    private $_nodeCmd;

    /**
     * WebmappGenerateElevationChartImageJob constructor.
     * @param string $instanceUrl containing the instance url
     * @param string $params containing an encoded JSON with the poi ID
     * @param boolean $verbose
     */
    public function __construct(string $instanceUrl, string $params, $verbose = false)
    {
        parent::__construct("elevation_chart_image", $instanceUrl, $params, $verbose);
        global $wm_config;

        if (isset($wm_config["node"]["node"]) && is_string($wm_config["node"]["node"])) {
            $this->_nodeCmd = $wm_config["node"]["node"];
        } else {
            $this->_nodeCmd = "node";
        }
    }

    protected function process()
    {
        $id = $this->params["id"];
        $descriptorspec = array(
            0 => array("pipe", "r"),   // stdin is a pipe that the child will read from
            1 => array("pipe", "w"),   // stdout is a pipe that the child will write to
            2 => array("pipe", "w")    // stderr is a pipe that the child will write to
        );
        flush();

        chdir(__DIR__ . "/../../node");
        $src = "{$this->aProject->getRoot()}/geojson/{$id}.geojson";
        $dest = "{$this->aProject->getRoot()}/media/elevation-chart/";
        if (!file_exists($dest)) {
            $cmd = "mkdir -p {$dest}";
            system($cmd);
        }
        $dest .= "{$id}.png";
        $cmd = "{$this->_nodeCmd} generate-elevation-chart-png {$src} {$dest}";

        $config = "{$this->aProject->getRoot()}/server/server.conf";
        if (file_exists($config)) {
            $cmd .= " {$config}";
        }

        if ($this->verbose) {
            WebmappUtils::verbose("Running node command: {$cmd}");
        }

        $process = proc_open($cmd, $descriptorspec, $pipes, realpath('./'), array());
        if (is_resource($process)) {
            while ($s = fgets($pipes[1])) {
                if ($this->verbose) {
                    WebmappUtils::verbose($s);
                }
                flush();
            }

            if ($s = fgets($pipes[2])) {
                throw new WebmappException($s);
                return;
            }
        }
    }
}