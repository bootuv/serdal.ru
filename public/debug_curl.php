<?php
echo "PHP Version: " . phpversion() . "<br>";
echo "CURL Extension: " . (extension_loaded('curl') ? 'LOADED' : 'MISSING') . "<br>";
echo "CURLOPT_SSL_VERIFYPEER: " . (defined('CURLOPT_SSL_VERIFYPEER') ? constant('CURLOPT_SSL_VERIFYPEER') : 'UNDEFINED') . "<br>";
echo "php.ini loaded: " . php_ini_loaded_file() . "<br>";
