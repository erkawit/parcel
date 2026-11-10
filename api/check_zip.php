<?php
echo "<h1>PHP Zip Extension Diagnostics</h1>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Loaded Configuration File: " . php_ini_loaded_file() . "<br>";
echo "Scan this dir for additional .ini files: " . php_ini_scanned_files() . "<br>";
echo "<hr>";

if (class_exists('ZipArchive')) {
    echo "<h2 style='color:green'>SUCCESS: ZipArchive class exists. Extension is loaded!</h2>";
    $zip = new ZipArchive;
    echo "ZipArchive initialized successfully.<br>";
} else {
    echo "<h2 style='color:red'>FAILURE: ZipArchive class does NOT exist. Extension is NOT loaded.</h2>";

    // Check if extension is loaded by name
    if (extension_loaded('zip')) {
        echo "extension_loaded('zip') returned TRUE, but class missing? (Very strange)<br>";
    } else {
        echo "extension_loaded('zip') returned FALSE.<br>";
    }

    echo "<h3>Troubleshooting:</h3>";
    echo "1. <strong>Restart Apache:</strong> Changes to php.ini require a full restart of Apache.<br>";
    echo "2. <strong>Verify php.ini:</strong> Check " . php_ini_loaded_file() . " for <code>extension=zip</code>.<br>";
    echo "3. <strong>Check Error Logs:</strong> Look at <code>c:/xampp/php/logs/php_error_log</code> or Apache error logs for startup errors about 'Unable to load dynamic library'.<br>";
}

echo "<hr>";
echo "Loaded Extensions:<br>";
print_r(get_loaded_extensions());
?>