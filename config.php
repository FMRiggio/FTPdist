<?php
// Define path to base directory
defined('BASE_PATH') || define('BASE_PATH', realpath(dirname(__FILE__)));

class Conf {
							  
    public $ftpData = array(),
		   $ignoringFiles = array(),
		   $indexOn = 'file',
		   $ftpBaseDir = '/',
		   $localBaseDir,
           $debug = TRUE;

	public function __construct()
	{
		// Load the configuration here
		$this->ftpData['host']     = 'ftp.domain.com';
		$this->ftpData['username'] = 'ftp_username';
		$this->ftpData['password'] = 'ftp_password';
		$this->ftpData['port']     = 21;

		$this->ftpBaseDir          = '/htdocs';
		$this->localBaseDir        = '';

		$this->ignoringFiles[]     = '.git';
		$this->ignoringFiles[]     = '.gitignore';
		$this->ignoringFiles[]     = '.project';
		$this->ignoringFiles[]     = 'README.md';
		$this->ignoringFiles[]     = '/cgi-bin';
		$this->ignoringFiles[]     = '/conf';
		$this->ignoringFiles[]     = '/library';
		$this->ignoringFiles[]     = '/docs';
		$this->ignoringFiles[]     = '/var/log';

	}
}
?>