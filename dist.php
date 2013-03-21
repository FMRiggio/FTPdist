<?php
require 'config.php';
require 'iterator.php';

class Dist {

    private $ftpConnection;
    private $ignoringFolder;
    private $csv;
    private $index;

    public function __construct()
    {
    	$config = new Conf();
		
        $this->ftpConnection = ftp_connect($config->ftpData['host'], 0) or die('Couldn\'t connect to the server.');

        if (ftp_login($this->ftpConnection, $config->ftpData['username'], $config->ftpData['password'])) {
            if ($config->debug) {
                echo 'Connected to the server<br>';
            }
        } else {
            die('Couldn\'t connect to the server. Wrong data.');
        }

        // Start reading folder
        $ftpIterator = new FtpDirectoryIterator($config->localBaseDir, $this->ftpConnection, $config->ftpBaseDir, $config->ignoringFiles, $config->debug);
        ftp_close($this->ftpConnection);  
    }
}

?>