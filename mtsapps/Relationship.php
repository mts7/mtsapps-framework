<?php
/**
 * @author Mike Rodarte
 * @version 1.00
 */
namespace mtsapps;

use \mtsapps\Helpers;
use \mtsapps\Log;

/**
 * Class Relationship
 *
 * @package mtsapps
 */
class Relationship extends Generator
{
    /**
     * @var array
     */
    private $relationship_array = array();

    /**
     * Relationship constructor.
     *
     * @param array $options
     */
    public function __construct($options = array())
    {
        // set defaults
        $defaults = array(
            'directory' => PHP_DIR,
            'file_name' => 'gen_relationships.php',
            'log_level' => Log::LOG_LEVEL_WARNING,
            'log_directory' => LOG_DIR,
            'log_file' => 'db_' . date('Y-m-d') . '.log',
            'user' => '',
            'pass' => '',
            'host' => 'localhost',
            'db' => 'INFORMATION_SCHEMA',
            'dump_file' => '',
        );

        // enforce the use of INFORMATION_SCHEMA as the database name
        if (array_key_exists('db', $options)) {
            unset($options['db']);
        }

        // blindly merge the options with the defaults and let the parent class handle the result
        $params = array_merge($defaults, $options);

        parent::__construct($params);
    }


    /**
     * Build the PHP array and write it to a file.
     * 
     * @param string $database
     * @param string $table
     * @return bool
     * @uses Relationship::getRows()
     * @uses Relationship::buildArray()
     * @uses Helpers::print_array()
     * @uses Relationship::buildTopContent()
     * @uses Relationship::write()
     */
    public function build($database = '', $table = '')
    {
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        // input validation
        if (!Helpers::is_string_ne($database)) {
            $this->Log->write('database is invalid', Log::LOG_LEVEL_WARNING, $database);

            return false;
        }

        $result = $this->getQuery($database, $table);
        if (!Helpers::is_array_ne($result)) {
            $this->Log->write('could not get SQL for ' . $table . ' in ' . $database, Log::LOG_LEVEL_WARNING);

            return false;
        }
        list($sql, $params) = $result;

        $set_iterator = $this->setIterator($sql, $params);
        if (!$set_iterator) {
            $this->Log->write('could not set iterator with SQL and params', Log::LOG_LEVEL_WARNING, $result);
        }

        $built = $this->generate();
        if (!$built) {
            $this->Log->write('could not build an array with rows', Log::LOG_LEVEL_WARNING);

            return false;
        }

        $this->php = Helpers::print_array($this->relationship_array, 1, false, false, true);
        if (!Helpers::is_string_ne($this->php)) {
            $this->Log->write('could not convert PHP array to string array', Log::LOG_LEVEL_WARNING, $this->relationship_array);

            return false;
        }
        $this->php = '$GLOBALS[\'table_relationships\'] = array(' . PHP_EOL . $this->php . ');' . PHP_EOL;

        $size = $this->buildTopContent(__CLASS__);
        if (!Helpers::is_valid_int($size) || $size < 1) {
            $this->Log->write('could not write top content', Log::LOG_LEVEL_WARNING);

            return false;
        }

        $size = $this->write();
        if (!Helpers::is_valid_int($size) || $size < 1) {
            $this->Log->write('could not write php', Log::LOG_LEVEL_WARNING, $this->php);

            return false;
        }

        $this->Log->write('successfully created and wrote file', Log::LOG_LEVEL_USER, $this->file_path);

        require $this->file_path;

        return true;
    }


    /**
     * Create a PHP array in the proper format with the relationship values.
     * 
     * @return bool
     */
    protected function generate() {
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        // input validation
        if (!($this->iterator instanceof DbIterator)) {
            $this->Log->write('iterator is not valid', Log::LOG_LEVEL_WARNING, Helpers::get_type_size($this->iterator));

            return false;
        }

        $array = array();
        foreach($this->iterator as $row) {
            if (!Helpers::is_string_ne($row['child_table'])) {
                continue;
            }
            if (!array_key_exists($row['child_table'], $array)) {
                $array[$row['child_table']] = array();
            }
            $array[$row['child_table']][$row['child_field']] = array(
                'table' => $row['parent_table'],
                'field' => $row['parent_field'],
            );
        }

        $this->relationship_array = $array;

        return Helpers::is_array_ne($this->relationship_array);
    }


    /**
     * Get query with optional table as a filter.
     * 
     * @param string $database
     * @param string $table
     * @return bool|array
     */
    protected function getQuery($database = '', $table = '')
    {
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        // input validation
        if (!Helpers::is_string_ne($database)) {
            $this->Log->write('database is invalid', Log::LOG_LEVEL_WARNING, $database);

            return false;
        }

        $sql = 'SELECT 
  SUBSTRING(t.FOR_NAME, LENGTH(@db) + 2) AS "child_table"
  , c.FOR_COL_NAME AS "child_field"
  , SUBSTRING(t.REF_NAME, LENGTH(@db) + 2) AS "parent_table"
  , c.REF_COL_NAME AS "parent_field"
  FROM INNODB_SYS_FOREIGN_COLS c
    JOIN INNODB_SYS_FOREIGN t ON (c.ID = t.ID)
  WHERE SUBSTRING(t.ID, 1, LENGTH(@db)) = @db';

        $params = [];
        if (Helpers::is_string_ne($table)) {
            $sql .= PHP_EOL . '    AND t.FOR_NAME = ?';
            $params[] = $database . '/' . $table;
        }

        // use quoted database name instead of variable
        $sql = str_replace('@db', $this->quote($database, 'string'), $sql);

        return array($sql, $params);
    }
}