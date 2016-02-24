<?php
/**
 * Helper functions
 *
 * @author Mike Rodarte
 * @version 1.01
 */
namespace mtsapps;

/**
 * Class Helpers
 *
 * @package mtsapps
 */
class Helpers
{
    /**
     * Flatten a multi-dimensional array
     * http://stackoverflow.com/questions/1319903/how-to-flatten-a-multidimensional-array#answer-1320259
     *
     * @param array $array Multi-dimensional array to flatten
     * @return array
     */
    public static function array_flatten($array = array())
    {
        if (!self::is_array_ne($array)) {
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
    public static function backup_database($host = '', $user = '', $pass = '', $db = '', $backup_path = '')
    {
        // use this directory if one is not provided
        if (!self::is_string_ne($backup_path) || !is_dir($backup_path)) {
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
     * Get bytes from shorthand code (like 8M or 1.5G)
     *
     * @param string $short
     * @return int|string
     */
    public static function bytes_from_shorthand($short = '')
    {
        if (!self::is_string_ne($short)) {
            return $short;
        }

        $sz = 'BKMGTP';
        $unit = substr($short, strlen($short) - 1, 1);
        $pos = strpos($sz, $unit);

        if ($pos === false) {
            return $short;
        }

        return (int)$short * pow(1024, $pos);
    }


    /**
     * Display an exception.
     *
     * @param \Exception $ex
     * @param bool|false $trace
     * @return string
     */
    public static function display_exception(\Exception $ex, $trace = false)
    {
        $br = "<br />\n";
        $output = get_class($ex) . $br;
        $output .= $ex->getMessage() . ' in ' . $ex->getFile() . ' on line ' . $ex->getLine() . $br;
        if ($trace) {
            $output .= 'Trace: ' . $br . self::print_array($ex->getTrace(), 0, false);
        }

        return $output;
    }


    /**
     * Return a string based on value type.
     *
     * @param mixed $value
     * @return string
     */
    public static function get_string($value)
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
                $result = self::print_array($value, 0, false);
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
    public static function is_array_ne($value)
    {
        return is_array($value) && count($value) > 0;
    }


    /**
     * Check for valid date
     *
     * @param string $value
     * @return bool
     */
    public static function is_date($value = '')
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
    public static function is_string_ne($value)
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
    public static function is_valid_decimal($value = 0.00, $unsigned = false)
    {
        if (!is_numeric($value)) {
            return false;
        }

        $parts = explode('.', $value);
        if (count($parts) > 2) {
            return false;
        }

        return self::is_valid_int($parts[0], $unsigned) && self::is_valid_int($parts[1], true);
    }


    /**
     * Check to see if the passed value is an integer.
     *
     * @param int $value
     * @param bool $unsigned Do additional check for positive or negative.
     * @return bool
     */
    public static function is_valid_int($value, $unsigned = false)
    {
        $result = is_int($value) || (is_numeric($value) && (int)$value === $value + 0);

        if ($unsigned) {
            $result = $result >= 0;
        }

        return $result;
    }


    /**
     * Convert a string to use lower case and underscores instead of spaces
     *
     * @param string $str
     * @return string
     */
    public static function lower_underscore($str = '')
    {
        if (!self::is_string_ne($str)) {
            return '';
        }

        return strtolower(preg_replace('/[A-Z]/', '_$1', $str));
    }


    /**
     * Send email with PHPMailer or mail.
     *
     * @param string|array $to Email address or email addresses
     * @param string $subject Subject
     * @param string $body_text Plain-text message to use
     * @param string|array $from Email address or array with name and email keys
     * @param array $attachments File names to attach
     * @param string $body_html HTML message to use
     * @return bool|string
     * @throws \vendor\PHPMailer\phpmailerException
     */
    public static function mts_mail($to = '', $subject = '', $body_text = '', $from = '', $attachments = array(), $body_html = '')
    {
        $mail = new \vendor\PHPMailer\PHPMailer();

        if (!is_object($mail)) {
            return mail($to, $subject, $body_text);
        }

        // to
        if (self::is_string_ne($to)) {
            $mail->addAddress($to, '');
        } elseif (self::is_array_ne($to)) {
            foreach ($to as $email) {
                $mail->addAddress($email, '');
            }
        } else {
            // no $to address

            return false;
        }

        // subject
        $mail->Subject = $subject;

        // body
        if (self::is_string_ne($body_html)) {
            $mail->msgHTML($body_html);
            if (self::is_string_ne($body_text)) {
                $mail->AltBody = $body_text;
            }
        } else {
            $mail->Body = $body_text;
        }

        // from
        if (self::is_array_ne($from)) {
            if (array_key_exists('name', $from)) {
                $from_name = $from['name'];
            } else {
                $from_name = '';
            }

            if (array_key_exists('email', $from)) {
                $from_email = $from['email'];
            } else {
                $from_email = '';
            }
        } elseif (self::is_string_ne($from)) {
            $from_email = $from;
            $from_name = '';
        } else {
            $from_email = 'server@' . $_SERVER['HTTP_HOST'];
            $from_name = 'Server Admin';
        }
        $mail->setFrom($from_email, $from_name);

        // attachments
        if (self::is_array_ne($attachments)) {
            foreach ($attachments as $attachment) {
                if (file_exists($attachment)) {
                    $mail->addAttachment($attachment);
                }
            }
        }

        // send
        if (!$mail->send()) {
            return $mail->ErrorInfo;
        } else {
            return true;
        }
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
    public static function parse_html($file = '', $placeholders = array())
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
     * @param bool|true $html Use &nbsp; and <br> instead of [space] and \n
     * @param bool|false $php Display as PHP (with quotes and commas)
     * @return string
     * @uses get_string()
     */
    public static function print_array($array, $tab_mult = 0, $display = true, $html = true, $php = false)
    {
        if (is_object($array)) {
            $array = (array)$array;
        }

        $output = '';
        $br = "<br />\n";
        $nl = "\n";

        if (is_array($array)) {
            $tab = str_repeat('&nbsp;', 4);

            $mult = $tab_mult;

            // go through each value of each array and display in formatted way
            foreach ($array as $key => $val) {
                if (is_object($val) || is_array($val)) {
                    // display the tab
                    $output .= str_repeat($tab, $tab_mult);
                    if (!!$php) {
                        $output .= "'$key'";
                    } else {
                        $output .= $key;
                    }
                    $output .= ' => array (';
                    if (!!$php) {
                        $output .= $nl;
                    } else {
                        $output .= $br;
                    }
                    if (!empty($val)) {
                        $tab_mult++;
                        // call this function and provide it with the current tab count
                        $output .= self::print_array($val, $tab_mult, false, !!$html, !!$php);
                    }
                    $output .= str_repeat($tab, $mult);
                    $output .= ')';
                    if (!!$php) {
                        $output .= ',' . $nl;
                    } else {
                        $output .= $br;
                    }
                    // reset the tab to avoid cumulative indents
                    $tab_mult = $mult;
                } else {
                    // display tab originally set in the function
                    $output .= str_repeat($tab, $mult);
                    if (!!$php) {
                        $output .= "'$key'";
                    } else {
                        $output .= $key;
                    }
                    $output .= ' => ';
                    if (!!$php) {
                        if (!is_numeric($val) && !is_bool($val)) {
                            $output .= '\'' . addslashes(self::get_string($val)) . '\',' . $nl;
                        } else {
                            $output .= self::get_string($val) . ',' . $nl;
                        }
                    } else {
                        $output .= self::get_string($val) . $br;
                    }
                }
            }
        } else if (!is_array($array)) {
            $output .= '-' . $array . '-';
            $output .= $br;
        }

        if (!$html) {
            $output = str_replace(array('&nbsp;', $br), array(' ', $nl), $output);
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
    public static function space_to_underscore($value = '')
    {
        if (!self::is_string_ne($value)) {
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
    public static function upper_camel($str = '')
    {
        if (!self::is_string_ne($str)) {
            return '';
        }

        $parts = explode('_', strtolower($str));
        $first = array_shift($parts);
        $parts = array_map('ucwords', $parts);

        return $first . implode('', $parts);
    }

}