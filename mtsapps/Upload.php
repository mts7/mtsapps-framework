<?php
/**
 * @author Mike Rodarte
 * @version 1.03
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
     * @var bool
     */
    private $ajax = false;

    /**
     * @var array
     */
    private $files = array();

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
     * @var array
     */
    private $public_properties = array(
        'accepted_exts',
        'ajax',
        'form_file',
        'log_dir',
        'log_file',
        'log_level',
        'max_size',
        'output_file',
        'output_path',
        'overwrite',
        'upload_file_name',
    );

    /**
     * @var string
     */
    private $upload_file_name = '';


    /**
     * Upload constructor.
     *
     * @param array $params
     */
    public function __construct($params = array())
    {
        $this->log_dir .= '/';

        // start a new Log
        $this->Log = new Log([
            'file' => $this->log_file,
            'log_level' => Log::LOG_LEVEL_DEBUG,
            'log_directory' => $this->log_dir,
        ]);

        // handle input parameters
        if (is_array_ne($params)) {
            foreach ($params as $key => $value) {
                $method = upper_camel($key);
                if (method_exists($this, $method)) {
                    $this->$method($value);
                } else {
                    $property = lower_underscore($key);
                    if ($property === 'exts') {
                        $property = 'accepted_exts';
                    }
                    if (property_exists($this, $property) && in_array($property, $this->public_properties)) {
                        $type_value = gettype($value);
                        $type_property = gettype($this->$property);
                        if ($type_value === $type_property) {
                            $this->$property = $value;
                        }
                    }
                }
            }
        } else {
            $this->output_path = __DIR__ . '/';
        }

        $this->init();
    }


    /**
     * Initial method to use to set the value of post_file.
     *
     * @return bool
     * @throws \Exception
     * @uses Upload::$form_file
     * @uses $_FILES
     * @uses Upload::handleError()
     */
    public function init()
    {
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        if (!$this->validateServerSize()) {
            $this->Log->write('uploaded file is bigger than post_max_size', Log::LOG_LEVEL_ERROR);

            throw new \Exception('Uploaded file is too big.');
        }

        if ($this->ajax) {
            return true;
        }

        $this->arrangeFiles();

        // input validation
        if (!is_string_ne($this->form_file)) {
            $this->Log->write('Form file field name needs to be provided before uploading can begin.', Log::LOG_LEVEL_WARNING);

            return false;
        }

        if (!array_key_exists($this->form_file, $_FILES)) {
            $this->Log->write('$_FILES does not contain ' . $this->form_file, Log::LOG_LEVEL_WARNING);

            return false;
        }

        $this->post_file = $this->files[$this->form_file][0];
        $this->Log->write(__FUNCTION__ . ' post_file', Log::LOG_LEVEL_USER, $this->post_file);

        // check for errors
        $file_error = $this->post_file['error'];
        if (!is_valid_int($file_error)) {
            $this->Log->write('invalid type of error', Log::LOG_LEVEL_WARNING, gettype($file_error));

            return false;
        }

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
     * Get and/or set the log directory.
     *
     * @param string $dir
     * @return bool|string
     */
    public function logDir($dir = '')
    {
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        // input validation
        if (!is_string_ne($dir)) {
            $this->Log->write('directory name missing', Log::LOG_LEVEL_WARNING);

            return $this->log_dir;
        }

        $log_dir = realpath($dir) . '/';
        $this->Log->write('set log directory', Log::LOG_LEVEL_USER, $log_dir);

        if (!is_dir($log_dir)) {
            $this->Log->write('log directory is not a directory', Log::LOG_LEVEL_WARNING, $log_dir);

            return false;
        }

        $this->log_dir = $log_dir;
        $this->Log->write('set log directory', Log::LOG_LEVEL_SYSTEM_INFORMATION, $log_dir);

        return $this->log_dir;
    }


    /**
     * Get and/or set the log file [and directory].
     *
     * @param string $file
     * @return string
     */
    public function logFile($file = '')
    {
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        // input validation
        if (!is_string_ne($file)) {
            $this->Log->write('file name missing', Log::LOG_LEVEL_WARNING);

            return $this->log_file;
        }

        // set file and maybe directory
        if (is_file($file)) {
            $this->log_dir = pathinfo($file, PATHINFO_DIRNAME);
            $this->log_file = pathinfo($file, PATHINFO_BASENAME);
            $this->Log->write('set directory', Log::LOG_LEVEL_USER, $this->log_dir);
        } else {
            $this->log_file = $file;
        }
        $this->Log->write('set file', Log::LOG_LEVEL_USER, $this->log_file);

        return $this->log_file;
    }


    /**
     * Get and/or set the log level for this class and Log.
     *
     * @param int $level
     * @return bool|int
     */
    public function logLevel($level = 0)
    {
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        // input validation
        if (!is_valid_int($level, true)) {
            $this->Log->write('level value invalid', Log::LOG_LEVEL_WARNING);

            return $this->log_level;
        }

        if (!Log::validateLevel($level)) {
            $this->Log->write('level is not acceptable', Log::LOG_LEVEL_WARNING, $level);

            return false;
        }

        $this->log_level = $level;
        $set_level = $this->Log->logLevel($this->log_level);
        if ($set_level === $this->log_level) {
            $this->Log->write('set log_level', Log::LOG_LEVEL_USER, $this->log_level);
        } else {
            $this->Log->write('could not set level', Log::LOG_LEVEL_WARNING, $set_level);
        }

        return $this->log_level;
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
                    $this->output_path = realpath($args[0]) . DIRECTORY_SEPARATOR;
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
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        // input validation
        if (!$this->validateFile()) {
            $this->Log->write('file is invalid', Log::LOG_LEVEL_WARNING);

            return false;
        }

        // set output path
        $this->outputPath($this->output_path);

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
     * Get file from input and move it to the output directory.
     *
     * @return bool
     */
    public function processAjax()
    {
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        // input validation
        if (!$this->ajax) {
            $this->Log->write('AJAX must be specified to do AJAX uploads', Log::LOG_LEVEL_WARNING);

            return false;
        }

        if (!is_string_ne($this->upload_file_name)) {
            $this->Log->write('a file name must be specified', Log::LOG_LEVEL_WARNING);

            return false;
        }

        // set post_file
        $this->post_file['tmp_name'] = $this->upload_file_name;
        $this->post_file['name'] = $this->upload_file_name;
        $this->Log->write('set name fields in post_file', Log::LOG_LEVEL_USER, $this->post_file);

        $file = file_get_contents('php://input');
        $this->post_file['size'] = strlen($file);

        if (!$this->validateFile()) {
            $this->Log->write('file is invalid', Log::LOG_LEVEL_WARNING);

            return false;
        }

        // set output path
        $this->outputPath($this->output_path);

        $this->output_file = $this->uniqueName();
        $this->Log->write('output_file', Log::LOG_LEVEL_USER, $this->output_file);

        // write file to output_file
        $bytes = file_put_contents($this->output_path . $this->output_file, $file);
        $this->Log->write('wrote ' . $bytes . ' bytes', Log::LOG_LEVEL_USER, $this->output_path . $this->output_file);

        return $bytes > 0;
    }


    /**
     * Process multiple files uploaded.
     *
     * @return array
     */
    public function processMultiple()
    {
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        // input validation
        if (!is_array_ne($this->files)) {
            $this->Log->write('need to have files available', Log::LOG_LEVEL_WARNING);

            return false;
        }

        $results = array();
        foreach ($this->files as $form_name => $array) {
            $this->form_file = $form_name;
            $this->Log->write('working with form file element', Log::LOG_LEVEL_USER, $this->form_file);

            foreach ($array as $values) {
                $this->Log->write('values', Log::LOG_LEVEL_USER, $values);
                $this->post_file = array(
                    'name' => $values['name'],
                    'type' => $values['type'],
                    'tmp_name' => $values['tmp_name'],
                    'error' => $values['error'],
                    'size' => $values['size'],
                );
                $this->Log->write(__FUNCTION__ . ' post_file', Log::LOG_LEVEL_USER, $this->post_file);
                $this->output_file = $this->uniqueName();
                $results[] = $this->process();
            }
        }
        $this->Log->write('results', Log::LOG_LEVEL_USER, $results);

        return $results;
    }


    /**
     * Reset members to prepare for another upload file.
     */
    public function reset()
    {
        $this->accepted_exts = array();
        $this->ajax = false;
        $this->files = array();
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
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        if (!array_key_exists('tmp_name', $this->post_file) || (!$this->ajax && !is_file($this->post_file['tmp_name']))) {
            $this->Log->write('Could not find temporary file.', Log::LOG_LEVEL_WARNING);

            return false;
        }

        $temp_hash = $this->ajax ? sha1($this->post_file['tmp_name']) : sha1_file($this->post_file['tmp_name']);

        $hash_name = md5(microtime(true) . $temp_hash) . '.' . pathinfo($this->post_file['name'], PATHINFO_EXTENSION);

        if ($hash_name === false) {
            $this->Log->write('Could not get hash of temporary file.', Log::LOG_LEVEL_WARNING);

            return false;
        }

        return $this->outputFile($hash_name);
    }


    /**
     * Rearrange the $_FILES super global to group each upload file in one array element.
     *
     * @return bool
     * @uses $_FILES
     */
    private function arrangeFiles()
    {
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        // input validation
        if (!is_array_ne($_FILES)) {
            $this->Log->write('there were no files uploaded', Log::LOG_LEVEL_WARNING);

            return false;
        }

        foreach ($_FILES as $form_field => $array) {
            foreach ($array as $key => $values) {
                foreach ($values as $index => $value) {
                    $this->files[$form_field][$index][$key] = $value;
                }
            }
        }
        $this->Log->write('arranged files', Log::LOG_LEVEL_USER, $this->files);

        return count($this->files) > 0;
    }


    /**
     * Return error message, based on upload error status.
     *
     * @param int $error
     * @return bool|string
     */
    private function handleError($error)
    {
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

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
                $message = 'Unknown upload error (' . get_string($error) . ')';
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
     * Validate path, size, extension with post_file.
     *
     * @return bool
     */
    private function validateFile()
    {
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
            $this->Log->write('extension {' . $ext . '} is not allowed. Please upload a file with one of these extensions', Log::LOG_LEVEL_WARNING, $this->accepted_exts);

            return false;
        }

        return true;
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
        $this->Log->write(__METHOD__, Log::LOG_LEVEL_SYSTEM_INFORMATION);

        $file_size = $this->post_file['size'];

        $valid = $file_size <= $this->max_size;
        $this->Log->write('valid file size', Log::LOG_LEVEL_USER, $valid);

        return $valid;
    }


    /**
     * Validate content length with post_max_size (useful for large files).
     *
     * @return bool
     */
    private function validateServerSize()
    {
        $valid = false;

        if (isset($_SERVER['CONTENT_LENGTH'])) {
            $post_max_size = ini_get('post_max_size');
            $content_length = $_SERVER['CONTENT_LENGTH'];

            $post_max_size = bytes_from_shorthand($post_max_size);

            $valid = $content_length <= $post_max_size;
        }

        return $valid;
    }
}