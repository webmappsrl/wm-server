<?php

class WebmappCliServerCommand extends WebmappCliAbstractCommand
{
    private $_serverId = null;
    private $_jobs = null;
    private $_acceptInstances = null;
    private $_excludeInstances = null;

    public function __construct($argv)
    {
        parent::__construct($argv);
        global $wm_config;

        $options = $this->_getOpts();

        if (isset($options["configUrl"]) && !empty($options["configUrl"]) && is_string($options["configUrl"])) {
            $newConfig = json_decode(file_get_contents($options["configUrl"]), true);
            $wm_config = $newConfig;
        }

        if (!isset($wm_config['hoqu']))
            throw new WebmappExceptionParameterMandatory("HOQU configuration missing. Aborting");
        if (!isset($wm_config['hoqu']['url']))
            throw new WebmappExceptionParameterMandatory("HOQU url missing. Aborting");
        if (!isset($wm_config['hoqu']['pull_token']))
            throw new WebmappExceptionParameterMandatory("HOQU pull key missing. Aborting");

        if (isset($options["serverId"]) && !empty($options["serverId"]))
            $this->_serverId = $options["serverId"];
        if (isset($this->_serverId))
            $wm_config["hoqu"]["server_id"] = $this->_serverId;

        if (isset($options["jobs"]) && !empty($options["jobs"]) && is_string($options["jobs"]))
            $this->_jobs = explode(",", $options["jobs"]);
        if (isset($this->_jobs))
            $wm_config["hoqu"]["jobs"] = $this->_jobs;

        if (isset($options["acceptInstances"]) && !empty($options["acceptInstances"]))
            $this->_acceptInstances = explode(",", $options["acceptInstances"]);
        if (isset($this->_acceptInstances))
            $wm_config["hoqu"]["accept_instances"] = $this->_acceptInstances;

        if (isset($options["excludeInstances"]) && !empty($options["excludeInstances"]))
            $this->_excludeInstances = explode(",", $options["excludeInstances"]);
        if (isset($this->_excludeInstances))
            $wm_config["hoqu"]["exclude_instances"] = $this->_excludeInstances;

        if (isset($options['verbose']) && !!$options["verbose"])
            $wm_config['debug'] = true;

        if (!isset($wm_config['hoqu']['server_id']))
            throw new WebmappExceptionParameterMandatory("HOQU server id missing. Aborting");
        if (!isset($wm_config['hoqu']['jobs']))
            throw new WebmappExceptionParameterMandatory("HOQU jobs missing. Aborting");
    }

    public function specificConstruct()
    {
        return true;
    }

    public function getExcerpt()
    {
        $string = "Create a server instance that uses HOQU (start, stop, log)";
        return $string;
    }

    public function showHelp()
    {
        $string = "
Usage: wmcli server [--serverId] [--jobs]
  --serverId      string that represent the server id to use to communicate with HOQU during execution
  --jobs          string that represent the list of jobs executable by this server. The jobs must be specified separated by commas. The available jobs are: " . implode(", ", JOBS_AVAILABLE) . "\n
Example:
  wmcli server --serverId=\"server_poi_update\" --jobs=\"update_poi\"\n\n";
        echo $string;
    }

    public function executeNoHelp()
    {
        global $wm_config;
        try {
            WebmappUtils::title("Starting a new HOQU Server...");
            $server = new WebmappHoquServer($wm_config["debug"]);
            $server->run();
        } catch (WebmappExceptionParameterMandatory $e) {
            WebmappUtils::error($e->getMessage());
        }
    }
}