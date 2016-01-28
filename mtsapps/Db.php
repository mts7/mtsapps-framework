<?php
/**
 * @author Mike Rodarte
 * @version 1.08
 */
namespace mtsapps;

/**
 * Class Db
 *
 * @package mtsapps
 */
class Db
{
    /**
     * @var \PDO
     */
    private $dbh = null;

    /**
     * @var string
     */
    private $dbname = '';

    /**
     * @var string
     */
    private $dump_file = '';

    /**
     * @var \PDOException
     */
    private $exception = null;

    /**
     * @var string
     */
    private $host = '';

    /**
     * @var Log
     */
    protected $Log = null;

    /**
     * @var int
     */
    protected $log_level = 0;

    /**
     * @var string
     */
    private $pass = '';

    /**
     * @var array
     */
    private $queue = array();

    /**
     * @var \PDOStatement
     */
    private $stmt = null;

    /**
     * @var array
     */
    private $table_structure = array();

    /**
     * @var bool
     */
    private $transaction_started = false;

    /**
     * @var string
     */
    private $user = '';


    /**
     * Db constructor.
     *
     * @param array $params
     */
    public function __construct($params = array())
    {
        $file = 'db_' . date('Y-m-d') . '.log';
        if (is_array_ne($params) && array_key_exists('log_level', $params)) {
            $log_level = $params['log_level'];
        } else {
            $log_level = Log::LOG_LEVEL_WARNING;
        }
        if (is_array_ne($params) && array_key_exists('log_directory', $params)) {
            $log_directory = $params['log_directory'];
        } else {
            $log_directory = LOG_DIR;
        }
        $this->Log = new Log([
            'file' => $file,
            'log_level' => $log_level,
            'log_directory' => $log_directory,
        ]);
        $log_file = $this->Log->file();
        if ($log_file !== $file) {
            $this->Log->write('could not set file properly', Log::LOG_LEVEL_WARNING);
        }

        $this->Log->write('Db::__construct()', Log::LOG_LEVEL_SYSTEM_INFORMATION);

        if (is_array_ne($params)) {
            if (array_key_exists('user', $params) && is_string($params['user']) && strlen($params['user']) > 0) {
                $this->user = $params['user'];
            }

            if (array_key_exists('pass', $params) && is_string($params['pass']) && strlen($params['pass']) > 0) {
                $this->pass = $params['pass'];
            }

            if (array_key_exists('host', $params) && is_string($params['host']) && strlen($params['host']) > 0) {
                $this->host = $params['host'];
            }

            if (array_key_exists('db', $params) && is_string($params['db']) && strlen($params['db']) > 0) {
                $this->dbname = $params['db'];
            }

            if (array_key_exists('dump_file', $params) && is_string_ne($params['dump_file'])) {
                $path = realpath(__DIR__ . $params['dump_file']);
                $this->dump_file = $path;
            } else {
                $now = date('Y-m-d');
                $this->dump_file = realpath(__DIR__ . '/dump_' . $now . '.sql');
            }
        }

        $this->connect();
        $this->Log->write('Db connected', Log::LOG_LEVEL_SYSTEM_INFORMATION);
    }


    /**
     * Db destructor.
     *
     * @uses Db::disconnect()
     */
    public function __destruct()
    {
        $this->Log->write('Db::__destruct()', Log::LOG_LEVEL_SYSTEM_INFORMATION);
        $this->disconnect();
        $this->Log->write('disconnected', Log::LOG_LEVEL_SYSTEM_INFORMATION);
    }


    /**
     * Connect to PDO
     */
    private function connect()
    {
        $this->Log->write('Db::connect()', Log::LOG_LEVEL_SYSTEM_INFORMATION);

        try {
            if (strlen($this->host) == 0 || strlen($this->dbname) == 0) {
                $this->Log->write('Host OR database variables are empty', Log::LOG_LEVEL_WARNING);
                die();
            }
            $connection_string = 'mysql:host=' . $this->host . ';dbname=' . $this->dbname;
            $this->dbh = new \PDO($connection_string, $this->user, $this->pass);
            $this->dbh->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        } catch (\PDOException $e) {
            $this->exception = $e;
            $this->Log->exception($e);
            die();
        }
    }


    /**
     * Disconnect database handler
     */
    private function disconnect()
    {
        $this->Log->write('Db::disconnect()', Log::LOG_LEVEL_SYSTEM_INFORMATION);
        $this->dbh = null;
    }


    /**
     * Save the provided arguments to execute later
     *
     * @param string $sql
     * @param array $parameters
     * @param null $return_type
     * @return bool True if 1 entry was added
     * @uses Db::$queue
     */
    public function enqueue($sql = '', $parameters = array(), $return_type = null)
    {
        $this->Log->write('Db::enqueue()', Log::LOG_LEVEL_SYSTEM_INFORMATION);
        $queue_length = count($this->queue);
        $this->Log->write('original queue length', Log::LOG_LEVEL_USER, $queue_length);
        $this->queue[] = array(
            'sql' => $sql,
            'parameters' => $parameters,
            'return_type' => $return_type,
        );
        $new_queue_length = count($this->queue);
        $this->Log->write('new queue length', Log::LOG_LEVEL_USER, $new_queue_length);

        return $new_queue_length - $queue_length === 1;
    }


    /**
     * Execute queries from the queue.
     *
     * @return array|bool Array of results from calling query in the order of entrance to the queue (FIFO)
     * @uses Db::$queue
     * @uses Db::query()
     */
    public function executeQueue()
    {
        $this->Log->write('Db::executeQueue()', Log::LOG_LEVEL_SYSTEM_INFORMATION);

        if (!is_array_ne($this->queue)) {
            $this->Log->write('queue is empty', Log::LOG_LEVEL_WARNING);

            return false;
        }

        $results = array();
        foreach ($this->queue as $array) {
            $results[] = call_user_func_array(array($this, 'query'), $array);
        }
        $this->Log->write('found ' . count($results) . ' query results', Log::LOG_LEVEL_USER);

        // empty the queue
        $this->queue = array();

        return $results;
    }


    /**
     * Execute the query and return results according to the string provided.
     *
     * @param string $sql
     * @param array $parameters
     * @param string $return_type
     * @return mixed
     */
    public function query($sql = '', $parameters = array(), $return_type = null)
    {
        $this->Log->write('Db::query()', Log::LOG_LEVEL_SYSTEM_INFORMATION);

        // input validation
        if (!is_string_ne($sql)) {
            $this->Log->write('sql is empty', Log::LOG_LEVEL_WARNING);

            return false;
        }

        $this->Log->write('return type', Log::LOG_LEVEL_USER, $return_type);

        try {
            if ($return_type === 'iterator') {
                $this->stmt = $this->dbh->prepare($sql, array(\PDO::ATTR_CURSOR => \PDO::CURSOR_SCROLL));
            } else {
                $this->stmt = $this->dbh->prepare($sql);
            }
        } catch (\PDOException $ex) {
            $this->exception = $ex;
            $this->Log->exception($ex);

            return false;
        } catch (\Exception $ex) {
            $this->exception = $ex;
            $this->Log->exception($ex);

            return false;
        }

        if (!is_object($this->stmt) || !$this->stmt) {
            $this->Log->write('error preparing statement', Log::LOG_LEVEL_WARNING);

            return false;
        }

        if (!is_array($parameters)) {
            $this->Log->write('parameters is empty', Log::LOG_LEVEL_USER);
            $parameters = null;
        } else {
            $this->bind($parameters);
        }

        try {
            $executed = $this->stmt->execute();
        } catch (\PDOException $ex) {
            $this->exception = $ex;
            $this->rollback();
            $this->Log->exception($ex);

            return false;
        } catch (\Exception $ex) {
            $this->exception = $ex;
            $this->rollback();
            $this->Log->exception($ex);

            return false;
        }

        if (!$executed) {
            $this->Log->write('could not execute statement with parameters', Log::LOG_LEVEL_WARNING);
            $this->rollback();

            return false;
        }

        switch ($return_type) {
            case 'array':
                $result = $this->stmt->fetchAll(\PDO::FETCH_ASSOC);
                break;
            case 'flat':
                $rows = $this->stmt->fetchAll(\PDO::FETCH_ASSOC);
                $result = array_flatten($rows);
                break;
            case 'single':
                $row = $this->stmt->fetch(\PDO::FETCH_NUM);
                $result = $row[0];
                break;
            case 'first':
                $result = $this->stmt->fetch(\PDO::FETCH_ASSOC);
                break;
            case 'keyvalue':
                $rows = new DbIterator($this->stmt);
                $id_field = '';
                $value_field = '';
                $result = array();
                foreach ($rows as $ri => $row) {
                    // prepare id and value field names
                    if ($ri === 0) {
                        $keys = array_keys($row);
                        $num_keys = count($keys);
                        if ($num_keys > 2) {
                            $this->Log->write('Too many fields (' . $num_keys . ') found in query results; expected 2.', Log::LOG_LEVEL_WARNING);
                        }
                        unset($num_keys);
                        $id_field = $keys[0];
                        $value_field = $keys[1];
                        unset($keys);
                    }

                    // assign key/value pairs to $result
                    $result[$row[$id_field]] = $row[$value_field];
                }
                unset($rows, $id_field, $value_field);
                break;
            case 'insert':
                $result = $this->dbh->lastInsertId();
                break;
            case 'update':
            case 'delete':
                $result = $this->stmt->rowCount() > 0;
                break;
            case 'iterator':
                $result = new DbIterator($this->stmt);
                break;
            case null:
            default:
                $result = null;
                break;
        }

        $this->Log->write('have a result', Log::LOG_LEVEL_USER);

        return $result;
    }


    /**
     * Create SQL and parameters needed for inserting into database
     *
     * @param string $table Table name
     * @param array $pairs Either pairs of values (a single row) or multiple rows
     * @param bool|false $multiple_rows Flag for if using rows or row for $pairs
     * @return array|bool
     */
    public function buildInsert($table = '', $pairs = array(), $multiple_rows = false)
    {
        $this->Log->write('Db::buildInsert()', Log::LOG_LEVEL_SYSTEM_INFORMATION);

        if (!is_string_ne($table) || !is_array_ne($pairs)) {
            $this->Log->write('table OR pairs is empty', Log::LOG_LEVEL_WARNING);

            return false;
        }

        $this->Log->write('multiple_rows', Log::LOG_LEVEL_USER, $multiple_rows);

        $sql = 'INSERT INTO ' . $table . PHP_EOL;
        if ($multiple_rows === true) {
            $fields = array();
            $values = array();
            foreach ($pairs as $row) {
                // make sure add_date is present
                if (!array_key_exists('add_date', $row)) {
                    $row['add_date'] = date('Y-m-d H:i:s');
                }

                // prepare fields and values from the row of the pair
                $fields = array_keys($row);
                $values[] = array_values($row);
            }

            if (!is_array_ne($fields) || !is_array_ne($values)) {
                $this->Log->write('multiple rows fields OR values is empty', Log::LOG_LEVEL_WARNING);

                return false;
            }

            $sql .= '  (' . implode(',', $fields) . ')' . PHP_EOL;
            $sql .= '  VALUES' . PHP_EOL;
            foreach ($values as $key => $array) {
                $sql .= '  (' . implode(', ', array_fill(0, count($array), '?')) . '),' . PHP_EOL;
            }
            $sql = substr($sql, 0, -2);
            // get all values into one array (to pass as parameters for placeholders)
            $values = array_flatten($values);
        } else {
            $fields = array_keys($pairs);
            $values = array_values($pairs);

            if (!is_array_ne($fields) || !is_array_ne($values)) {
                $this->Log->write('fields OR values is empty', Log::LOG_LEVEL_WARNING);

                return false;
            }

            // make sure add_date is present
            if (!array_key_exists('add_date', $values)) {
                $values['add_date'] = date('Y-m-d H:i:s');
                $fields[] = 'add_date';
            }

            $sql .= '  (' . implode(',', $fields) . ')' . PHP_EOL;
            $sql .= '  VALUES' . PHP_EOL;
            $sql .= '  (' . implode(', ', array_fill(0, count($values), '?')) . ')' . PHP_EOL;
        }
        $this->Log->write('have sql and ' . count($values) . ' values', Log::LOG_LEVEL_USER);
        $this->Log->write('sql', Log::LOG_LEVEL_USER, $sql);

        return array($sql, $values);
    }


    /**
     * Build UPDATE SQL
     *
     * @param string $table Table to update
     * @param array $pairs Key/value pairs to SET
     * @return array|bool
     */
    public function buildUpdate($table = '', $pairs = array())
    {
        $this->Log->write('Db::buildUpdate()', Log::LOG_LEVEL_SYSTEM_INFORMATION);

        // input validation
        if (!is_string_ne($table) || !is_array_ne($pairs)) {
            $this->Log->write('table OR pairs is empty', Log::LOG_LEVEL_WARNING);

            return false;
        }

        // build SQL
        $sql = 'UPDATE ' . $table;
        $sql .= '  SET ';
        $glue = ' = ?,' . PHP_EOL;
        $sql .= implode($glue, $pairs) . $glue;
        $sql = substr($sql, 0, -2);
        $this->Log->write('have sql and pairs', Log::LOG_LEVEL_USER);

        return array($sql, array_values($pairs));
    }


    /**
     * Set and/or get dump file name.
     *
     * @return string
     */
    public function dumpFile()
    {
        $this->Log->write('Db::dumpFile()', Log::LOG_LEVEL_SYSTEM_INFORMATION);

        $args = func_get_args();
        if (count($args) === 1) {
            $this->Log->write('argument provided', Log::LOG_LEVEL_USER);
            if (is_string_ne($args[0])) {
                $this->dump_file = realpath(__DIR__ . '/' . $args[0]);
                $this->Log->write('set dump file path', Log::LOG_LEVEL_USER);
            }
        }

        return $this->dump_file;
    }


    /**
     * Export data from the provided tables (or all tables in the database if no tables are provided).
     *
     * @param array $tables
     * @return bool
     * @uses Db::query()
     * @uses Db::buildInsert()
     * @uses Db::writeFile()
     */
    public function export($tables = array())
    {
        $this->Log->write('Db::export()', Log::LOG_LEVEL_SYSTEM_INFORMATION);

        // make sure there are tables to use
        if (!is_array_ne($tables)) {
            $this->Log->write('tables not provided, getting tables from database', Log::LOG_LEVEL_USER);
            // get tables from current database
            $sql = 'SHOW TABLES';
            $tables = $this->query($sql, array(), 'flat');

            if (!is_array_ne($tables)) {
                $this->Log->write('could not find tables', Log::LOG_LEVEL_WARNING);

                return false;
            } else {
                $this->Log->write('found tables', Log::LOG_LEVEL_USER);
            }
        } else {
            $this->Log->write(count($tables) . ' tables have been provided', Log::LOG_LEVEL_USER);
        }

        // disable foreign key checks in dump file
        $this->writeFile($this->dump_file, '/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;');

        foreach ($tables as $table) {
            $sql = 'SELECT *';
            $sql .= '  FROM `' . $table . '`';
            $rows = $this->query($sql, array(), 'iterator');
            $output = $this->buildInsert($table, $rows);
            $this->writeFile($this->dump_file, $output);
        }

        // reset foreign key checks in dump file
        $this->writeFile($this->dump_file, '/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;');

        $this->Log->write('wrote sql to dump file', Log::LOG_LEVEL_USER);

        return filesize($this->dump_file) > 0;
    }


    /**
     * @return \PDOException
     */
    public function exception()
    {
        $this->Log->write('Db::exception()');

        return $this->exception;
    }


    /**
     * Get field structure based on table.field
     *
     * @param string $table
     * @param string $field
     * @return bool
     * @uses Db::tableStructure()
     */
    public function fieldStructure($table = '', $field = '')
    {
        // input validation
        if (!is_string_ne($table)) {
            $this->Log->write('table must be a string', Log::LOG_LEVEL_WARNING);

            return false;
        }
        if (!is_string_ne($field)) {
            $this->Log->write('field name must be a string', Log::LOG_LEVEL_WARNING);

            return false;
        }

        // get table structure
        $structure = $this->tableStructure($table);

        // make sure structure is a valid array
        if (!is_array_ne($structure)) {
            $this->Log->write('error getting table structure for ' . $table, Log::LOG_LEVEL_ERROR);

            return false;
        }

        // check for structure being written to property
        if (!array_key_exists($table, $this->table_structure)) {
            $this->Log->write('table ' . $table . ' not added to table_structure', Log::LOG_LEVEL_WARNING);
            $this->table_structure[$table] = $structure;
        }

        // check for field existence
        if (!array_key_exists($field, $structure)) {
            $this->Log->write('field ' . $field . ' does not exist in table ' . $table, Log::LOG_LEVEL_WARNING);

            return false;
        }

        return $structure[$field];
    }


    /**
     * Get a single value or all values from a table.
     *
     * @param string $table
     * @param string $order_by
     * @param null $id
     * @return bool|mixed|string
     */
    public function get($table = '', $order_by = 'id', $id = null)
    {
        $this->Log->write('Db::get()', Log::LOG_LEVEL_SYSTEM_INFORMATION);

        // input validation
        if (!is_string_ne($table)) {
            $this->Log->write('no table provided', Log::LOG_LEVEL_WARNING);

            return false;
        }

        $sql = 'SELECT *';
        $sql .= '  FROM `' . $table . '`';
        $params = array();

        if ($id !== null) {
            $sql .= PHP_EOL . '  WHERE id = ?';
            $params[] = $id;
        }

        $sql .= PHP_EOL . '  ORDER BY ' . $order_by;
        $this->Log->write('sql', Log::LOG_LEVEL_USER, $sql);

        $result = $this->query($sql, $params, 'array');

        if (!$result) {
            $exception = $this->exception();
            $result = $exception->getMessage();
        }

        $this->Log->write('result', Log::LOG_LEVEL_DEBUG, $result);

        return $result;
    }


    /**
     * Get the ID from the specified table with the given name.
     *
     * @param string $table Table name for SELECT
     * @param string $name Name of the value
     * @param array $where_fields List of fields to compare with $name
     * @param string $id_field ID field in table to return (typically id)
     * @return mixed|null
     */
    public function getIdFromName($table = '', $name = '', $where_fields = array(), $id_field = 'id')
    {
        $this->Log->write('Db::getIdFromName()');

        // input validation
        if (!is_string_ne($table) || !is_string_ne($name)) {
            $this->Log->write('table OR name is empty', Log::LOG_LEVEL_WARNING);

            return null;
        }

        // build SELECT query
        $sql = 'SELECT ' . $id_field . ' FROM ' . $table . ' WHERE name = ?';

        // prepare parameters to send, including $name as the first parameter
        $params = array();
        $params[] = $name;

        // add any additional fields to WHERE to compare with $name
        if (is_array_ne($where_fields)) {
            foreach ($where_fields as $field) {
                $sql .= ' OR ' . $field . ' = ?';
                $params[] = $name;
            }
        }
        $this->Log->write('built sql and parameters', Log::LOG_LEVEL_USER);

        // get the ID from the table for the name
        return $this->query($sql, $params, 'single');
    }


    /**
     * Execute SQL from a file (after running file_get_contents).
     *
     * @param string $sql
     * @return bool|int
     * @see http://stackoverflow.com/questions/147821/loading-sql-files-from-within-php#answer-7178917
     */
    public function import($sql = '')
    {
        $this->Log->write('Db::import()', Log::LOG_LEVEL_SYSTEM_INFORMATION);

        // input validation
        if (!is_string_ne($sql)) {
            $this->Log->write('sql is empty', Log::LOG_LEVEL_WARNING);

            return false;
        }

        $num_rows = $this->dbh->exec($sql);

        if (is_valid_int($num_rows)) {
            $this->Log->write('import success', Log::LOG_LEVEL_USER);
        } else {
            list($sql_state, $code, $message) = $this->dbh->errorInfo();
            $error_message = $sql_state . '|' . $code . ' - ' . $message;
            $this->Log->write('import fail: ' . $error_message, Log::LOG_LEVEL_WARNING);
        }

        return $num_rows;
    }


    /**
     * Insert values into specified table using key/value pairs array.
     *
     * @param string $table
     * @param array $pairs Key/Value pairs to insert
     * @param bool $enqueue Enqueue or execute query
     * @return bool|mixed
     * @uses Db::buildInsert()
     * @uses Db::query()
     */
    public function insert($table = '', $pairs = array(), $enqueue = false)
    {
        $this->Log->write('Db::insert()', Log::LOG_LEVEL_SYSTEM_INFORMATION);

        // input validation
        if (!is_string_ne($table) || !is_array_ne($pairs)) {
            $this->Log->write('table OR pairs is empty', Log::LOG_LEVEL_WARNING);

            return false;
        }

        // build SQL
        list($sql, $params) = $this->buildInsert($table, $pairs);

        if ($enqueue) {
            $this->Log->write('enqueue parameters', Log::LOG_LEVEL_USER);

            return $this->enqueue($sql, $params, 'insert');
        } else {
            $this->Log->write('inserting sql in transaction', Log::LOG_LEVEL_USER);
            // execute INSERT query in transaction
            $this->begin();
            $insert_id = $this->query($sql, $params, 'insert');
            $this->commit();
            $this->Log->write('insert finished', Log::LOG_LEVEL_USER);

            return $insert_id;
        }
    }


    /**
     * Set and/or get the log level.
     *
     * @return int
     * @uses Log::validateLevel()
     */
    public function logLevel()
    {
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        $args = func_get_args();
        if (is_array_ne($args)) {
            if (is_valid_int($args[0]) && (is_object($this->Log) && $this->Log->validateLevel($args[0]))) {
                $this->log_level = $args[0];
                $this->Log->logLevel($this->log_level);
            }
        }

        return $this->log_level;
    }


    /**
     * Return the value quoted after its type, or null if it is invalid.
     *
     * @param mixed $value
     * @param string $type
     * @return mixed
     */
    public function quote($value = '', $type = 'string')
    {
        $this->Log->write('Db::quote()');

        if (empty($value)) {
            $this->Log->write('value is empty', Log::LOG_LEVEL_WARNING);

            return "''";
        }

        $this->Log->write('type', Log::LOG_LEVEL_USER, $type);

        switch ($type) {
            case 'int':
                if (is_valid_int($value)) {
                    return $value;
                }
                break;
            case 'decimal':
                if (is_valid_decimal($value)) {
                    return $value;
                }
                break;
            case 'bool':
                return !!$value;
                break;
            case 'date':
                if (is_date($value)) {
                    return "'$value'";
                }
                break;
            case 'string':
            default:
                return "'$value'";
                break;
        }
        $this->Log->write('error processing value for type', Log::LOG_LEVEL_USER);

        return null;
    }


    /**
     * Get table structure using DESCRIBE and cache the value if not already cached.
     *
     * @param string $table Table name
     * @return array|bool
     * @uses Db::$table_structure
     */
    public function tableStructure($table = '')
    {
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        // input validation
        if (!is_string_ne($table)) {
            $this->Log->write('table name not provided', Log::LOG_LEVEL_WARNING);

            return false;
        }

        // use a cached value to avoid querying the database and processing again
        if (array_key_exists($table, $this->table_structure) && is_array_ne($this->table_structure[$table])) {
            $this->Log->write('using cached structure value', Log::LOG_LEVEL_USER);

            return $this->table_structure[$table];
        }

        $sql = 'DESCRIBE ' . $table;

        $fields = $this->query($sql, null, 'array');

        if (!$fields) {
            $this->Log->write('could not describe fields for ' . $table, Log::LOG_LEVEL_ERROR);

            return false;
        }

        $type_pattern = '/([a-z]+)(\([\d]+\))? ?([a-z]+)?/';

        $structure = array();
        foreach ($fields as $field) {
            $type = '';
            $size = '';
            $extra = '';
            $matches = array();
            // determine type attributes
            if (preg_match($type_pattern, $field['Type'], $matches)) {
                if (isset($matches[1])) {
                    $type = $matches[1];
                }
                if (isset($matches[2])) {
                    $size = str_replace(array('(', ')'), '', $matches[2]);
                }
                if (isset($matches[3])) {
                    $extra = $matches[3];
                }
            }

            switch ($field['Key']) {
                case 'PRI':
                    $key = 'Primary';
                    break;
                case 'MUL':
                    $key = 'Multiple';
                    break;
                default:
                    $key = '';
                    break;
            }

            // build structure array
            $structure[$field['Field']] = array(
                'type' => $type,
                'size' => $size,
                'type_extra' => $extra,
                'key' => $key,
                'default' => $field['Default'],
                'extra' => $field['Extra'],
            );
        }

        // add array to property for caching purposes
        $this->table_structure[$table] = $structure;

        return $structure;
    }


    /**
     * Update a table based on key/value pairs and WHERE parameters
     *
     * @param string $table Table to update
     * @param array $pairs Key/value pairs to SET
     * @param array $where Where values
     * @param int $limit Limit value (only positive integers will be used for limiting)
     * @param bool $enqueue Enqueue or execute the SQL
     * @return bool|mixed
     * @uses Db::buildUpdate()
     * @uses Db::where()
     * @uses Db::begin()
     * @uses Db::query()
     * @uses Db::commit()
     */
    public function update($table = '', $pairs = array(), $where = array(), $limit = -1, $enqueue = false)
    {
        $this->Log->write('Db::update()', Log::LOG_LEVEL_SYSTEM_INFORMATION);

        // input validation
        if (!is_string_ne($table) || !is_array_ne($pairs)) {
            $this->Log->write('table OR pairs is empty', Log::LOG_LEVEL_WARNING);

            return false;
        }

        if (!is_array_ne($where)) {
            $this->Log->write('there is no where clause', Log::LOG_LEVEL_WARNING);
            // there might be a problem: we will update everything
            // TODO: determine the best way to handle no WHERE clause
            return false;
        }

        // build SQL
        list($sql, $params) = $this->buildUpdate($table, $pairs);

        // handle WHERE
        list($wsql, $wparams) = $this->where($where);
        $sql .= $wsql;
        $params = array_merge($params, $wparams);

        // handle LIMIT
        if (is_valid_int($limit, true)) {
            $sql .= PHP_EOL . '  LIMIT ' . $limit;
        }

        if ($enqueue) {
            $this->Log->write('enqueue sql', Log::LOG_LEVEL_SYSTEM_INFORMATION);

            return $this->enqueue($sql, $params, 'update');
        } else {
            $this->Log->write('updating sql in transaction', Log::LOG_LEVEL_SYSTEM_INFORMATION);
            // execute UPDATE query in transaction
            $this->begin();
            $updated = $this->query($sql, $params, 'update');
            $this->commit();
            $this->Log->write('committed transaction for update', Log::LOG_LEVEL_USER);

            return $updated;
        }
    }


    /**
     * Begin transaction
     *
     * @uses Db::$dbh
     */
    public function begin()
    {
        $this->Log->write('Db::begin()', Log::LOG_LEVEL_SYSTEM_INFORMATION);
        $this->transaction_started = $this->dbh->beginTransaction();
    }


    /**
     * Commit transaction
     *
     * @uses Db::$dbh
     */
    public function commit()
    {
        $this->Log->write('Db::commit()', Log::LOG_LEVEL_SYSTEM_INFORMATION);
        if ($this->transaction_started) {
            $this->dbh->commit();
            $this->transaction_started = false;
        }
    }


    /**
     * Rollback transaction
     *
     * @uses Db::$dbh
     */
    public function rollback()
    {
        $this->Log->write('Db::rollback()', Log::LOG_LEVEL_SYSTEM_INFORMATION);
        if ($this->transaction_started) {
            $this->dbh->rollback();
            $this->transaction_started = false;
        }
    }


    /**
     * Bind parameters to execute.
     *
     * @param array $parameters
     * @return bool
     * @uses \PDOStatement::bindValue()
     */
    private function bind($parameters = array())
    {
        $this->Log->write('Db::bind()', Log::LOG_LEVEL_SYSTEM_INFORMATION);

        if (!is_array_ne($parameters)) {
            $this->Log->write('parameters is empty', Log::LOG_LEVEL_WARNING);

            return false;
        }
        $this->Log->write('parameters: ' . implode(', ', $parameters), Log::LOG_LEVEL_USER);

        // make sure the array has numeric indexes
        $parameters = array_values($parameters);

        try {
            foreach ($parameters as $i => $value) {
                // parameters are 1-based
                $this->stmt->bindValue($i + 1, $value);
            }
            $this->Log->write('bound values', Log::LOG_LEVEL_USER);
        } catch (\PDOException $ex) {
            $this->Log->exception($ex);

            return false;
        }

        return true;
    }


    /**
     * Build WHERE clause, with basic elements of xPDOQuery::where()
     *
     * @param array $conditions Array of conditions with an optional operator in the key [like 'id:IN' => array(1, 2, 3)]
     * @param string $conjunction AND or OR
     * @return array|bool
     * @see https://rtfm.modx.com/xpdo/2.x/class-reference/xpdoquery/xpdoquery.where
     */
    private function where($conditions = array(), $conjunction = 'AND')
    {
        $this->Log->write('Db::where()', Log::LOG_LEVEL_SYSTEM_INFORMATION);

        if (!is_array_ne($conditions)) {
            $this->Log->write('no conditions provided', Log::LOG_LEVEL_WARNING);

            return false;
        }

        $params = array();
        $sql = PHP_EOL . '  WHERE (';
        $line_end = ' ' . $conjunction . PHP_EOL;
        $i = 0;
        foreach ($conditions as $fieldop => $value) {
            list($field, $op) = explode(':', $fieldop);
            if (!in_array(strtoupper($op), array('IN', 'NOT IN')) && is_array_ne($value)) {
                // this is a set of OR conditions
                if ($i > 0) {
                    $sql .= PHP_EOL . '  OR ' . PHP_EOL;
                }
                $sql .= '  (' . PHP_EOL;
                $vi = 0;
                foreach ($value as $vfieldop => $vvalue) {
                    if ($vi > 0) {
                        $sql .= $line_end;
                    }
                    list($field, $op) = explode(':', $vfieldop);
                    if (in_array(strtoupper($op), array('IN', 'NOT IN'))) {
                        // handle IN array elements
                        $sql .= '  ' . $field . ' ' . $op . ' (';
                        $sql .= implode(', ', array_fill(0, count($vvalue), '?'));
                        $sql .= ')';
                        $params = array_merge($params, array_values($vvalue));
                    } else {
                        if ($op === null || !is_string_ne($op)) {
                            $op = '=';
                        }
                        $sql .= '  ' . $field . ' ' . $op . ' ?';
                        $params[] = $value;
                    }
                    $vi++;
                }
                $sql .= PHP_EOL . '  )' . PHP_EOL;
            } else {
                if ($i > 0) {
                    $sql .= $line_end;
                }
                if (in_array(strtoupper($op), array('IN', 'NOT IN'))) {
                    // handle IN array elements
                    $sql .= '  ' . $field . ' ' . $op . ' (';
                    $sql .= implode(', ', array_fill(0, count($value), '?'));
                    $sql .= ')';
                    $params = array_merge($params, array_values($value));
                } else {
                    if ($op === null || !is_string_ne($op)) {
                        $op = '=';
                    }
                    $sql .= '  ' . $field . ' ' . $op . ' ?';
                    $params[] = $value;
                }
            }
            $i++;
        }
        $sql .= PHP_EOL . '  )';
        $this->Log->write('built sql and parameters', Log::LOG_LEVEL_USER);

        return array($sql, $params);
    }


    /**
     * Write contents to a file.
     *
     * @param string $file
     * @param string $content
     * @return bool|int
     */
    private function writeFile($file = '', $content = '')
    {
        $this->Log->write('Db::writeFile()', Log::LOG_LEVEL_SYSTEM_INFORMATION);

        if (!is_string_ne($file) || !is_file($file) || !file_exists($file)) {
            $this->Log->write('file is not a string or does not exist for writing', Log::LOG_LEVEL_WARNING);

            return false;
        }

        return file_put_contents($file, $content . PHP_EOL, FILE_APPEND);
    }
}