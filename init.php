<?php
/**
 * Initializing PHP file that includes files and adds auto loader.
 */

$timezone = 'America/Denver';
ini_set('date.timezone', $timezone);
date_default_timezone_set($timezone);

require_once 'settings.php';

spl_autoload_register(function ($class) {
    $include_directories = array(
        '',
        'vendor',
        'vendor/PdfMerge',
    );

    // handle directories
    foreach ($include_directories as $inc) {
        $dir = realpath(__DIR__ . DIRECTORY_SEPARATOR . $inc) . DIRECTORY_SEPARATOR;
        $file = $dir . str_replace('\\', DIRECTORY_SEPARATOR, $class) . '.php';

        if (file_exists($file)) {
            require $file;
        }
    }
});
