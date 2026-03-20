<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\caching;

use Yii;
use yii\helpers\File_Helper;
/**
 * FileCache implements a cache component using files.
 *
 * For each data value being cached, FileCache will store it in a separate file.
 * The cache files are placed under [[cachePath]]. FileCache will perform garbage collection
 * automatically to remove expired cache files.
 *
 * Please refer to [[Cache]] for common cache operations that are supported by FileCache.
 *
 * For more details and usage information on Cache, see the [guide article on caching](guide:caching-overview).
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class File_Cache extends Cache
{
    /**
     * @var string a string prefixed to every cache key. This is needed when you store
     * cache data under the same [[cachePath]] for different applications to avoid
     * conflict.
     *
     * To ensure interoperability, only alphanumeric characters should be used.
     */
    public $key_prefix = '';
    /**
     * @var string the directory to store cache files. You may use [path alias](guide:concept-aliases) here.
     * If not set, it will use the "cache" subdirectory under the application runtime path.
     */
    public $cache_path = '@runtime/cache';
    /**
     * @var string cache file suffix. Defaults to '.bin'.
     */
    public $cache_file_suffix = '.bin';
    /**
     * @var int the level of sub-directories to store cache files. Defaults to 1.
     * If the system has huge number of cache files (e.g. one million), you may use a bigger value
     * (usually no bigger than 3). Using sub-directories is mainly to ensure the file system
     * is not over burdened with a single directory having too many files.
     */
    public $directory_level = 1;
    /**
     * @var int the probability (parts per million) that garbage collection (GC) should be performed
     * when storing a piece of data in the cache. Defaults to 10, meaning 0.001% chance.
     * This number should be between 0 and 1000000. A value 0 means no GC will be performed at all.
     */
    public $gc_probability = 10;
    /**
     * @var int|null the permission to be set for newly created cache files.
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
     * Initializes this component by ensuring the existence of the cache path.
     */
    public function init(): void
    {
        parent::init();
        $this->cache_path = Yii::get_alias($this->cache_path);
        if (!is_dir($this->cache_path)) {
            File_Helper::create_directory($this->cache_path, $this->dir_mode, true);
        }
    }
    /**
     * Checks whether a specified key exists in the cache.
     * This can be faster than getting the value from the cache if the data is big.
     * Note that this method does not check whether the dependency associated
     * with the cached data, if there is any, has changed. So a call to [[get]]
     * may return false while exists returns true.
     * @param mixed $key a key identifying the cached value. This can be a simple string or
     * a complex data structure consisting of factors representing the key.
     * @return bool true if a value exists in cache, false if the value is not in the cache or expired.
     */
    public function exists($key): bool
    {
        $cache_file = $this->get_cache_file($this->build_key($key));
        return @filemtime($cache_file) > time();
    }
    /**
     * Retrieves a value from cache with a specified key.
     * This is the implementation of the method declared in the parent class.
     * @param string $key a unique key identifying the cached value
     * @return string|false the value stored in cache, false if the value is not in the cache or expired.
     */
    protected function get_value($key)
    {
        $cache_file = $this->get_cache_file($key);
        if (@filemtime($cache_file) > time()) {
            $fp = @fopen($cache_file, 'r');
            if ($fp !== false) {
                @flock($fp, LOCK_SH);
                $cache_value = @stream_get_contents($fp);
                @flock($fp, LOCK_UN);
                @fclose($fp);
                return $cache_value;
            }
        }
        return false;
    }
    /**
     * Stores a value identified by a key in cache.
     * This is the implementation of the method declared in the parent class.
     *
     * @param string $key the key identifying the value to be cached
     * @param string $value the value to be cached. Other types (If you have disabled [[serializer]]) unable to get is
     * correct in [[getValue()]].
     * @param int $duration the number of seconds in which the cached value will expire. Fewer than or equal to 0 means 1 year expiration time.
     * @return bool true if the value is successfully stored into cache, false otherwise
     */
    protected function set_value($key, $value, $duration): bool
    {
        $this->gc();
        $cache_file = $this->get_cache_file($key);
        if ($this->directory_level > 0) {
            @File_Helper::create_directory(dirname($cache_file), $this->dir_mode, true);
        }
        // If ownership differs the touch call will fail, so we try to
        // rebuild the file from scratch by deleting it first
        // https://github.com/yiisoft/yii2/pull/16120
        if (is_file($cache_file) && function_exists('posix_geteuid') && fileowner($cache_file) !== posix_geteuid()) {
            @unlink($cache_file);
        }
        if (@file_put_contents($cache_file, $value, LOCK_EX) !== false) {
            if ($this->file_mode !== null) {
                @chmod($cache_file, $this->file_mode);
            }
            if ($duration <= 0) {
                $duration = 31536000;
                // 1 year
            }
            if (@touch($cache_file, $duration + time())) {
                clearstatcache();
                return true;
            }
            return false;
        }
        $message = "Unable to write cache file '{$cache_file}'";
        if ($error = error_get_last()) {
            $message .= ": {$error['message']}";
        }
        Yii::warning($message, __METHOD__);
        return false;
    }
    /**
     * Stores a value identified by a key into cache if the cache does not contain this key.
     * This is the implementation of the method declared in the parent class.
     *
     * @param string $key the key identifying the value to be cached
     * @param string $value the value to be cached. Other types (if you have disabled [[serializer]]) unable to get is
     * correct in [[getValue()]].
     * @param int $duration the number of seconds in which the cached value will expire. 0 means never expire.
     * @return bool true if the value is successfully stored into cache, false otherwise
     */
    protected function add_value($key, $value, $duration)
    {
        $cache_file = $this->get_cache_file($key);
        if (@filemtime($cache_file) > time()) {
            return false;
        }
        return $this->set_value($key, $value, $duration);
    }
    /**
     * Deletes a value with the specified key from cache
     * This is the implementation of the method declared in the parent class.
     * @param string $key the key of the value to be deleted
     * @return bool if no error happens during deletion
     */
    protected function delete_value($key)
    {
        $cache_file = $this->get_cache_file($key);
        return @unlink($cache_file);
    }
    /**
     * Returns the cache file path given the normalized cache key.
     * @param string $normalizedKey normalized cache key by [[buildKey]] method
     * @return string the cache file path
     */
    protected function get_cache_file(string $normalized_key): string
    {
        $cache_key = $normalized_key;
        if ($this->key_prefix !== '') {
            // Remove key prefix to avoid generating constant directory levels
            $len_key_prefix = strlen($this->key_prefix);
            $cache_key = substr_replace($normalized_key, '', 0, $len_key_prefix);
        }
        $cache_path = $this->cache_path;
        if ($this->directory_level > 0) {
            for ($i = 0; $i < $this->directory_level; ++$i) {
                if (($sub_directory = substr($cache_key, $i + $i, 2)) !== false) {
                    $cache_path .= DIRECTORY_SEPARATOR . $sub_directory;
                }
            }
        }
        return $cache_path . DIRECTORY_SEPARATOR . $normalized_key . $this->cache_file_suffix;
    }
    /**
     * Deletes all values from cache.
     * This is the implementation of the method declared in the parent class.
     * @return bool whether the flush operation was successful.
     */
    protected function flush_values(): bool
    {
        $this->gc(true, false);
        return true;
    }
    /**
     * Removes expired cache files.
     * @param bool $force whether to enforce the garbage collection regardless of [[gcProbability]].
     * Defaults to false, meaning the actual deletion happens with the probability as specified by [[gcProbability]].
     * @param bool $expiredOnly whether to removed expired cache files only.
     * If false, all cache files under [[cachePath]] will be removed.
     */
    public function gc($force = false, $expired_only = true): void
    {
        if ($force || random_int(0, 1000000) < $this->gc_probability) {
            $this->gc_recursive($this->cache_path, $expired_only);
        }
    }
    /**
     * Recursively removing expired cache files under a directory.
     * This method is mainly used by [[gc()]].
     * @param string $path the directory under which expired cache files are removed.
     * @param bool $expiredOnly whether to only remove expired cache files. If false, all files
     * under `$path` will be removed.
     */
    protected function gc_recursive(string $path, $expired_only)
    {
        if (($handle = opendir($path)) !== false) {
            while (($file = readdir($handle)) !== false) {
                if (strncmp($file, '.', 1) === 0) {
                    continue;
                }
                $full_path = $path . DIRECTORY_SEPARATOR . $file;
                $message = null;
                if (is_dir($full_path)) {
                    $this->gc_recursive($full_path, $expired_only);
                    if (!$expired_only) {
                        if (!@rmdir($full_path)) {
                            $message = "Unable to remove directory '{$full_path}'";
                            if ($error = error_get_last()) {
                                $message .= ": {$error['message']}";
                            }
                        }
                    }
                } elseif (!$expired_only || $expired_only && @filemtime($full_path) < time()) {
                    if (!@unlink($full_path)) {
                        $message = "Unable to remove file '{$full_path}'";
                        if ($error = error_get_last()) {
                            $message .= ": {$error['message']}";
                        }
                    }
                }
                $message and Yii::warning($message, __METHOD__);
            }
            closedir($handle);
        }
    }
}