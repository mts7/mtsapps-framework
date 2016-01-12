<?php
namespace mtsapps;


class Validate
{
    protected $error_message = '';


    /**
     * Validate an email address, assuming the user name has valid characters in it.
     *
     * @param string $email Email address
     * @param bool $network Check to see if the server portion exists and is reachable.
     * @return bool
     */
    public function email($email = '', $network = false)
    {
        if (!is_string_ne($email)) {
            $this->error_message = 'Empty input: email';

            return false;
        }

        if (substr_count($email, '@') > 1) {
            $this->error_message = 'More than 1 @ in email';

            return false;
        }

        list($user, $server) = explode('@', $email);

        if (!is_string_ne($user)) {
            $this->error_message = 'Empty input: email user portion';

            return false;
        }

        if (!is_string_ne($server)) {
            $this->error_message = 'Empty input: email server portion';

            return false;
        }

        if (strlen($server) < 3) {
            $this->error_message = 'Invalid length: email server portion';

            return false;
        }

        $valid = true;

        if ($network === true) {
            $valid = checkdnsrr($server, 'MX');

            if (!$valid) {
                $this->error_message = 'Network error: server portion does not resolve';
            }
        }

        return $valid;
    }


    /**
     * Return last error message.
     *
     * @return string
     */
    public function error()
    {
        return $this->error_message;
    }


    /**
     * Validate number is an integer.
     *
     * @param int $int Integer to verify
     * @param int $min Minimum integer to check
     * @param int $max Maximum integer to check
     * @return bool
     */
    public function integer($int = null, $min = null, $max = null)
    {
        $valid = is_int($int) || (is_numeric($int) && $int + 0 === (int)$int);

        if (is_int($min) || (is_numeric($min) && $min + 0 === (int)$min) && $min !== null) {
            $valid = $valid && $int >= $min;
        }

        if (is_int($max) || (is_numeric($max) && $max + 0 === (int)$max) && $max !== null) {
            $valid = $valid && $max >= $int;
        }

        return $valid;
    }


    /**
     * Validate United States phone number.
     *
     * @param string $phone
     * @param bool $strict
     * @return bool
     */
    public function phone($phone = '', $strict = false)
    {
        if (!is_string_ne($phone)) {
            $this->error_message = 'Empty input: phone';

            return false;
        }

        // phone should be 10 digits
        if ($strict === true) {
            $valid = strlen($phone) === 10;
            if (!$valid) {
                $this->error_message = 'Phone is not 10 digits';
            }
        } else {
            // strip all non-numeric values
            $phone = preg_replace('[\D]', '', $phone);
            $valid = strlen($phone) === 10;
            if (!$valid) {
                $this->error_message = 'Stripped phone is not 10 digits';
            }
        }

        // verify this is not a 555 number if it is already valid
        if ($valid) {
            $valid = substr($phone, 3, 3) !== '555';
            if (!$valid) {
                $this->error_message = 'Phone contains xxx555xxxx';
            }
        }

        return $valid;
    }
}