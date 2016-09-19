<?php
/**
 * @author Mike Rodarte
 * @version 1.02
 */

/**
 * mtsapps namespace
 */
namespace mtsapps;

/**
 * Class Login
 *
 * @package mtsapps
 */
class Login extends Db
{
    /**
     * @var array
     */
    private $public_properties = array(
        'log_level',
        'table',
    );

    /**
     * @var string
     */
    private $table = 'login';


    /**
     * Login constructor.
     *
     * @param array $params
     */
    public function __construct($params = array())
    {
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        if (Helpers::is_array_ne($params)) {
            if (array_key_exists('log_level', $params)) {
                $this->logLevel($params['log_level']);
            }
            $this->Log->write('params', Log::LOG_LEVEL_USER, $params);

            // set properties based on parameters using magic method
            foreach ($params as $name => $value) {
                $this->__set($name, $value);
            }
        }

        parent::__construct($params);
    }


    /**
     * Add an entry to the login tracking table.
     *
     * @param int $user_id
     * @param int $success
     * @param string $message
     * @return bool|mixed
     */
    public function add($user_id = 0, $success = 0, $message = '')
    {
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        // input validation
        if (!Helpers::is_valid_int($user_id, true)) {
            $this->Log->write('user_id is not a valid integer', Log::LOG_LEVEL_WARNING);

            return false;
        }

        if (!Helpers::is_valid_int($success, true)) {
            $this->Log->write('success is not a valid integer', Log::LOG_LEVEL_WARNING);

            return false;
        }

        if (!in_array($success, array(0, 1))) {
            $this->Log->write('success is not 0 or 1', Log::LOG_LEVEL_WARNING, $success);

            return false;
        }

        if (!is_string($message)) {
            $message = Helpers::get_string($message);
        }

        // get values from PHP
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $user_agent = $_SERVER['HTTP_USER_AGENT'];
        $session_id = session_id();
        if (!Helpers::is_string_ne($session_id)) {
            session_start();
            $session_id = session_id();
        }

        // prepare values for insert
        $pairs = array(
            'user_id' => $user_id,
            'ip_address' => $ip_address,
            'user_agent' => $user_agent,
            'success' => $success,
            'session_id' => $session_id,
            'message' => $message,
        );

        $insert_id = $this->insert($this->table, $pairs);

        $this->Log->write('insert_id', Log::LOG_LEVEL_USER, $insert_id);

        return $insert_id;
    }


    /**
     * Get failed attempts for an user, either grabbing all failed or failed since the last success.
     *
     * @param int $user_id
     * @param bool $since_success Failures since the last success if true, or all failed attempts if false
     * @return bool|int
     */
    public function failedAttempts($user_id = 0, $since_success = false)
    {
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        if (!Helpers::is_valid_int($user_id, true)) {
            $this->Log->write('invalid user id', Log::LOG_LEVEL_WARNING, $user_id);

            return false;
        }

        $attempts = $this->getAll($user_id);
        $failed = 0;
        foreach($attempts as $attempt) {
            if ($since_success && $attempt['success'] == 1) {
                break;
            }
            if ($attempt['success'] == 0) {
                $failed++;
            }
        }

        return $failed;
    }


    /**
     * Get all login attempts for an user.
     *
     * @param int $user_id
     * @return bool|mixed
     */
    public function getAll($user_id = 0)
    {
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        if (!Helpers::is_valid_int($user_id, true)) {
            $this->Log->write('invalid user id', Log::LOG_LEVEL_WARNING, $user_id);

            return false;
        }

        $sql = 'SELECT *' . PHP_EOL;
        $sql .= '  FROM ' . $this->table . PHP_EOL;
        $sql .= '  WHERE user_id = ?' . PHP_EOL;
        $sql .= '  ORDER BY add_date DESC' . PHP_EOL;

        $rows = $this->query($sql, array($user_id), 'iterator');
        $this->Log->write('found ' . count($rows) . ' rows', Log::LOG_LEVEL_USER);

        return $rows;
    }


    /**
     * Get last login attempt.
     *
     * @param int $user_id
     * @return bool|mixed
     */
    public function getLast($user_id = 0)
    {
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        if (!Helpers::is_valid_int($user_id, true)) {
            $this->Log->write('invalid user id', Log::LOG_LEVEL_WARNING, $user_id);

            return false;
        }

        $sql = 'SELECT *' . PHP_EOL;
        $sql .= '  FROM ' . $this->table . PHP_EOL;
        $sql .= '  WHERE user_id = ?' . PHP_EOL;
        $sql .= '  ORDER BY add_date DESC' . PHP_EOL;
        $sql .= '  LIMIT 1';

        $row = $this->query($sql, array($user_id), 'single');
        $this->Log->write('found row', Log::LOG_LEVEL_USER, $row);

        return $row;
    }


    /**
     * Get the last successful login attempt from the database.
     *
     * @param int $user_id
     * @return bool|mixed
     */
    public function lastSuccessful($user_id = 0)
    {
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        if (!Helpers::is_valid_int($user_id, true)) {
            $this->Log->write('invalid user id', Log::LOG_LEVEL_WARNING, $user_id);

            return false;
        }

        $sql = 'SELECT *' . PHP_EOL;
        $sql .= '  FROM ' . $this->table . PHP_EOL;
        $sql .= '  WHERE user_id = ?' . PHP_EOL;
        $sql .= '    AND success = 1' . PHP_EOL;
        $sql .= '  ORDER BY add_date DESC' . PHP_EOL;
        $sql .= '  LIMIT 1';

        $row = $this->query($sql, array($user_id), 'single');
        $this->Log->write('found row', Log::LOG_LEVEL_USER, $row);

        return $row;
    }


    /**
     * Magic method getter that uses a white list for properties to disclose
     *
     * @param string $name
     * @return bool|null
     */
    public function __get($name = '')
    {
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        if (!Helpers::is_string_ne($name)) {
            $this->Log->write('name not set', Log::LOG_LEVEL_WARNING);

            return false;
        }

        $property = Helpers::lower_underscore($name);

        if (!property_exists($this, $property)) {
            $this->Log->write($property . ' does not exist as a property', Log::LOG_LEVEL_WARNING);

            return null;
        } elseif (!in_array($property, $this->public_properties)) {
            $this->Log->write('property is not in public properties white list', Log::LOG_LEVEL_WARNING, $property);

            return null;
        } else {
            $this->Log->write('accessed property', Log::LOG_LEVEL_USER, $property);

            return $this->$property;
        }
    }


    /**
     * Magic method setter
     *
     * @param string $name
     * @param $value
     * @return bool
     */
    public function __set($name = '', $value)
    {
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        if (!Helpers::is_string_ne($name)) {
            $this->Log->write('name not set', Log::LOG_LEVEL_WARNING);

            return false;
        }

        $method = Helpers::upper_camel($name);

        if (method_exists($this, $method)) {
            $this->Log->write('method ' . $method . ' exists', Log::LOG_LEVEL_USER);
            $this->$method($value);
        } else {
            $property = Helpers::lower_underscore($name);

            if (!property_exists($this, $property)) {
                $this->Log->write($property . ' does not exist as a property', Log::LOG_LEVEL_WARNING);

                return false;
            }

            $type_value = gettype($value);
            $type_property = gettype($this->$property);

            if ($type_value === $type_property) {
                $this->Log->write('types match for ' . $property, Log::LOG_LEVEL_USER, $type_property);
                $this->$property = $value;
            } else {
                $this->Log->write($type_value . ' != ' . $type_property . '; consider type casting', Log::LOG_LEVEL_WARNING);

                return false;
            }
        }

        return true;
    }
}