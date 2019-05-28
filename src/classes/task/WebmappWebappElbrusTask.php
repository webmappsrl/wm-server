<?php
class WebmappWebappElbrusTask extends WebmappAbstractTask
{
    private $path;
    private $zip_base_url;

    public function check()
    {
        $this->zip_base_url = '/root/wm-webapp-elbrus';
        if (array_key_exists('zip_base_url', $this->options)) {
            $this->zip_base_url = $this->options['zip_base_url'];
        }

        if (!file_exists($this->zip_base_url . '/core.zip')) {
            throw new WebmappExceptionNoFile("ERROR: Missing file 'core.zip' in '{$this->zip_base_url}/'", 1);
        }

        $cmd = "rm -Rf {$this->zip_base_url}/tmp";
        exec($cmd);

        $cmd = "mkdir {$this->zip_base_url}/tmp";
        exec($cmd);

        $cmd = "unzip {$this->zip_base_url}/core.zip -d {$this->zip_base_url}/tmp";
        exec($cmd);

        if (!file_exists("{$this->zip_base_url}/tmp/core/index.html")) {
            $this->clearTemp();
            throw new WebmappExceptionNoFile("ERROR: Missing file 'index.html' in {$this->zip_base_url}/core.zip", 1);
        }

        if (!file_exists("{$this->zip_base_url}/tmp/core/assets")) {
            $this->clearTemp();
            throw new WebmappExceptionNoFile("ERROR: Missing folder 'assets' in {$this->zip_base_url}/core.zip", 1);
        }

        if (!file_exists("{$this->zip_base_url}/tmp/core/assets/icon")) {
            $this->clearTemp();
            throw new WebmappExceptionNoFile("ERROR: Missing folder 'assets/icon' in {$this->zip_base_url}/core.zip", 1);
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

        echo "Updating core...  ";

        $cmd = "rm -Rf {$this->zip_base_url}/core";
        exec($cmd);

        $cmd = "mkdir {$this->zip_base_url}/core";
        exec($cmd);

        $cmd = "unzip {$this->zip_base_url}/core.zip -d {$this->zip_base_url}";
        exec($cmd);

        echo "Extracted {$this->zip_base_url}/core.zip in {$this->zip_base_url}/\n";

        // For each instance copy the updated core, copy the icon, update the index.html and link config.json
        echo "\nUpdating webapp core...\n";
        echo "Removing old core...       ";

        $cmd = "rm -Rf {$this->path}/core";
        exec($cmd);

        echo " OK\n";
        echo "Copying new core...        ";

        $cmd = "cp -r {$this->zip_base_url}/core {$this->path}/core";
        exec($cmd);

        echo " OK\n";
        echo "Copying favicon.png...     ";

        $cmd = "cp {$this->path}/resources/icon.png {$this->path}/core/assets/icon/favicon.png";
        exec($cmd);

        echo " OK\n";
        echo "Updating index.html...     ";

        $json = json_decode(file_get_contents("{$this->path}/config.json"), true);
        $title = $json["APP"]["name"];

        $file = file_get_contents("{$this->path}/core/index.html");
        $file = preg_replace('/<title>[^<]*<\/title>/', "<title>" . $title . "</title>", $file);
        file_put_contents("{$this->path}/core/index.html", $file);

        echo " OK\n";
        echo "Linking config.json...     ";

        $cmd = "ln -s {$this->path}/config.json {$this->path}/core/config.json";
        exec($cmd);

        echo " OK\n";

        echo "Linking deeplinks files... ";

        if (file_exists("{$this->path}/.well-known")) {
            $cmd = "ln -s {$this->path}/.well-known {$this->path}/core/.well-known";
            exec($cmd);
            echo "\niOS files                   OK\n";
        } else {
            echo "\nWARNING: {$this->path}/.well-known/ directory missing and needed for iOS deeplinks\n";
        }

        $list = glob("{$this->path}/google*.html");
        if (sizeof($list) >= 1) {
            $cmd = "ln -s {$this->path}/google*.html {$this->path}/core/";
            exec($cmd);
            echo "\nandroid files               OK\n";
        } else {
            echo "\nWARNING: {$this->path}/google*.html file missing and needed for android deeplinks\n";
        }

        echo "Webapp updated successfully\n\n\n";

        return true;
    }

    private function clearTemp()
    {
        $cmd = "rm -Rf {$this->zip_base_url}/tmp";
        exec($cmd);
    }
}
