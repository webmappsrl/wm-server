<?php

abstract class WebmappAbstractJob
{
    protected $name; // Job name
    protected $params; // Job params
    protected $instanceUrl; // Job instance url
    protected $instanceName; // Instance name
    protected $verbose; // Verbose option
    protected $wp; // WordPress backend
    protected $aProject; // Project root

    public function __construct($name, $instanceUrl, $params, $verbose)
    {
        $this->verbose = $verbose;
        $this->name = $name;

        if (substr($instanceUrl, 0, 4) == "http") {
            $this->instanceUrl = $instanceUrl;
            $this->instanceName = str_replace("http://", "", str_replace("https://", "", $instanceUrl));
        } else {
            $this->instanceUrl = "http://" . $instanceUrl;
            $this->instanceName = $instanceUrl;
        }

        global $wm_config;

        $this->aProject = new WebmappProjectStructure(
            isset($wm_config["endpoint"]) && isset($wm_config["endpoint"]["a"])
                ? "{$wm_config["endpoint"]["a"]}/{$this->instanceName}"
                : "/var/www/html/a.webmapp.it/{$this->instanceName}");

        try {
            $this->params = json_decode($params, TRUE);
        } catch (Exception $e) {
            $this->params = array();
        }

        $this->wp = new WebmappWP($this->instanceUrl);

        if ($this->verbose) {
            WebmappUtils::verbose("Instantiating $name job with");
            WebmappUtils::verbose("  instanceName: $this->instanceName");
            WebmappUtils::verbose("  instanceUrl: $this->instanceUrl");
            WebmappUtils::verbose("  params: " . json_encode($this->params));
        }
    }

    public function run()
    {
        $startTime = round(microtime(true) * 1000);
        if ($this->verbose) {
            WebmappUtils::title("[{$this->name} JOB] Starting");
        }
        if ($this->verbose) {
            WebmappUtils::verbose("start time: $startTime");
        }
        $this->process();
        $endTime = round(microtime(true) * 1000);
        $duration = ($endTime - $startTime) / 1000;
        if ($this->verbose) {
            WebmappUtils::verbose("end time: $endTime");
        }
        if ($this->verbose) {
            WebmappUtils::success("[{$this->name} JOB] Completed in {$duration} seconds");
        }
    }

    abstract protected function process();
}
