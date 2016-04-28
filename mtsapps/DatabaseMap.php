<?php
/**
 * Map data from one database to new tables in the same database, requiring certain methods to be defined in the child
 * class. Use Db::export() and Db::import() to move new data to a new database.
 *
 * @author Mike Rodarte
 * @version 1.12
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
 * The implementation of transitionValue is extremely important for the mapping process.
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

    /** @var array $map Map of table.field to table.field */
    protected $map = array();

    /** @var array $missing_keys Report of values that should be inserted, but are missing from parent tables */
    protected $missing_keys = array();

    /** @var array $order_by Array indexed by table name with field to use for order */
    protected $order_by = array();

    /** @var array $table_field_values Array indexed by table name, then field name, containing values that exist */
    protected $table_field_values = array();

    /** @var string $table_separator Used to separate table and field names */
    protected $table_separator = '.';

    /** @var array $to_tables Array indexed by table name with fields as values */
    protected $to_tables = array();

    /** @var array $to_values Array indexed by table.field with original or modified values */
    protected $to_values = array();

    /** @var array $unique_fields Array indexed by table with field names as values */
    protected $unique_fields = array();


    /**
     * DatabaseMap constructor.
     * Calls parent constructor, setting database settings
     *
     * @param array $params
     */
    public function __construct($params = array())
    {
        // ensure there is a log level to use, defaulting to WARNING
        if (!array_key_exists('log_level', $params)) {
            $params['log_level'] = Log::LOG_LEVEL_WARNING;
        }

        // set debug value
        if (array_key_exists('debug', $params)) {
            $this->debug = !!$params['debug'];
        }

        parent::__construct($params);

        $this->Log->write('done constructing ' . __METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);
    }


    /**
     * Add map array to existing map by merging them together.
     *
     * @param array $map
     * @return bool
     */
    public function addMap($map = array())
    {
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        // input validation
        if (!Helpers::is_array_ne($map)) {
            $this->Log->write('map is not an array', Log::LOG_LEVEL_WARNING);

            return false;
        }

        // merge contents of map parameter with existing map array
        $this->map = array_merge($this->map, $map);

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
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        // input validation
        $variables = array(
            'from_table',
            'from_field',
            'to_table',
            'to_field',
        );
        $valid = true;
        foreach ($variables as $variable) {
            if (!Helpers::is_string_ne($$variable)) {
                $valid = false;
            }
        }
        if (!$valid) {
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
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        $this->debug = !!$debug;
    }


    /**
     * Get values, manipulate results, and insert values.
     * Most of the memory usage comes from $from_values query and buildToValues().
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
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        // populate $from_tables and $to_tables
        $this->Log->write('populating tables', Log::LOG_LEVEL_USER);
        $this->getTables();

        if (!Helpers::is_array_ne($this->from_tables)) {
            $this->Log->write('from tables was not set properly', Log::LOG_LEVEL_WARNING);

            return false;
        }
        $this->Log->write('tables populated', Log::LOG_LEVEL_USER);

        // begin loop of map
        foreach ($this->from_tables as $table => $fields) {
            $order = array_key_exists($table, $this->order_by) ? $this->order_by[$table] : null;
            // get values from table
            $sql = $this->buildSelect($table, $fields, $order);
            $this->from_values[$table] = $this->query($sql, null, 'array');
            // manipulate values
            $result = $this->buildToValues($table);
            if (!$result) {
                $this->Log->write('could not build to values from ' . $table, Log::LOG_LEVEL_WARNING);
            }
        } // end loop

        if (!Helpers::is_array_ne($this->to_values)) {
            $this->Log->write('could not build to values properly', Log::LOG_LEVEL_WARNING, $this->to_values);

            return false;
        }
        $this->Log->write('finished building to values', Log::LOG_LEVEL_USER);

        // sortToValuesTables() was not working, and the tables were ordered properly (since they were added in the
        // correct order).
        //$this->sortToValuesTables();

        $this->Log->write('inserting values for all to values after sorting', Log::LOG_LEVEL_USER);
        foreach ($this->to_values as $table => &$rows) {
            // map duplicate key values to the unique key values
            $this->fixDuplicateIds($table, $rows);
            // insert to values for this table
            $this->insertValues($table);
        }

        if ($this->debug && Helpers::is_array_ne($this->missing_keys)) {
            Helpers::display_now('Missing Foreign Key IDs');
            Helpers::display_now($this->missing_keys);
            Helpers::display_now('Key values');
            Helpers::display_now($this->table_field_values);
        }

        // some values are held in a queue because they depend on other values being in the database prior to insertion
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
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

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
     * @param string $order_by Field to use to order
     * @return bool|string
     */
    private function buildSelect($table = '', $fields = array(), $order_by = '')
    {
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        if (!Helpers::is_string_ne($table) || !Helpers::is_array_ne($fields)) {
            $this->Log->write('table OR fields is not valid', Log::LOG_LEVEL_WARNING);

            return false;
        }

        $sql = 'SELECT ' . implode(', ', $fields) . PHP_EOL . '  FROM ' . $table;
        if (Helpers::is_string_ne($order_by)) {
            $sql .= PHP_EOL . '  ORDER BY ' . $order_by;
        }

        return $sql;
    }


    /**
     * Build table field values from keys for use when checking if a key exists.
     *
     * @param array $relation from Relationship for specified table in caller
     * @return bool|int
     * @see DatabaseMap::fixDuplicateIds()
     * @see Relationship::build()
     */
    private function buildTableFieldValues($relation = array())
    {
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        // input validation
        if (!Helpers::is_array_ne($relation)) {
            $this->Log->write('invalid parameter provided', Log::LOG_LEVEL_WARNING, Helpers::get_type_size($relation));

            return false;
        }

        $values = 0;
        // check unique values
        foreach ($relation as $array) {
            if (!array_key_exists('table', $array)) {
                continue;
            }

            if (array_key_exists($array['table'], $this->to_values)) {
                $temp = $this->to_values[$array['table']];
                if (!Helpers::is_array_ne($temp)) {
                    $this->Log->write('could not get to values for ' . $array['table'], Log::LOG_LEVEL_WARNING);
                    continue;
                }

                $keys = array('primary', 'unique', 'foreign');
                $fields = array();
                foreach ($keys as $key) {
                    $stuff = $this->getKeyField($array['table'], $key);
                    if (Helpers::is_array_ne($stuff)) {
                        $fields = array_merge($fields, $stuff);
                    }
                }
            } else {
                $temp = $this->getKeyValues($array['table']);
                if ($temp === false) {
                    $this->Log->write('could not get key values for ' . $array['table'], Log::LOG_LEVEL_WARNING);
                    continue;
                }

                $first = current($temp);
                $fields = array_keys($first);
            }

            // make sure there is an array set up for this table
            if (!array_key_exists($array['table'], $this->table_field_values)) {
                $this->table_field_values[$array['table']] = array();
            }
            // make sure there is an array set up for each field in this table
            foreach ($fields as $f) {
                if (!array_key_exists($f, $this->table_field_values[$array['table']])) {
                    $this->table_field_values[$array['table']][$f] = array();
                }
            }

            // loop through each row and assign the values of the fields to the array
            foreach ($temp as $row) {
                foreach ($row as $f => $v) {
                    if (!in_array($f, $fields)) {
                        continue;
                    }
                    if (!in_array($v, $this->table_field_values[$array['table']][$f])) {
                        $this->table_field_values[$array['table']][$f][] = $v;
                        $values++;
                        sort($this->table_field_values[$array['table']][$f]);
                    }
                }
            }
        }

        return $values;
    }


    /**
     * Build to values from the from values with the transitionValue method for cleaning and additional mapping.
     *
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
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        // input validation
        if (!Helpers::is_array_ne($this->from_values[$from_table])) {
            $this->Log->write('from values does not contain records for from table', Log::LOG_LEVEL_WARNING);

            return false;
        }

        // track how many keys are created
        $from_keys = 0;
        $to_keys = 0;

        // loop through values and rows to transition values
        foreach ($this->from_values[$from_table] as $row) {
            if (!Helpers::is_array_ne($row)) {
                continue;
            }

            $this->current_row = $row;
            $to_row = array();
            $to_table = '';
            $skip = false;

            // loop through row to get fields and values set up properly
            foreach ($row as $field => $value) {
                // create key as it exists in map
                $from_key = $from_table . $this->table_separator . $field;
                $from_keys++;
                if (!array_key_exists($from_key, $this->map)) {
                    $this->Log->write('could not find ' . $from_key . ' in map', Log::LOG_LEVEL_USER);
                    $skip = true;
                    break;
                }

                // transition the value to what the to table needs
                $to_value = $this->transitionValue($from_key, $value);

                if (false === $to_value) {
                    // from key must be invalid
                    $this->Log->write('to_value is false; skipping ' . $from_key, Log::LOG_LEVEL_USER, $value);
                    $skip = true;
                    break;
                }

                // get to table and field from from key
                $to_keys++;
                $result = $this->getToTable($from_key);
                if (!$result) {
                    $this->Log->write('could not determine table from ' . $from_key, Log::LOG_LEVEL_WARNING);
                    $skip = true;
                    break;
                }
                if (Helpers::is_array_ne($result) && count($result) === 2) {
                    list($to_table, $to_field) = $result;

                    // assign value to row[field]
                    $to_row[$to_field] = $to_value;
                }
            } // end foreach row

            if ($skip) {
                $this->Log->write('skipping row', Log::LOG_LEVEL_USER, $this->current_row);
                $this->current_row = null;
                continue;
            }

            if (!array_key_exists('add_date', $to_row)) {
                $to_row['add_date'] = date('Y-m-d H:i:s');
            }

            // add the new to row to the to values array, indexed by to table
            if (Helpers::is_string_ne($to_table) && Helpers::is_array_ne($to_row)) {
                $this->to_values[$to_table][] = $to_row;
            } else {
                $this->Log->write('to_table is a ' . Helpers::get_type_size($to_table) . ' and to_row is a ' . Helpers::get_type_size($to_row), Log::LOG_LEVEL_WARNING);
            }

            // reset current_row so it is no longer accessible
            $this->current_row = null;
        } // end foreach from_values[from_table]

        $result = $from_keys === $to_keys;
        $this->Log->write('set all mappings', Log::LOG_LEVEL_USER, $result);

        // this will be true if there were no problems and all fields and values were transitioned
        return $to_keys > 0;
    }


    /**
     * Removes duplicate rows (based on UNIQUE key), tracks duplicated IDs, and maps duplicate IDs to unique IDs.
     *
     * @param string $table To table name
     * @param array $rows All rows from the table that need to be inserted
     * @return bool
     * @uses Db::getKeyField()
     * @uses $GLOBALS['table_relationships']
     * @uses DatabaseMap::$unique_fields
     * @uses Db::getIdFromName()
     * @see  Relationship::build()
     */
    private function fixDuplicateIds($table = '', &$rows = array())
    {
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        // input validation
        if (!Helpers::is_string_ne($table)) {
            $this->Log->write('table is invalid', Log::LOG_LEVEL_WARNING, $table);

            return false;
        }
        if (!Helpers::is_array_ne($rows)) {
            $this->Log->write('array is invalid', Log::LOG_LEVEL_WARNING, $rows);

            return false;
        }

        // get this table's unique fields
        $unique_fields = $this->getKeyField($table, 'unique');

        // prepare unique array based on $unique_fields (which might be empty)
        $unique = array();
        foreach ($unique_fields as $field) {
            $unique[$field] = array();
        }

        // get relationships for $table as child table
        if (array_key_exists($table, $GLOBALS['table_relationships'])) {
            $relation = $GLOBALS['table_relationships'][$table];

            $result = $this->buildTableFieldValues($relation);
            if ($result === false) {
                $this->Log->write('Could not get values from relations', Log::LOG_LEVEL_USER, $relation);
            } elseif ($result === 0) {
                $this->Log->write('Found no values from relations', Log::LOG_LEVEL_USER, $relation);
            } else {
                $this->Log->write('Found values', Log::LOG_LEVEL_USER, $result);
            }
        }

        // fix the rows
        foreach ($rows as $index => &$row) {
            // check for duplicate rows
            foreach ($unique_fields as $field) {
                $value = $row[$field];
                $existing_id = $this->getIdFromName($table, $value, $field);
                if (Helpers::is_valid_int($existing_id) && $existing_id > 0) {
                    // consider unique values already in the database table
                    $unique[$field][$value][] = $existing_id;
                    unset($rows[$index]);
                    continue 2;
                } elseif (array_key_exists($value, $unique[$field])) {
                    // remove rows that would duplicate unique values
                    $unique[$field][$value][] = $row['id'];
                    unset($rows[$index]);
                    continue 2;
                } else {
                    // this is the first instance of this value, so build a list for future mapping of IDs
                    $unique[$field][$value] = array();
                }
            }

            // check relationships in order to change parent IDs in rows to use actual parent ID values (after removing duplicates)
            if (isset($relation)) {
                // loop through relations to find parent tables for the child fields
                foreach ($relation as $field => $array) {
                    // make sure the child field exists in the row
                    if (!array_key_exists($field, $row)) {
                        continue;
                    }

                    // make sure this parent table exists in unique fields
                    $relation_table = $relation[$field]['table'];
                    if (!array_key_exists($relation_table, $this->unique_fields)) {
                        continue;
                    }

                    // check for the current row's parent ID in the unique_fields for the parent table
                    $current_id = $row[$field];
                    $use_value = null;
                    $use_field = null;
                    foreach ($this->unique_fields[$relation_table] as $unique_field => $values) {
                        foreach ($values as $value => $ids) {
                            // get the field and value used for this parent ID to use later
                            if (in_array($current_id, $ids)) {
                                $use_value = $value;
                                $use_field = $unique_field;
                                break 2;
                            }
                        }
                    }
                    // make sure there is a value to use to proceed
                    if ($use_value === null) {
                        continue;
                    }

                    // get the actual parent ID for the field and value that were found earlier
                    $use_id = $this->getIdFromName($relation[$field]['table'], $use_value, $use_field);
                    if (!Helpers::is_valid_int($use_id) || $use_id === 0) {
                        $this->Log->write('could not get ID from ' . $relation[$field]['table'] . ' for ' . $use_value, Log::LOG_LEVEL_WARNING);
                        continue;
                    }

                    // update the row to use the actual parent ID for the value (like find and replace)
                    $row[$field] = $use_id;

                    // check for existence of key field value before allowing it to be inserted (in order to avoid foreign
                    // key constraint violations) and generate a report of the rows that would have failed
                    if (!in_array($use_id, $this->table_field_values[$array['table']][$array['field']])) {
                        $this->missing_keys[$array['table']][$array['field']][] = $use_id;
                        $this->missing_keys[$array['table']][$array['field']] = array_unique($this->missing_keys[$array['table']][$array['field']]);
                        sort($this->missing_keys[$array['table']][$array['field']]);

                        //unset($rows[$index]);
                    }
                }
            }
        }

        // sort by either first field, id field, or specified field in parameters
        usort($rows, function ($a, $b) {
            if (array_key_exists('id', $a) && array_key_exists('id', $b)) {
                $id_field = 'id';
                $id_field_a = $id_field;
                $id_field_b = $id_field;
            } else {
                // get first key
                $keys = array_keys($a);
                $id_field_a = $keys[0];
                $keys = array_keys($b);
                $id_field_b = $keys[0];
            }

            return $a[$id_field_a] > $b[$id_field_b];
        });

        // save any values from $unique to the unique fields array to use in the loop above
        $this->unique_fields[$table] = $unique;

        return true;
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
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        // input validation
        if (empty($this->map)) {
            $this->Log->write('map is empty', Log::LOG_LEVEL_WARNING);

            return false;
        }

        $this->Log->write('looping through $this->map', Log::LOG_LEVEL_USER, $this->map);
        foreach ($this->map as $from => $to) {
            $this->Log->write('from', Log::LOG_LEVEL_USER, $from);
            $this->Log->write('to', Log::LOG_LEVEL_USER, $to);
            if ($to === 'skip') {
                $this->Log->write('skipping this one', Log::LOG_LEVEL_USER);
                continue;
            } elseif ($to === null || is_null($to) || !Helpers::is_string_ne($to)) {
                $this->Log->write('this is not a field to use', Log::LOG_LEVEL_WARNING, $from . ' => to ' . Helpers::get_type_size($to));
                continue;
            }
            list($from_table, $from_field) = explode($this->table_separator, $from);

            if (Helpers::is_string_ne($from_table)) {
                if (!array_key_exists($from_table, $this->from_tables)) {
                    $this->from_tables[$from_table] = array();
                }
                if (Helpers::is_string_ne($from_field) && !array_search($from_field, $this->from_tables[$from_table])) {
                    $this->from_tables[$from_table][] = $from_field;
                }
            }

            // populate is a value that is used to indicate the value is needed, but the field is not needed
            if ($to !== 'populate' && strstr($to, $this->table_separator)) {
                list($to_table, $to_field) = explode($this->table_separator, $to);
                if (Helpers::is_string_ne($to_table)) {
                    if (!array_key_exists($to_table, $this->to_tables)) {
                        $this->to_tables[$to_table] = array();
                    }
                    if (Helpers::is_string_ne($to_field) && !array_search($to_field, $this->to_tables[$to_table])) {
                        $this->to_tables[$to_table][] = $to_field;
                    }
                }
            }
        }
        $this->Log->write('finished looping through $this->map', Log::LOG_LEVEL_USER);

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
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        // input validation
        if (!array_key_exists($from_key, $this->map)) {
            $this->Log->write('from key ' . $from_key . ' does not exist in map', Log::LOG_LEVEL_WARNING);

            return false;
        }
        $this->Log->write('map[from_key]', Log::LOG_LEVEL_USER, $this->map[$from_key]);

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
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        // input validation
        if (!Helpers::is_string_ne($table) || !Helpers::is_array_ne($this->to_values[$table])) {
            $this->Log->write('table is invalid or to values does not contain table ' . $table, Log::LOG_LEVEL_WARNING);

            return false;
        }

        // get values for table
        $values = $this->to_values[$table];

        // build insert query and parameters
        $result = $this->buildInsert($table, $values, true, true);
        if ($result === false) {
            $this->Log->write('could not build insert for ' . $table, Log::LOG_LEVEL_WARNING);

            return false;
        }

        list($sql, $parameters) = $result;

        $this->Log->write('trying insert query in transaction', Log::LOG_LEVEL_USER);
        $this->begin();
        $this->query($sql, $parameters, 'insert');

        if ($this->debug) {
            $this->Log->write('rolling back transaction due to debug', Log::LOG_LEVEL_USER);
            $this->rollback();
            $output = $sql . PHP_EOL . Helpers::get_string($parameters);
        } else {
            $this->Log->write('committing transaction', Log::LOG_LEVEL_USER);
            $this->commit();
            $output = true;
        }

        return $output;
    }
}