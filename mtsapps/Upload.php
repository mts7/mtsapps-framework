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
 * Class Upload
 *
 * @package mtsapps
 */
class Upload
{
    /**
     * @var array
     */
    private $accepted_exts = array();

    /**
     * @var string
     */
    private $form_file = 'upload_file';

    /**
     * @var Log|null
     */
    protected $Log = null;

    /**
     * @var mixed|string
     */
    private $log_dir = __DIR__;

    /**
     * @var string
     */
    private $log_file = 'uploads.log';

    /**
     * @var int
     */
    private $log_level = 0;

    /**
     * @var int
     */
    private $max_size = 5242880; // default to 5MB

    /**
     * @var string
     */
    private $output_file = 'output.txt';

    /**
     * @var string
     */
    private $output_path = __DIR__;

    /**
     * @var bool
     */
    private $overwrite = false;

    /**
     * @var array
     */
    private $post_file = array();


    /**
     * Upload constructor.
     *
     * @param array $params
     */
    public function __construct($params = array())
    {
        $this->log_dir .= '/';

        // handle input parameters
        if (is_array_ne($params)) {
            if (array_key_exists('form_file', $params) && is_string_ne($params['form_file'])) {
                $this->form_file = $params['form_file'];
            }

            if (array_key_exists('max_size', $params) && is_valid_int($params['max_size'], true)) {
                $this->max_size = $params['max_size'];
            }

            if (array_key_exists('exts', $params) && is_array_ne($params['exts'])) {
                $this->accepted_exts = $params['exts'];
            }

            if (array_key_exists('output_file', $params) && is_string_ne($params['output_file'])) {
                $this->output_file = $params['output_file'];
            }

            if (array_key_exists('output_path', $params) && is_string_ne($params['output_path']) && is_dir($params['output_path'])) {
                $this->output_path = realpath($params['output_path']) . '/';
            }

            if (array_key_exists('log_dir', $params) && is_string_ne($params['log_dir']) && is_dir($params['log_dir'])) {
                $this->log_dir = realpath($params['log_dir']) . '/';
            }

            if (array_key_exists('log_file', $params) && is_string_ne($params['log_file'])) {
                if (is_file($params['log_file'])) {
                    $this->log_dir = pathinfo($params['log_file'], PATHINFO_DIRNAME);
                    $this->log_file = pathinfo($params['log_file'], PATHINFO_BASENAME);
                } else {
                    $this->log_file = $params['log_file'];
                }
            }

            if (array_key_exists('log_level', $params) && is_valid_int($params['log_level'], true)) {
                $this->log_level = $params['log_level'];
            }

            if (array_key_exists('overwrite', $params)) {
                $this->overwrite = !!$params['overwrite'];
            }
        } else {
            $this->output_path = __DIR__ . '/';

        }

        // start a new Log
        $this->Log = new Log();
        $this->Log->file($this->log_file);
        $this->Log->logLevel($this->log_level);

        $this->init();
    }


    /**
     * Initial method to use to set the value of post_file.
     *
     * @return bool
     * @uses Upload::$form_file
     * @uses $_FILES
     * @uses Upload::handleError()
     */
    public function init()
    {
        $this->Log->write('Upload::init()', Log::LOG_LEVEL_SYSTEM_INFORMATION);

        // input validation
        if (!is_string_ne($this->form_file)) {
            $this->Log->write('Form file field name needs to be provided before uploading can begin.', Log::LOG_LEVEL_WARNING);

            return false;
        }

        if (!array_key_exists($this->form_file, $_FILES)) {
            $this->Log->write('$_FILES does not contain ' . $this->form_file, Log::LOG_LEVEL_WARNING);

            return false;
        }

        $this->post_file = $_FILES[$this->form_file];

        // check for errors
        $file_error = $this->post_file['error'];
        if ($file_error > UPLOAD_ERR_OK) {
            $this->handleError($file_error);

            // error message logging is handled in handleError, so do not log here

            return false;
        }

        return true;
    }


    /**
     * Get the last log message.
     *
     * @return mixed
     * @uses Log::last()
     */
    public function lastMessage()
    {
        return $this->Log->last();
    }


    /**
     * Set or get output_file.
     *
     * @return string
     */
    public function outputFile()
    {
        $args = func_get_args();
        if (is_array_ne($args)) {
            if (is_string_ne($args[0])) {
                $this->output_file = $args[0];
            } else {
                $this->Log->write('Provided argument is a ' . gettype($args[0]) . ' instead of a string.', Log::LOG_LEVEL_WARNING);
            }
        }

        return $this->output_file;
    }


    /**
     * Set or get output_path.
     *
     * @return string
     */
    public function outputPath()
    {
        $args = func_get_args();
        if (is_array_ne($args)) {
            if (is_string_ne($args[0])) {
                if (!is_dir($args[0])) {
                    $this->Log->write('output path provided {' . $args[0] . '} is not a valid directory', Log::LOG_LEVEL_WARNING);
                } else {
                    $this->output_path = $args[0];
                }
            }
        }

        return $this->output_path;
    }


    /**
     * Process upload of file, handling validations.
     *
     * @return bool
     * @uses Upload::$post_file
     * @uses Upload::$output_path
     * @uses Upload::validateMaxSize()
     * @uses Upload::humanSize()
     * @uses Upload::$accepted_exts
     * @uses Upload::$overwrite
     * @uses Upload::$output_file
     */
    public function process()
    {
        $this->Log->write('Upload::process()', Log::LOG_LEVEL_SYSTEM_INFORMATION);

        // make sure there is a post file
        if (!is_array_ne($this->post_file)) {
            $this->Log->write('The $_FILES array has not been saved. Please call init() first.', Log::LOG_LEVEL_WARNING);

            return false;
        }

        // verify output directory exists
        if (!is_dir($this->output_path)) {
            $this->Log->write('Output path not specified or does not exist. Please set the output path with Upload::outputPath() and process again.', Log::LOG_LEVEL_WARNING);

            return false;
        }

        // check max file size
        if (!$this->validateMaxSize()) {
            $this->Log->write('File size {' . $this->humanSize($this->post_file['size']) . '} is greater than max file size {' . $this->humanSize($this->max_size) . '}.', Log::LOG_LEVEL_WARNING);

            return false;
        }

        // validate extension
        $ext = pathinfo($this->post_file['name'], PATHINFO_EXTENSION);
        if (!in_array($ext, $this->accepted_exts)) {
            $this->Log->write('extension {' . $ext . '} is not allowed. Please upload a file with one of these extensions: ' . implode(', ', $this->accepted_exts), Log::LOG_LEVEL_WARNING);

            return false;
        }

        // validate output file does not already exist
        if (!$this->overwrite && is_file($this->output_path . $this->output_file)) {
            $this->Log->write('Output file already exists. Please specify a new output file name with Upload::outputFile() or a new output directory with Upload::outputPath().', Log::LOG_LEVEL_WARNING);

            return false;
        }

        // move uploaded file to output directory
        if (!move_uploaded_file($this->post_file['tmp_name'], $this->output_path . $this->output_file)) {
            $this->Log->write('Error saving the uploaded file. Please try again.', Log::LOG_LEVEL_WARNING);

            return false;
        }

        $this->Log->write('Successfully uploaded ' . basename($this->post_file['name']), Log::LOG_LEVEL_USER);

        return true;
    }


    /**
     * Reset members to prepare for another upload file.
     */
    public function reset()
    {
        $this->accepted_exts = array();
        $this->form_file = '';
        $this->Log = null;
        $this->log_dir = __DIR__ . '/';
        $this->log_file = 'uploads.log';
        $this->max_size = 5242880;
        $this->output_file = 'output.txt';
        $this->output_path = __DIR__ . '/';
        $this->overwrite = false;
        $this->post_file = array();
    }


    /**
     * Generate an unique name based on the contents of the uploaded file and its file extension.
     *
     * @return bool|string
     * @uses Upload::$post_file
     * @uses Upload::outputFile()
     */
    public function uniqueName()
    {
        $this->Log->write('Upload::uniqueName()', Log::LOG_LEVEL_SYSTEM_INFORMATION);

        if (!array_key_exists('tmp_name', $this->post_file) || !is_file($this->post_file['tmp_name'])) {
            $this->Log->write('Could not find temporary file.', Log::LOG_LEVEL_WARNING);

            return false;
        }

        $hash_name = sha1_file($this->post_file['tmp_name']) . pathinfo($this->post_file['name'], PATHINFO_EXTENSION);

        if ($hash_name === false) {
            $this->Log->write('Could not get hash of temporary file.', Log::LOG_LEVEL_WARNING);

            return false;
        }

        return $this->outputFile($hash_name);
    }


    /**
     * Return error message, based on upload error status.
     *
     * @param int $error
     * @return bool|string
     */
    private function handleError($error)
    {
        $this->Log->write('Upload::handleError()', Log::LOG_LEVEL_SYSTEM_INFORMATION);

        if (!is_valid_int($error, true)) {
            $this->Log->write('Cannot handle an error that is not a positive integer.', Log::LOG_LEVEL_WARNING);

            return false;
        }

        switch ($error) {
            case UPLOAD_ERR_INI_SIZE:
                $message = 'The uploaded file exceeds the upload_max_filesize directive in php.ini.';
                break;
            case UPLOAD_ERR_FORM_SIZE:
                $message = 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.';
                break;
            case UPLOAD_ERR_PARTIAL:
                $message = 'The uploaded file was only partially uploaded.';
                break;
            case UPLOAD_ERR_NO_FILE:
                $message = 'No file was uploaded.';
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $message = 'Missing a temporary folder.';
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $message = 'Failed to write file to disk.';
                break;
            case UPLOAD_ERR_EXTENSION:
                $message = 'A PHP extension stopped the file upload.';
                break;
            default:
                $message = 'Unknown upload error (' . $error . ')';
                break;
        }

        $this->Log->write('Error: ' . $message, Log::LOG_LEVEL_ERROR);

        return $message;
    }


    /**
     * Convert the byte size to human-readable size.
     *
     * @param int $bytes
     * @param int $decimals
     * @return string
     * @see http://php.net/manual/en/function.filesize.php#106569
     */
    private function humanSize($bytes = 0, $decimals = 2)
    {
        $sz = 'BKMGTP';
        $factor = floor((strlen($bytes) - 1) / 3);

        return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$sz[$factor];
    }


    /**
     * Validate the size of the upload file.
     *
     * @return bool
     * @uses Upload::$post_file
     * @uses Upload::$max_size
     */
    private function validateMaxSize()
    {
        $this->Log->write('Upload::validateMaxSize()', Log::LOG_LEVEL_SYSTEM_INFORMATION);

        $file_size = $this->post_file['size'];

        return $file_size <= $this->max_size;
    }
}