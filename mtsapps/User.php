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
 * Class User
 *
 * @package mtsapps
 */
class User extends Db
{
    /**
     * @var int
     */
    private $id = 0;

    /**
     * @var string
     */
    protected $id_field = 'id';

    /**
     * @var bool
     */
    private $logged_in = false;

    /**
     * Max life time between transactions in seconds
     *
     * @var int
     */
    private $max_life = 1800;

    /**
     * @var int
     */
    private $minimum_password_strength = 0;

    /**
     * user name
     *
     * @var string
     */
    protected $name = '';

    /**
     * @var string
     */
    private $name_field = 'user';

    /**
     * @var string
     */
    private $salt = 'd9cb5aE!34eB075^19!e6E54';

    /**
     * @var string
     */
    protected $table = 'user';

    /**
     * @var Login
     */
    private $Login = null;

    /**
     * @var array
     */
    private $user_data = array();


    /**
     * User constructor.
     *
     * @param array $params
     */
    public function __construct($params = array())
    {
        $this->Login = new Login($params);

        if (is_array_ne($params)) {
            if (array_key_exists('user_id', $params)) {
                $this->id = $params['user_id'];
            } elseif (array_key_exists('user_id', $_SESSION)) {
                $this->id = $_SESSION['user_id'];
            }

            if (array_key_exists('user_name', $params)) {
                $this->name = $params['user_name'];
            } elseif (array_key_exists('user_name', $_SESSION)) {
                $this->name = $_SESSION['user_name'];
            }

            $this->load();
        }

        // TODO: set parameters to properties

        parent::__construct($params);
    }


    /**
     * Change a password from an old one to a new one (with a confirmation password).
     *
     * @param string $old
     * @param string $new
     * @param string $confirm
     * @return bool|mixed
     */
    public function changePassword($old = '', $new = '', $confirm = '')
    {
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        if (!$this->isValidUser()) {
            $this->Log->write('invalid user', Log::LOG_LEVEL_WARNING);

            return false;
        }

        $db_pass = $this->user_data['password'];
        $old_hash = Password::hash($old, $this->salt);

        if ($db_pass !== $old_hash) {
            $this->Log->write('entered password and old password do not match', Log::LOG_LEVEL_WARNING);

            return false;
        }

        if ($new !== $confirm) {
            $this->Log->write('new password does not match confirm password', Log::LOG_LEVEL_WARNING);

            return false;
        }

        $strength = Password::strength($new);
        $this->Log->write('password strength', Log::LOG_LEVEL_USER, $strength);

        if ($strength < $this->minimum_password_strength) {
            $this->Log->write('strength ' . $strength . ' is less than the required strength of '
                . $this->minimum_password_strength, Log::LOG_LEVEL_WARNING);

            return false;
        }

        $new_hash = Password::hash($new, $this->salt);

        $saved = $this->savePassword($new_hash);

        // email the user
        if ($saved) {
            $message = 'Your password has been changed. If you did not request this, please reset your password.';
        } else {
            $message = 'Your password was not successfully changed, though the request was made.';
        }
        $to = $this->user_data['email'];
        $subject = 'Changed Password on ' . $_SERVER['HTTP_HOST'];

        mts_mail($to, $subject, $message);

        return $saved;
    }


    public function hasPermission($action)
    {

    }


    /**
     * Return the user data from the database.
     *
     * @return array
     */
    public function information()
    {
        $data = $this->getUser();
        unset($data['password']);

        return $data;
    }


    /**
     * Load the user session (if the user is logged in within the appropriate amount of time).
     */
    public function load()
    {
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        if (!$this->isValidUser()) {
            $this->Log->write('invalid user', Log::LOG_LEVEL_WARNING);

            return;
        }

        $this->resetSession();

        if ($this->user_data['logged_in'] != 1) {
            $this->Log->write('user is not logged in', Log::LOG_LEVEL_WARNING, $this->user_data['logged_in']);

            return;
        }

        // determine last login time
        $last_login = $this->Login->getLast($this->user_data['id']);
        if ($last_login['message'] === 'success') {
            $login_time = strtotime($last_login['add_date']);

            // set logged in if time is within time tolerance
            $this->logged_in = time() - $login_time < $this->max_life;
        } else {
            $this->logged_in = false;
        }

        // set session variables
        $this->setSession();

        $this->updateLoggedIn();
    }


    /**
     * Log in the user.
     *
     * @param string $password
     * @return bool
     */
    public function login($password = '')
    {
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        $this->resetSession();

        $this->getUser();

        if (!is_string_ne($password)) {
            $this->Log->write('password not provided', Log::LOG_LEVEL_WARNING);
            $this->Login->add($this->id, 0, 'no password');

            return false;
        }

        if (Password::hash($password, $this->salt) === $this->user_data['password'] && $this->user_data['active'] == 1) {
            // set class property
            $this->logged_in = true;

            // set session variables
            $this->setSession();

            // update logged in value in database
            $updated = $this->updateLoggedIn();

            if (!$updated) {
                $this->Log->write('could not update database and set user to logged in', Log::LOG_LEVEL_WARNING);
                $this->Login->add($this->id, 0, 'database error');
                $this->resetSession();

                return false;
            }

            // add login attempt to database
            $this->Login->add($this->id, 1, 'success');
        } else {
            $this->Log->write('passwords do not match or user is not active', Log::LOG_LEVEL_WARNING);

            $this->Login->add($this->id, 0, 'password mismatch');
        }

        return $this->logged_in;
    }


    /**
     * Log out the user.
     *
     * @return bool
     */
    public function logout()
    {
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        if (!is_valid_int($this->id, true)) {
            $this->Log->write('no user to log out', Log::LOG_LEVEL_WARNING);

            return false;
        }

        // reset session
        $this->resetSession();

        $updated = $this->updateLoggedIn();
        $this->Log->write('logout updated', Log::LOG_LEVEL_USER, $updated);

        return true;
    }


    /**
     * Reset the password to a randomly generated password and send an email to the user.
     *
     * @return bool|string
     */
    public function resetPassword()
    {
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        // input validation
        if (!$this->isValidUser()) {
            $this->Log->write('invalid user', Log::LOG_LEVEL_WARNING);

            return false;
        }

        if (!is_string_ne($this->user_data['email'])) {
            $this->Log->write('no email address for user', Log::LOG_LEVEL_WARNING);

            return false;
        }

        $new_password = Password::generateRandom(16);

        $saved = $this->savePassword($new_password);

        // prepare message stuff
        $nl = "\r\n";

        $last_successful = $this->Login->lastSuccessful($this->id);

        $to = $this->user_data['email'];
        $subject = 'Password Reset Request on ' . $_SERVER['HTTP_HOST'];
        if ($saved) {
            $message = 'Your password has been reset. This was requested by ' . $this->name . ' on ' . date('Y-m-d H:i:s') . '.' . $nl;
            $message .= 'IP Address: ' . $_SERVER['REMOTE_ADDR'] . $nl;
            $message .= 'User Agent: ' . $_SERVER['HTTP_USER_AGENT'] . $nl;
            $message .= 'Last Successful Login: ' . $last_successful['add_date'] . $nl;
            $message .= 'Number of Failed Attempts: ' . $this->Login->failedAttempts($this->id) . $nl;
            $message .= $nl . $nl;
            $message .= 'Your new password is ' . $nl . $nl;
            $message .= $new_password . $nl . $nl;
            $message .= 'Please log in to the website and change your password.' . $nl . $nl;
            $message .= 'Thank you.' . $nl;
        } else {
            $message = 'Your password was not reset. Please attempt to reset the password again.';
        }

        return mts_mail($to, $subject, $message);
    }


    public function save()
    {

    }


    /**
     * Get the user from the database and store in a property.
     *
     * @return array|bool|mixed
     */
    private function getUser()
    {
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        // use cached version
        if (is_array_ne($this->user_data)) {
            return $this->user_data;
        }

        // determine which field and value to use in the query
        if (is_valid_int($this->id, true)) {
            $where_field = $this->id_field;
            $value = $this->id;
        } elseif (is_string_ne($this->name)) {
            $where_field = $this->name_field;
            $value = $this->name;
        } elseif (array_key_exists('user_id', $_SESSION)) {
            $where_field = $this->id_field;
            $value = $_SESSION['user_id'];
        } elseif (array_key_exists('user_name', $_SESSION)) {
            $where_field = $this->name_field;
            $value = $_SESSION['user_name'];
        } else {
            $this->Log->write('cannot determine user data to use', Log::LOG_LEVEL_WARNING);

            return false;
        }

        // build query
        $sql = 'SELECT *' . PHP_EOL;
        $sql .= '  FROM ' . $this->table . PHP_EOL;
        $sql .= '  WHERE ' . $where_field . ' = ?';

        $row = $this->query($sql, array($value), 'first');

        if (!is_array_ne($row)) {
            return false;
        }

        $this->user_data = $row;
        $this->id = $row['id'];
        $this->name = $row['name'];

        return $row;
    }


    /**
     * Check for user being valid and then save the user data in a property.
     *
     * @return bool
     */
    private function isValidUser()
    {
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        // input validation
        if (!is_string_ne($this->name)) {
            $this->Log->write('no user', Log::LOG_LEVEL_WARNING);

            return false;
        }

        // session activity validation
        $now = microtime(true);
        if ($now - $_SESSION['last_activity'] >= $this->max_life) {
            $this->Log->write('session expired', Log::LOG_LEVEL_WARNING);
            $this->logout();

            return false;
        }

        $this->getUser();

        return $this->user_data !== false;
    }


    /**
     * Reset the session.
     */
    private function resetSession()
    {
        session_regenerate_id(true);
        $_SESSION = array();
        $_SESSION['last_activity'] = microtime(true);
        $this->logged_in = false;
    }


    /**
     * Save the password in the database.
     *
     * @param string $password
     * @return bool|mixed
     */
    private function savePassword($password = '')
    {
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        // input validation
        if (!is_string_ne($password)) {
            $this->Log->write('password must be provided', Log::LOG_LEVEL_WARNING);

            return false;
        }

        $pairs = array(
            'password' => $password,
        );

        $updated = $this->update($this->table, $pairs, array($this->id_field => $this->id));
        $this->Log->write('password saved', Log::LOG_LEVEL_USER, get_string($updated));

        return $updated;
    }


    /**
     * Set the session variables.
     */
    private function setSession()
    {
        $_SESSION['user_logged_in'] = $this->logged_in;
        $_SESSION['user_id'] = $this->user_data['id'];
        $_SESSION['user_name'] = $this->user_data['name'];
        $_SESSION['last_activity'] = microtime(true);
    }


    /**
     * Update the database with the current value of logged_in.
     *
     * @return bool|mixed
     */
    private function updateLoggedIn()
    {
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        $logged_in = $this->logged_in ? 1 : 0;
        $updated = $this->update($this->table, array('logged_in' => $logged_in), array('id' => $this->id));

        if ($updated) {
            $this->user_data['logged_in'] = $logged_in;
        }

        $this->Log->write('updated to ' . $logged_in, Log::LOG_LEVEL_USER, $updated);

        return $updated;
    }
}
