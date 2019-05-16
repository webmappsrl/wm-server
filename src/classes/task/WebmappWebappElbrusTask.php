<?php
class WebmappWebappElbrusTask extends WebmappAbstractTask
{
    private $path;
    private $base_path;

    public function check()
    {
        if (!file_exists($this->project_structure->getRoot() . '/core.zip')) {
            throw new WebmappExceptionNoFile("ERROR: Missing core zip in '{$this->project_structure->getRoot()}/core.zip'", 1);
        }

        if (!array_key_exists('base_url', $this->options)) {
            throw new WebmappExceptionParameterMandatory("ERROR: 'base_url' option is mandatory", 1);
        }

        if (!array_key_exists('codes', $this->options)) {
            throw new WebmappExceptionParameterMandatory("ERROR: 'codes' option is mandatory", 1);
        }

        $codes = '';

        foreach ($this->options['codes'] as $code) {
            $codes .= "\n - {$code}";
        }

        $codes .= "\n";

        echo "\nThe following webapps located in the root directory " . $this->options['base_url'] . " will be regenerated:{$codes}\n\n";

        return true;
    }

    public function process()
    {
        $this->path = $this->project_structure->getRoot();

        // Check if zip contains everything
        $cmd = "rm -Rf {$this->path}/tmp";
        exec($cmd);

        $cmd = "mkdir {$this->path}/tmp";
        exec($cmd);

        $cmd = "unzip {$this->path}/core.zip -d {$this->path}/tmp";
        exec($cmd);

        echo "Extracted {$this->path}/core.zip in {$this->path}/tmp\n";
        echo "Checking {$this->path}/tmp content...\n";

        if (!file_exists("{$this->path}/tmp/core/index.html")) {
            $this->clearTemp();
            throw new WebmappExceptionNoFile("ERROR: File index.hml mancante nel file {$this->path}/core.zip", 1);
        }

        echo "index.html    OK\n";

        if (!file_exists("{$this->path}/tmp/core/assets")) {
            $this->clearTemp();
            throw new WebmappExceptionNoFile("ERROR: Cartella assets mancante nel file {$this->path}/core.zip", 1);
        }

        echo "assets        OK\n";

        if (!file_exists("{$this->path}/tmp/core/assets/icon")) {
            $this->clearTemp();
            throw new WebmappExceptionNoFile("ERROR: Cartella assets/icon mancante nel file {$this->path}/core.zip", 1);
        }

        echo "assets/icon   OK\n";
        echo "Updating existing core...";

        // Update wm-webapp/core
        $cmd = "rm -Rf {$this->path}/core";
        exec($cmd);

        $cmd = "mv {$this->path}/tmp/core {$this->path}/core";
        exec($cmd);

        echo " OK\n";
        echo "Updating index.html...";

        $file = file_get_contents("{$this->path}/core/index.html");
        $file = preg_replace('/<base href="\/" \/>/', '<base href="/core/" \/>', $file);
        file_put_contents("{$this->path}/core/index.html", $file);

        echo " OK\n";

        $base_path = $this->options['base_url'];

        // For each instance copy the updated core, copy the icon and update the index.html
        foreach ($this->options['codes'] as $code) {
            echo "\nUpdating {$code}...\n";
            echo "Removing old core...";

            $cmd = "rm -Rf {$base_path}/{$code}/core";
            exec($cmd);

            echo " OK\n";
            echo "Copying new core...   ";

            $cmd = "cp -r {$this->path}/core {$base_path}/{$code}/core";
            exec($cmd);

            echo " OK\n";
            echo "Copying favicon.png...";

            $cmd = "cp {$base_path}/{$code}/resources/icon.png {$base_path}/{$code}/core/assets/icon/favicon.png";
            exec($cmd);

            echo " OK\n";

            echo "{$code} updated successfully\n";
        }

        echo "\n\n";

        echo "Clearing temp files...";
        $this->clearTemp();
        echo " OK\n\n";

        echo "Task terminated successfully\n\n";

        return true;
    }

    private function clearTemp()
    {
        $cmd = "rm -rf {$this->path}/tmp";
        system($cmd);
    }
}
