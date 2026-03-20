<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\log;

use Yii;
use yii\base\Invalid_Config_Exception;
use yii\helpers\File_Helper;
/**
 * FileTarget records log messages in a file.
 *
 * The log file is specified via [[logFile]]. If the size of the log file exceeds
 * [[maxFileSize]] (in kilo-bytes), a rotation will be performed, which renames
 * the current log file by suffixing the file name with '.1'. All existing log
 * files are moved backwards by one place, i.e., '.2' to '.3', '.1' to '.2', and so on.
 * The property [[maxLogFiles]] specifies how many history files to keep.
 *
 * Since 2.0.46 rotation of the files is done only by copy and the
 * `rotateByCopy` property is deprecated.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class File_Target extends Target
{
    /**
     * @var string|null log file path or [path alias](guide:concept-aliases). If not set, it will use the "@runtime/logs/app.log" file.
     * The directory containing the log files will be automatically created if not existing.
     */
    public $log_file;
    /**
     * @var bool whether log files should be rotated when they reach a certain [[maxFileSize|maximum size]].
     * Log rotation is enabled by default. This property allows you to disable it, when you have configured
     * an external tools for log rotation on your server.
     * @since 2.0.3
     */
    public $enable_rotation = true;
    /**
     * @var int maximum log file size, in kilo-bytes. Defaults to 10240, meaning 10MB.
     */
    public $max_file_size = 10240;
    // in KB
    /**
     * @var int number of log files used for rotation. Defaults to 5.
     */
    public $max_log_files = 5;
    /**
     * @var int|null the permission to be set for newly created log files.
     * This value will be used by PHP chmod() function. No umask will be applied.
     * If not set, the permission will be determined by the current environment.
     */
    public $file_mode;
    /**
     * @var int the permission to be set for newly created directories.
     * This value will be used by PHP chmod() function. No umask will be applied.
     * Defaults to 0775, meaning the directory is read-writable by owner and group,
     * but read-only for other users.
     */
    public $dir_mode = 0775;
    /**
     * @var bool Whether to rotate log files by copy and truncate in contrast to rotation by
     * renaming files. Defaults to `true` to be more compatible with log tailers and windows
     * systems which do not play well with rename on open files. Rotation by renaming however is
     * a bit faster.
     *
     * The problem with windows systems where the [rename()](https://www.php.net/manual/en/function.rename.php)
     * function does not work with files that are opened by some process is described in a
     * [comment by Martin Pelletier](https://www.php.net/manual/en/function.rename.php#102274) in
     * the PHP documentation. By setting rotateByCopy to `true` you can work
     * around this.
     * @deprecated since 2.0.46 and setting it to false has no effect anymore
     * since rotating is now always done by copy.
     */
    public $rotate_by_copy = true;
    /**
     * Initializes the route.
     * This method is invoked after the route is created by the route manager.
     */
    public function init(): void
    {
        parent::init();
        if ($this->log_file === null) {
            $this->log_file = Yii::$app->get_runtime_path() . '/logs/app.log';
        } else {
            $this->log_file = Yii::get_alias($this->log_file);
        }
        if ($this->max_log_files < 1) {
            $this->max_log_files = 1;
        }
        if ($this->max_file_size < 1) {
            $this->max_file_size = 1;
        }
    }
    /**
     * Writes log messages to a file.
     * Starting from version 2.0.14, this method throws LogRuntimeException in case the log can not be exported.
     * @throws InvalidConfigException if unable to open the log file for writing
     * @throws LogRuntimeException if unable to write complete log to file
     */
    public function export(): void
    {
        $text = implode("\n", array_map([$this, 'formatMessage'], $this->messages)) . "\n";
        if (trim($text) === '') {
            return;
            // No messages to export, so we exit the function early
        }
        if (strpos($this->log_file, '://') === false || strncmp($this->log_file, 'file://', 7) === 0) {
            $log_path = dirname($this->log_file);
            File_Helper::create_directory($log_path, $this->dir_mode, true);
        }
        if (($fp = @fopen($this->log_file, 'a')) === false) {
            throw new Invalid_Config_Exception("Unable to append to log file: {$this->log_file}");
        }
        @flock($fp, LOCK_EX);
        if ($this->enable_rotation) {
            // clear stat cache to ensure getting the real current file size and not a cached one
            // this may result in rotating twice when cached file size is used on subsequent calls
            clearstatcache();
        }
        if ($this->enable_rotation && @filesize($this->log_file) > $this->max_file_size * 1024) {
            $this->rotate_files();
        }
        $write_result = @fwrite($fp, $text);
        if ($write_result === false) {
            $message = "Unable to export log through file ({$this->log_file})!";
            if ($error = error_get_last()) {
                $message .= ": {$error['message']}";
            }
            throw new Log_Runtime_Exception($message);
        }
        $text_size = strlen($text);
        if ($write_result < $text_size) {
            throw new Log_Runtime_Exception("Unable to export whole log through file ({$this->log_file})! Wrote {$write_result} out of {$text_size} bytes.");
        }
        @fflush($fp);
        @flock($fp, LOCK_UN);
        @fclose($fp);
        if ($this->file_mode !== null) {
            @chmod($this->log_file, $this->file_mode);
        }
    }
    /**
     * Rotates log files.
     */
    protected function rotate_files()
    {
        $file = $this->log_file;
        for ($i = $this->max_log_files; $i >= 0; --$i) {
            // $i == 0 is the original log file
            $rotate_file = $file . ($i === 0 ? '' : '.' . $i);
            if (is_file($rotate_file)) {
                // suppress errors because it's possible multiple processes enter into this section
                if ($i === $this->max_log_files) {
                    @unlink($rotate_file);
                    continue;
                }
                $new_file = $this->log_file . '.' . ($i + 1);
                $this->rotate_by_copy($rotate_file, $new_file);
                if ($i === 0) {
                    $this->clear_log_file($rotate_file);
                }
            }
        }
    }
    /***
     * Clear log file without closing any other process open handles
     * @param string $rotateFile
     */
    private function clear_log_file(string $rotate_file): void
    {
        if ($file_pointer = @fopen($rotate_file, 'a')) {
            @ftruncate($file_pointer, 0);
            @fclose($file_pointer);
        }
    }
    /***
     * Copy rotated file into new file
     * @param string $rotateFile
     * @param string $newFile
     */
    private function rotate_by_copy(string $rotate_file, string $new_file): void
    {
        @copy($rotate_file, $new_file);
        if ($this->file_mode !== null) {
            @chmod($new_file, $this->file_mode);
        }
    }
}