<?php
/**
 * Log messages to a log file in a log directory with the designated log level. Only messages with a level greater than
 * or equal to the set log level are written to the file, discarding all others.
 * Use the LOG_LEVEL_* constants instead of their numeric values.
 * 
 * @author Mike Rodarte
 * @version 1.13
 */

/**
 * mtsapps namespace
 */
namespace mtsapps;


/**
 * Class Log
 *
 * @package mtsapps
 */
class Log
{
    /**
     * @const int Debugging messages that would not normally be wanted
     */
    const LOG_LEVEL_DEBUG = 0;

    /**
     * @const int Generic system information
     */
    const LOG_LEVEL_SYSTEM_INFORMATION = 1;

    /**
     * @const int A message that an user would want to see at some point
     */
    const LOG_LEVEL_USER = 2;

    /**
     * @const int An error occurred in standard processing, but is not an exception
     */
    const LOG_LEVEL_WARNING = 3;

    /**
     * @const int An exception occurred during standard processing and needs to be addressed
     */
    const LOG_LEVEL_ERROR = 4;

    /**
     * @const int Logging is disabled
     */
    const LOG_LEVEL_OFF = 256;

    /**
     * @var string Date stamp format
     */
    private $date_format = 'Y-m-d H:i:s';

    /**
     * @var string Default file name
     */
    private $default_file = 'log.log';

    /**
     * @var string file name (including path)
     */
    private $file = '';

    /**
     * @var null File handle
     */
    private $handle = null;

    /**
     * @var string Directory for log file
     */
    private $log_directory = '';

    /**
     * @var int Current log level
     */
    private $log_level = 0;

    /**
     * @var array Messages that have been logged
     * Consider disabling this functionality to save memory
     */
    private $messages = array();

    /**
     * @var string Separator between parts of the log entry
     */
    private $separator = ' - ';


    /**
     * Log constructor.
     *
     * @param array $params
     */
    public function __construct($params = array())
    {
        // set defaults
        $this->log_level = $this::LOG_LEVEL_ERROR;
        if (defined('LOG_DIR')) {
            $this->logDirectory(LOG_DIR);
        }

        // set values from parameters
        if (Helpers::is_array_ne($params)) {
            if (array_key_exists('log_directory', $params)) {
                $this->logDirectory($params['log_directory']);
            }
            if (array_key_exists('log_level', $params) && Helpers::is_valid_int($params['log_level'])) {
                $this->logLevel($params['log_level']);
            }

            if (array_key_exists('file', $params) && Helpers::is_string_ne($params['file'])) {
                $this->file($params['file']);
            }

            if (array_key_exists('date_format', $params) && Helpers::is_string_ne($params['date_format'])) {
                $this->date_format = $params['date_format'];
            }

            if (array_key_exists('separator', $params) && Helpers::is_string_ne($params['separator'])) {
                $this->separator = $params['separator'];
            }
        }
    }


    /**
     * Destructor
     * Close the file handle.
     */
    public function __destruct()
    {
        if ($this->handle !== null && $this->handle !== false && is_resource($this->handle)) {
            fclose($this->handle);
        }
    }


    /**
     * Write the exception data to the log.
     *
     * @param \Exception $ex
     * @param mixed $extra
     * @return bool|int
     * @uses \Exception::getMessage()
     * @uses \Exception::getFile()
     * @uses \Exception::getLine()
     * @uses \Exception::getCode()
     * @uses \Exception::getTraceAsString()
     * @uses Log::write()
     */
    public function exception(\Exception $ex, $extra = null)
    {
        // input validation
        if (!is_object($ex)) {
            return false;
        }

        // prepare variables
        $message = $ex->getMessage();
        $file = $ex->getFile();
        $line = $ex->getLine();
        $code = $ex->getCode();
        $trace = $ex->getTraceAsString();
        $type = get_class($ex);

        // prepare error message
        $error = $type . ' error ' . $code . ' in ' . $file . ' on line ' . $line . PHP_EOL;
        $error .= $message . PHP_EOL;

        if (defined('DISPLAY_ERRORS') && DISPLAY_ERRORS) {
            Helpers::display_now($error . "<br />\n");
            if (!empty($extra)) {
                Helpers::display_now($extra);
            }
        }

        // add the trace if the log level is appropriate
        if ($this->log_level >= Log::LOG_LEVEL_WARNING) {
            $error .= $trace . PHP_EOL;
        }

        // write the exception error message to the log
        return $this->write($error, Log::LOG_LEVEL_ERROR, $extra);
    }


    /**
     * Set and/or get the file name.
     *
     * [@param] string $file_name File name to set
     * @return string
     */
    public function file()
    {
        // set the file name if the parameter is valid
        $args = func_get_args();
        if (count($args) > 0 && Helpers::is_string_ne($args[0])) {
            $this->file = $args[0];
        } elseif (!Helpers::is_string_ne($this->file)) {
            $this->file = $this->default_file;
        }

        // close the file handle
        if ($this->handle !== null && $this->handle !== false) {
            fclose($this->handle);
        }
        // open the file handle
        $this->handle = fopen($this->log_directory . $this->file, 'a');

        // return the file name
        return $this->file;
    }


    /**
     * Get the last message logged
     * 
     * @return mixed
     */
    public function last()
    {
        return end($this->messages);
    }


    /**
     * Set and/or get the log directory.
     *
     * @return string
     */
    public function logDirectory()
    {
        $args = func_get_args();
        if (count($args) > 0) {
            if (Helpers::is_string_ne($args[0]) && is_dir(realpath($args[0]))) {
                $this->log_directory = realpath($args[0]) . DIRECTORY_SEPARATOR;
            } else {
                $this->write('invalid log directory', Log::LOG_LEVEL_WARNING, $args[0]);
            }
        }

        return $this->log_directory;
    }


    /**
     * Set and/or get the log level.
     *
     * [@param] int $log_level Optional log level value
     * @return int
     */
    public function logLevel()
    {
        // set the log level if the parameter is valid
        $args = func_get_args();
        if (count($args) === 1) {
            if (Helpers::is_valid_int($args[0]) && $this->validateLevel($args[0])) {
                $this->log_level = $args[0];
            } else {
                $this->write('invalid log level {' . $args[0] . '}', Log::LOG_LEVEL_WARNING);
            }
        }

        // return the log level
        return $this->log_level;
    }


    /**
     * Validate the level to set.
     *
     * @param $level
     * @return bool
     */
    public static function validateLevel($level)
    {
        $reflection = new \ReflectionClass(__CLASS__);
        $constants = $reflection->getConstants();

        return in_array($level, $constants);
    }


    /**
     * Write the message to the log file if the log level is appropriate.
     *
     * @param string $message
     * @param int $log_level
     * @return bool|int
     * @uses Log::$log_level
     * @uses Log::$file
     * @uses Log::$date_format
     * @uses Log::$separator
     * @uses get_string()
     */
    public function write($message = '', $log_level = Log::LOG_LEVEL_SYSTEM_INFORMATION)
    {
        // input validation
        if (!Helpers::is_string_ne($message)) {
            return false;
        }

        if (func_num_args() === 3) {
            $value = func_get_arg(2);
            // check for value and convert it to a string for writing
            if (isset($value)) {
                // convert $value to string
                $value = Helpers::get_string($value);

                // remove HTML line breaks from log message
                $value = str_replace(array("<br />\n", '<br />', '&nbsp;'), array("\n", "\n", ' '), $value);

                $message = $message . ': ' . $value;
            }
        }

        if ($this->log_level <= $log_level && Helpers::is_string_ne($this->file)) {
            // get call string from backtrace
            $call_string = Helpers::get_call_string();

            // build the message
            $message = date($this->date_format) . $this->separator . $call_string . $this->separator . $message;
            $this->messages[] = $message;

            // write the message to the provided log file
            //return file_put_contents($this->log_directory . $this->file, $message . PHP_EOL, FILE_APPEND);
            return fwrite($this->handle, $message . PHP_EOL);
        }

        return true;
    }
}