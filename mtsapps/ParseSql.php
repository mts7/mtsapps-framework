<?php
/**
 * Parse a SQL string into lines of executable SQL to use in a loop.
 *
 * @author Mike Rodarte
 * @version 1.01
 *
 * @see http://stackoverflow.com/questions/147821/loading-sql-files-from-within-php#answer-6607547
 */

/** mtsapps namespace */
namespace mtsapps;


use mtsapps\Helpers;
use mtsapps\Log;

/**
 * Class ParseSql
 *
 * @package mtsapps
 * @todo Create a way to add directory, file, and log stuff outside of __construct().
 */
class ParseSql
{
    /**
     * @var string Directory
     */
    private $dir = __DIR__;

    /**
     * @var string File name
     */
    private $file = '';

    /**
     * @var Log
     */
    private $Log;

    /**
     * @var string Log directory
     */
    private $log_directory = LOG_DIR;

    /**
     * @var mixed|string Log file name
     */
    private $log_file = '';

    /**
     * @var int|mixed Log level
     */
    private $log_level = Log::LOG_LEVEL_WARNING;

    /**
     * @var string SQL loaded from file
     */
    private $sql = '';

    /**
     * @var array Lines of SQL, split by delimiter
     */
    private $sql_lines = array();


    /**
     * ParseSql constructor.
     *
     * @param array $params
     */
    public function __construct($params = array())
    {
        $this->log_file = 'parse_sql_' . date('Y-m-d') . '.log';

        // handle parameters
        if (Helpers::is_array_ne($params)) {
            // get directory
            if (array_key_exists('dir', $params) && Helpers::is_string_ne($params['dir'])) {
                $dir = realpath($params['dir']);
                if (Helpers::is_string_ne($dir)) {
                    $this->dir = $dir . DIRECTORY_SEPARATOR;
                }
            }

            // get file name (and maybe directory name)
            if (array_key_exists('file', $params) && Helpers::is_string_ne($params['file'])) {
                if (file_exists($this->dir . $params['file'])) {
                    // directory has been set properly and file exists
                    $this->file = $params['file'];
                } elseif (file_exists($params['file'])) {
                    // file is a full path, so split it to directory and file name
                    $this->dir = dirname($params['file']) . DIRECTORY_SEPARATOR;
                    $this->file = basename($params['file']);
                } elseif (file_exists(__DIR__ . DIRECTORY_SEPARATOR . $params['file'])) {
                    // file exists in this directory, so set directory and file name
                    $this->dir = __DIR__ . DIRECTORY_SEPARATOR;
                    $this->file = $params['file'];
                }
            }

            if (array_key_exists('log_level', $params) && Helpers::is_valid_int($params['log_level'])) {
                $this->log_level = $params['log_level'];
            }

            if (array_key_exists('log_directory', $params) && Helpers::is_string_ne($params['log_directory'])) {
                if (is_dir($params['log_directory'])) {
                    $this->log_directory = $params['log_directory'];
                } else {
                    $this->log_directory = LOG_DIR;
                }
            }

            if (array_key_exists('log_file', $params) && Helpers::is_string_ne($params['log_file'])) {
                $this->log_file = $params['log_file'];
            }
        }

        // set up Log
        $this->Log = new Log([
            'file' => $this->log_file,
            'log_level' => $this->log_level,
            'log_directory' => $this->log_directory,
        ]);
        // verify log file was set properly
        $log_file = $this->Log->file();
        if ($log_file !== $this->log_file) {
            $this->Log->write('could not set file properly', Log::LOG_LEVEL_WARNING);
        }

        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);
    }


    /**
     * Destructor
     */
    public function __destruct()
    {
        $this->Log->__destruct();
        $this->Log = null;
        unset($this->Log);
    }


    /**
     * Remove comments, remove remarks, split SQL into executable lines, and return the lines as an array.
     *
     * @param string $delimiter
     * @return array|bool
     */
    public function process($delimiter = ';')
    {
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        $read = $this->readFile();
        if (!$read) {
            $this->Log->write('Could not read file ' . $this->dir . $this->file, Log::LOG_LEVEL_WARNING);

            return false;
        }

        $valid = $this->removeComments();
        if (!$valid) {
            $this->Log->write('SQL string is empty after removing comments', Log::LOG_LEVEL_WARNING, Helpers::get_type_size($this->sql));

            return false;
        }

        $valid = $this->removeRemarks();
        if (!$valid) {
            $this->Log->write('SQL string is empty after removing remarks', Log::LOG_LEVEL_WARNING, Helpers::get_type_size($this->sql));

            return false;
        }

        $valid = $this->splitDelimiter();
        if (!$valid) {
            $this->Log->write('SQL could not be split into lines', Log::LOG_LEVEL_WARNING, Helpers::get_type_size($this->sql));
        }

        return $this->sql_lines;
    }


    /**
     * Read a file and store its contents.
     *
     * @return bool
     * @uses ParseSql::$sql
     */
    private function readFile()
    {
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        // input validation
        if (!Helpers::is_string_ne($this->file)) {
            $this->Log->write('file name provided is invalid', Log::LOG_LEVEL_WARNING, $this->file);

            return false;
        }

        if (!is_file($this->dir . $this->file)) {
            $this->Log->write('file name does not exist in directory', Log::LOG_LEVEL_WARNING, $this->dir . $this->file);

            return false;
        }

        // file exists, so read it into a variable, keeping it in memory
        $sql = @fread(@fopen($this->dir . $this->file, 'r'), @filesize($this->dir . $this->file));
        if ($sql === false) {
            $this->Log->write('could not read file', Log::LOG_LEVEL_WARNING);

            return false;
        }

        $this->sql = trim($sql);

        return strlen($this->sql) > 0;
    }


    /**
     * Remove multi-line comments from stored SQL string.
     *
     * @return bool
     * @uses ParseSql::$sql
     */
    private function removeComments()
    {
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        // input validation
        if (!Helpers::is_string_ne($this->sql)) {
            $this->Log->write('SQL needs content before removing comments', Log::LOG_LEVEL_WARNING);

            return false;
        }

        // get lines from the SQL
        $lines = explode(PHP_EOL, $this->sql);

        $in_comment = false;
        $this->sql = '';

        // loop through lines to rewrite $this->sql to be without multi-line comments
        foreach ($lines as $line) {
            if (preg_match('/^\/\*/', preg_quote($line))) {
                $in_comment = true;
            }

            if (!$in_comment) {
                $this->sql .= $line . PHP_EOL;
            }

            if (preg_match('/\*\/$/', preg_quote($line))) {
                $in_comment = false;
            }
        }

        unset($lines);

        return strlen($this->sql) > 0;
    }


    /**
     * Remove starting remarks from lines in SQL string.
     *
     * @return bool
     * @uses ParseSql::$sql
     * @todo Leave remarks in multi-line string literals
     */
    private function removeRemarks()
    {
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        // input validation
        if (!Helpers::is_string_ne($this->sql)) {
            $this->Log->write('SQL needs content before removing remarks', Log::LOG_LEVEL_WARNING);

            return false;
        }

        $lines = explode(PHP_EOL, $this->sql);

        $this->sql = '';
        $line_count = count($lines);
        foreach ($lines as $i => &$line) {
            if (($i != ($line_count - 1)) || (strlen($line) > 0)) {
                // TODO: determine a better way to handle multiple starting remarks
                if ((isset($line[0]) && $line[0] != '#') || substr($line, 0, 3) !== '-- ') {
                    $this->sql .= $line . PHP_EOL;
                } else {
                    $this->sql .= PHP_EOL;
                }

                $line = '';
            }
        }

        return strlen($this->sql) > 0;
    }


    /**
     * Split SQL string into individual lines of SQL by the provided delimiter.
     *
     * @param string $delimiter
     * @return bool
     * @todo Add handling for changing delimiters during SQL execution
     * @todo @see http://stackoverflow.com/questions/147821/loading-sql-files-from-within-php#answer-149456
     */
    private function split($delimiter = ';')
    {
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        // input validation
        if (!Helpers::is_string_ne($this->sql)) {
            $this->Log->write('SQL needs content before processing', Log::LOG_LEVEL_WARNING);

            return false;
        }

        if (!Helpers::is_string_ne($delimiter)) {
            $this->Log->write('delimiter provided is not a string', Log::LOG_LEVEL_WARNING, Helpers::get_type_size($delimiter));

            return false;
        }

        // split string into possible SQL statements
        $tokens = explode($delimiter, $this->sql);

        // save memory since we have the SQL in tokens
        $this->sql = '';

        $this->sql_lines = array();
        $matches = array();
        $token_count = count($tokens);
        for ($i = 0; $i < $token_count; $i++) {
            $token = $tokens[$i];
            if (($i != ($token_count - 1)) || (strlen($token) > 0)) {
                // total number of single quotes in the token
                $total_quotes = preg_match_all("/'/", $token, $matches);
                // count escaped quotes (preceded by odd number of backslashes)
                $escaped_quotes = preg_match_all("/(?<!\\\\)(\\\\\\\\)*\\\\'/", $token, $matches);
                $unescaped_quotes = $total_quotes - $escaped_quotes;

                // if unescaped quotes is even, the delimiter did not occur in the token
                if (($unescaped_quotes % 2) == 0) {
                    // this is a complete SQL statement
                    $this->sql_lines[] = $token;
                    $tokens[$i] = '';
                } else {
                    // this is an incomplete SQL statement
                    $temp = $token . $delimiter;
                    $tokens[$i] = '';

                    $complete_stmt = false;

                    for ($j = $i + 1; (!$complete_stmt && ($j < $token_count)); $j++) {
                        // total number of single quotes in the token
                        $total_quotes = preg_match_all("/'/", $tokens[$j], $matches);
                        // count escaped quotes (preceded by odd number of backslashes)
                        $escaped_quotes = preg_match_all("/(?<!\\\\)(\\\\\\\\)*\\\\'/", $tokens[$j], $matches);
                        $unescaped_quotes = $total_quotes - $escaped_quotes;

                        if (($unescaped_quotes % 2) == 1) {
                            // odd number of quotes matches this statement with the previous statement
                            $this->sql_lines[] = $temp . $tokens[$j];

                            $tokens[$j] = '';
                            $temp = '';

                            // exit the loop
                            $complete_stmt = true;

                            // update outer loop iteration
                            $i = $j;
                        } else {
                            // even number of quotes indicates an incomplete statement
                            $temp .= $tokens[$j] . $delimiter;
                            $tokens[$j] = '';
                        }
                    } // end for inner loop
                }
            }
        } // end for outer loop

        return count($this->sql) > 0;
    }


    /**
     * @return array|bool
     * @todo Add a check for inside string literal
     */
    private function splitDelimiter()
    {
        $delimiters = substr_count(strtoupper($this->sql), 'DELIMITER');
        if ($delimiters === 0) {
            return false;
        }

        $delimiter = ';';
        $words = explode(' ', $this->sql);
        $this->sql_lines = array();
        $current_statement = '';
        $num_words = count($words);

        // go through string word by word, checking for delimiter for statement, consuming minimal memory
        for ($i = 0; $i < $num_words; $i++) {
            $word = $words[$i];

            if (!Helpers::is_string_ne(trim($word))) {
                continue;
            }

            if ($word === $delimiter) {
                $this->sql_lines[] = trim($current_statement);
                $current_statement = '';
                continue;
            } elseif (strtoupper(trim($word)) === 'DELIMITER') {
                $delimiter = $words[$i + 1];
                $i++;
                continue;
            } else {
                $current_statement .= trim($word) . ' ';
            }

            if ($i >= $num_words && Helpers::is_string_ne($current_statement)) {
                $this->sql_lines[] = trim($current_statement);
                break;
            }
        }

        return $this->sql_lines;
    }
}