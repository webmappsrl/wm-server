<?php

abstract class WebmappCliAbstractCommand {
	protected $options=array();
	private $name;
	public function __construct($argv) {
		// Set Name
		$name=strtolower(trim(get_class($this)));
		$name=preg_replace('/webmappcli/','',$name);
		$name=preg_replace('/command/','',$name);
		$this->name=$name;

		// Set command options
		if (count($argv)>2) {
			$this->options=array_slice($argv,2);
		}
		$this->specificConstruct();
	}
	abstract public function specificConstruct();
	abstract public function getExcerpt();
	abstract public function showHelp();
	public function execute() {
		if(count($this->options)>0 && $this->options[0]=='help') {
			$this->showHelp();
		}
		else {
			$this->executeNoHelp();
		}
	}
	abstract public function executeNoHelp();
	public function getName() { return $this->name; }
	public function getOptions() { return $this->options; }
}

class WebmappCliVersionCommand extends WebmappCliAbstractCommand {
	public function specificConstruct() { return true; }
	public function getExcerpt() {
        $string = "returns webmappServer version";
        return $string;
	}
	public function showHelp() {
		$string = "\nThis simply command (no options needed) show the webmappServerVersion\n\n";
        echo $string;
	}
	public function executeNoHelp() {
		echo "\nCurrent WebmappServerVersion: XX.XX.XX\n";
		return true;
	}
}

class WebmappCliShowconfigCommand extends WebmappCliAbstractCommand {
	public function specificConstruct() { return true; }
	public function getExcerpt() {
        $string = "shows all configuration settings.";
        return $string;
	}
	public function showHelp() {
		$string = "\nDisplay all configuration seettings with different sections.\n\n";
        echo $string;
	}
	public function executeNoHelp() {
		global $wm_config;
		echo "\n";
		foreach ($wm_config as $section => $items) {
			echo "SECTION: $section\n";
			foreach($items as $k => $v) {
				echo " -> $k : $v\n";
			}
			echo "\n";
		}
		return true;
	}
}

// class WebmappCliXXXCommand extends WebmappCliAbstractCommand {
//  public function specificConstruct() { return true; }
// 	public function getExcerpt() {
//         $string = "Excerpt";
//         return $string;
// 	}
// 	public function showHelp() {
// 		$string = "\nHelp\n\n";
//         echo $string;
// 	}
// 	public function executeNoHelp() {
// 		echo "\ncommand\n";
// 		return true;
// 	}
// }
