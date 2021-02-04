<?php

class WebmappHelpers
{
    public static function createProjectStructure(
        string $a,
        string $k,
        string $instanceName,
        array $aConf = null,
        string $instanceCode = '',
        array $kConf = null
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
        if (!empty($instanceCode)) {
            $wm_config["a_k_instances"] = [
                $instanceName => [
                    "k" => [$instanceCode]
                ]
            ];
        }

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

        if (!empty($instanceCode)) {
            if (!file_exists("{$k}/{$instanceCode}/geojson")) {
                $cmd = "mkdir -p {$k}/{$instanceCode}/geojson";
                system($cmd);
            }
            if (!file_exists("{$k}/{$instanceCode}/taxonomies")) {
                $cmd = "mkdir -p {$k}/{$instanceCode}/taxonomies";
                system($cmd);
            }
            if (!file_exists("{$k}/{$instanceCode}/routes")) {
                $cmd = "mkdir -p {$k}/{$instanceCode}/routes";
                system($cmd);
            }
            if (!file_exists("{$k}/{$instanceCode}/server")) {
                $cmd = "mkdir -p {$k}/{$instanceCode}/server";
                system($cmd);
            }
            if (is_array($kConf) && count($kConf) > 0)
                file_put_contents("{$k}/{$instanceCode}/server/server.conf", json_encode($kConf));
        }

        $cmd = "rm {$a}/{$code}/geojson/* &>/dev/null";
        system($cmd);
        $cmd = "rm {$a}/{$code}/taxonomies/* &>/dev/null";
        system($cmd);
        if (!empty($instanceCode)) {
            $cmd = "rm {$k}/{$instanceCode}/geojson/* &>/dev/null";
            system($cmd);
            $cmd = "rm {$k}/{$instanceCode}/taxonomies/* &>/dev/null";
            system($cmd);
            $cmd = "rm -r {$k}/{$instanceCode}/routes/* &>/dev/null";
            system($cmd);
        }
    }
}