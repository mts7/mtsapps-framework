<?php
/**
 * Generate constants PHP file outside of namespace directories, based on values from the constant table.
 *
 * @author Mike Rodarte
 * @version 1.08
 */

/**
 * mtsapps namespace
 */
namespace mtsapps;

/**
 * Class Constants
 *
 * @package mtsapps
 */
class Constants extends Generator
{
    /**
     * @var string $constant_table Table containing the list of constant tables and fields
     */
    private $constant_table = 'constant_build_list';


    /**
     * Constants constructor.
     *
     * @param array $params
     * @uses Constants::setFileName()
     */
    public function __construct($params = array())
    {
        // set defaults
        $defaults = array(
            'directory' => PHP_DIR,
            'file_name' => 'gen_constants.php',
            'log_level' => Log::LOG_LEVEL_WARNING,
            'log_directory' => LOG_DIR,
            'log_file' => 'db_' . date('Y-m-d') . '.log',
            'user' => '',
            'pass' => '',
            'host' => 'localhost',
            'db' => '',
            'dump_file' => '',
        );

        if (Helpers::is_array_ne($params)) {
            if (array_key_exists('file_name', $params)) {
                $this->setFileName($params['file_name']);
            }

            if (array_key_exists('table_name', $params) && Helpers::is_string_ne($params['table_name'])) {
                $this->constant_table = $params['table_name'];
                unset($params['table_name']);
            }
        } else {
            $this->setFileName($params['file_name']);
        }
        // set up parameters
        $params = array_merge($defaults, $params);

        // initialize generator class
        parent::__construct($params);

        $this->Log->write('done constructing ' . __METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);
    }


    /**
     * Build constants from values in database and store them in a PHP file. Require the file at the end of execution.
     *
     * @return bool
     * @uses Constants::getQuery()
     * @uses Generator::setIterator()
     * @uses Generator::buildTopContent()
     * @uses Constants::generate()
     * @uses Constants::write()
     */
    public function build()
    {
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        // get constant list
        $this->Log->write('getting constant list', Log::LOG_LEVEL_USER);
        $result = $this->getQuery();
        if (!Helpers::is_array_ne($result)) {
            $this->Log->write('could not get constant list', Log::LOG_LEVEL_WARNING);

            return false;
        }
        list($sql, $params) = $result;

        // set the iterator from the SQL and parameters
        $set_iterator = $this->setIterator($sql, $params);
        if (!$set_iterator) {
            $this->Log->write('could not set iterator with SQL and params', Log::LOG_LEVEL_WARNING, $result);
        }

        $this->Log->write('have constant list', Log::LOG_LEVEL_USER);

        // build the top content of the PHP file
        $size = $this->buildTopContent(__CLASS__);
        if (!Helpers::is_valid_int($size) || $size < 1) {
            $this->Log->write('could not write top content', Log::LOG_LEVEL_WARNING, __CLASS__);

            return false;
        }

        // write after each call to generate to decrease memory consumption on server
        foreach ($this->iterator as $i => $row) {
            $generated = $this->generate($row);
            if ($generated === false) {
                $this->Log->write($i . ': Failed to generate code from row', Log::LOG_LEVEL_WARNING);
                continue;
            }

            $bytes = $this->write();
            if ($bytes === false) {
                $this->Log->write($i . ': could not write PHP to file', Log::LOG_LEVEL_WARNING);
            }
        }

        // require the generated file to provide constants to the rest of the application
        require_once $this->file_path;

        return true;
    }


    /**
     * Generate PHP string for this table and field.
     *
     * @param array $array Row of results from constant list
     * @return bool|int
     * @uses Db::query()
     * @uses Db::quote()
     */
    protected function generate($array = array())
    {
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        // input validation
        if (!Helpers::is_array_ne($array)) {
            $this->Log->write('array is invalid', Log::LOG_LEVEL_WARNING, Helpers::get_call_string());

            return false;
        }
        // these fields need to be present in the array
        $fields = array(
            'table_name',
            'name_field',
            'value_field',
            'type',
        );
        $valid = true;
        // check for the existence of each field in the array and break if one of them does not exist
        foreach ($fields as $field) {
            if (!array_key_exists($field, $array)) {
                $valid = false;
                break;
            }
        }
        if (!$valid) {
            $this->Log->write('input invalid', Log::LOG_LEVEL_WARNING);

            return false;
        }

        // assign parameters to variables
        $table = $array['table_name'];
        $field = $array['name_field'];
        $value_field = $array['value_field'];
        $type = $array['type'];
        $prefix = array_key_exists('prefix', $array) ? $array['prefix'] : $table;

        // build SELECT query for field and value
        $sql = 'SELECT ' . $field . ', ' . $value_field . PHP_EOL;
        $sql .= '  FROM ' . $table . PHP_EOL;
        $this->Log->write('generate SQL', Log::LOG_LEVEL_USER, $sql);

        // get rows from table
        $rows = $this->query($sql, array(), 'iterator');

        if (!($rows instanceof DbIterator)) {
            $this->Log->write('could not find rows from query', Log::LOG_LEVEL_WARNING);

            return false;
        }
        $this->Log->write('found rows for generate query', Log::LOG_LEVEL_USER);

        // build PHP string with comments to indicate table and field used in generation
        $php = PHP_EOL . '/**' . PHP_EOL;
        $php .= ' * ' . $table . '.' . $field . PHP_EOL;
        $php .= ' */' . PHP_EOL;
        foreach ($rows as $row) {
            if ($row === null || !array_key_exists($field, $row)) {
                continue;
            }
            // prepare constant name (upper case, underscores instead of spaces, no multiple underscores together)
            $val = strtoupper(Helpers::space_to_underscore($prefix . '_' . $row[$field]));
            // add define statement to string
            $php .= 'define(\'' . $val . '\', ' . $this->quote($row[$value_field], $type) . ');' . PHP_EOL;
        }
        $php .= '// END ' . $table . '.' . $field . PHP_EOL . PHP_EOL;
        $this->Log->write('built PHP string with ' . strlen($php) . ' characters', Log::LOG_LEVEL_USER);

        if (!Helpers::is_string_ne($php)) {
            $this->Log->write('There was an issue building the PHP.', Log::LOG_LEVEL_WARNING, Helpers::get_type_size($php));

            return false;
        }

        // append string to global string
        $this->php .= $php;

        return strlen($php);
    }


    /**
     * Get list of tables and fields to use for generating constants.
     *
     * @return array
     */
    protected function getQuery()
    {
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        $sql = 'SELECT table_name, name_field, value_field, prefix, type' . PHP_EOL;
        $sql .= '  FROM ' . $this->constant_table;
        $this->Log->write('sql', Log::LOG_LEVEL_USER, $sql);

        return array($sql, array());
    }
}