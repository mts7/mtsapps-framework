<?php
/**
 * Iterator for PDO
 *
 * @author Mike Rodarte
 * @version 1.02
 */

/**
 * mtsapps namespace
 */
namespace mtsapps;


/**
 * Class DbIterator
 *
 * @package Webolutions\Database
 */
class DbIterator implements \Iterator
{
    /**
     * @var int Current cursor location
     */
    private $key = 0;

    /**
     * @var Log
     */
    protected $Log = null;

    /**
     * @var array|bool
     */
    private $result = null;

    /**
     * @var \PDOStatement $stmt
     */
    private $stmt = null;

    /**
     * @var bool
     */
    private $valid = false;


    /**
     * DbIterator constructor.
     *
     * @param \PDOStatement $stmt
     * @throws \Exception
     */
    public function __construct(\PDOStatement $stmt)
    {
        $file = 'db_' . date('Y-m-d') . '.log';
        $this->Log = new Log([
            'file' => $file,
            'log_directory' => LOG_DIR,
            'log_level' => Log::LOG_LEVEL_WARNING,
        ]);
        $log_file = $this->Log->file();
        if ($log_file !== $file) {
            $this->Log->write('could not set file properly', Log::LOG_LEVEL_WARNING);
        }

        $this->Log->write('DbIterator::__construct()', Log::LOG_LEVEL_SYSTEM_INFORMATION);

        if (is_object($stmt) && $stmt instanceof \PDOStatement) {
            $this->stmt = $stmt;
        } else {
            $this->Log->write('stmt is not a PDOStatement, but is a ' . gettype($stmt), Log::LOG_LEVEL_ERROR);
            throw new \Exception('parameter is not a PDOStatement');
        }
    }


    /**
     * @return array|bool
     */
    public function current()
    {
        $this->Log->write('DbIterator::current()', Log::LOG_LEVEL_SYSTEM_INFORMATION);

        return $this->result;
    }


    /**
     * @return int
     */
    public function key()
    {
        $this->Log->write('DbIterator::key()', Log::LOG_LEVEL_SYSTEM_INFORMATION);

        return $this->key;
    }


    /**
     * @return null
     * @uses \PDOStatement::fetch()
     */
    public function next()
    {
        $this->Log->write('DbIterator::next()', Log::LOG_LEVEL_SYSTEM_INFORMATION);
        $this->key++;
        $this->result = $this->stmt->fetch(\PDO::FETCH_ASSOC, \PDO::FETCH_ORI_ABS, $this->key);
        if (false === $this->result) {
            $this->valid = false;

            return null;
        }
    }


    /**
     * Set the cursor to the beginning.
     */
    public function rewind()
    {
        $this->Log->write('DbIterator::rewind()', Log::LOG_LEVEL_SYSTEM_INFORMATION);
        $this->key = 0;
    }


    /**
     * @return bool
     */
    public function valid()
    {
        $this->Log->write('DbIterator::valid()', Log::LOG_LEVEL_SYSTEM_INFORMATION);

        return $this->valid;
    }
}