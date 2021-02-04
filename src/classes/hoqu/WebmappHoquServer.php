<?php

define("SLEEP_TIME", 5);
define("PULL_ENDPOINT", "/api/pull");
define("UPDATE_DONE_ENDPOINT", "/api/updateDone");
define("UPDATE_ERROR_ENDPOINT", "/api/updateError");
define("JOBS_AVAILABLE", [
    "delete_event",
    "delete_poi",
    "delete_route",
    "delete_taxonomy",
    "delete_track",
    "generate_elevation_chart_image",
    "generate_mbtiles",
    "update_event",
    "update_poi",
    "update_route",
    "update_taxonomy",
    "update_track"
]);

class WebmappHoquServer
{
    private $serverId;
    private $hoquBaseUrl;
    private $pullToken;
    private $updateToken;
    private $jobsAvailable;
    private $acceptInstances;
    private $excludeInstances;
    private $verbose;
    private $interrupted;
    private $running;

    /**
     * WebmappHoquServer constructor.
     *
     * @param bool $verbose
     * @throws WebmappExceptionParameterMandatory
     */
    public function __construct(bool $verbose)
    {
        // TODO: add concurrency param to handle multiple server loops
        global $wm_config;
        if (!isset($wm_config['hoqu'])) {
            throw new WebmappExceptionParameterMandatory("HOQU configuration missing. Aborting");
        }
        if (!isset($wm_config['hoqu']['server_id'])) {
            throw new WebmappExceptionParameterMandatory("HOQU server id missing. Aborting");
        }
        if (!isset($wm_config['hoqu']['url'])) {
            throw new WebmappExceptionParameterMandatory("HOQU url missing. Aborting");
        }
        if (!isset($wm_config['hoqu']['pull_token'])) {
            throw new WebmappExceptionParameterMandatory("HOQU pull key missing. Aborting");
        }
        if (!isset($wm_config['hoqu']['jobs'])) {
            throw new WebmappExceptionParameterMandatory("HOQU jobs missing. Aborting");
        }

        $this->serverId = $wm_config['hoqu']['server_id'];
        $this->hoquBaseUrl = $wm_config['hoqu']['url'];
        $this->pullToken = $wm_config['hoqu']['pull_token'];
        $this->updateToken = $wm_config['hoqu']['pull_token'];

        $this->jobsAvailable = [];
        if (is_array($wm_config['hoqu']['jobs'])) {
            foreach ($wm_config['hoqu']['jobs'] as $job) {
                if (in_array($job, JOBS_AVAILABLE))
                    $this->jobsAvailable[] = $job;
            }
        } else if (is_string($wm_config['hoqu']['jobs']) && in_array($wm_config['hoqu']['jobs'], JOBS_AVAILABLE)) {
            $this->jobsAvailable[] = $wm_config['hoqu']['jobs'];
        }

        $this->acceptInstances = [];
        if (isset($wm_config['hoqu']['accept_instances']) && is_array($wm_config['hoqu']['accept_instances'])) {
            foreach ($wm_config['hoqu']['accept_instances'] as $instance) {
                $this->acceptInstances[] = $instance;
            }
        } else if (isset($wm_config['hoqu']['accept_instances']) && is_string($wm_config['hoqu']['accept_instances'])) {
            $this->acceptInstances[] = $wm_config['hoqu']['accept_instances'];
        } else $this->acceptInstances = null;

        $this->excludeInstances = [];
        if (isset($wm_config['hoqu']['exclude_instances']) && is_array($wm_config['hoqu']['exclude_instances'])) {
            foreach ($wm_config['hoqu']['exclude_instances'] as $instance) {
                $this->excludeInstances[] = $instance;
            }
        } else if (isset($wm_config['hoqu']['exclude_instances']) && is_string($wm_config['hoqu']['exclude_instances'])) {
            $this->excludeInstances[] = $wm_config['hoqu']['exclude_instances'];
        } else $this->excludeInstances = null;

        $this->verbose = $verbose;
    }

    /**
     * Prepare curl for a put request
     *
     * @param string $url the request url
     * @param array $payload the payload to pass
     * @param array|null $headers the headers - optional
     * @return bool|resource
     */
    private function _getPutCurl(string $url, array $payload, array $headers = null)
    {
        if (!isset($headers)) {
            $headers = [
                "Accept: application/json",
                "Authorization: Bearer {$this->pullToken}",
                "Content-Type:application/json"
            ];
        }

        if ($this->verbose) {
            WebmappUtils::verbose("Initializing PUT curl using:");
            WebmappUtils::verbose("  url: {$url}");
            $fakePayload = [];
            foreach ($payload as $key => $property) {
                if ($key === 'log' || $key === 'error_log') {
                    $string = json_encode($property);
                    $fakePayload[$key] = substr($string, 0, 10) . "..." . substr($string, strlen($string) - 5, 14);
                } else
                    $fakePayload[$key] = $property;
            }
            WebmappUtils::verbose("  payload: " . json_encode($fakePayload));
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        return $ch;
    }

    /**
     * Handle the command line signals
     *
     * @param int $signal the signal number
     */
    public function signalHandler(int $signal): void
    {
        if ($this->running) {
            WebmappUtils::warning("");
        }
        switch ($signal) {
            case SIGINT:
                if ($this->running) {
                    WebmappUtils::warning("  [CTRL - C] Performing soft interruption. Terminating job before closing");
                }
                $this->interrupted = true;
                break;
        }
        if ($this->running) {
            WebmappUtils::warning("");
        }
    }

    /**
     * Handle errors and warnings
     *
     * @param int $errno
     * @param string $errstr
     * @param string $errfile
     * @param string $errline
     * @throws WebmappExceptionFatalError triggers this when an error of any type is caught
     */
    public function errorHandler(int $errno, string $errstr, string $errfile, string $errline)
    {
        switch ($errno) {
            case E_WARNING:
            case E_USER_WARNING:
            case E_NOTICE:
            case E_USER_NOTICE:
                WebmappUtils::warning("Execution warning");
                WebmappUtils::warning("  error code: $errno");
                WebmappUtils::warning("  message: $errstr");
                WebmappUtils::warning("  file: $errfile");
                WebmappUtils::warning("  line: $errline");
                break;
            case E_ERROR:
            case E_USER_ERROR:
            default:
                WebmappUtils::error("Execution error");
                WebmappUtils::error("  error code: $errno");
                WebmappUtils::error("  message: $errstr");
                WebmappUtils::error("  file: $errfile");
                WebmappUtils::error("  line: $errline");
                throw new WebmappExceptionFatalError("Execution error: $errno, $errstr, $errfile, $errline");
        }
    }

    /**
     * @return string
     */
    private function _logHeader(): string
    {
        return date("Y-m-d H:i:s") . " - {$this->serverId} | ";
    }

    /**
     * Run the HOQU server
     */
    public function run()
    {
        WebmappUtils::success("New HOQU server started. Press CTRL + C to stop");

        $pullUrl = $this->hoquBaseUrl . PULL_ENDPOINT;

        $payload = [
            "id_server" => $this->serverId,
            "task_available" => $this->jobsAvailable,
        ];

        if (isset($this->acceptInstances) && is_array($this->acceptInstances) && count($this->acceptInstances) > 0)
            $payload["accept_instances"] = $this->acceptInstances;
        if (isset($this->excludeInstances) && is_array($this->excludeInstances) && count($this->excludeInstances) > 0)
            $payload["exclude_instances"] = $this->excludeInstances;

        declare(ticks=1);
        pcntl_signal(SIGINT, array($this, "signalHandler"));
        set_error_handler(array($this, "errorHandler"), E_ALL);

        // TODO: Make it a daemon using a concurrency parameter
        while (!$this->interrupted) {
            $this->running = true;
            WebmappUtils::message("---------------------------------");
            WebmappUtils::resetLogs();
            $ch = $this->_getPutCurl($pullUrl, $payload);
            $job = curl_exec($ch);
            if (curl_getinfo($ch, CURLINFO_HTTP_CODE) != 200) {
                $this->running = false;
                if (curl_getinfo($ch, CURLINFO_HTTP_CODE) == 204)
                    WebmappUtils::message($this->_logHeader() . "No jobs currently available. Retrying in " . SLEEP_TIME . " seconds");
                else if (curl_getinfo($ch, CURLINFO_HTTP_CODE) == 0)
                    WebmappUtils::warning($this->_logHeader() . "HOQU appears slow: " . curl_error($ch));
                else
                    WebmappUtils::error($this->_logHeader() . "An error " . curl_getinfo($ch, CURLINFO_HTTP_CODE) . " occurred while getting a new job: " . curl_error($ch));

                curl_close($ch);
                sleep(SLEEP_TIME);
            } else {
                curl_close($ch);
                if ($job) {
                    $job = json_decode($job, true);
                    if ($job && $job["job"]) {
                        $jobTypeSplit = explode('_', $job['job']);
                        foreach ($jobTypeSplit as $key => $jobToken) {
                            $jobTypeSplit[$key] = strtoupper($jobToken[0]) . substr($jobToken, 1);
                        }
                        $jobType = implode("", $jobTypeSplit);
                        $jobClass = "Webmapp{$jobType}Job";

                        if (class_exists("Webmapp{$jobType}Job")) {
                            try {
                                $startTime = round(microtime(true) * 1000);
                                WebmappUtils::title($this->_logHeader() . "Starting {$jobType} job {$job['id']} on instance {$job['instance']}");
                                $a = new $jobClass($job['instance'], $job['parameters'], $this->verbose);
                                WebmappUtils::verbose($this->_logHeader() . "Running process...");
                                $a->run();
                                WebmappUtils::verbose($this->_logHeader() . "Process completed");
                                $endTime = round(microtime(true) * 1000);
                                $duration = ($endTime - $startTime) / 1000;
                                WebmappUtils::success($this->_logHeader() . "Job {$job['id']} completed in {$duration} seconds");
                                $this->_jobCompleted(true, $job['id']);
                            } catch (Exception $e) {
                                WebmappUtils::error($this->_logHeader() . "Error executing job {$job['id']}: {$e->getMessage()}");
                                $this->_jobCompleted(false, $job['id']);
                            }
                        } else {
                            WebmappUtils::error($this->_logHeader() . "Error executing job {$job['id']} - Job not supported, params: " . json_encode($job));
                            $this->_jobCompleted(false, $job['id']);
                        }
                        $this->running = false;
                    } else {
                        $this->running = false;
                        WebmappUtils::message($this->_logHeader() . "No jobs currently available. Retrying in " . SLEEP_TIME . " seconds");
                        sleep(SLEEP_TIME);
                    }
                } else {
                    $this->running = false;
                    WebmappUtils::message($this->_logHeader() . "No jobs currently available. Retrying in " . SLEEP_TIME . " seconds");
                    sleep(SLEEP_TIME);
                }
            }
        }

        WebmappUtils::success("");
        WebmappUtils::success("    Server {$this->serverId} terminated successfully");
        WebmappUtils::success("");
    }

    /**
     * Notify HOQU about the completed job
     *
     * @param bool $done true if the process has completed successfully
     * @param int $jobId the id of the job just completed
     */
    private function _jobCompleted(bool $done, int $jobId)
    {
        $url = $this->hoquBaseUrl;
        if ($done)
            $url .= UPDATE_DONE_ENDPOINT;
        else
            $url .= UPDATE_ERROR_ENDPOINT;

        $log = WebmappUtils::getLog();
        $errorLog = WebmappUtils::getErrorLog();

        $payload = [
            'id_server' => $this->serverId,
            'log' => $log,
            'id_task' => $jobId
        ];

        if (isset($errorLog) && !$done)
            $payload['error_log'] = $errorLog;

        $ch = $this->_getPutCurl($url, $payload);
        curl_exec($ch);
        if (curl_getinfo($ch, CURLINFO_HTTP_CODE) != 200) {
            WebmappUtils::error($this->_logHeader() . "An error occurred while calling {$url}: " . curl_getinfo($ch, CURLINFO_HTTP_CODE) . " - " . curl_error($ch));
        }
        curl_close($ch);
    }
}

