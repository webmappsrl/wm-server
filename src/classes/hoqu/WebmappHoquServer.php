<?php

define("SLEEP_TIME", 5);
define("PULL_ENDPOINT", "/api/pull");
define("UPDATE_DONE_ENDPOINT", "/api/updateDone");
define("UPDATE_ERROR_ENDPOINT", "/api/updateError");
define("JOBS_AVAILABLE", [
    "update_poi",
    "update_track",
    "update_track_metadata",
    "update_track_geometry",
    "update_route",
    "generate_mbtiles",
    "generate_elevation_chart_image"
]);

class WebmappHoquServer
{
    private $serverId;
    private $hoquBaseUrl;
    private $pullToken;
    private $updateToken;
    private $jobsAvailable;
    private $verbose;

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
                if (in_array($job, JOBS_AVAILABLE)) {
                    $this->jobsAvailable[] = $job;
                }
            }
        } else if (is_string($wm_config['hoqu']['jobs']) && in_array($wm_config['hoqu']['jobs'], JOBS_AVAILABLE)) {
            $this->jobsAvailable = $wm_config['hoqu']['jobs'];
        }

        $this->verbose = $verbose;
    }

    /**
     * Prepare curl for a put request
     *
     * @param string $url the request url
     * @param array $payload the payload to pass
     * @param array|null $headers the headers - optional
     * @return false|resource
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
            WebmappUtils::verbose("  payload: " . json_encode($payload));
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

        // TODO: Make it a daemon using a concurrency parameter
        while (true) {
            WebmappUtils::message("---------------------------------");
            $ch = $this->_getPutCurl($pullUrl, $payload);
            $job = curl_exec($ch);
            if (curl_getinfo($ch, CURLINFO_HTTP_CODE) != 200) {
                if (curl_getinfo($ch, CURLINFO_HTTP_CODE) == 204) {
                    WebmappUtils::message("No tasks currently available. Retrying in " . SLEEP_TIME . " seconds");
                } else if (curl_getinfo($ch, CURLINFO_HTTP_CODE) == 0) {
                    WebmappUtils::warning("HOQU appears slow: " . curl_error($ch));
                } else {
                    WebmappUtils::error("An error " . curl_getinfo($ch, CURLINFO_HTTP_CODE) . " occurred while getting a new task: " . curl_error($ch));
                }
                curl_close($ch);
                sleep(SLEEP_TIME);
            } else {
                curl_close($ch);
                if ($job) {
                    $job = json_decode($job, true);
                    $jobTypeSplit = explode('_', $job['job']);
                    foreach ($jobTypeSplit as $key => $jobToken) {
                        $jobTypeSplit[$key] = strtoupper($jobToken[0]) . substr($jobToken, 1);
                    }
                    $jobType = implode("", $jobTypeSplit);
                    $jobClass = "Webmapp{$jobType}Job";

                    if (class_exists("Webmapp{$jobType}Job")) {
                        try {
                            $startTime = round(microtime(true) * 1000);
                            WebmappUtils::title("Starting new {$jobType} job");
                            $a = new $jobClass($job['instance'], $job['parameters'], $this->verbose);
                            if ($this->verbose) {
                                WebmappUtils::verbose("Running process...");
                            }
                            $a->run();
                            if ($this->verbose) {
                                WebmappUtils::verbose("Process completed");
                            }
                            $this->_jobCompleted(true, $job['id']);
                            $endTime = round(microtime(true) * 1000);
                            $duration = ($endTime - $startTime) / 1000;
                            WebmappUtils::success("Job {$job['id']} completed in {$duration}");
                        } catch (Exception $e) {
                            WebmappUtils::error("Error executing job {$job['id']}: {$e->getMessage()}");
                            $this->_jobCompleted(false, $job['id'], $e->getMessage());
                        }
                    } else {
                        WebmappUtils::error("Error executing job {$job['id']} - Job not supported");
                        $this->_jobCompleted(false, $job['id'], "The retrieved job is not supported: " . json_encode($job));
                    }
                } else {
                    WebmappUtils::message("No tasks currently available. Retrying in " . SLEEP_TIME . " seconds");
                    sleep(SLEEP_TIME);
                }
            }
        }
    }

    /**
     * Notify HOQU about the completed job
     *
     * @param bool $done true if the process has completed successfully
     * @param int $jobId the id of the job just completed
     * @param string|null $message with a log
     */
    private function _jobCompleted(bool $done, int $jobId, string $message = null)
    {
        $url = $this->hoquBaseUrl;
        if ($done) {
            $url .= UPDATE_DONE_ENDPOINT;
        } else {
            $url .= UPDATE_ERROR_ENDPOINT;
        }

        $payload = [
            'id_server' => $this->serverId,
            'log' => $message,
            'id_task' => $jobId
        ];

        $ch = $this->_getPutCurl($url, $payload);
        curl_exec($ch);
        if (curl_getinfo($ch, CURLINFO_HTTP_CODE) != 200) {
            WebmappUtils::error("An error occurred while calling {$url}: " . curl_getinfo($ch, CURLINFO_HTTP_CODE) . " - " . curl_error($ch));
        }
        curl_close($ch);
    }
}

