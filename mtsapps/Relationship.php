<?php
/**
 * Build foreign key relationships and store them as a global variable in a PHP file.
 *
 * @author Mike Rodarte
 * @version 1.01
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
     * @var bool Set to true if running on MySQL 5.6 or later. This uses INNODB_SYS_FOREIGN and INNODB_SYS_FOREIGN_COLS
     * The older way of executing the same query takes much longer than using the new tables.
     */
    private $mysql_56 = false;

    /**
     * @var array Array containing relationships, indexed by table name
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

        // set the MySQL version is at least 5.6 property
        if (array_key_exists('mysql56', $options)) {
            $this->mysql_56 = !!$options['mysql56'];
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

        // get SQL and parameters from the table
        $result = $this->getQuery($database, $table);
        if (!Helpers::is_array_ne($result)) {
            $this->Log->write('could not get SQL for ' . $table . ' in ' . $database, Log::LOG_LEVEL_WARNING);

            return false;
        }
        list($sql, $params) = $result;

        // set the iterator from the SQL
        $set_iterator = $this->setIterator($sql, $params);
        if (!$set_iterator) {
            $this->Log->write('could not set iterator with SQL and params', Log::LOG_LEVEL_WARNING, $result);
        }

        // generate the results
        $built = $this->generate();
        if (!$built) {
            $this->Log->write('could not build an array with rows', Log::LOG_LEVEL_WARNING);

            return false;
        }

        // get PHP array as string
        $this->php = Helpers::print_array($this->relationship_array, 1, false, false, true);
        if (!Helpers::is_string_ne($this->php)) {
            $this->Log->write('could not convert PHP array to string array', Log::LOG_LEVEL_WARNING, $this->relationship_array);

            return false;
        }
        // write the declaration
        $this->php = '$GLOBALS[\'table_relationships\'] = array(' . PHP_EOL . $this->php . ');' . PHP_EOL;

        // create the top PHP content for the file
        $size = $this->buildTopContent(__CLASS__);
        if (!Helpers::is_valid_int($size) || $size < 1) {
            $this->Log->write('could not write top content', Log::LOG_LEVEL_WARNING);

            return false;
        }

        // write the PHP string to the file
        $size = $this->write();
        if (!Helpers::is_valid_int($size) || $size < 1) {
            $this->Log->write('could not write php', Log::LOG_LEVEL_WARNING, $this->php);

            return false;
        }

        $this->Log->write('successfully created and wrote file', Log::LOG_LEVEL_USER, $this->file_path);

        require_once $this->file_path;

        return true;
    }


    /**
     * Create a PHP array in the proper format with the relationship values.
     *
     * @return bool
     * @uses Generator::$iterator
     * @uses Relationship::$relationship_array
     */
    protected function generate()
    {
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        // input validation
        if (!($this->iterator instanceof DbIterator)) {
            $this->Log->write('iterator is not valid', Log::LOG_LEVEL_WARNING, Helpers::get_type_size($this->iterator));

            return false;
        }

        $array = array();
        foreach ($this->iterator as $row) {
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

        if ($this->mysql_56) {
            // If the server is running MySQL 5.6 or later, use the first query because it executes extremely quickly.
            // build SELECT statement for foreign key relationships for MySQL 5.6+
            $sql = 'SELECT 
  SUBSTRING(t.FOR_NAME, LENGTH(@db) + 2) AS "child_table"
  , c.FOR_COL_NAME AS "child_field"
  , SUBSTRING(t.REF_NAME, LENGTH(@db) + 2) AS "parent_table"
  , c.REF_COL_NAME AS "parent_field"
  FROM INNODB_SYS_FOREIGN_COLS c
    JOIN INNODB_SYS_FOREIGN t ON (c.ID = t.ID)
  WHERE SUBSTRING(t.ID, 1, LENGTH(@db)) = @db';
            $table_field = 't.FOR_NAME';
        } else {
            // build SELECT statement for foreign key relationships for < MySQL 5.6
            // This query takes about 4.5 seconds to fully execute.
            $sql = 'SELECT k.TABLE_NAME AS "child_table"
  , k.COLUMN_NAME AS "child_field"
  , k.REFERENCED_TABLE_NAME AS "parent_table"
  , k.REFERENCED_COLUMN_NAME AS "parent_field"
  FROM KEY_COLUMN_USAGE k
    JOIN TABLE_CONSTRAINTS c ON (k.`CONSTRAINT_SCHEMA` = c.`CONSTRAINT_SCHEMA` AND k.CONSTRAINT_NAME = c.CONSTRAINT_NAME)
  WHERE k.CONSTRAINT_SCHEMA = @db
    AND c.CONSTRAINT_TYPE = \'FOREIGN KEY\'
  ORDER BY child_table, child_field';
            $table_field = 'k.TABLE_NAME';
        }

        $params = [];
        if (Helpers::is_string_ne($table)) {
            $sql .= PHP_EOL . '    AND ' . $table_field . ' = ?';
            $params[] = $database . '/' . $table;
        }

        // use quoted database name instead of variable
        $sql = str_replace('@db', $this->quote($database, 'string'), $sql);

        return array($sql, $params);
    }
}