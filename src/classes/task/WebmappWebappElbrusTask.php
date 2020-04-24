<?php
class WebmappWebappElbrusTask extends WebmappAbstractTask
{
    private $path;
    private $zip_base_url;
    private $zip_name;

    public function check()
    {
        $this->zip_base_url = '/root/wm-webapp-elbrus';
        if (array_key_exists('zip_base_url', $this->options)) {
            $this->zip_base_url = $this->options['zip_base_url'];
        }

        $this->zip_name = 'core';
        if (array_key_exists('zip_name', $this->options)) {
            $this->zip_name = $this->options['zip_name'];
        }

        if (!file_exists($this->zip_base_url . "/{$this->zip_name}.zip")) {
            throw new WebmappExceptionNoFile("ERROR: Missing file '{$this->zip_name}.zip' in '{$this->zip_base_url}/'", 1);
        }

        $cmd = "rm -Rf {$this->zip_base_url}/tmp";
        exec($cmd);

        $cmd = "mkdir {$this->zip_base_url}/tmp";
        exec($cmd);

        $cmd = "unzip {$this->zip_base_url}/{$this->zip_name}.zip -d {$this->zip_base_url}/tmp";
        exec($cmd);

        if (!file_exists("{$this->zip_base_url}/tmp/core/index.html")) {
            $this->clearTemp();
            throw new WebmappExceptionNoFile("ERROR: Missing file 'index.html' in {$this->zip_base_url}/{$this->zip_name}.zip", 1);
        }

        if (!file_exists("{$this->zip_base_url}/tmp/core/assets")) {
            $this->clearTemp();
            throw new WebmappExceptionNoFile("ERROR: Missing folder 'assets' in {$this->zip_base_url}/{$this->zip_name}.zip", 1);
        }

        if (!file_exists("{$this->zip_base_url}/tmp/core/assets/icon")) {
            $this->clearTemp();
            throw new WebmappExceptionNoFile("ERROR: Missing folder 'assets/icon' in {$this->zip_base_url}/{$this->zip_name}.zip", 1);
        }

        $this->clearTemp();
        echo "Check OK - Ready to generate the webapp in " . $this->project_structure->getRoot() . "\n\n";

        return true;
    }

    public function process()
    {
        $this->path = $this->project_structure->getRoot();

        $this->zip_base_url = '/root/wm-webapp-elbrus';
        if (array_key_exists('zip_base_url', $this->options)) {
            $this->zip_base_url = $this->options['zip_base_url'];
        }

        $this->zip_name = 'core';
        if (array_key_exists('zip_name', $this->options)) {
            $this->zip_name = $this->options['zip_name'];
        }

        echo "Updating core...  ";

        $cmd = "rm -Rf {$this->zip_base_url}/core";
        exec($cmd);

        $cmd = "mkdir {$this->zip_base_url}/core";
        exec($cmd);

        $cmd = "unzip {$this->zip_base_url}/{$this->zip_name}.zip -d {$this->zip_base_url}";
        exec($cmd);

        echo "Extracted {$this->zip_base_url}/{$this->zip_name}.zip in {$this->zip_base_url}/\n";

        // For each instance copy the updated core, copy the icon, update the index.html and link config.json
        echo "\nUpdating webapp core...\n";
        echo "Removing old core...                      ";

        $cmd = "rm -Rf {$this->path}/core";
        exec($cmd);

        echo " OK\n";
        echo "Copying new core...                       ";

        $cmd = "cp -r {$this->zip_base_url}/core {$this->path}/core";
        exec($cmd);

        echo " OK\n";
        echo "Copying favicon.png and splash.png...     ";

        $cmd = "cp {$this->path}/resources/icon.png {$this->path}/core/assets/icon/favicon.png";
        exec($cmd);
        $cmd = "cp {$this->path}/resources/splash.png {$this->path}/core/assets/icon/splash.png";
        exec($cmd);

        echo " OK\n";
        echo "Updating index.html...                    ";

        $json = json_decode(file_get_contents("{$this->path}/config.json"), true);
        $title = $json["APP"]["name"];

        $file = file_get_contents("{$this->path}/core/index.html");
        $file = preg_replace('/<title>[^<]*<\/title>/', "<title>" . $title . "</title>", $file);
        file_put_contents("{$this->path}/core/index.html", $file);

        echo " OK\n";
        echo "Linking config.json...                    ";

        $cmd = "cd {$this->path}/core && ln -s ../config.json ./config.json";
        exec($cmd);

        echo " OK\n";

        echo "Linking deeplinks files... ";

        if (file_exists("{$this->path}/.well-known")) {
            $cmd = "cd {$this->path}/core && ln -s ../.well-known ./.well-known";
            exec($cmd);
            echo "\n - iOS files                   OK";
        } else {
            echo "\n - iOS: WARNING: {$this->path}/.well-known/ directory missing and needed for iOS deeplinks";
        }

        $list = glob("{$this->path}/google*.html");
        if (sizeof($list) >= 1) {
            $cmd = "cd {$this->path}/core && ln -s ../google*.html ./google*.html";
            exec($cmd);
            echo "\n - Android files               OK";
        } else {
            echo "\n - Android: WARNING: {$this->path}/google*.html file missing and needed for android deeplinks";
        }

        echo "\n\nWebapp updated successfully\n\n\n";

        return true;
    }

    private function clearTemp()
    {
        $cmd = "rm -Rf {$this->zip_base_url}/tmp";
        exec($cmd);
    }
}
