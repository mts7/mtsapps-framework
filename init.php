<?php
/**
 * Initializing PHP file that includes files and adds auto loader.
 */

$timezone = 'America/Denver';
ini_set('date.timezone', $timezone);
date_default_timezone_set($timezone);

require_once 'helpers.php';
require_once 'settings.php';

spl_autoload_register(function($class) {
    $base_dir = __DIR__ . DIRECTORY_SEPARATOR;
    $file = $base_dir . str_replace('\\', DIRECTORY_SEPARATOR, $class) . '.php';

    $include_directories = array(
        'PdfMerge',
    );

    if (file_exists($file)) {
        require $file;
    } else {
        // handle other directories
        foreach($include_directories as $inc) {
            $dir = $base_dir . $inc . DIRECTORY_SEPARATOR;
            $other_file = $dir . str_replace('\\', DIRECTORY_SEPARATOR, $class) . '.php';

            if (file_exists($other_file)) {
                require $other_file;
            }
        }
    }
});
