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
 * Class Password
 *
 * @package mtsapps
 */
class Password
{
    /**
     * Generate a pseudo-random password with letters, numbers, and symbols.
     *
     * @param int $length
     * @return string
     * @uses is_valid_int() from helpers.php
     */
    public static function generateRandom($length = 64)
    {
        // input validation
        if (!Helpers::is_valid_int($length, true)) {
            return '';
        }

        // generate a pseudo-random password
        $password = '';

        // these are used as variable variables in the for loop
        $all_lower_letters = 'abcdefghijklmnopqrstuvwxyz';
        $all_upper_letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $all_numbers = '0123456789';
        $all_symbols = '`~!@#$%^*()-_=+[{]}|:,.?';

        $types = array('lower_letters', 'upper_letters', 'numbers', 'symbols');

        $max_same = 3;
        $last_type = -1;
        $same_type = 0;

        for ($i = 0; $i < $length; $i++) {
            // determine the type of the character
            $type_index = rand(0, count($types) - 1);
            $type = 'all_' . $types[$type_index];

            if ($last_type === $type_index) {
                $same_type++;
            } else {
                $same_type = 0;
            }

            if ($same_type > 2) {
                $i--;
                continue;
            }

            // determine how many characters to use for type
            $num_same = rand(1, $max_same);
            for ($j = 0; $j < $num_same; $j++) {
                $password .= substr($$type, rand(0, strlen($$type) - 1), 1);
                if ($j > 0) {
                    $i++;
                }
            }

            $last_type = $type_index;

            if (strlen($password) >= $length) {
                break;
            }
        }

        return substr($password, 0, $length);
    }


    /**
     * Generate a hashed password with hexadecimal characters and symbols of the specified length.
     *
     * @param string $input User password or phrase to hash
     * @param int $length Output hashed length
     * @param bool $unique Make it unique (by time) or keep it the same, but hashed.
     * @return string
     */
    public static function generateFromInput($input = '', $length = 8, $unique = true)
    {
        // input validation
        if (!Helpers::is_string_ne($input)) {
            return false;
        }

        // initial variables
        $seed = !!$unique ? str_replace('.', '', microtime(true)) : 'mts7PasswordGenerator';
        $s_first = substr($seed, 0, 5);
        $s_last = substr($seed, 5);
        $symbols = array(0 => ')', 1 => '!', 2 => '@', 3 => '#', 4 => '$', 5 => '%', 6 => '^', 7 => '&', 8 => '*', 9 => '(',
            'a' => '`', 'b' => '~', 'c' => '[', 'd' => ']', 'e' => '|', 'f' => ',');
        $numbers = array('a' => '0', 'b' => '1', 'c' => '2', 'd' => '3', 'e' => '4', 'f' => '5');
        $letters = array('a', 'b', 'c', 'd', 'e', 'f');

        // hash input
        $temp = '';
        $hash = $s_first . $input;
        for ($i = 1; $i <= 6; $i++) {
            $val = $i * 3 - 1;
            $hash = md5($hash);
            $char = $hash[$val];
            $temp .= $char;
        }

        // pull out characters for new hash
        $hashed = md5($temp . $s_last);
        $new_pass = substr($hashed, 0, 6);
        $hash = md5($new_pass . $input);

        // extract characters from specific positions
        $pos = 2;
        $temp = '';
        for ($i = 1; $i <= $length; $i++) {
            // leave the loop if every character has been used
            if (strlen($temp) === strlen($hash)) {
                break;
            }

            $val = $pos;
            $char = $hash[$val];
            $temp .= $char;
            $pos += 4;

            // loop position
            if ($pos >= strlen($hash)) {
                $pos = $pos - strlen($hash);
            }
        }

        // convert duplicates to upper case or symbols
        $array = array();
        for ($i = 0; $i < strlen($temp); $i++) {
            $char = $temp[$i];
            if (!in_array($char, $array)) {
                $array[] = $char;
            } else {
                $sym = $symbols[$char];
                if (!$sym) {
                    // this is not a number
                    $upper = strtoupper($char);
                    if (!in_array($upper, $array)) {
                        $array[] = $upper;
                    } else {
                        // this is a character that is repeating->do something with it
                        $array[] = $char;
                    }
                } else {
                    if (!in_array($sym, $array)) {
                        $array[] = $sym;
                    } else {
                        // the number and symbol both exist
                        $array[] = $char;
                    }
                }
            }
        }
        $pass = implode('', $array);

        // make sure a capital letter is in the string
        if (!preg_match('/[A-Z]/', $pass)) {
            $matches = array();
            if (preg_match_all('/([a-z])/', $pass, $matches)) {
                $matches = $matches[1];
                $num_matches = count($matches);
                $index = $num_matches > 1 ? $num_matches - 1 : 0;
                $char = $matches[$index];
                $pos = strrpos($pass, $char);
                $pass[$pos] = strtoupper($char);
            } else {
                // there is no letter in the password
                $matches = array();
                if (preg_match_all('/([0-5])/', $pass, $matches)) {
                    $matches = $matches[1];
                    $num_matches = count($matches);
                    $index = $num_matches > 1 ? $num_matches - 1 : 0;
                    $char = $matches[$index];
                    $pos = strrpos($pass, $char);
                    $pass[$pos] = strtoupper($letters[$char]);
                }
            }
        }

        // make sure a symbol exists in the string
        if (!preg_match('/[!@#\$%\^&\*\(\)]/', $pass)) {
            // find a number
            $matches = array();
            if (preg_match_all('/([0-9])/', $pass, $matches)) {
                $matches = $matches[1];
                $num_matches = count($matches);
                $index = $num_matches > 1 ? $num_matches - 1 : 0;
                $char = $matches[$index];
                $pos = strrpos($pass, $char);
                $pass[$pos] = $symbols[$char];
            } else {
                // there is no number
                $matches = array();
                if (preg_match_all('/([a-f])/', $pass, $matches)) {
                    $matches = $matches[1];
                    $num_matches = count($matches);
                    $index = $num_matches > 1 ? $num_matches - 1 : 0;
                    $char = $matches[$index];
                    $pos = strrpos($pass, $char);
                    $number = $numbers[strtolower($char)];
                    $pass[$pos] = $symbols[$number];
                }
            }
        }

        // make sure a number exists
        if (!preg_match('/[\d]/', $pass)) {
            // there is no number
            $matches = array();
            if (preg_match_all('/([a-f])/', $pass, $matches)) {
                $matches = $matches[1];
                $num_matches = count($matches);
                $index = $num_matches > 3 ? $num_matches - 3 : 0;
                $char = $matches[$index];
                $pos = strrpos($pass, $char);
                $number = $numbers[strtolower($char)];
                $pass[$pos] = $numbers[$number];
            }
        }

        // make sure a lowercase letter exists
        // there might be a string like 2F@@@@@@ or 2F@22222
        if (!preg_match('/[a-z]/', $pass)) {
            // there is no lower case letter
            $matches = array();
            if (preg_match_all('/([0-9])/', $pass, $matches)) {
                $matches = $matches[1];
                $num_matches = count($matches);
                $index = $num_matches > 2 ? $num_matches - 2 : 0;
                $num = $matches[$index];
                $pos = strrpos($pass, $num);
                if ($num > 5) {
                    $num = $num - 5;
                }
                $letter = $letters[$num];
                if (!empty($letter)) {
                    $pass[$pos] = $letter;
                }
            }
        }

        return $pass;
    }


    /**
     * Hash a password with a given salt.
     *
     * @param string $password
     * @param string $salt
     * @return string
     * @uses is_string_ne() from helpers.php
     */
    public static function hash($password = '', $salt = '')
    {
        // input validation
        if (!Helpers::is_string_ne($password) || !Helpers::is_string_ne($salt)) {
            return '';
        }

        return md5(md5($salt) . 'mts7' . md5($password));
    }


    /**
     * Get the strength of the password. This checks length, characters, case, symbols, and repetition.
     *
     * @param string $password
     * @return int 0-100
     */
    public static function strength($password = '')
    {
        // input validation
        if (!is_string($password)) {
            return 0;
        }

        // set weights of different checks
        $weights = array(
            'repeat_all' => 0,
            'symbols_none' => 0,
            'case_same' => 0,
            'length_1' => 1,
            'char_num' => 1,
            'symbols_one' => 2,
            'repeat_some' => 2,
            'char_hex_letter' => 4,
            'char_alpha' => 6,
            'length_8' => 7,
            'symbols_few' => 8,
            'char_hex_letter_num' => 8,
            'length_13' => 10,
            'char_alpha_num' => 10,
            'symbols_all' => 13,
            'char_hex_letter_num_symbol' => 14,
            'symbols_some' => 16,
            'char_alpha_num_symbol' => 16,
            'length_24' => 22,
            'repeat_none' => 12,
            'case_mixed' => 14,
            'char_all' => 18,
            'symbols_many' => 27,
            'length_32' => 29,
        );

        // set initial strength
        $strength = 0;

        // check length
        // length: short (1-7), medium (8-12), long (13-23), super long (24-31), way super long (32+)
        $length = strlen($password);
        $length_breaks = array(32, 24, 13, 8, 1);
        foreach ($length_breaks as $break) {
            if ($length >= $break) {
                $strength += $weights['length_' . $break];
                break;
            }
        }

        // check character diversity
        // character diversity: a-fA-F, a-fA-F0-9, 0-9, a-zA-Z, a-zA-Z0-9, a-zA-Z0-9!@#$%^&*(), .+
        $char_patterns = array(
            'num' => '/([0-9]+)/',
            'hex_letter' => '/([a-fA-F]+)/',
            'hex_letter_num' => '/([a-fA-F0-9]+)/',
            'hex_letter_num_symbol' => '/([a-fA-F0-9!@#\$%\^&\*\(\)`~\[\]\|,\-_]+)/',
            'alpha' => '/([a-zA-Z]+)/',
            'alpha_num' => '/([a-zA-Z0-9]+)/',
            'alpha_num_symbol' => '/([a-zA-Z0-9!@#\$%\^&\*\(\)`~\[\]\|,\-_]+)/',
        );

        $char_score = 0;
        foreach ($char_patterns as $char_label => $pattern) {
            if (1 === preg_match($pattern, $password, $matches)) {
                if (isset($matches[1]) && $password === $matches[1]) {
                    $char_score = $weights['char_' . $char_label];
                    break;
                }
            }
        }
        if ($char_score === 0) {
            $char_score = $weights['char_all'];
        }
        $strength += $char_score;

        // check case
        // mixed case: upper|lower, mixed
        if (strtoupper($password) === $password || strtolower($password) === $password) {
            $strength += $weights['case_same'];
        } else {
            $strength += $weights['case_mixed'];
        }

        // check repeating characters
        // repeated character patterns: none, some, all
        if (preg_match('/([\w]{3})\1/', $password, $matches) === 1) {
            // a group of 3 characters repeats
            if ($password == $matches[1] . $matches[1]) {
                $strength += $weights['repeat_all'];
            } else {
                $strength += $weights['repeat_some'];
            }
        } elseif (preg_match('/(.)\\1{2}/', $password, $matches) === 1) {
            // 3 of the same character repeat
            if ($password === $matches[1] . $matches[1] . $matches[1]) {
                $strength += $weights['repeat_all'];
            } else {
                $strength += $weights['repeat_some'];
            }
        } else {
            // http://stackoverflow.com/questions/22627134/how-to-search-for-consecutive-repeated-characters-in-a-string-without-using-any#answer-22627274
            $has_repeats = false;
            $pieces = [];
            for ($i = 0; $i < $length - 2; $i++) {
                $piece = substr($password, $i, 3);
                if (array_key_exists($piece, $pieces)) {
                    $pieces[$piece] += 1;
                    if ($pieces[$piece] >= 3) {
                        $has_repeats = true;
                        break;
                    }
                } else {
                    $pieces[$piece] = 1;
                }
            }

            if (!$has_repeats) {
                $strength += $weights['repeat_none'];
            } else {
                $strength += $weights['repeat_some'];
            }
        }

        // check multiple symbols
        $all_symbols = '`~!@#$%^*()-_=+[{]}|:,.?';
        $symbols = str_split($all_symbols);
        $count = 0;
        foreach($symbols as $symbol) {
            if (strpos($password, $symbol) !== false) {
                $count++;
            }
        }

        if ($count == $length) {
            $strength += $weights['symbols_all'];
        } elseif ($count >= 5) {
            $strength += $weights['symbols_many'];
        } else if ($count >= 3) {
            $strength += $weights['symbols_some'];
        } else if ($count > 1) {
            $strength += $weights['symbols_few'];
        } elseif ($count == 1) {
            $strength += $weights['symbols_one'];
        } else {
            $strength += $weights['symbols_none'];
        }

        return $strength;
    }
}