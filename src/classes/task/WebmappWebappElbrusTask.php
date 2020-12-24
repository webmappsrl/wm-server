<?php

class WebmappWebappElbrusTask extends WebmappAbstractTask
{
    private $__path;
    private $__zip_base_url;
    private $__zip_name;

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
     * Make string appear in color
     */
    public static function fg_color($color, $string)
    {
        if (!isset(self::$foreground[$color])) {
            throw new \Exception('Foreground color is not defined');
        }

        return "\033[" . self::$foreground[$color] . "m" . $string . "\033[0m";
    }

    /**
     * Make string appear with background color
     */
    public static function bg_color($color, $string)
    {
        if (!isset(self::$background[$color])) {
            throw new \Exception('Background color is not defined');
        }

        return "\033[" . self::$background[$color] . 'm' . $string . "\033[0m";
    }

    public function check()
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

        if (!file_exists($this->__zip_base_url . "/{$this->__zip_name}.zip")) {
            die(self::fg_color('red', "ERROR: Missing file '{$this->__zip_name}.zip' in '{$this->__zip_base_url}/'\n"));
        }

        $cmd = "rm -Rf {$this->__zip_base_url}/tmp";
        exec($cmd);

        $cmd = "mkdir {$this->__zip_base_url}/tmp";
        exec($cmd);

        $cmd = "unzip {$this->__zip_base_url}/{$this->__zip_name}.zip -d {$this->__zip_base_url}/tmp";
        exec($cmd);

        if (!file_exists("{$this->__zip_base_url}/tmp/core/index.html")) {
            $this->__clearTemp();
            die(self::fg_color('red', "ERROR: Missing file 'index.html' in {$this->__zip_base_url}/{$this->__zip_name}.zip\n"));
        }

        if (!file_exists("{$this->__zip_base_url}/tmp/core/assets")) {
            $this->__clearTemp();
            die(self::fg_color('red', "ERROR: Missing folder 'assets' in {$this->__zip_base_url}/{$this->__zip_name}.zip\n"));
        }

        if (!file_exists("{$this->__zip_base_url}/tmp/core/assets/icon")) {
            $this->__clearTemp();
            die(self::fg_color('red', "ERROR: Missing folder 'assets/icon' in {$this->__zip_base_url}/{$this->__zip_name}.zip\n"));
        }

        if (!file_exists("{$this->__path}/config.json")) {
            $this->__clearTemp();
            die(self::fg_color('red', "ERROR: Missing file 'config.json' in {$this->__path}/config.json\n"));
        }

        $this->__clearTemp();
        echo self::fg_color('green', "Check OK - Ready to generate the webapp in " . $this->project_structure->getRoot() . "\n\n");

        return true;
    }

    public function process()
    {
        $this->__zip_base_url = '/root/wm-webapp-elbrus';
        if (array_key_exists('zip_base_url', $this->options)) {
            $this->__zip_base_url = $this->options['zip_base_url'];
        }

        $this->__zip_name = 'core';
        if (array_key_exists('zip_name', $this->options)) {
            $this->__zip_name = $this->options['zip_name'];
        }

        echo "Updating core...  ";

        if (file_exists("{$this->__zip_base_url}/core")) {
            $cmd = "rm -Rf {$this->__zip_base_url}/core";
            exec($cmd);
        }

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

        echo self::fg_color('cyan', " OK\n");
        echo "Copying new core...                       ";

        $cmd = "cp -r {$this->__zip_base_url}/core {$this->__path}/core";
        exec($cmd);

        echo self::fg_color('cyan', " OK\n");
        echo "Copying icon...                           ";

        if (file_exists("{$this->__path}/resources/icon.png")) {
            $cmd = "cp {$this->__path}/resources/icon.png {$this->__path}/core/assets/icon/favicon.png";
            exec($cmd);
            echo self::fg_color('cyan', " OK\n");
        } else {
            echo self::fg_color('yellow', " Icon missing in resources/icon.png\n");
        }

        echo "Copying splash...                         ";

        if (file_exists("{$this->__path}/resources/splash.png")) {
            $cmd = "cp {$this->__path}/resources/splash.png {$this->__path}/core/assets/icon/splash.png";
            exec($cmd);
            echo self::fg_color('cyan', " OK\n");
        } else {
            echo self::fg_color('yellow', " Splash missing in resources/splash.png\n");
        }

        echo "Updating title in index.html...           ";

        $json = json_decode(file_get_contents("{$this->__path}/config.json"), true);
        $title = $json["APP"]["name"];

        $file = file_get_contents("{$this->__path}/core/index.html");
        $file = preg_replace('/<title>[^<]*<\/title>/', "<title>" . $title . "</title>", $file);

        echo self::fg_color('cyan', " OK\n");
        if (isset($json["APP"]["gtagId"])) {
            echo "Adding analytics code in index.html...    ";
            $gtagCode = <<<EOD
  <!-- Global site tag (gtag.js) - Google Analytics -->
  <script async src="https://www.googletagmanager.com/gtag/js?id={$json["APP"]["gtagId"]}"></script>
  <script>
  window.dataLayer = window.dataLayer || [];
  function gtag() {
    dataLayer.push(arguments);
  }
  gtag("js", new Date());
  gtag("config", "{$json["APP"]["gtagId"]}");
  </script>

EOD;
            $file = preg_replace('/<\/head>/', "$gtagCode</head>", $file);
            echo self::fg_color('cyan', " OK\n");
        }

        echo "Updating index.html...                    ";

        file_put_contents("{$this->__path}/core/index.html", $file);

        echo self::fg_color('cyan', " OK\n");
        echo "Linking config.json...                    ";

        $cmd = "cd {$this->__path}/core && ln -s ../config.json ./config.json";
        exec($cmd);

        echo self::fg_color('cyan', " OK\n");

        echo "Linking deeplinks files... ";

        if (file_exists("{$this->__path}/.well-known")) {
            $cmd = "cd {$this->__path}/core && ln -s ../.well-known ./.well-known";
            exec($cmd);
            echo "\n - iOS files                   " . self::fg_color('cyan', "OK\n");
        } else {
            echo self::fg_color('yellow', "\n - iOS: WARNING: {$this->__path}/.well-known/ directory missing and needed for iOS deeplinks");
        }

        $list = glob("{$this->__path}/google*.html");
        if (sizeof($list) >= 1) {
            $cmd = "cd {$this->__path}/core && ln -s ../google*.html ./google*.html";
            exec($cmd);
            echo "\n - Android files               " . self::fg_color('cyan', "OK\n");
        } else {
            echo self::fg_color('yellow', "\n - Android: WARNING: {$this->__path}/google*.html file missing and needed for android deeplinks");
        }

        if (file_exists("{$this->__zip_base_url}/core")) {
            echo "\nRemoving temp files...                    ";
            $cmd = "rm -Rf {$this->__zip_base_url}/core";
            exec($cmd);
            echo self::fg_color('cyan', " OK\n");
        }

        echo self::fg_color('green', "\n\nWebapp updated successfully\n\n\n");

        return true;
    }

    private function __clearTemp()
    {
        $cmd = "rm -Rf {$this->__zip_base_url}/tmp";
        exec($cmd);
    }
}
