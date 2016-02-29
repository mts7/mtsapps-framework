<?php
/**
 * Helper functions
 *
 * @author Mike Rodarte
 * @version 1.02
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
     * @var array Character sets (used for password-type applications)
     * There were issues handling the back slash character, so it was removed from the symbols, though it is still used
     * to calculate max_char_entropy.
     */
    public static $char_sets = array(
        'num' => array(
            'pattern' => '/([0-9]+)/',
            'chars' => 10,
        ),
        'symbols' => array(
            'pattern' => '/([`~!@#\$%\^&\*\(\)\-_=\+\[\{\]\}\|;:\'\",<\.>\/\?]+)/',
            'chars' => 31,
        ),
        'hex_letter_lower' => array(
            'pattern' => '/([a-f]+)/',
            'chars' => 6,
        ),
        'hex_letter_upper' => array(
            'pattern' => '/([A-F]+)/',
            'chars' => 6,
        ),
        'hex_letter' => array(
            'pattern' => '/([a-fA-F]+)/',
            'chars' => 12,
        ),
        'hex_letter_lower_num' => array(
            'pattern' => '/([a-f0-9]+)/',
            'chars' => 16,
        ),
        'hex_letter_upper_num' => array(
            'pattern' => '/([A-F0-9]+)/',
            'chars' => 16,
        ),
        'hex_letter_num' => array(
            'pattern' => '/([a-fA-F0-9]+)/',
            'chars' => 22,
        ),
        'hex_letter_num_symbol' => array(
            'pattern' => '/([a-fA-F0-9`~!@#\$%\^&\*\(\)\-_=\+\[\{\]\}\|;:\'\",<\.>\/\?]+)/',
            'chars' => 53,
        ),
        'alpha_lower' => array(
            'pattern' => '/([a-z]+)/',
            'chars' => 26,
        ),
        'alpha_upper' => array(
            'pattern' => '/([A-Z]+)/',
            'chars' => 26,
        ),
        'alpha' => array(
            'pattern' => '/([a-zA-Z]+)/',
            'chars' => 52,
        ),
        'alpha_lower_num' => array(
            'pattern' => '/([a-z0-9]+)/',
            'chars' => 36,
        ),
        'alpha_upper_num' => array(
            'pattern' => '/([A-Z0-9]+)/',
            'chars' => 36,
        ),
        'alpha_num' => array(
            'pattern' => '/([a-zA-Z0-9]+)/',
            'chars' => 62,
        ),
        'alpha_lower_num_symbol' => array(
            'pattern' => '/([a-z0-9`~!@#\$%\^&\*\(\)\-_=\+\[\{\]\}\|;:\'\",<\.>\/\?]+)/',
            'chars' => 67,
        ),
        'alpha_upper_num_symbol' => array(
            'pattern' => '/([A-Z0-9`~!@#\$%\^&\*\(\)\-_=\+\[\{\]\}\|;:\'\",<\.>\/\?]+)/',
            'chars' => 67,
        ),
        'alpha_num_symbol' => array(
            'pattern' => '/([a-zA-Z0-9`~!@#\$%\^&\*\(\)\-_=\+\[\{\]\}\|;:\'\",<\.>\/\?]+)/',
            'chars' => 93,
        ),
    );

    /**
     * The maximum entropy value for 94 characters (like alpha_num_symbol, but with back slash)
     * @var float
     */
    public static $max_char_entropy = 6.5545888516776;

    /**
     * Flatten a multi-dimensional array
     *
     * @param array $array Multi-dimensional array to flatten
     * @return array
     * @see http://stackoverflow.com/questions/1319903/how-to-flatten-a-multidimensional-array#answer-1320259
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
     * @param string $backup_path Directory to store the backup file
     * @return int file size of written dump file
     */
    public static function backup_database($host = '', $user = '', $pass = '', $db = '', $backup_path = '')
    {
        // use this directory if one is not provided
        if (!self::is_string_ne($backup_path) || !is_dir($backup_path)) {
            $backup_path = __DIR__;
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
     * Calculate the entropy bits of the string itself and the string according to its character set.
     *
     * @param string $string
     * @return array
     */
    public static function entropy($string = '')
    {
        // nats
        $self = 0;
        $size = strlen($string);
        foreach (count_chars($string, 1) as $v) {
            $p = $v / $size;
            $self -= $p * log($p) / log(2);
        }
        $char = $self;
        $self = round($char * $size);

        // combinations based on character sets
        $bits = 0;
        $charset = self::get_charset($string);
        $array = self::$char_sets[$charset];
        if (1 === preg_match($array['pattern'], $string, $matches)) {
            if (isset($matches[1]) && $string === $matches[1]) {
                $bits = round(log(pow($array['chars'], strlen($string)), 2));
            }
        }

        return array(
            'char' => $char,
            'string' => $self,
            'charset' => $bits,
        );
    }


    /**
     * Determine the character set of the string based on predetermined patterns.
     *
     * @param $string
     * @return int|string
     */
    public static function get_charset($string)
    {
        if (!self::is_string_ne($string)) {
            return 'empty';
        }

        $charset = '';
        foreach (self::$char_sets as $label => $array) {
            if (1 === preg_match($array['pattern'], $string, $matches)) {
                if (isset($matches[1]) && $string === $matches[1]) {
                    $charset = $label;
                    break;
                }
            }
        }

        return $charset;
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
     * @todo parse inner tags first
     */
    public static function parse_html($file = '', $placeholders = array())
    {
        if (!is_string($file) || strlen($file) > 0 || is_file($file) || file_exists($file)) {
            // file is invalid
            return '';
        }

        $file_str = file_get_contents($file);

        if (self::is_array_ne($placeholders)) {
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
        } elseif (!is_array($array)) {
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