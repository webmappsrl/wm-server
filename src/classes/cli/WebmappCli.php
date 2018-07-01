<?php 
class WebmappCli {
	private $command;
	private $commands=array();

	// $a must be argv
	public function __construct($a) {
		$this->setCommands();
		if(count($a)==1) {
			echo "\n\nNo command given\n\n";
			$this->showHelp();
		} else {
			$c=strtolower(trim($a[1]));
			if ($c=='help') {
				$this->showHelp();
			}
			else {
				if ($this->setCommand($a)) {
					$this->command->execute($a);
				} 
				else {
					echo "\n\nIl comando $c non esiste\n\n";
					$this->showHelp();
				}
			}
		}
	}
	private function setCommand($a) {
		$c=strtolower(trim($a[1]));
		$classname = 'WebmappCli'.ucfirst(strtolower(trim($c))).'Command';
		if (class_exists($classname)) {
			$this->command=new $classname($a);
			return true;
		}
		else {
			return false;
		}
	}

	private function setCommands() {
		$commands=array();
		$classes = get_declared_classes();
		foreach($classes as $class) {
			if (is_subclass_of($class,'WebmappCliAbstractCommand')) {
				array_push($this->commands, $class);
			}
		}
	}

	private function showHelp() {
		echo "\n\n====================================\n";
		echo "WEBMAPP CLI (command line interface)\n";
		echo "====================================\n";
		echo "\n";
		echo "Usage: php src/wmcli.php command [option1] [option2] ... [options]\n";
		echo "\n";
		echo "HELP COMMAND:\n";
		echo "help: show this generic message\n";
		echo "[command] help: show help message for command\n\n";
		// Loop sulle classi disponibili che mostrano il loro messaggio
		echo "OTHER COMMANDS:\n";
		foreach($this->commands as $command) {
			$a = array('X','X');
			$c = new $command($a);
			echo $c->getName().": ".$c->getExcerpt()."\n";
		}
		echo "\n\n";
	}
}

