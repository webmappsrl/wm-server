<?php

class WebmappCliServerCommand extends WebmappCliAbstractCommand
{
    private $_serverId = null;
    private $_jobs = null;

    public function __construct($argv)
    {
        parent::__construct($argv);
        global $wm_config;

        $options = $this->_get_opts();

        if (isset($options["configUrl"]) && !empty($options["configUrl"]) && is_string($options["configUrl"])) {
            $newConfig = json_decode(file_get_contents($options["configUrl"]), true);
            $wm_config = $newConfig;
        }

        if (!isset($wm_config['hoqu'])) {
            throw new WebmappExceptionParameterMandatory("HOQU configuration missing. Aborting");
        }
        if (!isset($wm_config['hoqu']['url'])) {
            throw new WebmappExceptionParameterMandatory("HOQU url missing. Aborting");
        }
        if (!isset($wm_config['hoqu']['pull_token'])) {
            throw new WebmappExceptionParameterMandatory("HOQU pull key missing. Aborting");
        }

        if (isset($options["serverId"]) && !empty($options["serverId"]) && intval($options["serverId"]) > 0) {
            $this->_serverId = intval($options["serverId"]);
        }
        if (isset($this->_serverId)) {
            $wm_config["hoqu"]["server_id"] = $this->_serverId;
        }

        if (isset($options["jobs"]) && !empty($options["jobs"]) && is_string($options["jobs"])) {
            $this->_jobs = explode(",", $options["jobs"]);
        }
        if (isset($this->_jobs)) {
            $wm_config["hoqu"]["jobs"] = $this->_jobs;
        }

        if (!isset($wm_config['hoqu']['server_id'])) {
            throw new WebmappExceptionParameterMandatory("HOQU server id missing. Aborting");
        }
        if (!isset($wm_config['hoqu']['jobs'])) {
            throw new WebmappExceptionParameterMandatory("HOQU jobs missing. Aborting");
        }
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
        $string = "\n
Usage: wmcli server [subcommands]
Available subcommands:
//start (default) : Start a new server instance
//stop [pid]      : Stop the existing server instance with the specified pid
//log             : Log the active server instances\n";
        echo $string;
    }

    public function executeNoHelp()
    {
        global $wm_config;
        try {
            WebmappUtils::title("Starting a HOQU Server...");
            $server = new WebmappHoquServer($wm_config["debug"]);
            $server->run();
        } catch (WebmappExceptionParameterMandatory $e) {
            WebmappUtils::error($e->getMessage());
        }
    }
}