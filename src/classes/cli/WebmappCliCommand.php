<?php

abstract class WebmappCliAbstractCommand {
	private $options=array();
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
	}
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

// class WebmappCliXXXCommand implements WebmappCliAbstractCommand {
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
