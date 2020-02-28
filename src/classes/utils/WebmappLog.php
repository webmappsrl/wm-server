<?php

/**
 * Class WebmappLog
 */
final class WebmappLog {

	private $log_path;
	private $log_file;
	private $index_file;
	private $index = array();
	private $id;
	private $is_open = false;

    /**
     * @return WebmappLog|null
     * @throws WebmappExceptionLogPathNotExist
     */
	public static function Instance()
	{
		static $inst = null;
		if ($inst === null) {
			$inst = new WebmappLog();
		}
		return $inst;
	}

    /**
     * WebmappLog constructor.
     * @throws WebmappExceptionLogPathNotExist
     */
	private function __construct()
	{
		global $wm_config;
		if(!isset($wm_config['log'])) {
			throw new WebmappExceptionConfLog("No Log section in conf.json", 1);  
		}
		if(!isset($wm_config['log']['path'])) {
			throw new WebmappExceptionConfLog("No Log PATH section in conf.json", 1);  
		}
        // TODO: check other parametr
		$this->log_path = $wm_config['log']['path'];

		// Check if log_path exists
		if(!file_exists($this->log_path)) {
			throw new WebmappExceptionLogPathNotExist("Log path {$this->log_path} does not exixts.", 1);		
		}

		// Check if log_path is writable
		if(!is_writable($this->log_path)) {
			throw new WebmappExceptionLogIsNotWritable("Log path {$this->log_path} is not writable.", 1);		
		}

		// Gestione del file index e ID
		$day_path = $this->log_path . '/' . date('Ymd');
		$this->index_file = $day_path.'/index.json';
		if(!file_exists($day_path)) {
			// First time in day_path
			$this->id = 1;
			mkdir($day_path);
		} else {
			// Retrieve dell'index, set id, write new entry
			$this->index = json_decode(file_get_contents($this->index_file),TRUE);
			$this->id = count($this->index)+1;
		}

		$this->setIndexVal('id',$this->id);
		$this->setIndexVal('start',date('r'));
		$this->setIndexVal('desc','ND');
		$this->setIndexVal('stop','ND');

		$this->log_file=$day_path.'/'.str_pad((int) $this->id,5,"0",STR_PAD_LEFT).'.log';
	}

    /**
     * @return int
     */
	public function getId() {
		return $this->id;
	}

    /**
     * @param $k
     * @param $v
     */
	private function setIndexVal($k,$v) {
		$this->index[$this->id][$k]=$v;
		file_put_contents($this->index_file, json_encode($this->index));
	}

    /**
     * @param $msg
     */
	public function open($msg) {
		if (!$this->is_open) {
			$start_msg = '* LOG START AT '.date('r').': '.$msg.' *';
			file_put_contents($this->log_file,str_repeat('*',strlen($start_msg))."\n",FILE_APPEND);
			file_put_contents($this->log_file,$start_msg."\n",FILE_APPEND);
			file_put_contents($this->log_file,str_repeat('*',strlen($start_msg))."\n\n",FILE_APPEND);
			$this->setIndexVal('desc',$msg);
			$this->is_open = true;
		}
	}

    /**
     * @param $msg
     */
	public function log($msg) {
		if(!$this->is_open) {
			$this->is_open('UNKNOWN');
		}
		$new_msg = 'LOG AT '.date('r').': '.$msg.' *';
		file_put_contents($this->log_file,$new_msg."\n",FILE_APPEND);
	}

    /**
     *
     */
	public function close() {
		$stop_msg = '* LOG STOP AT '.date('r').' *';
		file_put_contents($this->log_file,"\n\n",FILE_APPEND);
		file_put_contents($this->log_file,str_repeat('*',strlen($stop_msg))."\n",FILE_APPEND);
		file_put_contents($this->log_file,$stop_msg."\n",FILE_APPEND);
		file_put_contents($this->log_file,str_repeat('*',strlen($stop_msg))."\n\n",FILE_APPEND);
		$this->setIndexVal('stop',date('r'));
	}

}