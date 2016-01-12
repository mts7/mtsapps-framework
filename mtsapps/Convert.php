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
 * Class Convert
 *
 * @package mtsapps
 * @uses Log::write()
 */
class Convert
{
    /**
     * @var array
     * @todo Add more from types with their extensions.
     * @todo Consider adding audio, video, and image types.
     */
    private $extensions = array(
        'text' => array(
            'docx',
            'doc',
            'odt',
            'txt',
        ),
    );

    /**
     * @var string
     */
    private $input_file = '';

    /**
     * @var Log
     */
    protected $Log = null;

    /**
     * @var string
     */
    private $type_from = '';

    /**
     * @var string
     */
    private $type_to = '';


    /**
     * Convert constructor.
     */
    public function __construct()
    {
        $this->Log = new Log([
            'log_file' => __DIR__ . '/convert_' . date('Y-m-d') . '.log',
            'log_level' => Log::LOG_LEVEL_WARNING,
        ]);
    }


    /**
     * Set the input file path.
     *
     * @param string $file_path
     * @return bool
     */
    public function inputFile($file_path = '')
    {
        $this->Log->write('Convert::inputFile()', Log::LOG_LEVEL_SYSTEM_INFORMATION);

        // input validation
        if (!is_string_ne($file_path) || !is_file($file_path)) {
            $this->Log->write('File path {' . $file_path . '} is invalid.', Log::LOG_LEVEL_WARNING);

            return false;
        }

        $this->input_file = $file_path;

        return true;
    }


    /**
     * Process the conversion by checking the from and to types and determining the best conversion method to use.
     *
     * @return bool|mixed|string
     * @uses Convert::$input_file
     * @uses Convert::$type_from
     * @uses Convert::$type_to
     * @uses Convert::textToPdf()
     */
    public function process()
    {
        if (!is_string_ne($this->input_file)) {
            $this->Log->write('Set the input file path name before attempting to convert the file.', Log::LOG_LEVEL_WARNING);

            return false;
        }

        if (!is_string_ne($this->type_from)) {
            $this->Log->write('type_from was not set, so get type_from from the input file name', Log::LOG_LEVEL_USER);
            // get type_from from the input file name
            $this->typeFrom(pathinfo($this->input_file, PATHINFO_EXTENSION));
        }

        if (!is_string_ne($this->type_to)) {
            $this->Log->write('Set the destination type with Convert::typeTo() before attempting to convert the file.', Log::LOG_LEVEL_WARNING);

            return false;
        }

        // determine which convert method to use
        switch ($this->type_from) {
            case 'docx':
            case 'doc':
            case 'odt':
            case 'txt':
                // determine which converter to use
                switch ($this->type_to) {
                    case 'pdf':
                        // use libreoffice
                        $output_file = $this->textToPdf();
                        break;
                    default:
                        $output_file = 'unknown to type: ' . $this->type_to;
                        break;
                }
                break;
            default:
                $output_file = 'unknown from type: ' . $this->type_from;
                break;
        }

        return $output_file;
    }


    /**
     * Set or get the source file type [extension].
     *
     * @return string
     * @uses Convert::$type_from
     */
    public function typeFrom()
    {
        $args = func_get_args();
        if (is_array_ne($args)) {
            if (is_string_ne($args[0])) {
                $this->type_from = $args[0];
            } else {
                $this->Log->write('typeFrom requires a string parameter.', Log::LOG_LEVEL_WARNING);
            }
        }

        return $this->type_from;
    }


    /**
     * Set or get the destination file type [extension].
     *
     * @return string
     * @uses Convert::$type_to
     */
    public function typeTo()
    {
        $args = func_get_args();
        if (is_array_ne($args)) {
            if (is_string_ne($args[0])) {
                $this->type_to = $args[0];
            } else {
                $this->Log->write('typeTo requires a string parameter.', Log::LOG_LEVEL_WARNING);
            }
        }

        return $this->type_to;
    }


    /**
     * Upload a file to convert and pass the output name to inputFile.
     *
     * @param string $form_file
     * @param string $expected_type
     * @return bool|mixed|string
     * @uses Convert::$extensions
     * @uses Upload::uniqueName()
     * @uses Upload::process()
     * @uses Upload::lastMessage()
     * @uses Upload::outputPath()
     * @uses Convert::inputFile()
     * @see Log::last()
     */
    public function uploadFile($form_file = 'upload_file', $expected_type = 'text')
    {
        $this->Log->write('Convert::uploadFile()', Log::LOG_LEVEL_SYSTEM_INFORMATION);

        if (!is_string_ne($form_file)) {
            $this->Log->write('Form file name must be provided.', Log::LOG_LEVEL_WARNING);

            return false;
        }

        $exts = $this->extensions[$expected_type];

        if (!is_array_ne($exts)) {
            $this->Log->write('Expected type {' . $expected_type . '} is not valid.', Log::LOG_LEVEL_WARNING);

            return false;
        }

        $message = '';

        $target_dir = '~/uploads/';
        $Upload = new Upload([
            'form_file' => 'upload_file',
            'output_file' => $target_dir,
            'exts' => $exts,
        ]);
        $name = $Upload->uniqueName();
        if ($name !== false) {
            $processed = $Upload->process();
            if (!$processed) {
                $message = $Upload->lastMessage();
            } else {
                $this->inputFile($Upload->outputPath() . $name);
            }
        } else {
            $message = $Upload->lastMessage();
        }

        return $message;
    }


    /**
     * Replace the extension of $input_file with the one provided as a parameter.
     *
     * @param string $ext
     * @return bool|mixed
     * @uses Convert::$input_file
     */
    private function replaceExtension($ext = '')
    {
        $this->Log->write('Convert::replaceExtension()', Log::LOG_LEVEL_SYSTEM_INFORMATION);

        // input validation
        if (!is_string_ne($ext)) {
            $this->Log->write('Extension must be provided.', Log::LOG_LEVEL_WARNING);

            return false;
        }

        $current = pathinfo($this->input_file, PATHINFO_EXTENSION);

        return preg_replace('/' . $current . '$/', $ext, $this->input_file);
    }


    /**
     * Convert a text file (with appropriate file extension [docx, doc, odt, txt]) to a PDF file.
     * This uses Libre Office, a command line utility, to convert the file in the file system and requires exec permission.
     *
     * @return bool|mixed
     * @uses Convert::$input_file
     * @uses Convert::replaceExtension()
     */
    private function textToPdf()
    {
        if (!is_file($this->input_file)) {
            $this->Log->write('Input file is not part of this file system. Please specify the correct file with Convert::inputFile() or Convert::uploadFile().', Log::LOG_LEVEL_WARNING);

            return false;
        }
        $command = '/usr/bin/soffice --headless --convert-to pdf ' . $this->input_file;
        exec($command, $output, $return_var);

        if (is_valid_int($return_var) && $return_var != 0) {
            $this->Log->write('An error occurred when executing command:' . PHP_EOL . implode(PHP_EOL, $output), Log::LOG_LEVEL_WARNING);

            return false;
        }

        $output_file = $this->replaceExtension('pdf');

        return $output_file;
    }
}