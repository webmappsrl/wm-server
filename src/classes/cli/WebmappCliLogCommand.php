<?php 
class WebmappCliLogCommand extends WebmappCliAbstractCommand {

	private $log_path;

 	public function specificConstruct() {
 		global $wm_config;
 		if(!isset($wm_config['log']['path'])) {
 			throw new Webmapp ("Error Processing Request", 1);
 		} 
 		$this->log_path=$wm_config['log']['path'];
 		return true; 
 	}

	public function getExcerpt() {
        $string = "Show log files";
        return $string;
	}
	public function showHelp() {
		$string = <<<EOT

Usage: wmcli log [YYYYMMDD] [id]

With non argument all available dates will be shown.
With frist argument (date) all log files for that date will be shown.
With date and id, less command will be launched for that log files.


EOT;
        echo $string;
	}
	public function executeNoHelp() {
		switch (count($this->options)) {
			case '0':
				# LIST DATES
				echo "DATES available:\n";
				$d = dir($this->log_path);
				while(($name=$d->read())!==false) {
					echo "$name \n";
				}
				break;
			case '1':
				# LIST IDS
				$path = $this->log_path.'/'.$this->options[0].'/index.json';
				if (!file_exists($path)) {
					echo "Invalid date: $path does not exists\n";
				} else {
					$j = json_decode(file_get_contents($path),TRUE);
					foreach ($j as $id => $info) {
						echo "ID:$id ";
						echo $info['desc'];
						echo "\n";
					}
				}

				break;
			case '2':
				# LESS FILE
				$path = $this->log_path.'/'.$this->options[0].'/'.str_pad((int) $this->options[1],5,"0",STR_PAD_LEFT).'.log';
				if (!file_exists($path)) {
					echo "Invalid file: $path does not exists\n";
				} else {
					echo "less $path\n";
				}
				break;
		}
		;
		return true;
	}
}
