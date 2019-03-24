<?php
namespace logging;

class LogWriter {
	/**
	* $log_file - path to store the log file
	* @var string
	*/
	private $log_file;

	/**
	* $file - file
	* @var string
	*/
	private $file;

	/**
	* $options - settable options - future use - passed through constructor
	* @var array
	*/
	private $options = array(
		'dateFormat' => 'd-M-Y H:i:s'
	);

	/**
	* Class constructor
	* @param string $log_file - path and filename of log
	* @param array $params
	*/
	public function __construct($log_file = 'error.txt', $params = array()){
		$this->log_file = $log_file;
		$this->params = array_merge($this->options, $params);
		//Create log file if it doesn't exist.
		if(!file_exists($log_file)){               
			fopen($log_file, 'w') or exit("Can't create $log_file!");
		}
		//Check permissions of file.
		if(!is_writable($log_file)){   
			//throw exception if not writable
			throw new Exception("ERROR: Unable to write to file!", 1);
		}
	}
	/**
	* Info method (write info message)
	* @param string $message
	* @return void
	*/
	public function info($message){
		$this->writeLog($message, 'INFO');
	}
	/**
	* Debug method (write debug message)
	* @param string $message
	* @return void
	*/
	public function debug($message){
		$this->writeLog($message, 'DEBUG');
	}
	/**
	* Warning method (write warning message)
	* @param string $message
	* @return void
	*/
	public function warning($message){
		$this->writeLog($message, 'WARNING');
	}
	/**
	* Error method (write error message)
	* @param string $message
	* @return void
	*/
	public function error($message){
		$this->writeLog($message, 'ERROR');
	}
	/**
	* Write to log file
	* @param string $message
	* @param string $severity
	* @return void
	*/
	private function writeLog($message, $severity) {
		// open log file
		if (!is_resource($this->file)) {
			$this->openLog();
		}
		//Grab time - based on timezone in php.ini
		$time = date($this->params['dateFormat']);
		// Write time, url, & message to end of file
		fwrite($this->file, "[$time] : [$severity] - $message" . PHP_EOL);
	}
	/**
	* Open log file
	* @return void
	*/
	private function openLog(){
		$openFile = $this->log_file;
		// 'a' option = place pointer at end of file
		$this->file = fopen($openFile, 'a') or exit("Can't open $openFile!");
	}
	/**
	 * Class destructor
	 */
	public function __destruct(){
		if ($this->file) {
			fclose($this->file);
		}
	}
}