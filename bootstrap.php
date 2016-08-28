<?php

require('config/paths.php');

if (php_sapi_name() == "cli") require('config/shell_paths.php');;
require($CONFIG['CENTRAL_PATH'].'lib/autoloader.php');

session_start();

?>