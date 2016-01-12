<?php
/**
 * @author Mike Rodarte
 * @version 1.00
 */

/**
 * mtsapps namespace
 */
namespace mtsapps;

/**
 * Class PdfMerge
 *
 * @package mtsapps
 */
class PdfMerge
{
    /**
     * Debug flag: default to false
     *
     * @var bool
     */
    private $debug = false;

    /**
     * Author for PDF file
     *
     * @var string
     */
    private $author = 'Mike Rodarte';

    /**
     * Creator for PDF file
     *
     * @var string
     */
    private $creator = 'Mike Rodarte - mtsapps';

    /**
     * Directory name (for input and output)
     *
     * @var string
     */
    private $directory = __DIR__;

    /**
     * File names to merge into a single PDF file
     *
     * @var array
     */
    private $files = array();

    /**
     * Keywords for the PDF file
     *
     * @var array
     */
    private $keywords = array();

    /**
     * @var Log
     */
    protected $Log = null;

    /**
     * Initial log level
     *
     * @var int
     */
    private $log_level = 0;

    /**
     * Page orientation (P|L)
     *
     * @var string
     */
    private $orientation = 'P';

    /**
     * Output file name (without directory)
     *
     * @var string
     */
    private $output_file = 'output.pdf';

    /**
     * Owner password for PDF file
     *
     * @var string
     */
    private $owner_password = '';

    /**
     * Paper size
     *
     * @var string
     * @see TCPDF_STATIC::getPageSizeFromFormat()
     */
    private $paper_size = 'Letter';

    /**
     * Subject of the PDF file
     *
     * @var string
     */
    private $subject = 'mtsapps PDF Merge';

    /**
     * Title of the PDF file
     *
     * @var string
     */
    private $title = 'mtsapps PDF Merge';

    /**
     * User password for PDF file
     *
     * @var string
     */
    private $user_password = '';


    /**
     * PdfMerge constructor.
     *
     * @param array $params
     */
    public function __construct($params = array())
    {
        $this->Log = new Log([
            'file' => 'pdf-merge_' . date('Y-m-d') . '.log',
        ]);
        $this->logLevel(Log::LOG_LEVEL_WARNING);

        if (is_array_ne($params)) {
            if (array_key_exists('log_level', $params)) {
                $this->logLevel($params['log_level']);
            }
            $this->Log->write('params', Log::LOG_LEVEL_USER, $params);

            // set properties based on parameters
            foreach ($params as $param => $value) {
                $method = upper_camel($param);
                $this->$method($value);
            }
        }
    }


    /**
     * Merge PDF files together in the order they were entered to the $files array.
     *
     * @return string|bool
     * @see http://stackoverflow.com/questions/19855712/merge-multiple-pdf-files-into-one-in-php#answer-19856524
     * @uses FPDI_Protection::setSourceFile()
     * @uses FPDI_Protection::importPage()
     * @uses FPDI_Protection::addPage()
     * @uses FPDI_Protection::useTemplate()
     * @uses FPDI_Protection::SetAuthor()
     * @uses FPDI_Protection::SetCreator()
     * @uses FPDI_Protection::SetTitle()
     * @uses FPDI_Protection::SetSubject()
     * @uses FPDI_Protection::SetKeywords()
     * @uses FPDI_Protection::Output()
     */
    public function merge()
    {
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        // input validation
        if (!is_array_ne($this->files)) {
            $this->Log->write('Files is not an array. Enter at least 1 file before attempting to combine.', Log::LOG_LEVEL_WARNING);

            return false;
        }

        try {
            $pdf = new \FPDI_Protection();
            foreach ($this->files as $file) {
                $page_count = $pdf->setSourceFile($this->directory . $file);
                for ($j = 0; $j < $page_count; $j++) {
                    $template_index = $pdf->importPage(($j + 1), '/MediaBox');
                    $pdf->addPage($this->orientation, $this->paper_size);
                    $pdf->useTemplate($template_index, 0, 0, 0, 0, true);
                }

                // CAUTION: Delete the PDF input file if not debugging.
                if (!$this->debug) {
                    unlink($file);
                }
            }

            $pdf->SetAuthor($this->author);
            $pdf->SetCreator($this->creator);
            $pdf->SetTitle($this->title);
            $pdf->SetSubject($this->subject);
            $pdf->SetKeywords(implode(', ', $this->keywords));

            if (is_string_ne($this->user_password)) {
                $pdf->SetProtection(array('print', 'copy'), $this->user_password, $this->owner_password);
            }

            $output_path = $this->directory . $this->output_file;
            $this->Log->write('output file path', Log::LOG_LEVEL_USER, $output_path);
            $output = $pdf->Output('F', $output_path);

            if ($output === '') {
                // file was created
                return $output_path;
            } else {
                $this->Log->write('Error writing file to output', Log::LOG_LEVEL_WARNING, $output);
                return $output;
            }
        } catch (\Exception $ex) {
            $this->Log->exception($ex);

            return false;
        }
    }


    // BEGIN setters and getters
    /**
     * Add a file to the array.
     *
     * @param string $file
     * @return bool
     */
    public function addFile($file = '')
    {
        if (!is_string_ne($file) || !is_file($file)) {
            return false;
        }

        $this->files[] = $file;

        return true;
    }


    /**
     * Add a keyword to the array.
     *
     * @param string $keyword
     * @return bool
     */
    public function addKeyword($keyword = '')
    {
        if (!is_string_ne($keyword)) {
            return false;
        }

        $this->keywords[] = $keyword;

        return true;
    }


    /**
     * Set and/or get the author name.
     *
     * [@param] string|array|mixed $author
     * @return string
     */
    public function author()
    {
        $args = func_get_args();
        if (is_array_ne($args)) {
            if (is_string_ne($args[0])) {
                $this->author = $args[0];
            } elseif (is_array_ne($args[0])) {
                $this->author = implode(', ', $args[0]);
            } else {
                $this->author = get_string($args[0]);
            }
        }

        return $this->author;
    }


    /**
     * Set and/or get the creator.
     *
     * [@param] string|array|mixed $creator
     * @return string
     */
    public function creator()
    {
        $args = func_get_args();
        if (is_array_ne($args)) {
            if (is_string_ne($args[0])) {
                $this->creator = $args[0];
            } elseif (is_array_ne($args[0])) {
                $this->creator = implode(', ', $args[0]);
            } else {
                $this->creator = get_string($args[0]);
            }
        }

        return $this->creator;
    }


    /**
     * Set and/or get the debug flag.
     *
     * @return bool
     */
    public function debug()
    {
        $args = func_get_args();
        if (is_array_ne($args)) {
            if (is_bool($args[0])) {
                $this->debug = $args[0];
            } else {
                $this->debug = !!$args[0];
            }
        }

        return $this->debug;
    }


    /**
     * Set and/or get the directory path.
     *
     * [@param] string $directory
     * @return string
     */
    public function directory()
    {
        $args = func_get_args();
        if (is_array_ne($args)) {
            if (is_string_ne($args[0]) && is_dir($args[0])) {
                $this->directory = $args[0];
            }
        }

        return $this->directory;
    }


    /**
     * Set and/or get the files array.
     *
     * [@param] array|string $files
     * @return array
     */
    public function files()
    {
        $args = func_get_args();
        if (is_array_ne($args)) {
            if (is_array_ne($args[0])) {
                $this->files = $args[0];
            } elseif (is_string_ne($args[0])) {
                $this->addFile($args[0]);
            }
        }

        return $this->files;
    }


    /**
     * Set and/or get the keywords array.
     *
     * [@param] array|string $keywords
     * @return array
     */
    public function keywords()
    {
        $args = func_get_args();
        if (is_array_ne($args)) {
            if (is_array_ne($args[0])) {
                $this->keywords = $args[0];
            } elseif (is_string_ne($args[0])) {
                $this->addKeyword($args[0]);
            }
        }

        return $this->keywords;
    }


    /**
     * Set and/or get the log level.
     *
     * @return int
     * @uses Log::validateLevel()
     */
    public function logLevel()
    {
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
     * Set and/or get the orientation.
     *
     * [@param] string P|L
     * @return string
     */
    public function orientation()
    {
        $args = func_get_args();
        if (is_array_ne($args)) {
            if (is_string_ne($args[0]) && in_array(strtoupper($args[0]), array('P', 'L'))) {
                $this->orientation = strtoupper($args[0]);
            } else {
                $this->Log->write('Invalid orientation {' . $args[0] . '} provided. Need to specify P or L.', Log::LOG_LEVEL_WARNING);
            }
        }

        return $this->orientation;
    }


    /**
     * Set and/or get the output file name. This should be the base name of the file, though full path is supported.
     *
     * [@param] string $output_file
     * @return string
     */
    public function outputFile()
    {
        $args = func_get_args();
        if (is_array_ne($args)) {
            if (is_string_ne($args[0])) {
                $base_name = pathinfo($args[0], PATHINFO_BASENAME);
                if ($base_name != $args[0]) {
                    $directory = pathinfo($args[0], PATHINFO_DIRNAME);
                    if (is_string_ne($directory)) {
                        if (is_dir($directory)) {
                            $this->Log->write('Need to set directory from ' . __FUNCTION__, Log::LOG_LEVEL_SYSTEM_INFORMATION);
                            $this->directory($directory);
                        }
                    }
                }
                $this->output_file = $base_name;
            }
        }

        return $this->output_file;
    }


    /**
     * Set and/or get owner password for PDF file.
     *
     * @return string
     */
    public function ownerPassword()
    {
        $args = func_get_args();
        if (is_array_ne($args)) {
            if (is_string_ne($args[0])) {
                $this->owner_password = $args[0];
            } else {
                $this->Log->write('invalid type for owner password', Log::LOG_LEVEL_WARNING);
            }
        }

        return $this->owner_password;
    }


    /**
     * Set and/or get the paper size.
     *
     * @return string
     * @see TCPDF_STATIC::getPageSizeFromFormat()
     */
    public function paperSize()
    {
        $args = func_get_args();
        if (is_array_ne($args)) {
            if (is_string_ne($args[0])) {
                $this->paper_size = $args[0];
            } else {
                $this->Log->write('Invalid paper size provided.', Log::LOG_LEVEL_WARNING);
            }
        }

        return $this->paper_size;
    }


    /**
     * Set and/or get the subject.
     *
     * [@param] string $subject
     * @return string
     */
    public function subject()
    {
        $args = func_get_args();
        if (is_array_ne($args)) {
            if (is_string_ne($args[0])) {
                $this->subject = $args[0];
            }
        }

        return $this->subject;
    }


    /**
     * Set and/or get the title.
     *
     * [@param] string $title
     * @return string
     */
    public function title()
    {
        $args = func_get_args();
        if (is_array_ne($args)) {
            if (is_string_ne($args[0])) {
                $this->title = $args[0];
            }
        }

        return $this->title;
    }


    /**
     * Set and/or get user password for PDF file. If this is set, the PDF file will be encrypted.
     *
     * @return string
     */
    public function userPassword()
    {
        $args = func_get_args();
        if (is_array_ne($args)) {
            if (is_string_ne($args[0])) {
                $this->user_password = $args[0];
            } else {
                $this->Log->write('invalid type for user password', Log::LOG_LEVEL_WARNING);
            }
        }

        return $this->user_password;
    }


    /**
     * Magic method for getter that uses defined methods or the property to return properties.
     *
     * @param string $name
     * @return mixed|null
     */
    public function __get($name = '')
    {
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        $property = lower_underscore($name);
        if (property_exists($this, $property)) {
            $method = upper_camel($name);
            if (method_exists($this, $method)) {
                return $this->$method();
            } else {
                return $this->$property;
            }
        } else {
            $this->Log->write('Error: property {' . $property . '} does not exist.', Log::LOG_LEVEL_WARNING);

            return null;
        }
    }


    /**
     * Magic method for setter that uses defined methods or matches type to set properties.
     *
     * @param string $name
     * @param mixed $value
     * @uses lower_underscore()
     * @uses upper_camel(0
     */
    public function __set($name = '', mixed $value)
    {
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION, $value);

        $property = lower_underscore($name);
        if (property_exists($this, $property)) {
            $method = upper_camel($name);
            if (method_exists($this, $method)) {
                $this->$method($value);
            } else {
                $property_type = gettype($this->$property);
                $value_type = gettype($value);
                if ($property_type == $value_type) {
                    $this->$property = $value;
                } else {
                    $this->Log->write('Error: value type mismatch in __set(): {' . $property_type . '} != {' . $value_type . '}', Log::LOG_LEVEL_WARNING);
                }
            }
        } else {
            $this->Log->write('property {' . $property . '} does not exist.', Log::LOG_LEVEL_WARNING);
        }
    }

    // END setters and getters


    /**
     * Undefined method call handler
     *
     * @param string $name
     * @param array $arguments
     */
    public function __call($name = '', $arguments = array())
    {
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION, $arguments);

        if (!method_exists($this, $name)) {
            $this->Log->write($name . ' is not a callable method.', Log::LOG_LEVEL_WARNING);
        } else {
            call_user_func_array(array($this, $name), $arguments);
        }
    }
}