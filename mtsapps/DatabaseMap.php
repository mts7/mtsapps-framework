<?php
/**
 * @author Mike Rodarte
 * @version 1.03
 */

/** mtsapps namespace */
namespace mtsapps;


/**
 * Class DatabaseMap
 *
 * @package mtsapps
 *
 * Object assumes the from and to tables are in the same database.
 * Create a map and copy rows from one set of tables and fields to another.
 * This requires the implementation of DatabaseMap::buildToValues() in the child class.
 */
abstract class DatabaseMap extends Db
{
    /**
     * @var array $current_row Current row being processed (to gain access to other elements during process)
     */
    protected $current_row = array();

    /** @var bool $debug Commit when false, rollback when true */
    protected $debug = false;

    /** @var array $from_tables Array indexed by table name with fields as values */
    protected $from_tables = array();

    /** @var array $from_values Array indexed by table.field with original values */
    protected $from_values = array();

    /**
     * @var Log
     */
    protected $Log = null;

    /** @var array $map Map of table.field to table.field */
    protected $map = array();

    /** @var string $table_separator Used to separate table and field names */
    protected $table_separator = '.';

    /** @var array $to_tables Array indexed by table name with fields as values */
    protected $to_tables = array();

    /** @var array $to_values Array indexed by table.field with original or modified values */
    protected $to_values = array();


    /**
     * DatabaseMap constructor.
     * Calls parent constructor, setting database settings
     *
     * @param array $params
     */
    public function __construct($params = array())
    {
        if (is_array_ne($params) && array_key_exists('log_level', $params)) {
            $log_level = $params['log_level'];
        } else {
            $log_level = Log::LOG_LEVEL_WARNING;
        }
        $file = 'db_' . date('Y-m-d') . '.log';
        $this->Log = new Log([
            'file' => $file,
            'log_directory' => LOG_DIR,
            'log_level' => $log_level,
        ]);
        $log_file = $this->Log->file();
        if ($log_file !== $file) {
            $this->Log->write('could not set file properly', Log::LOG_LEVEL_WARNING);
        }

        $this->Log->write('DatabaseMap::__construct()', Log::LOG_LEVEL_SYSTEM_INFORMATION);

        parent::__construct($params);

        $this->Log->write('DatabaseMap constructed', Log::LOG_LEVEL_SYSTEM_INFORMATION);
    }


    /**
     * Add map array to existing map.
     *
     * @param array $map
     * @return bool
     */
    public function addMap($map = array())
    {
        $this->Log->write('DatabaseMap::addMap()', Log::LOG_LEVEL_SYSTEM_INFORMATION);

        // input validation
        if (!is_array_ne($map)) {
            $this->Log->write('map is not an array', Log::LOG_LEVEL_WARNING);

            return false;
        }

        // merge contents of map parameter with existing map array
        $this->map = array_merge($this->map, $map);
        // remove any duplicate key/value pairs from the map
        $this->map = array_unique($this->map);

        $this->Log->write('merged map', Log::LOG_LEVEL_WARNING);

        return true;
    }


    /**
     * Add individual mappings to map array.
     *
     * @param string $from_table From table name
     * @param string $from_field From table field name
     * @param string $to_table To table name
     * @param string $to_field To table field name
     * @return bool
     * @uses DatabaseMap::$map
     * @uses DatabaseMap::$table_separator
     */
    public function addMapItem($from_table = '', $from_field = '', $to_table = '', $to_field = '')
    {
        $this->Log->write('DatabaseMap::addMapItem()', Log::LOG_LEVEL_SYSTEM_INFORMATION);

        if (!is_string_ne($from_table) || !is_string_ne($from_field) || !is_string_ne($to_table) || !is_string_ne($to_field)) {
            $this->Log->write('tables OR fields are invalid', Log::LOG_LEVEL_WARNING);

            return false;
        }

        // prepare the keys
        $from_key = $from_table . $this->table_separator . $from_field;
        $to_key = $to_table . $this->table_separator . $to_field;
        $this->Log->write('set keys', Log::LOG_LEVEL_USER);

        // assign the to key to the from key
        $this->map[$from_key] = $to_key;
        $this->Log->write('assigned key mappings', Log::LOG_LEVEL_USER);

        return true;
    }


    /**
     * Set debug value (used to commit or rollback transactions)
     *
     * @param bool $debug Set debug value
     */
    public function debug($debug = false)
    {
        $this->Log->write('DatabaseMap::debug()', Log::LOG_LEVEL_SYSTEM_INFORMATION);

        $this->debug = !!$debug;
    }


    /**
     * Get values, manipulate results, and insert values
     *
     * @return bool
     * @uses DatabaseMap::getTables()
     * @uses DatabaseMap::$from_tables
     * @uses Db::query()
     * @uses DatabaseMap::buildToValues()
     * @uses DatabaseMap::$to_values
     * @uses DatabaseMap::insertValues()
     * @uses Db::executeQueue()
     */
    public function process()
    {
        $this->Log->write('DatabaseMap::process()', Log::LOG_LEVEL_SYSTEM_INFORMATION);

        // populate $from_tables and $to_tables
        $this->Log->write('populating tables', Log::LOG_LEVEL_USER);
        $this->getTables();

        if (!is_array_ne($this->from_tables)) {
            $this->Log->write('from tables was not set properly', Log::LOG_LEVEL_WARNING);

            return false;
        }
        $this->Log->write('tables populated', Log::LOG_LEVEL_USER);

        // begin loop of map
        foreach ($this->from_tables as $table => $fields) {
            // get values from table
            $sql = $this->buildSelect($table, $fields);
            $this->from_values[$table] = $this->query($sql, null, 'iterator');
            // manipulate values
            $this->buildToValues($table);
        } // end loop

        if (!is_array_ne($this->to_values)) {
            $this->Log->write('could not build to values properly', Log::LOG_LEVEL_WARNING);

            return false;
        }
        $this->Log->write('finished building to values', Log::LOG_LEVEL_USER);

        $this->sortToValuesTables();

        $this->Log->write('inserting values for all to values after sorting', Log::LOG_LEVEL_USER);
        foreach ($this->to_values as $table => $rows) {
            $this->insertValues($table);
        }

        $this->Log->write('executing queue', Log::LOG_LEVEL_USER);
        $this->executeQueue();

        return true;
    }


    /**
     * Reset data variables (in preparation of running through the map process again)
     *
     * @uses DatabaseMap::$current_row
     * @uses DatabaseMap::$from_tables
     * @uses DatabaseMap::$from_values
     * @uses DatabaseMap::$map
     * @uses DatabaseMap::$to_tables
     * @uses DatabaseMap::$to_values
     */
    public function reset()
    {
        $this->Log->write('DatabaseMap::reset()', Log::LOG_LEVEL_SYSTEM_INFORMATION);

        $this->current_row = array();
        $this->from_tables = array();
        $this->from_values = array();
        $this->map = array();
        $this->to_tables = array();
        $this->to_values = array();
        $this->Log->write('reset members', Log::LOG_LEVEL_USER);
    }


    /**
     * Key sort to_values by table name according to foreign key relationships.
     *
     * @return bool
     */
    abstract protected function sortToValuesTables();


    /**
     * Transition from value to to value (which might be a straight copy).
     *
     * @param string $from_key From key (table.field) determines how to transition the value
     * @param mixed $value Value from database to transition
     * @return bool|null
     */
    abstract protected function transitionValue($from_key = '', $value = null);


    /**
     * Build select query based on a table name and field list.
     *
     * @param string $table Table name
     * @param array $fields fields in table
     * @return bool|string
     */
    private function buildSelect($table = '', $fields = array())
    {
        $this->Log->write('DatabaseMap::buildSelect()', Log::LOG_LEVEL_SYSTEM_INFORMATION);

        if (!is_string_ne($table) || !is_array_ne($fields)) {
            $this->Log->write('table OR fields is not valid', Log::LOG_LEVEL_WARNING);

            return false;
        }

        $sql = 'SELECT ' . implode(', ', $fields) . ' FROM ' . $table;

        return $sql;
    }


    /**
     * @param string $from_table From table name
     * @return bool All fields and values were transitioned
     * @uses DatabaseMap::$from_values
     * @uses DatabaseMap::$table_separator
     * @uses DatabaseMap::$map
     * @uses DatabaseMap::$to_values
     * @uses DatabaseMap::transitionValue()
     * @uses DatabaseMap::getToTable()
     */
    private function buildToValues($from_table = '')
    {
        $this->Log->write('DatabaseMap::buildToValues()', Log::LOG_LEVEL_SYSTEM_INFORMATION);

        // input validation
        if (!is_array_ne($this->from_values[$from_table])) {
            $this->Log->write('from values does not contain records for from table', Log::LOG_LEVEL_WARNING);

            return false;
        }

        // track how many keys are created
        $from_keys = 0;
        $to_keys = 0;

        // loop through values and rows to transition values
        foreach ($this->from_values[$from_table] as $row) {
            $this->current_row = $row;
            $to_row = array();
            $to_table = '';

            // loop through row to get fields and values set up properly
            foreach ($row as $field => $value) {
                // create key as it exists in map
                $from_key = $from_table . $this->table_separator . $field;
                $from_keys++;
                if (!array_key_exists($from_key, $this->map)) {
                    $this->Log->write('could not find ' . $from_key . ' in map', Log::LOG_LEVEL_WARNING);
                    continue;
                }

                // transition the value to what the to table needs
                $to_value = $this->transitionValue($from_key, $value);

                if (false === $to_value) {
                    // from key must not be a string
                    $this->Log->write('skipping ' . $from_key, Log::LOG_LEVEL_WARNING);
                    continue;
                }

                // get to table and field from from key
                $to_keys++;
                list($to_table, $to_field) = $this->getToTable($from_key);

                // assign value to row[field]
                $to_row[$to_field] = $to_value;
            } // end foreach row

            // add the new to row to the to values array, indexed by to table
            if (is_string_ne($to_table) && is_array_ne($to_row)) {
                $this->to_values[$to_table][] = $to_row;
            }
        } // end foreach from_values[from_table]

        $result = $from_keys === $to_keys;
        $this->Log->write('set all mappings', Log::LOG_LEVEL_USER, $result);

        // this will be true if there were no problems and all fields and values were transitioned
        return $result;
    }


    /**
     * Parse values in map and add to corresponding table array.
     *
     * @return bool
     * @uses DatabaseMap::$map
     * @uses DatabaseMap::$table_separator
     * @uses DatabaseMap::$from_tables
     * @uses DatabaseMap::$to_tables
     */
    private function getTables()
    {
        $this->Log->write('DatabaseMap::getTables()', Log::LOG_LEVEL_SYSTEM_INFORMATION);

        // input validation
        if (empty($this->map)) {
            $this->Log->write('map is empty', Log::LOG_LEVEL_WARNING);

            return false;
        }

        foreach ($this->map as $from => $to) {
            list($from_table, $from_field) = explode($this->table_separator, $from);
            list($to_table, $to_field) = explode($this->table_separator, $to);

            if (is_string_ne($from_table)) {
                if (!array_key_exists($from_table, $this->from_tables)) {
                    $this->from_tables[$from_table] = array();
                }
                if (is_string_ne($from_field) && !array_search($from_field, $this->from_tables[$from_table])) {
                    $this->from_tables[$from_table][] = $from_field;
                }
            }

            if (is_string_ne($to_table)) {
                if (!array_key_exists($to_table, $this->to_tables)) {
                    $this->to_tables[$to_table] = array();
                }
                if (is_string_ne($to_field) && !array_search($to_field, $this->to_tables[$to_table])) {
                    $this->to_tables[$to_table][] = $to_field;
                }
            }
        }

        return true;
    }


    /**
     * Get the to_table and to_field from the map with the from_key.
     *
     * @param string $from_key
     * @return array|bool
     */
    private function getToTable($from_key = '')
    {
        $this->Log->write('DatabaseMap::getToTable()', Log::LOG_LEVEL_SYSTEM_INFORMATION);

        // input validation
        if (!array_key_exists($from_key, $this->map)) {
            $this->Log->write('from key ' . $from_key . ' does not exist in map', Log::LOG_LEVEL_WARNING);

            return false;
        }

        return explode($this->table_separator, $this->map[$from_key]);
    }


    /**
     * Insert values into to table.
     *
     * @param string $table
     * @return bool|string
     * @uses DatabaseMap::$to_values
     * @uses Db::buildInsert()
     * @uses Db::begin()
     * @uses Db::query()
     * @uses Db::commit()
     */
    private function insertValues($table = '')
    {
        $this->Log->write('DatabaseMap::insertValues()', Log::LOG_LEVEL_SYSTEM_INFORMATION);

        // input validation
        if (!is_string_ne($table) || !is_array_ne($this->to_values[$table])) {
            $this->Log->write('table is invalid or to values does not contain table ' . $table, Log::LOG_LEVEL_WARNING);

            return false;
        }

        // get values for table
        $values = $this->to_values[$table];
        // make sure add date is set
        $values['add_date'] = date('Y-m-d H:i:s');
        // build insert query and parameters
        list($sql, $parameters) = $this->buildInsert($table, $values, true);

        $this->Log->write('trying insert query in transaction', Log::LOG_LEVEL_USER);
        $this->begin();
        $this->query($sql, $parameters, 'insert');

        if ($this->debug) {
            $this->Log->write('rolling back transaction, due to debug', Log::LOG_LEVEL_USER);
            $this->rollback();
            ob_start();
            echo $sql . PHP_EOL;;
            print_r($parameters);
            $output = ob_get_flush();
        } else {
            $this->Log->write('committing transaction', Log::LOG_LEVEL_USER);
            $this->commit();
            $output = true;
        }

        return $output;
    }
}