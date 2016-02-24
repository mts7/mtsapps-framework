<?php
/**
 * Generate constants PHP file outside of namespace directories, based on values from the constant table.
 *
 * @author Mike Rodarte
 * @version 1.03
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
class Constants extends Db
{
    /**
     * @var string $directory Directory where the output file should be stored
     */
    private $directory = '';

    /**
     * @var string $file_name Name of generated constants file
     */
    private $file_name = 'gen_constants.php';

    /**
     * @var string $constant_table Table containing the list of constant tables and fields
     */
    private $constant_table = 'constant_build_list';

    /**
     * @var string $php PHP string with define commands
     */
    private $php = '';

    /**
     * @var Log
     */
    protected $Log = null;


    /**
     * Constants constructor.
     *
     * @param array $params
     * @uses Constants::setFileName()
     */
    public function __construct($params = array())
    {
        $file = 'db_' . date('Y-m-d') . '.log';
        $this->Log = new Log([
            'file' => $file,
            'log_directory' => LOG_DIR,
        ]);
        $log_file = $this->Log->file();
        if ($log_file !== $file) {
            $this->Log->write('could not set file properly', Log::LOG_LEVEL_WARNING);
        }

        $this->Log->write('Constants::__construct()', Log::LOG_LEVEL_SYSTEM_INFORMATION);

        // constants file must be outside of the namespace
        $this->directory = realpath(__DIR__ . '/../..');
        $this->Log->write('set directory to ' . $this->directory, Log::LOG_LEVEL_USER);

        if (Helpers::is_array_ne($params)) {
            if (array_key_exists('log_level', $params)) {
                $this->Log->logLevel($params['log_level']);
            }
            $this->Log->write('params is an array', Log::LOG_LEVEL_USER);

            if (array_key_exists('file_name', $params)) {
                $this->setFileName($params['file_name']);
            }

            if (array_key_exists('table_name', $params) && Helpers::is_string_ne($params['table_name'])) {
                $this->constant_table = $params['table_name'];
            }

            if (array_key_exists('log_file', $params) && Helpers::is_string_ne($params['log_file'])) {
                $this->Log->file($params['log_file']);
            }

            // set up database parameters, if needed
            if (!array_key_exists('host', $params)) {
                $params['host'] = DB_HOST;
            }
            if (!array_key_exists('user', $params)) {
                $params['user'] = DB_USER;
            }
            if (!array_key_exists('pass', $params)) {
                $params['pass'] = DB_PASS;
            }
            if (!array_key_exists('db', $params)) {
                $params['db'] = DB_DB;
            }
        } else {
            $this->Log->write('setting parameters from system constants', Log::LOG_LEVEL_USER);

            // set up database parameters
            $params = array(
                'host' => DB_HOST,
                'user' => DB_USER,
                'pass' => DB_PASS,
                'db' => DB_DB,
            );
        }

        $this->buildTopContent();

        // initialize database class
        $this->Log->write('initialize Db', Log::LOG_LEVEL_SYSTEM_INFORMATION);
        parent::__construct($params);
        $this->Log->write('done constructing', Log::LOG_LEVEL_SYSTEM_INFORMATION);
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
        $this->Log->write('Constants::build()', Log::LOG_LEVEL_SYSTEM_INFORMATION);

        $this->Log->write('getting constant list', Log::LOG_LEVEL_USER);
        $rows = $this->getConstantList();

        if (!Helpers::is_array_ne($rows)) {
            $this->Log->write('could not get constant list', Log::LOG_LEVEL_WARNING);

            return false;
        }
        $this->Log->write('have constant list', Log::LOG_LEVEL_USER);

        // write after each call to generate to decrease memory consumption on server
        foreach ($rows as $i => $row) {
            $this->generate($row);
            $bytes = $this->write();

            if ($bytes === false) {
                $this->Log->write($i . ': could not write PHP to file', Log::LOG_LEVEL_WARNING);
            }
        }

        return true;
    }


    /**
     * Set file name if it is a valid file name
     *
     * @param string $file
     * @return bool
     * @uses space_to_underscore()
     */
    public function setFileName($file = '')
    {
        $this->Log->write('Constants::setFileName()', Log::LOG_LEVEL_SYSTEM_INFORMATION);

        // input validation
        if (!Helpers::is_string_ne($file)) {
            $this->Log->write('file is not valid', Log::LOG_LEVEL_WARNING);

            return false;
        }

        $ext = substr($file, -3);
        if (strtolower($ext) !== 'php') {
            $this->Log->write('extension is not php, but is ' . $ext, Log::LOG_LEVEL_WARNING);

            return false;
        }

        $this->Log->write('setting file name after changing spaces to _ and making it lower case', Log::LOG_LEVEL_USER);
        $this->file_name = strtolower(Helpers::space_to_underscore($file));

        return true;
    }


    /**
     * Set the top content of the PHP file
     *
     * @return bool|int
     */
    private function buildTopContent()
    {
        $file_path = $this->directory . DIRECTORY_SEPARATOR . $this->file_name;
        $this->Log->write('file_path', Log::LOG_LEVEL_USER, $file_path);

        // build file contents
        $contents = '<?php' . PHP_EOL;
        $contents .= '/' . str_repeat('*', 79) . PHP_EOL . PHP_EOL;
        $contents .= ' This file is automatically generated by Constants::build().' . PHP_EOL;
        $contents .= ' DO NOT EDIT THIS FILE DIRECTLY' . PHP_EOL . PHP_EOL;
        $contents .= ' ' . str_repeat('*', 78) . '/' . PHP_EOL . PHP_EOL;

        // write contents to file
        return file_put_contents($file_path, $contents);
    }


    /**
     * Generate PHP string for this table and field.
     *
     * @param array $array Row of results from constant list
     * @return bool|int
     * @uses Db::query()
     * @uses Db::quote()
     */
    private function generate($array = array())
    {
        $this->Log->write('Constants::generate()', Log::LOG_LEVEL_SYSTEM_INFORMATION);

        // input validation
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
        $rows = $this->query($sql, array(), 'array');

        if (!Helpers::is_array_ne($rows)) {
            $this->Log->write('could not find rows from query', Log::LOG_LEVEL_WARNING);

            return false;
        }
        $this->Log->write('found rows for generate query', Log::LOG_LEVEL_USER);

        // build PHP string
        $php = PHP_EOL . '/**' . PHP_EOL;
        $php .= ' * ' . $table . '.' . $field . PHP_EOL;
        $php .= ' */' . PHP_EOL;
        foreach ($rows as $row) {
            // prepare constant name (upper case, underscores instead of spaces, no multiple underscores together)
            $val = strtoupper(Helpers::space_to_underscore($prefix . '_' . $field));
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
     * @return mixed
     */
    private function getConstantList()
    {
        $this->Log->write('Constants::getConstantList()', Log::LOG_LEVEL_SYSTEM_INFORMATION);

        $sql = 'SELECT table_name, name_field, value_field, prefix, type';
        $sql .= '  FROM ' . $this->constant_table;
        $this->Log->write('sql', Log::LOG_LEVEL_USER, $sql);

        $rows = $this->query($sql, array(), 'array');
        $this->Log->write('result of query', Log::LOG_LEVEL_USER, gettype($rows));

        return $rows;
    }


    /**
     * Write the constants to a PHP file.
     *
     * @return bool|int
     */
    private function write()
    {
        $this->Log->write('Constants::write()', Log::LOG_LEVEL_SYSTEM_INFORMATION);

        // input validation
        if (!Helpers::is_string_ne($this->php)) {
            $this->Log->write('php is not a string', Log::LOG_LEVEL_WARNING);

            return false;
        }

        // build file path
        $file_path = $this->directory . DIRECTORY_SEPARATOR . $this->file_name;
        $this->Log->write('file_path', Log::LOG_LEVEL_USER, $file_path);

        // write contents to file
        $bytes = file_put_contents($file_path, $this->php . PHP_EOL, FILE_APPEND);

        if ($bytes === false) {
            $this->Log->write('error writing contents to file', Log::LOG_LEVEL_WARNING);
        } else {
            $this->Log->write('file wrote ' . $bytes . ' bytes', Log::LOG_LEVEL_USER);
            $this->php = '';
        }

        return $bytes;
    }
}