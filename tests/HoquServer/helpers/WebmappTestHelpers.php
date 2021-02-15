<?php

class WebmappHelpers
{
    public static function createProjectStructure(
        string $a,
        string $k,
        string $instanceName,
        array $aConf = null,
        $instanceCode = '',
        $kConf = null
    )
    {
        global $wm_config;
        $wm_config["endpoint"] = [
            "a" => $a,
            "k" => $k
        ];
        $code = explode(',', $instanceName)[0];
        $wm_config["a_k_instances"] = [
            $instanceName => [
                "a" => $code
            ]
        ];

        if (!file_exists("{$a}/{$code}/geojson")) {
            $cmd = "mkdir -p {$a}/{$code}/geojson";
            system($cmd);
        }
        if (!file_exists("{$a}/{$code}/taxonomies")) {
            $cmd = "mkdir -p {$a}/{$code}/taxonomies";
            system($cmd);
        }
        if (!file_exists("{$a}/{$code}/server")) {
            $cmd = "mkdir -p {$a}/{$code}/server";
            system($cmd);
        }

        if (is_array($aConf) && count($aConf) > 0)
            file_put_contents("{$a}/{$code}/server/server.conf", json_encode($aConf));

        $cmd = "rm {$a}/{$code}/geojson/* &>/dev/null";
        system($cmd);
        $cmd = "rm {$a}/{$code}/taxonomies/* &>/dev/null";
        system($cmd);

        if (!empty($instanceCode)) {
            $codes = $instanceCode;
            $kConfs = $kConf;
            if (is_string($instanceCode)) {
                $codes = [$instanceCode];
                $kConfs = [$kConf];
            }
            if (!empty($instanceCode)) {
                $wm_config["a_k_instances"][$instanceName]["k"] = [];
            }
            foreach ($codes as $key => $kCode) {
                if (!file_exists("{$k}/{$kCode}/geojson")) {
                    $cmd = "mkdir -p {$k}/{$kCode}/geojson";
                    system($cmd);
                }
                if (!file_exists("{$k}/{$kCode}/taxonomies")) {
                    $cmd = "mkdir -p {$k}/{$kCode}/taxonomies";
                    system($cmd);
                }
                if (!file_exists("{$k}/{$kCode}/routes")) {
                    $cmd = "mkdir -p {$k}/{$kCode}/routes";
                    system($cmd);
                }
                if (!file_exists("{$k}/{$kCode}/server")) {
                    $cmd = "mkdir -p {$k}/{$kCode}/server";
                    system($cmd);
                }

                if (is_array($kConfs[$key]) && count($kConfs[$key]) > 0)
                    file_put_contents("{$k}/{$kCode}/server/server.conf", json_encode($kConfs[$key]));

                $wm_config["a_k_instances"][$instanceName]["k"][] = $kCode;
                if (!empty($kCode)) {
                    $cmd = "rm {$k}/{$kCode}/geojson/* &>/dev/null";
                    system($cmd);
                    $cmd = "rm {$k}/{$kCode}/taxonomies/* &>/dev/null";
                    system($cmd);
                    $cmd = "rm -r {$k}/{$kCode}/routes/* &>/dev/null";
                    system($cmd);
                }
            }
        }

    }
}