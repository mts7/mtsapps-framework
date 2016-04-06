<?php
/**
 * Generate constants PHP file outside of namespace directories, based on values from the constant table.
 *
 * @author Mike Rodarte
 * @version 1.06
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

        // initialize database class
        parent::__construct($params);

        $this->Log->write('done constructing ' . __METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);
    }


    /**
     * Build constants from values in database and store them in a PHP file.
     *
     * @return bool
     * @uses Constants::getConstantList()
     * @uses Constants::generate()
     * @uses Constants::write()
     */
    public function build()
    {
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        $this->Log->write('getting constant list', Log::LOG_LEVEL_USER);
        $result = $this->getQuery();
        if (!Helpers::is_array_ne($result)) {
            $this->Log->write('could not get constant list', Log::LOG_LEVEL_WARNING);

            return false;
        }
        list($sql, $params) = $result;

        $set_iterator = $this->setIterator($sql, $params);
        if (!$set_iterator) {
            $this->Log->write('could not set iterator with SQL and params', Log::LOG_LEVEL_WARNING, $result);
        }

        $this->Log->write('have constant list', Log::LOG_LEVEL_USER);

        $size = $this->buildTopContent(__CLASS__);
        if (!Helpers::is_valid_int($size) || $size < 1) {
            $this->Log->write('could not write top content', Log::LOG_LEVEL_WARNING, __CLASS__);

            return false;
        }

        // write after each call to generate to decrease memory consumption on server
        foreach ($this->iterator as $i => $row) {
            $this->generate($row);
            $bytes = $this->write();

            if ($bytes === false) {
                $this->Log->write($i . ': could not write PHP to file', Log::LOG_LEVEL_WARNING);
            }
        }

        require $this->file_path;

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
            $this->Log->write('array is invalid', Log::LOG_LEVEL_WARNING, $array);
            
            return false;
        }
        if (!array_key_exists('table_name', $array) || !array_key_exists('name_field', $array) || !array_key_exists('value_field', $array) || !array_key_exists('type', $array)) {
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

        // build PHP string
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

        // append string to global string
        $this->php .= $php;

        return strlen($php);
    }


    /**
     * Get list of tables and fields to use for generating constants.
     *
     * @return bool|array
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