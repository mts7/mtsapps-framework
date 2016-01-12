<?php
/**
 * @author Mike Rodarte
 * @version 1.03
 *
 * Helper functions for mtsapps library
 */


/**
 * Flatten a multi-dimensional array
 * http://stackoverflow.com/questions/1319903/how-to-flatten-a-multidimensional-array#answer-1320259
 *
 * @param array $array Multi-dimensional array to flatten
 * @return array
 */
function array_flatten($array = array())
{
    if (!is_array_ne($array)) {
        return array();
    }

    $temp = array();

    $it = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($array));
    foreach ($it as $v) {
        $temp[] = $v;
    }

    return $temp;
}


/**
 * Backup a MySQL database with the provided parameters. Save the file to the backup path or current directory.
 *
 * @param string $host
 * @param string $user
 * @param string $pass
 * @param string $db
 * @param string $backup_path
 * @return int
 */
function backup_database($host = '', $user = '', $pass = '', $db = '', $backup_path = '')
{
    // use this directory if one is not provided
    if (!is_string_ne($backup_path) || !is_dir($backup_path)) {
        $backup_path = '__DIR__';
    }
    // verify the path is valid and has a trailing /
    $backup_path = realpath($backup_path) . '/';

    // set the file name to use the database name and current datetime (in case of multiple backups per day)
    $sql_file = $backup_path . $db . date('_Y-m-d_H-i-s') . '.sql';

    // build the command
    $command = "mysqldump -u${user}@${host} -p${pass} ${db} > $sql_file";

    // execute the command
    exec($command);

    // return the size of the sql file (> 0 assumes it was written successfully)
    return filesize($sql_file);
}


/**
 * Display an exception.
 *
 * @param Exception $ex
 * @param bool|false $trace
 * @return string
 */
function display_exception(Exception $ex, $trace = false)
{
    $br = "<br />\n";
    $output = get_class($ex) . $br;
    $output .= $ex->getMessage() . ' in ' . $ex->getFile() . ' on line ' . $ex->getLine() . $br;
    if ($trace) {
        $output .= 'Trace: ' . $br . print_array($ex->getTrace(), 0, false);
    }

    return $output;
}


/**
 * Return a string based on value type.
 *
 * @param mixed $value
 * @return string
 */
function get_string($value)
{
    $type = gettype($value);

    switch ($type) {
        case 'string':
            $result = $value;
            break;
        case 'boolean':
            $result = !!$value ? 'true' : 'false';
            break;
        case 'integer':
        case 'double':
            $result = (string)$value;
            break;
        case 'array':
        case 'object':
            $result = print_array($value, 0, false);
            break;
        case 'resource':
            $result = get_resource_type($value);
            break;
        case 'NULL':
            $result = 'NULL';
            break;
        case 'unknown type':
            $result = 'unknown';
            break;
        default:
            $result = '';
            break;
    }

    return $result;
}


/**
 * Check to see if the passed value is an array with a length.
 *
 * @param array $value
 * @return bool
 */
function is_array_ne($value)
{
    return is_array($value) && count($value) > 0;
}


/**
 * Check for valid date
 *
 * @param string $value
 * @return bool
 */
function is_date($value = '')
{
    $time = strtotime($value);

    return $time !== false;
}


/**
 * Check to see if the passed value is a string with a length.
 *
 * @param string $value
 * @return bool
 */
function is_string_ne($value)
{
    return is_string($value) && strlen($value) > 0 && (string)$value === $value;
}


/**
 * Check for valid decimal/float, requiring only 1 . and integers on either side of it
 *
 * @param float $value
 * @param bool|false $unsigned
 * @return bool
 */
function is_valid_decimal($value = 0.00, $unsigned = false)
{
    if (!is_numeric($value)) {
        return false;
    }

    $parts = explode('.', $value);
    if (count($parts) > 2) {
        return false;
    }

    return is_valid_int($parts[0], $unsigned) && is_valid_int($parts[1], true);
}


/**
 * Check to see if the passed value is an integer.
 *
 * @param int $value
 * @param bool $unsigned Do additional check for positive or negative.
 * @return bool
 */
function is_valid_int($value, $unsigned = false)
{
    $result = is_int($value) || (is_numeric($value) && (int)$value === $value + 0);

    if ($unsigned) {
        $result = $result >= 0;
    }

    return $result;
}


function lower_underscore($str = '')
{
    if (!is_string_ne($str)) {
        return '';
    }

    return strtolower(preg_replace('/[A-Z]/', '_$1', $str));
}


/**
 * Parse a HTML file containing placeholder tags with the placeholders provided.
 * Placeholders look like [[+placeholder_name]]
 *
 * @author Mike Rodarte
 * @param string $file Complete file path of HTML file
 * @param array $placeholders Array of key/value pairs to use for search and replace.
 * @return string HTML file with replacements made (might still have lingering [[+placeholder]] tags)
 */
function parse_html($file = '', $placeholders = array())
{
    if (!is_string($file) || strlen($file) > 0 || is_file($file) || file_exists($file)) {
        // file is invalid
        return '';
    }

    $file_str = file_get_contents($file);

    if (is_array($placeholders) && count($placeholders) > 0) {
        foreach ($placeholders as $key => $val) {
            $file_str = str_replace('[[+' . $key . ']]', $val, $file_str);
        }
    }

    return $file_str;
}


/**
 * Display or return an array (or converted object) as a string.
 *
 * @param mixed $array Array to display
 * @param int $tab_mult Current tab multiplier
 * @param bool|true $display Display or return string
 * @return string
 * @uses get_string()
 */
function print_array($array, $tab_mult = 0, $display = true)
{
    if (is_object($array)) {
        $array = (array)$array;
    }

    $output = '';
    $br = "<br />\n";

    if (is_array($array)) {
        $tab = str_repeat('&nbsp;', 4);

        $mult = $tab_mult;

        // go through each value of each array and display in formatted way
        foreach ($array as $key => $val) {
            if (is_object($val) || is_array($val)) {
                // display the tab
                $output .= str_repeat($tab, $tab_mult);
                $output .= $key . ' => Array (' . $br;
                if (!empty($val)) {
                    $tab_mult++;
                    // call this function and provide it with the current tab count
                    $output .= print_array($val, $tab_mult, false);
                }
                $output .= str_repeat($tab, $mult);
                $output .= ')' . $br;
                // reset the tab to avoid cumulative indents
                $tab_mult = 0;
            } else {
                // display tab originally set in the function
                $output .= str_repeat($tab, $mult);
                $output .= $key . ' => ' . get_string($val) . $br;
            }
        }
    } else if (!is_array($array)) {
        $output .= '-' . $array . '-';
        $output .= $br;
    }

    if ($display) {
        echo $output;

        return true;
    } else {
        return $output;
    }
}


/**
 * Replace all spaces and multiple underscores with single underscore.
 *
 * @param string $value
 * @return mixed|string
 */
function space_to_underscore($value = '')
{
    if (!is_string_ne($value)) {
        return $value;
    }

    return preg_replace('/_+/', '_', str_replace(' ', '_', $value));
}


/**
 * Replace underscore letter with capital letter (even if letter is already capital).
 *
 * @param string $str
 * @return string
 */
function upper_camel($str = '')
{
    if (!is_string_ne($str)) {
        return '';
    }

    $parts = explode('_', strtolower($str));
    $first = array_shift($parts);
    $parts = array_map('ucwords', $parts);

    return $first . implode('', $parts);
}

