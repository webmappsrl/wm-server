<?php
class WebmappWebappElbrusTask extends WebmappAbstractTask
{
    private $__path;
    private $__zip_base_url;
    private $__zip_name;

    public function check()
    {
        $this->__zip_base_url = '/root/wm-webapp-elbrus';
        if (array_key_exists('zip_base_url', $this->options)) {
            $this->__zip_base_url = $this->options['zip_base_url'];
        }

        $this->__zip_name = 'core';
        if (array_key_exists('zip_name', $this->options)) {
            $this->__zip_name = $this->options['zip_name'];
        }

        if (!file_exists($this->__zip_base_url . "/{$this->__zip_name}.zip")) {
            throw new WebmappExceptionNoFile("ERROR: Missing file '{$this->__zip_name}.zip' in '{$this->__zip_base_url}/'", 1);
        }

        $cmd = "rm -Rf {$this->__zip_base_url}/tmp";
        exec($cmd);

        $cmd = "mkdir {$this->__zip_base_url}/tmp";
        exec($cmd);

        $cmd = "unzip {$this->__zip_base_url}/{$this->__zip_name}.zip -d {$this->__zip_base_url}/tmp";
        exec($cmd);

        if (!file_exists("{$this->__zip_base_url}/tmp/core/index.html")) {
            $this->clearTemp();
            throw new WebmappExceptionNoFile("ERROR: Missing file 'index.html' in {$this->__zip_base_url}/{$this->__zip_name}.zip", 1);
        }

        if (!file_exists("{$this->__zip_base_url}/tmp/core/assets")) {
            $this->clearTemp();
            throw new WebmappExceptionNoFile("ERROR: Missing folder 'assets' in {$this->__zip_base_url}/{$this->__zip_name}.zip", 1);
        }

        if (!file_exists("{$this->__zip_base_url}/tmp/core/assets/icon")) {
            $this->clearTemp();
            throw new WebmappExceptionNoFile("ERROR: Missing folder 'assets/icon' in {$this->__zip_base_url}/{$this->__zip_name}.zip", 1);
        }

        if (!file_exists("{$this->__path}/config.json")) {
            $this->clearTemp();
            throw new WebmappExceptionNoFile("ERROR: Missing file 'config.json' in {$this->__path}/config.json", 1);
        }

        $this->clearTemp();
        echo "Check OK - Ready to generate the webapp in " . $this->project_structure->getRoot() . "\n\n";

        return true;
    }

    public function process()
    {
        $this->__path = $this->project_structure->getRoot();

        $this->__zip_base_url = '/root/wm-webapp-elbrus';
        if (array_key_exists('zip_base_url', $this->options)) {
            $this->__zip_base_url = $this->options['zip_base_url'];
        }

        $this->__zip_name = 'core';
        if (array_key_exists('zip_name', $this->options)) {
            $this->__zip_name = $this->options['zip_name'];
        }

        echo "Updating core...  ";

        $cmd = "rm -Rf {$this->__zip_base_url}/core";
        exec($cmd);

        $cmd = "mkdir {$this->__zip_base_url}/core";
        exec($cmd);

        $cmd = "unzip {$this->__zip_base_url}/{$this->__zip_name}.zip -d {$this->__zip_base_url}";
        exec($cmd);

        echo "Extracted {$this->__zip_base_url}/{$this->__zip_name}.zip in {$this->__zip_base_url}/\n";

        // For each instance copy the updated core, copy the icon, update the index.html and link config.json
        echo "\nUpdating webapp core...\n";
        echo "Removing old core...                      ";

        $cmd = "rm -Rf {$this->__path}/core";
        exec($cmd);

        echo " OK\n";
        echo "Copying new core...                       ";

        $cmd = "cp -r {$this->__zip_base_url}/core {$this->__path}/core";
        exec($cmd);

        echo " OK\n";
        echo "Copying icon...                           ";

        if (file_exists("{$this->__path}/resources/icon.png")) {
            $cmd = "cp {$this->__path}/resources/icon.png {$this->__path}/core/assets/icon/favicon.png";
            exec($cmd);
            echo " OK\n";
        } else {
            echo "Icon missing in resources/icon.png\n";
        }

        echo "Copying splash...                         ";

        if (file_exists("{$this->__path}/.well-known")) {
            $cmd = "cp {$this->__path}/resources/splash.png {$this->__path}/core/assets/icon/splash.png";
            exec($cmd);
            echo " OK\n";
        } else {
            echo "Splash missing in resources/splash.png\n";
        }

        echo "Updating index.html...                    ";

        $json = json_decode(file_get_contents("{$this->__path}/config.json"), true);
        $title = $json["APP"]["name"];

        $file = file_get_contents("{$this->__path}/core/index.html");
        $file = preg_replace('/<title>[^<]*<\/title>/', "<title>" . $title . "</title>", $file);
        file_put_contents("{$this->__path}/core/index.html", $file);

        echo " OK\n";
        echo "Linking config.json...                    ";

        $cmd = "cd {$this->__path}/core && ln -s ../config.json ./config.json";
        exec($cmd);

        echo " OK\n";

        echo "Linking deeplinks files... ";

        if (file_exists("{$this->__path}/.well-known")) {
            $cmd = "cd {$this->__path}/core && ln -s ../.well-known ./.well-known";
            exec($cmd);
            echo "\n - iOS files                   OK";
        } else {
            echo "\n - iOS: WARNING: {$this->__path}/.well-known/ directory missing and needed for iOS deeplinks";
        }

        $list = glob("{$this->__path}/google*.html");
        if (sizeof($list) >= 1) {
            $cmd = "cd {$this->__path}/core && ln -s ../google*.html ./google*.html";
            exec($cmd);
            echo "\n - Android files               OK";
        } else {
            echo "\n - Android: WARNING: {$this->__path}/google*.html file missing and needed for android deeplinks";
        }

        echo "\n\nWebapp updated successfully\n\n\n";

        return true;
    }

    private function __clearTemp()
    {
        $cmd = "rm -Rf {$this->__zip_base_url}/tmp";
        exec($cmd);
    }
}
