<?php

class WebmappCliWebcacheCommand extends WebmappCliAbstractCommand {
	private $enabled = false;
	private $db_file = '';
	private $db_created = false ;
	private $db;
	private $db_count = 0;
	public function specificConstruct() {
		global $wm_config;
		if (isset($wm_config['webcache']) && 
			isset($wm_config['webcache']['enabled']) && 
			$wm_config['webcache']['enabled']==true) {
			if(!isset($wm_config['webcache']['db'])) {
				throw new Exception("config.json malconfigurato: webcache enabled e db non definito.", 1);
			}
			$this->enabled=true;
			$this->db_file=$wm_config['webcache']['db'];
			if(file_exists($this->db_file)) {
				$this->db_created=true;
				$this->db=new SQLite3($this->db_file);
				$this->db_count = $this->db->querySingle("SELECT COUNT(*) as count FROM webcache");
			}
		}
	}
	public function getExcerpt() {
        $string = "performs some operations on webcachedb (see help for details)";
        return $string;
	}
	public function showHelp() {
		$string = "\nWebcache command:\n";
		$string .= "Performs some operations on webcachedb (must be enabled in config.json)\n";
		$string .= "info: Give some infos on webcachedb\n";
		$string .= "create: Creates webcachedb (if does not exist)\n";
		$string .= "delete: Delete existing db\n";
		$string .= "clearall: Remove all entries on webcachedb\n";
		$string .= "clear [domain]: Remove all entries matching domain\n";
        echo $string;
	}
	public function executeNoHelp() {
		// Check conffiguration 
		global $wm_config;

		if(count($this->options)==0 || $this->options[0]=='info') {
			$this->showInfo();
		} else {
			$sub_command = $this->options[0];
			switch ($sub_command) {
				case 'create':
					if($this->db_created) {
						echo "Can't create db: already existing.";
					}
					else {
						// Create DB
						$this->db = new SQLite3($this->db_file);
						$q = "CREATE TABLE webcache (url TEXT, content TEXT, timestamp INT)";
						$this->db->query($q);
						echo "DB created\n";
					}
					break;
				case 'delete':
					$cmd = "rm -f ".$this->db_file;
					system($cmd);
					echo "DB removed.\n";
					break;
				case 'clearall':
					echo "clearall (not implemented)\n";
					break;
				case 'clear':
					echo "clear (not implemented)\n";
					break;				
				default:
					echo "$sub_command not defined.";
					$this->showHelp();
					break;
			}
		}
		return true;
	}
	public function showInfo() {
		if ($this->enabled) {
			echo "\nWEBCACHE enabled. Info:\n";
			echo "DB: ".$this->db_file."\n";
			if ($this->db_created) {
				echo "DB already created.\n";
				echo "Number of rows: ".$this->db_count."\n";
			} else {
				echo "DB not yet created. Use command createdb to generate it.";
			}
		} else {
			echo "WEBCACHE not enabled: check your config.json file";
		}
	}
}
