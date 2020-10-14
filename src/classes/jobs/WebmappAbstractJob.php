<?php

abstract class WebmappAbstractJob
{
    protected $name; // Job name
    protected $params; // Job params
    protected $instanceUrl; // Job instance url
    protected $instanceName; // Instance name
    protected $verbose; // Verbose option
    protected $wp; // WordPress backend

    public static $foreground = array(
        'black' => '0;30',
        'dark_gray' => '1;30',
        'red' => '0;31',
        'bold_red' => '1;31',
        'green' => '0;32',
        'bold_green' => '1;32',
        'brown' => '0;33',
        'yellow' => '1;33',
        'blue' => '0;34',
        'bold_blue' => '1;34',
        'purple' => '0;35',
        'bold_purple' => '1;35',
        'cyan' => '0;36',
        'bold_cyan' => '1;36',
        'white' => '1;37',
        'bold_gray' => '0;37',
    );
    public static $background = array(
        'black' => '40',
        'red' => '41',
        'magenta' => '45',
        'yellow' => '43',
        'green' => '42',
        'blue' => '44',
        'cyan' => '46',
        'light_gray' => '47',
    );

    /**
     * Return the given string in the given color
     *
     * @param $string the string to color
     * @param string $fgColor the foreground color
     * @return string a string that if printed will appear with the given colors
     */
    private function colorText($string, $fgColor = "white")
    {
        if (!isset(self::$foreground[$fgColor])) {
            $fgColor = "white";
        }
        return "\033[" . self::$foreground[$fgColor] . 'm' . $string . "\033[0m";
    }

    protected function title($message)
    {
        echo $this->colorText($message, "cyan") . "\n";
    }

    protected function success($message)
    {
        echo $this->colorText($message, "green") . "\n";
    }

    protected function message($message)
    {
        echo "$message\n";
    }

    protected function error($message)
    {
        echo $this->colorText($message, "red") . "\n";
    }

    protected function verbose($message)
    {
        echo $this->colorText("[VERBOSE]", "bold_gray") . " $message\n";
    }

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

        try {
            $this->params = json_decode($params, TRUE);
        } catch (Exception $e) {
            $this->params = array();
        }

        $this->wp = new WebmappWP($this->instanceUrl);

        if ($this->verbose) {
            $this->verbose("Instantiating $name job with");
            $this->verbose("  instanceName: $this->instanceName");
            $this->verbose("  instanceUrl: $this->instanceUrl");
            $this->verbose("  params: " . json_encode($this->params));
        }
    }

    public function run()
    {
        $startTime = round(microtime(true) * 1000);
        $this->title("Starting {$this->name} job");
        if ($this->verbose) {
            $this->verbose("start time: $startTime");
        }
        $this->process();
        $endTime = round(microtime(true) * 1000);
        $duration = ($endTime - $startTime) / 1000;
        if ($this->verbose) {
            $this->verbose("end time: $endTime");
        }
        $this->success("Completed {$this->name} job in {$duration} seconds");
    }

    abstract protected function process();
}
