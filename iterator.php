<?php
class FtpDirectoryIterator {

	public $ftpConnection,
		   $ignoringFiles;

	private $_currentFolder,
			$_navigatedFolder,
			$_filesRegister,
            $_startingDir;

	public function __construct($localDir, $ftpConnection, $ftpDir, $ignoringFiles = array(), $debug = TRUE)
	{
		$this->ftpConnection = $ftpConnection;
		$this->ignoringFiles = $ignoringFiles;

		$this->iterate($localDir, $ftpDir, array(), $debug);

	}

	public function iterate($localDir, $ftpDir, $filesRegistry = array(), $debug = TRUE)
	{
	    try {

	        if ($this->_startingDir == '') {
	            $this->_startingDir = $localDir;
	        }

    		$localFiles = $localFolders = array();
            $ftpFiles = $ftpFolders = array();
    
            $this->_filesRegister = $filesRegistry;

            if ($debug) {
                echo '<br><br>-------------------------------------------------------<br><br>';
                echo '1 - Start iteration<br>';
                echo '----Local dir: ' . $localDir . '<br>';
                echo '----FTP dir: ' . $ftpDir . '<br>';
                echo '2 - Retrieving local files<br>';
            }

    		$filesList = array();
    		if ($handle = opendir($localDir)) {
    			while (false !== ($entry = readdir($handle))) {
    				if (!in_array($entry, array('.', '..'))) {
    					$filesList[] = $entry;
    				}
    			}
    			closedir($handle);
    		}
    		$filesList = $this->_filterLocalFiles($localDir, $this->_currentFolder, $filesList);
    		if (count($filesList) > 0) {
    			foreach ($filesList as $file) {
    				if (is_file($localDir . '/' . $file) || is_link($localDir . '/' . $file)) {
    					$localFiles[$localDir . '/' . $file] = filemtime($localDir . '/' .$file);
    				} elseif (is_dir($localDir . '/' . $file) ) {
    					$localFolders[$localDir . '/' . $file] = filemtime($localDir . '/' .$file);
    				} else {
    					var_dump($file);die;
    					throw new Exception("Error! Unknown item type.", 1);
    				}
    			}
    		}

    		// Lacking in documentation
    		// ftp_rawlist accept 2 variables with 2 variants:
    		// First: ftp_rawlist (ftp_stream, directory)
    		//
    		// Second: ftp_rawlist (ftp_stream, "-A") *
    		// * Before the command we have to change the directory using ftp_chdir
            if ($debug) {
                echo '3 - Change FTP DIR<br>';
                echo '4 - Retrieving FTP files<br>';
            }
            ftp_chdir($this->ftpConnection, $ftpDir);
            $filesList = $this->_filterFtpFiles($ftpDir, $this->_parseRawList(ftp_rawlist( $this->ftpConnection , "-A")));

    		// Created the files and folders on FTP server		
    		foreach ($filesList as $index => $value) {
    			if (count($value) > 0) {
    				if ($value['type'] == '-' || $value['type'] == 'l') {
    					$ftpFiles[$ftpDir . '/' . $value['name']]   = $value['last_modification_time'];
    				} elseif ($value['type'] == 'd') {
    					$ftpFolders[$ftpDir . '/' . $value['name']] = $value['last_modification_time'];
    				} else {
    					throw new Exception("Error! Unknown item type.", 1);
    				}
    			}
    		}

            if ($debug) {
                echo '5 - Checking files in the current directory and update<br>';
                echo '----Current folder is: ' . $this->_currentFolder . '<br>';
            }

    		// Now, I have to check the files, update the files that i have to update, update the files register
    		if (count($localFiles) > 0) {
        		foreach ($localFiles as $localFileName => $localFileMtime) {
        			$filename = substr($localFileName, strlen($localDir) + 1);

					// File not exists on production, upload
					// OR
					// Local Files more recent than the Ftp File
					if (!isset($ftpFiles[$ftpDir . '/' . $filename]) || $localFileMtime > $ftpFiles[$ftpDir . '/' . $filename]) {

                        if (ftp_put($this->ftpConnection, $ftpDir . '/' . $filename , $localFileName, FTP_ASCII)) {
                            if ($debug) {
                                echo $filename . " trasferito correttamente<br>";
                            }
                            $this->_filesRegister[] = $filename;
                        } else {
                            throw new Exception("Error during the file uploading", 1);
                        }

                    }
        		}
            }

            if ($debug) {
                echo '6 - Checking folders in the current directory and changing<br>';
            }

            // Now, I have to check the folder, recursive
            $finded = FALSE;
            if (count($localFolders) > 0) {

                if ($debug) {
                    echo '7 - There is folders into the current folder, check if there is in the register.<br>';
                }

                foreach ($localFolders as $localFolderName => $folderMTime) {
                    $folderName = substr($localFolderName, strlen($localDir));
                    if (!in_array($this->_currentFolder . $folderName, $this->_filesRegister)) {
                        $finded = TRUE;
                        $this->_currentFolder = $this->_currentFolder . $folderName;
                        if ($debug) {
                            echo '8 - The folder is not in the registry, go deep into the folder: ' . $this->_currentFolder . '<br>';
                        }
                        $this->_filesRegister[] = $this->_currentFolder;
                        $this->iterate($localFolderName, $ftpDir . $folderName, $this->_filesRegister, $debug);
                        return;
                    }
                }
            }

            if (!$finded) {
                $folderToChange = substr($this->_currentFolder, 0, strrpos($this->_currentFolder, '/'));
                $localDirToChange = substr($localDir, 0, strrpos($localDir, '/'));
                $ftpDirToChange = substr($ftpDir, 0, strrpos($ftpDir, '/'));
                $this->_currentFolder = $folderToChange;

                if ($localDir == $this->_startingDir) {
                    if ($debug) {
                        echo '10 - Iteration END. (' . $localDir . ')';
                        echo '<pre>';
                        print_r($this->_filesRegister);
                        echo '</pre>';
                    }
                    return;
                }
                if ($debug) {
                    echo '<strong>9 - There is no more folder in the current directory, go up: ' . $folderToChange . '</strong><br>';
                }

                $this->iterate($localDirToChange, $ftpDirToChange, $this->_filesRegister, $debug);
                return;
            }

        } catch (Exception $ex) {
            echo '<pre>';
            print_r($ex->getMessage());
            die;
        }
	}
	
    private function _filterLocalFiles($localDir, $currentDir, $files)
    {
        if (count($files) > 0 && count($this->ignoringFiles) > 0) {
        	$ignoringFiles = $this->_generateIgnoringList($this->ignoringFiles, $localDir);
            foreach ($files as $index => $file) {
            	if (substr($file, 0, 1) == '/') {
            		$fileToCheck = $localDir . $currentDir . $file;
            	} else {
            		$fileToCheck = $localDir . $currentDir . '/' . $file;
            	}
                if (in_array($fileToCheck, $ignoringFiles)) {
                    unset($files[$index]);
                }
            }
        }
        return $files;
    }

    private function _filterFtpFiles($ftpDir, $files)
    {
        if (count($files) > 0 && count($this->ignoringFiles) > 0) {
        	$ignoringFiles = $this->_generateIgnoringList($this->ignoringFiles, $ftpDir);
            foreach ($files as $index => $data) {
            	if (substr($data['name'], 0, 1) == '/') {
            		$fileToCheck = $ftpDir . $data['name'];
            	} else {
            		$fileToCheck = $ftpDir . '/' . $data['name'];
            	}
                if (in_array($fileToCheck, $ignoringFiles)) {
                    unset($files[$index]);
                }
            }
        }
        return $files;
    }

	private function _generateIgnoringList($list, $dir)
	{
		$ignoringFiles = array();
		foreach ($list as $index => $file) {
    		if (substr($file, 0, 1) == '/') {
    			$ignoringFiles[$index] = $dir . $file;
    		} else {
    			$ignoringFiles[$index] = $dir . '/' . $file;
    		}
    	}
		return $ignoringFiles;
	}

	private function _parseRawList($rawList)
	{ 
		// If you want the dots (. & ..) set this variable to 0 (with the modification to the rawlist method, the start is useless)
		$start = 0;

		//=========================
		// Specify the order of the contents here:
		// d => directory
		// l => symbolic link
		// - => files
		$orderList = array('-', 'l', 'd');

		$typeCol = 'type'; 
		$cols = array('permissions', 'number', 'owner', 'group', 'size', 'month', 'day', 'time', 'name'); 

        foreach($rawList as $key => $value) {
			$parser = null; 
            if ($key >= $start) {
            	$parser = explode(' ', preg_replace('!\s+!', ' ', $value));
            }

            if (isset($parser)) { 
				foreach ($parser as $key => $item) { 
					$parser[$cols[$key]] = $item;
					unset($parser[$key]);
                }
				$parsedList[] = $parser; 
            }
        }

        foreach ($orderList as $order) {
			foreach ($parsedList as $key => $parsedItem) {
                $type = substr(current($parsedItem), 0, 1);
                if ($type == $order) {
                    $parsedItem[$typeCol] = $type;
                    unset($parsedList[$key]);
                    $parsedList[] = $parsedItem;
                }
            }
        }

		$result = array();
		if (count($parsedList) > 0) {
			foreach ($parsedList as $index => $value) {
				$partial = array();
				$partial['type'] = $value['type'];
				$partial['name'] = $value['name'];
				$partial['permissions'] = $value['permissions'];

				// Check on next year
				if ( strrpos($value['time'], ':') != 2 ) {
					$partial['last_modification_time'] = strtotime(implode(' ', array($value['day'], $value['month'], date('Y') + 1, '00:00')));
				} else {
					$partial['last_modification_time'] = strtotime(implode(' ', array($value['day'], $value['month'], date('Y'), $value['time'])));
				}

				$result[] = $partial;
			}
		}

        return array_values($result); 
    } 	
}
?>