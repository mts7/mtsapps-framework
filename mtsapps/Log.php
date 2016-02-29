<?php
/**
 * @author Mike Rodarte
 * @version 1.09
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
     * @const int
     */
    const LOG_LEVEL_DEBUG = 0;

    /**
     * @const int
     */
    const LOG_LEVEL_SYSTEM_INFORMATION = 1;

    /**
     * @const int
     */
    const LOG_LEVEL_USER = 2;

    /**
     * @const int
     */
    const LOG_LEVEL_WARNING = 3;

    /**
     * @const int
     */
    const LOG_LEVEL_ERROR = 4;

    /**
     * @const int
     */
    const LOG_LEVEL_OFF = 256;

    /**
     * @var string
     */
    private $date_format = 'Y-m-d H:i:s';

    /**
     * @var string
     */
    private $default_file = 'log.log';

    /**
     * @var string file name (including path)
     */
    private $file = '';

    /**
     * @var string
     */
    private $log_directory = '';

    /**
     * @var int
     */
    private $log_level = 0;

    /**
     * @var array
     */
    private $messages = array();

    /**
     * @var string
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
     * Write the exception data to the log.
     *
     * @param \Exception $ex
     * @return bool|int
     * @uses \Exception::getMessage()
     * @uses \Exception::getFile()
     * @uses \Exception::getLine()
     * @uses \Exception::getCode()
     * @uses \Exception::getTraceAsString()
     * @uses Log::write()
     */
    public function exception(\Exception $ex)
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

        // add the trace if the log level is appropriate
        if ($this->log_level >= Log::LOG_LEVEL_WARNING) {
            $error .= $trace . PHP_EOL;
        }

        // write the exception error message to the log
        return $this->write($error, Log::LOG_LEVEL_ERROR);
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
        } else {
            $this->file = $this->default_file;
        }

        // return the file name
        return $this->file;
    }


    /**
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
                $this->log_directory = realpath($args[0]) . '/';
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
        if (count($args) > 0) {
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
            $message = date($this->date_format) . $this->separator . $message;
            $this->messages[] = $message;

            // write the message to the provided log file
            return file_put_contents($this->log_directory . $this->file, $message . PHP_EOL, FILE_APPEND);
        }

        return true;
    }
}