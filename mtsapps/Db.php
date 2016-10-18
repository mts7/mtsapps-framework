<?php
/**
 * Standard database class for handling connections, queries, and transactions through PDO
 * This only works with MySQL for now, and will need updating to work with other RDBMSes like PostgreSQL and MSSQL.
 *
 * @author Mike Rodarte
 * @version 1.22
 * @todo Add database type handling for PostgreSQL, SQL Server, DB2, Oracle
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
     * @var \PDO Database handle
     */
    private $dbh = null;

    /**
     * @var string Database name
     */
    private $dbname = '';

    /**
     * @var string File name used for exporting SQL
     */
    private $dump_file = '';

    /**
     * @var \PDOException Exception
     */
    private $exception = null;

    /**
     * @var string Database host
     */
    private $host = '';

    /**
     * @var array Cached array of values for keys (primary, unique, foreign) in tables
     */
    private $key_values = array();

    /**
     * @var string The last query prepared
     */
    private $last_query = '';

    /**
     * @var Log Log object
     */
    protected $Log = null;

    /**
     * @var int Log level
     */
    protected $log_level = 0;

    /**
     * @var int Maximum number of inserts in a single statement
     */
    private $max_inserts = 500;

    /**
     * @var array Cached IDs based on names in tables
     */
    public $named_ids = array();

    /**
     * @var string Database password
     */
    private $pass = '';

    /**
     * @var array Queue of queries to execute later
     */
    protected $queue = array();

    /**
     * @var \PDOStatement PDO Statement
     */
    private $stmt = null;

    /**
     * @var array Array of tables, fields, and descriptions for fields (from DESCRIBE)
     */
    private $table_structure = array();

    /**
     * @var bool Is a transaction started
     */
    private $transaction_started = false;

    /**
     * @var string Database user name
     */
    private $user = '';


    /**
     * Db constructor.
     *
     * @param array $params
     */
    public function __construct($params = array())
    {
        // handle logging
        if (Helpers::is_array_ne($params) && array_key_exists('log_level', $params)) {
            $log_level = $params['log_level'];
        } else {
            $log_level = Log::LOG_LEVEL_WARNING;
        }
        if (Helpers::is_array_ne($params) && array_key_exists('log_directory', $params)) {
            $log_directory = $params['log_directory'];
        } else {
            $log_directory = LOG_DIR;
        }
        if (Helpers::is_array_ne($params) && array_key_exists('log_file', $params)) {
            $file = $params['log_file'];
        } else {
            $file = 'db_' . date('Y-m-d') . '.log';
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

        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        // assign parameters to properties
        if (Helpers::is_array_ne($params)) {
            if (array_key_exists('user', $params) && Helpers::is_string_ne($params['user'])) {
                $this->user = $params['user'];
            }

            if (array_key_exists('pass', $params) && Helpers::is_string_ne($params['pass'])) {
                $this->pass = $params['pass'];
            }

            if (array_key_exists('host', $params) && Helpers::is_string_ne($params['host'])) {
                $this->host = $params['host'];
            }

            if (array_key_exists('db', $params) && Helpers::is_string_ne($params['db'])) {
                $this->dbname = $params['db'];
            }

            if (array_key_exists('dump_file', $params) && Helpers::is_string_ne($params['dump_file'])) {
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
     * This disconnects from the database and destroys the Log object.
     *
     * @uses Db::disconnect()
     */
    public function __destruct()
    {
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);
        $this->disconnect();
        $this->Log->write('disconnected', Log::LOG_LEVEL_SYSTEM_INFORMATION);
        $this->Log->__destruct();
        $this->Log = null;
        unset($this->Log);
    }


    /**
     * Connect to PDO with the MySQL connection string.
     *
     * @todo Make this available for other SQL variants
     */
    private function connect()
    {
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        try {
            if (!Helpers::is_string_ne($this->user)) {
                $this->Log->write('Cannot connect to database without a user name provided.', Log::LOG_LEVEL_ERROR);

                throw new \Exception('Cannot connect to database without a user name provided.');
            }

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
        } catch (\Exception $e) {
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
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);
        $this->dbh = null;
    }


    /**
     * Save the provided arguments to execute later.
     * With multiple child classes, each one will have its own queue, as expected. When executing the queue, do so from
     * the same object that enqueued the query and parameters or copy the queue to the main Db object.
     *
     * @param string $sql
     * @param array $parameters
     * @param null $return_type
     * @return bool True if 1 entry was added
     * @uses Db::$queue
     * @uses Db::queueLength()
     * @see  Db::executeQueue()
     */
    public function enqueue($sql = '', $parameters = array(), $return_type = null)
    {
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);
        $queue_length = $this->queueLength();
        $this->Log->write('original queue length', Log::LOG_LEVEL_USER, $queue_length);
        $script = array(
            'sql' => $sql,
            'parameters' => $parameters,
            'return_type' => $return_type,
        );
        $this->queue[] = $script;
        $new_queue_length = $this->queueLength();
        $this->Log->write('new queue length', Log::LOG_LEVEL_USER, $new_queue_length);

        return $new_queue_length - $queue_length === 1;
    }


    /**
     * Execute queries from the queue.
     * When using multiple child classes of this class, beware of which one enqueued queries.
     *
     * @return array|bool Array of results from calling query in the order of entrance to the queue (FIFO)
     * @uses Db::$queue
     * @uses Db::query()
     * @see  Db::enqueue()
     */
    public function executeQueue()
    {
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        if (!Helpers::is_array_ne($this->queue)) {
            $this->Log->write('queue is empty', Log::LOG_LEVEL_USER);

            return false;
        }

        $results = array();
        // execute each query in the queue and store the results in an array to return
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
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        // input validation
        if (!Helpers::is_string_ne($sql)) {
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
            $this->last_query = $sql;
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

        // bind parameters, if needed
        if (!Helpers::is_array_ne($parameters)) {
            $this->Log->write('parameters is empty', Log::LOG_LEVEL_USER);
            $parameters = null;
        } else {
            $this->bind($parameters);
        }

        try {
            // writeQueryParameters is disabled until manually updated in the code (due to increased execution time)
            $this->writeQueryParameters($sql, $parameters);
            // execute the query with its parameters
            $executed = $this->stmt->execute();
        } catch (\PDOException $ex) {
            $this->exception = $ex;
            $this->rollback();
            $this->Log->exception($ex, array('sql' => $sql, 'params' => $parameters));
            $this->writeQueryParameters(wordwrap('/* ' . $ex->getMessage() . ' */', 120, PHP_EOL . ' * '), null);

            return false;
        } catch (\Exception $ex) {
            $this->exception = $ex;
            $this->rollback();
            $this->Log->exception($ex, array('sql' => $sql, 'params' => $parameters));
            $this->writeQueryParameters(wordwrap('/* ' . $ex->getMessage() . ' */', 120, PHP_EOL . ' * '), null);

            return false;
        }

        if (!$executed) {
            $this->Log->write('could not execute statement with parameters', Log::LOG_LEVEL_WARNING);
            $this->rollback();

            return false;
        }

        // the query executed successfully, so return according to the specified return type
        switch ($return_type) {
            case 'array':
                // an array of arrays indexed by fields, with a large memory footprint
                $result = $this->stmt->fetchAll(\PDO::FETCH_ASSOC);
                break;
            case 'flat':
                // an array of all values in a flattened state
                $rows = $this->stmt->fetchAll(\PDO::FETCH_ASSOC);
                $result = Helpers::array_flatten($rows);
                break;
            case 'single':
                // the first result in the first column of the first row
                $row = $this->stmt->fetch(\PDO::FETCH_NUM);
                $result = $row[0];
                $this->writeQueryParameters('/* ' . $result . ' */');
                break;
            case 'first':
                // an array with the first row indexed by field
                $result = $this->stmt->fetch(\PDO::FETCH_ASSOC);
                break;
            case 'keyvalue':
                // array of key value pairs for each row in the result
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
                // insert ID
                $result = $this->dbh->lastInsertId();
                $this->writeQueryParameters('/* ' . $result . ' */');
                break;
            case 'update':
            case 'delete':
                // row count greater than 0 (something was updated or deleted)
                $result = $this->stmt->rowCount() > 0;
                $this->writeQueryParameters('/* ' . $result . ' */');
                break;
            case 'iterator':
                // database iterator
                $result = new DbIterator($this->stmt);
                break;
            case null:
            default:
                // null
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
     * @param array|DbIterator $pairs Either pairs of values (a single row) or multiple rows
     * @param bool|false $multiple_rows Flag for if using rows or row for $pairs
     * @param bool $placeholders Use placeholders (true) or use values (false)
     * @param bool $ignore Use INSERT IGNORE
     * @return array|bool
     * @uses Db::quoteField()
     */
    public function buildInsert($table = '', $pairs = array(), $multiple_rows = false, $placeholders = true, $ignore = false)
    {
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        if (!Helpers::is_string_ne($table) || (!Helpers::is_array_ne($pairs) && !($pairs instanceof DbIterator))) {
            $this->Log->write('table OR pairs is empty', Log::LOG_LEVEL_WARNING, Helpers::get_type_size($table) . ', ' . Helpers::get_type_size($pairs));

            return false;
        }

        $this->Log->write('multiple_rows', Log::LOG_LEVEL_USER, $multiple_rows);

        $sql = 'INSERT ';
        if (!!$ignore) {
            $sql .= 'IGNORE ';
        }
        $sql .= 'INTO ' . $table . PHP_EOL;

        if ($multiple_rows === true) {
            $fields = array();
            $values = array();
            $insert_sql = $sql;

            // build fields and values from pairs
            foreach ($pairs as $key => $row) {
                if (!Helpers::is_array_ne($row)) {
                    $this->Log->write('row from pairs is not an array, but is ' . Helpers::get_type_size($row), Log::LOG_LEVEL_WARNING, $row);

                    continue;
                }

                // make sure add_date is present
                if (!array_key_exists('add_date', $row)) {
                    $row['add_date'] = date('Y-m-d H:i:s');
                }

                // prepare fields and values from the row of the pair
                $fields = array_keys($row);
                $values[] = array_values($row);
            }

            if (!Helpers::is_array_ne($fields) || !Helpers::is_array_ne($values)) {
                $this->Log->write('multiple rows fields OR values is empty', Log::LOG_LEVEL_WARNING);

                return false;
            }

            $insert_sql .= '  (' . implode(', ', $fields) . ')' . PHP_EOL;
            $insert_sql .= '  VALUES' . PHP_EOL;
            $sql = $insert_sql;

            // either use placeholders with bound parameters or actual, quoted parameters (typically for debugging)
            if ($placeholders) {
                $counter = 0;
                // build statements with attention to $max_inserts
                foreach ($values as $key => $array) {
                    $counter++;
                    if ($counter === $this->max_inserts) {
                        $sql = rtrim($sql, ',' . PHP_EOL) . ';' . PHP_EOL . PHP_EOL;
                        $sql .= $insert_sql;
                        $counter = 1;
                    }
                    // add a ? for each parameter
                    $sql .= '  (' . implode(', ', array_fill(0, count($array), '?')) . '),' . PHP_EOL;
                }
                // get all values into one array (to pass as parameters for placeholders)
                $values = Helpers::array_flatten($values);
            } else {
                foreach ($values as $key => $array) {
                    $sql .= '  (';
                    foreach ($array as $index => $value) {
                        if (is_array($value)) {
                            Helpers::display_now('values multiple');
                            Helpers::display_now($values);
                            break;
                        }
                        // add quoted value to SQL
                        $sql .= $this->quoteField($table, $fields[$index], $value) . ', ';
                    }
                    $sql = substr($sql, 0, -2);
                    $sql .= '),' . PHP_EOL;
                }
            }
        } else {
            // there are not multiple rows, this is a single row
            if (!Helpers::is_array_ne($pairs)) {
                $this->Log->write('pairs is not an array, but is ' . Helpers::get_type_size($pairs), Log::LOG_LEVEL_WARNING, $pairs);

                return false;
            }
            $fields = array_keys($pairs);
            $values = array_values($pairs);

            if (!Helpers::is_array_ne($fields) || !Helpers::is_array_ne($values)) {
                $this->Log->write('fields OR values is empty', Log::LOG_LEVEL_WARNING);

                return false;
            }

            // make sure add_date is present
            if (!array_key_exists('add_date', $values)) {
                $values['add_date'] = date('Y-m-d H:i:s');
                $fields[] = 'add_date';
            }

            $sql .= '  (' . implode(', ', $fields) . ')' . PHP_EOL;
            $sql .= '  VALUES' . PHP_EOL;
            if ($placeholders) {
                // add a ? for each parameter
                $sql .= '  (' . implode(', ', array_fill(0, count($values), '?')) . ')' . PHP_EOL;
            } else {
                $sql .= '  (';
                foreach ($values as $index => $value) {
                    // add quoted value to SQL
                    $sql .= $this->quoteField($table, $fields[$index], $value) . ', ';
                }
                $sql = substr($sql, 0, -2);
                $sql .= ')' . PHP_EOL;
            }
        }
        $sql = rtrim($sql, ',' . PHP_EOL);

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
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        // input validation
        if (!Helpers::is_string_ne($table) || !Helpers::is_array_ne($pairs)) {
            $this->Log->write('table OR pairs is empty', Log::LOG_LEVEL_WARNING);

            return false;
        }

        // build SQL
        $sql = 'UPDATE ' . $table . PHP_EOL;
        $sql .= '  SET ';
        $glue = ' = ?,' . PHP_EOL . '    ';
        $sql .= implode($glue, array_keys($pairs)) . $glue;
        $sql = rtrim($sql, ', ' . PHP_EOL);
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
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        $args = func_get_args();
        if (count($args) === 1) {
            $this->Log->write('argument provided', Log::LOG_LEVEL_USER);
            if (Helpers::is_string_ne($args[0])) {
                // file is a full path, so split it to directory and file name
                $dir = dirname($args[0]);
                $file = basename($args[0]);
                // handle relative directories
                if (!is_dir($dir)) {
                    $this->Log->write('directory does not exist', Log::LOG_LEVEL_USER, $dir);
                    $dir = __DIR__ . DIRECTORY_SEPARATOR . $dir;
                    if (!is_dir($dir)) {
                        $this->Log->write('this directory does not exist', Log::LOG_LEVEL_USER, $dir);
                        $dir = __DIR__;
                    }
                }
                $dir = realpath($dir) . DIRECTORY_SEPARATOR;
                $this->dump_file = $dir . $file;
                $this->Log->write('set dump file path', Log::LOG_LEVEL_USER, $dir . $file);
                touch($this->dump_file);
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
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        // make sure there are tables to use
        if (!Helpers::is_array_ne($tables)) {
            $this->Log->write('tables not provided, getting tables from database', Log::LOG_LEVEL_USER);
            // get tables from current database
            $sql = 'SHOW TABLES';
            $tables = $this->query($sql, array(), 'flat');

            if (!Helpers::is_array_ne($tables)) {
                $this->Log->write('could not find tables', Log::LOG_LEVEL_WARNING);

                return false;
            } else {
                $this->Log->write('found tables', Log::LOG_LEVEL_USER);
            }
        } else {
            $this->Log->write(count($tables) . ' tables have been provided', Log::LOG_LEVEL_USER);
        }

        // disable foreign key checks in dump file
        $this->writeFile($this->dump_file, '/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;' . PHP_EOL);

        // get data from table, build insert with the rows, and write the SQL to the dump file
        foreach ($tables as $table) {
            // build SELECT query
            $sql = 'SELECT *';
            $sql .= '  FROM `' . $table . '`';

            // get all values from the table
            $rows = $this->query($sql, array(), 'array');

            // build the insert statement
            list($sql,) = $this->buildInsert($table, $rows, true, false);

            // write the SQL to the file (with a TRUNCATE TABLE statement before it)
            if (Helpers::is_string_ne($sql)) {
                $output = 'TRUNCATE TABLE ' . $table . ';' . PHP_EOL;
                $output .= $sql . ';' . PHP_EOL;
                $this->writeFile($this->dump_file, $output);
            }
        }

        // reset foreign key checks in dump file
        $this->writeFile($this->dump_file, '/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;' . PHP_EOL);

        $this->Log->write('wrote sql to dump file', Log::LOG_LEVEL_USER);

        return filesize($this->dump_file) > 0;
    }


    /**
     * Save the exception for later use
     *
     * @return \PDOException
     */
    public function exception()
    {
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

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
        if (!Helpers::is_string_ne($table)) {
            $this->Log->write('table must be a string', Log::LOG_LEVEL_WARNING);

            return false;
        }
        if (!Helpers::is_string_ne($field)) {
            $this->Log->write('field name must be a string', Log::LOG_LEVEL_WARNING);

            return false;
        }

        // get table structure
        $structure = $this->tableStructure($table);

        // make sure structure is a valid array
        if (!Helpers::is_array_ne($structure)) {
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
     * This assumes id is the PRIMARY KEY field in any given table.
     *
     * @param string $table
     * @param string $order_by
     * @param null $id
     * @return bool|mixed|string
     */
    public function get($table = '', $order_by = 'id', $id = null)
    {
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        // input validation
        if (!Helpers::is_string_ne($table)) {
            $this->Log->write('no table provided', Log::LOG_LEVEL_WARNING);

            return false;
        }

        $sql = 'SELECT *';
        $sql .= '  FROM `' . $table . '`';
        $params = array();

        // specify the ID
        if ($id !== null) {
            $sql .= PHP_EOL . '  WHERE id = ?';
            $params[] = $id;
        }

        if (Helpers::is_string_ne($order_by)) {
            $sql .= PHP_EOL . '  ORDER BY ' . $order_by;
        }

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
     * @param array| $where_fields List of fields or exact field name to compare with $name
     * @param string $id_field ID field in table to return (typically id)
     * @return int
     */
    public function getIdFromName($table = '', $name = '', $where_fields = array(), $id_field = 'id')
    {
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        // input validation
        if (!Helpers::is_string_ne($table) || !Helpers::is_string_ne($name)) {
            $this->Log->write('table OR name is empty', Log::LOG_LEVEL_WARNING);

            return 0;
        }

        // check for cached value
        if (array_key_exists($table, $this->named_ids)) {
            if (array_key_exists($name, $this->named_ids[$table])) {
                $id = $this->named_ids[$table][$name];
                if (Helpers::is_valid_int($id, true)) {
                    $this->Log->write('found ' . $id . ' in cache', Log::LOG_LEVEL_USER);

                    return $id;
                }
            }
        }

        // build SELECT query
        $sql = 'SELECT ' . $id_field . PHP_EOL . '  FROM ' . $table . PHP_EOL;

        // prepare parameters to send, including $name as the first parameter
        $params = array();
        $params[] = $name;

        // add any additional fields to WHERE to compare with $name
        if (Helpers::is_string_ne($where_fields)) {
            $sql .= '  WHERE ' . $where_fields . ' = ?';
        } elseif (Helpers::is_array_ne($where_fields)) {
            $sql .= '  WHERE name = ? ' . PHP_EOL;
            foreach ($where_fields as $field) {
                $sql .= '    OR ' . $field . ' = ?';
                $params[] = $name;
            }
        } else {
            $sql .= '  WHERE name = ?';
        }
        $this->Log->write('built sql and parameters', Log::LOG_LEVEL_USER);

        // get the ID from the table for the name
        $id = $this->query($sql, $params, 'single');

        if (Helpers::is_valid_int($id, true)) {
            $this->named_ids[$table][$name] = (int)$id;

            return (int)$id;
        } else {
            return 0;
        }
    }


    /**
     * Get fields with a corresponding key type.
     *
     * @param string $table
     * @param string $key primary|foreign|unique
     * @return array|bool
     * @uses Db::tableStructure()
     */
    public function getKeyField($table = '', $key = 'primary')
    {
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        // input validation
        if (!Helpers::is_string_ne($table)) {
            $this->Log->write('invalid entry for table', Log::LOG_LEVEL_WARNING, $table);

            return false;
        }

        // get table structure
        $structure = $this->tableStructure($table);

        $fields = array();
        foreach ($structure as $field => $attrs) {
            // look for key type in attributes for field
            if ($attrs['key'] === $key) {
                $fields[] = $field;
            }
        }

        return $fields;
    }


    /**
     * Get values from key fields in a table.
     *
     * @param string $table
     * @param bool $force Force the cached value to update
     * @return bool|mixed
     * @uses Db::getKeyField()
     * @uses Db::query()
     */
    public function getKeyValues($table = '', $force = false)
    {
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        // input validation
        if (!Helpers::is_string_ne($table)) {
            $this->Log->write('table is invalid', Log::LOG_LEVEL_WARNING, Helpers::get_type_size($table));

            return false;
        }

        // check the cache
        if (array_key_exists($table, $this->key_values) && !$force) {
            $this->Log->write('using cached values for ' . $table, Log::LOG_LEVEL_USER);

            return $this->key_values[$table];
        }

        // set keys to use
        $keys = array('primary', 'unique', 'foreign');

        // get key fields
        $key_fields = array();
        foreach ($keys as $key) {
            $key_fields = array_merge($key_fields, $this->getKeyField($table, $key));
        }

        // build SQL SELECT query
        $field_list = implode(', ', $key_fields);
        $sql = 'SELECT ' . $field_list . PHP_EOL;
        $sql .= '  FROM ' . $table . PHP_EOL;
        $sql .= '  ORDER BY ' . $field_list;

        // get values for key fields
        $values = $this->query($sql, null, 'array');

        if (!Helpers::is_array_ne($values)) {
            $this->Log->write('no results or invalid query', Log::LOG_LEVEL_WARNING, array('sql' => $sql, 'values' => $values));

            return false;
        }

        // cache by table
        $this->key_values[$table] = $values;

        return $values;
    }


    /**
     * Get names (or whichever field is specified) in the given table.
     *
     * @param string $table
     * @param string $field
     * @param array $where
     * @return bool|mixed
     * @uses Db::where()
     * @uses Db::query()
     */
    public function getNames($table = '', $field = 'name', $where = array())
    {
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        // input validation
        if (!Helpers::is_string_ne($table)) {
            $this->Log->write('invalid entry for table', Log::LOG_LEVEL_WARNING, $table);

            return false;
        }

        if (!Helpers::is_string_ne($field)) {
            $this->Log->write('invalid entry for field', Log::LOG_LEVEL_WARNING, $field);

            return false;
        }

        // build standard query
        $sql = 'SELECT ' . $field . PHP_EOL;
        $sql .= '  FROM ' . $table . PHP_EOL;

        $params = array();

        // add WHERE clauses and parameters
        if (Helpers::is_array_ne($where)) {
            list($where_sql, $params) = $this->where($where);
            $sql .= $where_sql;
        }

        $sql .= '  ORDER BY ' . $field;

        return $this->query($sql, $params, 'flat');
    }


    /**
     * Execute SQL from a string (after running file_get_contents on a file).
     * This does not handle delimiters (as when used with stored procedures, triggers, or functions).
     *
     * @param string $sql
     * @return bool|int
     * @see http://stackoverflow.com/questions/147821/loading-sql-files-from-within-php#answer-7178917
     */
    public function import($sql = '')
    {
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        // input validation
        if (!Helpers::is_string_ne($sql)) {
            $this->Log->write('sql is empty', Log::LOG_LEVEL_WARNING);

            return false;
        }

        $num_rows = $this->dbh->exec($sql);

        if (Helpers::is_valid_int($num_rows)) {
            $this->Log->write('import success', Log::LOG_LEVEL_USER);
        } else {
            list($sql_state, $code, $message) = $this->dbh->errorInfo();
            $error_message = $sql_state . '|' . $code . ' - ' . $message;
            $this->Log->write('import fail: ' . $error_message, Log::LOG_LEVEL_WARNING);
        }

        return $num_rows;
    }


    /**
     * Import a file to MySQL through command line.
     *
     * @param string $file
     * @return bool
     * @todo Make this available for other SQL variants.
     */
    public function importFile($file = '')
    {
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        // input validation
        if (!Helpers::is_string_ne($file)) {
            $this->Log->write('no file provided', Log::LOG_LEVEL_WARNING, Helpers::get_type_size($file));

            return false;
        }
        if (!is_file($file)) {
            $this->Log->write('file does not exist', Log::LOG_LEVEL_WARNING, $file);

            return false;
        }
        $ext = substr($file, -3);
        if (strtolower($ext) !== 'sql') {
            $this->Log->write('file extension must be sql', Log::LOG_LEVEL_WARNING, $ext);

            return false;
        }

        $command = MYSQL_BIN . 'mysql --verbose -h ' . $this->host . ' -u' . $this->user;
        if (Helpers::is_string_ne($this->pass)) {
            $command .= ' -p\'' . trim($this->pass) . '\'';
        }
        $command .= ' ' . $this->dbname . ' < ' . $file;
        $output = array();
        exec($command, $output, $return_var);
        if ($return_var != 0) {
            $this->Log->write($return_var . ': Failed to import from file', Log::LOG_LEVEL_WARNING, $output);

            return false;
        }

        return true;
    }


    /**
     * Insert or enqueue values into specified table using key/value pairs array.
     *
     * @param string $table
     * @param array $pairs Key/Value pairs to insert
     * @param bool $enqueue Enqueue or execute query
     * @return bool|mixed
     * @uses Db::buildInsert()
     * @uses Db::enqueue()
     * @uses Db::query()
     */
    public function insert($table = '', $pairs = array(), $enqueue = false)
    {
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        // input validation
        if (!Helpers::is_string_ne($table) || !Helpers::is_array_ne($pairs)) {
            $this->Log->write('table OR pairs is empty', Log::LOG_LEVEL_WARNING);

            return false;
        }

        // build SQL
        list($sql, $params) = $this->buildInsert($table, $pairs, isset($pairs[0]) && is_array($pairs[0]));

        if ($enqueue) {
            $this->Log->write('enqueue parameters', Log::LOG_LEVEL_USER);

            $enqueued = $this->enqueue($sql, $params, 'insert');
            if (!$enqueued) {
                $this->Log->write('error adding query to queue', Log::LOG_LEVEL_WARNING, array('sql' => $sql, 'params' => $params));
            }

            return $enqueued;
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
     * @uses Log::logLevel()
     */
    public function logLevel()
    {
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        $args = func_get_args();
        if (Helpers::is_array_ne($args)) {
            if (Helpers::is_valid_int($args[0]) && (is_object($this->Log) && $this->Log->validateLevel($args[0]))) {
                $this->log_level = $args[0];
                $this->Log->logLevel($this->log_level);
            }
        }

        return $this->log_level;
    }


    /**
     * Get the length of the current queue for this instance of the class.
     *
     * @return mixed
     */
    public function queueLength()
    {
        return count($this->queue);
    }


    /**
     * Return the value quoted after its type, or null if it is invalid.
     *
     * @param mixed $value Value to quote
     * @param string $type Expected data type of value
     * @return mixed
     */
    public function quote($value = '', $type = 'string')
    {
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        $this->Log->write('type', Log::LOG_LEVEL_USER, $type);

        // input validation
        if (!Helpers::is_string_ne($type)) {
            $type = gettype($value);
            $this->Log->write('set type to ' . $type . ' for value', Log::LOG_LEVEL_USER, $value);
        }

        // if the type is string, the actual type might be different
        if ($type === 'string') {
            if (Helpers::is_valid_decimal($value)) {
                $type = 'decimal';
            } elseif (Helpers::is_valid_int($value)) {
                $type = 'int';
            } elseif (is_bool($value)) {
                $type = 'bool';
            } elseif (is_null($value)) {
                $type = 'NULL';
            }
        }

        // check for empty value that is not a number or null
        if (!(in_array($type, array('int', 'integer', 'decimal', 'double', 'float', 'NULL')) && ($value == 0 || $value == null)) && empty($value)) {
            $this->Log->write('value is empty', Log::LOG_LEVEL_USER);

            return "''";
        }

        // properly quote the value based on the type
        switch ($type) {
            case 'int':
            case 'tinyint':
            case 'integer':
                if (Helpers::is_valid_int($value)) {
                    return (int)$value;
                } else {
                    return 0;
                }
                break;
            case 'decimal':
            case 'double':
            case 'float':
                if (Helpers::is_valid_decimal($value)) {
                    return (float)$value;
                } else {
                    return 0.00;
                }
                break;
            case 'bool':
            case 'boolean':
                // boolean values should be returned as true or false, but those values don't work well in databases
                return !!$value ? 1 : 0;
                break;
            case 'date':
            case 'datetime':
                if (Helpers::is_date($value)) {
                    return "'$value'";
                } else {
                    if ($type === 'datetime') {
                        return "'0000-00-00 00:00:00'";
                    } else {
                        return "'0000-00-00'";
                    }
                }
                break;
            case 'string':
            case 'varchar':
            case 'char':
            case 'enum':
            case 'text':
                // TODO: consider using something like addslashes()
                $value = str_replace("'", '', $value);

                return "'$value'";
                break;
            case 'NULL':
                return 'NULL';
                break;
            case 'array':
            case 'object':
            case 'resource':
                $this->Log->write('cannot handle ' . $type, Log::LOG_LEVEL_WARNING, $value);
                break;
            default:
                $this->Log->write('unknown type ' . $type, Log::LOG_LEVEL_WARNING, $value);
                break;
        }
        $this->Log->write('error processing value for type', Log::LOG_LEVEL_USER, $type);

        return null;
    }


    /**
     * Determine the type of the value based on the database field type and pass that to Db::quote().
     *
     * @param string $table
     * @param string $field
     * @param null $value
     * @return mixed
     * @uses Db::fieldStructure()
     * @uses Db::quote()
     */
    public function quoteField($table = '', $field = '', $value = null)
    {
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        $structure = $this->fieldStructure($table, $field);
        // check for field accepting null values
        if (strtoupper($structure['null']) === 'YES' && $value === null) {
            $type = 'NULL';
        } else {
            $type = $structure['type'];
        }
        $this->Log->write($table . '.' . $field . ' type', Log::LOG_LEVEL_USER, $type);

        return $this->quote($value, $type);
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
        if (!Helpers::is_string_ne($table)) {
            $this->Log->write('table name not provided', Log::LOG_LEVEL_WARNING);

            return $this->table_structure;
        }

        // use a cached value to avoid querying the database and processing again
        if (array_key_exists($table, $this->table_structure) && Helpers::is_array_ne($this->table_structure[$table])) {
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
            if ($field === null) {
                continue;
            }
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
                    $key = 'primary';
                    break;
                case 'MUL':
                    $key = 'foreign';
                    break;
                case 'UNI':
                    $key = 'unique';
                    break;
                default:
                    $key = $field['Key'];
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
                'null' => $field['Null'],
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
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        // input validation
        if (!Helpers::is_string_ne($table) || !Helpers::is_array_ne($pairs)) {
            $this->Log->write('table OR pairs is empty', Log::LOG_LEVEL_WARNING);

            return false;
        }

        if (!Helpers::is_array_ne($where)) {
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
        if (Helpers::is_valid_int($limit, true)) {
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
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);
        try {
            if ($this->transaction_started) {
                $this->commit();
            }
            $this->transaction_started = $this->dbh->beginTransaction();
            $this->writeQueryParameters('/* BEGIN */', null);
        } catch (\Exception $ex) {
            $this->Log->exception($ex);
        }
    }


    /**
     * Commit transaction
     *
     * @uses Db::$dbh
     */
    public function commit()
    {
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);
        if ($this->transaction_started) {
            $this->dbh->commit();
            $this->writeQueryParameters('/* COMMIT */', null);
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
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);
        if ($this->transaction_started) {
            $this->dbh->rollBack();
            $this->writeQueryParameters('/* ROLLBACK */', null);
            $this->transaction_started = false;
        }
    }


    /**
     * Get ENUM values as an array for a field in a table.
     *
     * @param string $table
     * @param string $field
     * @return array
     * @see http://stackoverflow.com/questions/2350052/how-can-i-get-enum-possible-values-in-a-mysql-database#answer-11429272
     */
    protected function getEnumValues($table = '', $field = '')
    {
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        // input validation
        if (!Helpers::is_string_ne($table)) {
            $this->Log->write('table name is invalid', Log::LOG_LEVEL_WARNING);

            return false;
        }

        if (!Helpers::is_string_ne($field)) {
            $this->Log->write('field is invalid', Log::LOG_LEVEL_WARNING);

            return false;
        }

        $this->tableStructure($table);

        //if (!in_array($field, $this->table_structure[$table])) {
        if (!array_key_exists($field, $this->table_structure[$table])) {
            $this->Log->write('field ' . $field . ' is not in table ' . $table, Log::LOG_LEVEL_WARNING, $this->table_structure);

            return false;
        }
        // end input validation

        // get column definitions for the field of the table
        $sql = "SHOW COLUMNS\n  FROM {$table}\n  WHERE Field = '{$field}'";
        $row = $this->query($sql, null, 'first');

        if (!$row) {
            $this->Log->write('error getting result for query', Log::LOG_LEVEL_WARNING, $row);

            return false;
        }

        if (!array_key_exists('Type', $row)) {
            $this->Log->write('Type was not found in SHOW COLUMNS', Log::LOG_LEVEL_WARNING);

            return false;
        }

        $type = $row['Type'];

        if (preg_match("/^enum\(\'(.*)\'\)$/", $type, $matches)) {
            // get ENUM values
            if (isset($matches[1])) {
                return explode("','", $matches[1]);
            } else {
                $this->Log->write('Could not get group of ENUM values', Log::LOG_LEVEL_WARNING);

                return false;
            }
        } else {
            $this->Log->write('Type is not ENUM', Log::LOG_LEVEL_WARNING, $type);

            return false;
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
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        if (!Helpers::is_array_ne($parameters)) {
            $this->Log->write('parameters is empty', Log::LOG_LEVEL_WARNING, Helpers::get_call_string());

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
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        if (!Helpers::is_array_ne($conditions)) {
            $this->Log->write('no conditions provided', Log::LOG_LEVEL_WARNING);

            return false;
        }

        $params = array();
        $sql = PHP_EOL . '  WHERE (';
        $line_end = PHP_EOL . '    ' . $conjunction . ' ';
        $i = 0;
        foreach ($conditions as $fieldop => $value) {
            if (strstr($fieldop, ':')) {
                list($field, $op) = explode(':', $fieldop);
            } else {
                $field = $fieldop;
                $op = null;
            }
            if (!in_array(strtoupper($op), array('IN', 'NOT IN')) && Helpers::is_array_ne($value)) {
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
                        $sql .= $field . ' ' . $op . ' (';
                        $sql .= implode(', ', array_fill(0, count($vvalue), '?'));
                        $sql .= ')';
                        $params = array_merge($params, array_values($vvalue));
                    } else {
                        if ($op === null || !Helpers::is_string_ne($op)) {
                            $op = '=';
                        }
                        $sql .= $field . ' ' . $op . ' ?';
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
                    $sql .= $field . ' ' . $op . ' (';
                    $sql .= implode(', ', array_fill(0, count($value), '?'));
                    $sql .= ')';
                    $params = array_merge($params, array_values($value));
                } else {
                    if ($op === null || !Helpers::is_string_ne($op)) {
                        $op = '=';
                    }
                    $sql .= $field . ' ' . $op . ' ?';
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
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        if (!Helpers::is_string_ne($file) || !is_file($file) || !file_exists($file)) {
            $this->Log->write('file is not a string or does not exist for writing', Log::LOG_LEVEL_WARNING);

            return false;
        }

        return file_put_contents($file, $content . PHP_EOL, FILE_APPEND);
    }


    /**
     * Replace bound parameter placeholders with parameters and write query to a file.
     * WARNING: This method uses more memory and time. Only use it if necessary and NOT on production.
     *
     * @param string $sql
     * @param array $params
     * @return string
     * @see http://php.net/manual/en/pdostatement.debugdumpparams.php#113400
     */
    private function writeQueryParameters($sql = '', $params = array())
    {
        // @todo: ONLY ENABLE THIS METHOD IF NEEDED FOR DEBUGGING
        if (true) {
            return true;
        }
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        if (Helpers::is_array_ne($params)) {
            foreach ($params as $v) {
                $v = str_replace('?', '~', $this->quote($v));
                $sql = preg_replace('/\?/', $v, $sql, 1);
            }
            // free some memory
            unset($params);
        }

        // check for comment
        if (substr($sql, 0, 2) === '/*' && substr($sql, -2) === '*/') {
            // this is a multi-line comment
            $mult = strstr($sql, 'SQLSTATE') ? 4 : 2;
            $query = $sql . str_repeat(PHP_EOL, $mult);
        } else {
            $query = rtrim($sql, ';' . PHP_EOL) . ';' . PHP_EOL . PHP_EOL;
        }
        // free some memory
        unset($sql);

        return file_put_contents(ASSETS_DIR . 'sql/queries_' . date('Y-m-d') . '.sql', $query, FILE_APPEND);
    }
}