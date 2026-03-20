<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\caching;

use Yii;
use yii\base\Invalid_Config_Exception;
/**
 * MemCache implements a cache application component based on [memcache](https://pecl.php.net/package/memcache)
 * and [memcached](https://pecl.php.net/package/memcached).
 *
 * MemCache supports both [memcache](https://pecl.php.net/package/memcache) and
 * [memcached](https://pecl.php.net/package/memcached). By setting [[useMemcached]] to be true or false,
 * one can let MemCache to use either memcached or memcache, respectively.
 *
 * MemCache can be configured with a list of memcache servers by settings its [[servers]] property.
 * By default, MemCache assumes there is a memcache server running on localhost at port 11211.
 *
 * See [[Cache]] for common cache operations that MemCache supports.
 *
 * Note, there is no security measure to protected data in memcache.
 * All data in memcache can be accessed by any process running in the system.
 *
 * To use MemCache as the cache application component, configure the application as follows,
 *
 * ```
 * [
 *     'components' => [
 *         'cache' => [
 *             'class' => 'yii\caching\MemCache',
 *             'servers' => [
 *                 [
 *                     'host' => 'server1',
 *                     'port' => 11211,
 *                     'weight' => 60,
 *                 ],
 *                 [
 *                     'host' => 'server2',
 *                     'port' => 11211,
 *                     'weight' => 40,
 *                 ],
 *             ],
 *         ],
 *     ],
 * ]
 * ```
 *
 * In the above, two memcache servers are used: server1 and server2. You can configure more properties of
 * each server, such as `persistent`, `weight`, `timeout`. Please see [[MemCacheServer]] for available options.
 *
 * For more details and usage information on Cache, see the [guide article on caching](guide:caching-overview).
 *
 * @property-read \Memcache|\Memcached $memcache The memcache (or memcached) object used by this cache
 * component.
 * @property MemCacheServer[] $servers List of memcache server configurations. Note that the type of this
 * property differs in getter and setter. See [[getServers()]] and [[setServers()]] for details.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Mem_Cache extends Cache
{
    /**
     * @var bool whether to use memcached or memcache as the underlying caching extension.
     * If true, [memcached](https://pecl.php.net/package/memcached) will be used.
     * If false, [memcache](https://pecl.php.net/package/memcache) will be used.
     * Defaults to false.
     */
    public $use_memcached = false;
    /**
     * @var string an ID that identifies a Memcached instance. This property is used only when [[useMemcached]] is true.
     * By default the Memcached instances are destroyed at the end of the request. To create an instance that
     * persists between requests, you may specify a unique ID for the instance. All instances created with the
     * same ID will share the same connection.
     * @see https://www.php.net/manual/en/memcached.construct.php
     */
    public $persistent_id;
    /**
     * @var array options for Memcached. This property is used only when [[useMemcached]] is true.
     * @see https://www.php.net/manual/en/memcached.setoptions.php
     */
    public $options;
    /**
     * @var string memcached sasl username. This property is used only when [[useMemcached]] is true.
     * @see https://www.php.net/manual/en/memcached.setsaslauthdata.php
     */
    public $username;
    /**
     * @var string memcached sasl password. This property is used only when [[useMemcached]] is true.
     * @see https://www.php.net/manual/en/memcached.setsaslauthdata.php
     */
    public $password;
    /**
     * @var \Memcache|\Memcached the Memcache instance
     */
    private $_cache;
    /**
     * @var array list of memcache server configurations
     */
    private array $_servers = [];
    /**
     * Initializes this application component.
     * It creates the memcache instance and adds memcache servers.
     */
    public function init(): void
    {
        parent::init();
        $this->add_servers($this->get_memcache(), $this->get_servers());
    }
    /**
     * Add servers to the server pool of the cache specified.
     *
     * @param \Memcache|\Memcached $cache
     * @param MemCacheServer[] $servers
     * @throws InvalidConfigException
     */
    protected function add_servers($cache, $servers)
    {
        if (empty($servers)) {
            $servers = [new Mem_Cache_Server(['host' => '127.0.0.1', 'port' => 11211])];
        } else {
            foreach ($servers as $server) {
                if ($server->host === null) {
                    throw new Invalid_Config_Exception("The 'host' property must be specified for every memcache server.");
                }
            }
        }
        if ($this->use_memcached) {
            $this->add_memcached_servers($cache, $servers);
        } else {
            $this->add_memcache_servers($cache, $servers);
        }
    }
    /**
     * Add servers to the server pool of the cache specified
     * Used for memcached PECL extension.
     *
     * @param \Memcached $cache
     * @param MemCacheServer[] $servers
     */
    protected function add_memcached_servers($cache, $servers)
    {
        $existing_servers = [];
        if ($this->persistent_id !== null) {
            foreach ($cache->get_server_list() as $s) {
                $existing_servers[$s['host'] . ':' . $s['port']] = true;
            }
        }
        foreach ($servers as $server) {
            if (empty($existing_servers) || !isset($existing_servers[$server->host . ':' . $server->port])) {
                $cache->add_server($server->host, $server->port, $server->weight);
            }
        }
    }
    /**
     * Add servers to the server pool of the cache specified
     * Used for memcache PECL extension.
     *
     * @param \Memcache $cache
     * @param MemCacheServer[] $servers
     */
    protected function add_memcache_servers($cache, $servers)
    {
        $class = new \ReflectionClass($cache);
        $param_count = $class->get_method('addServer')->get_number_of_parameters();
        foreach ($servers as $server) {
            // $timeout is used for memcache versions that do not have $timeoutms parameter
            $timeout = (int) ($server->timeout / 1000) + ($server->timeout % 1000 > 0 ? 1 : 0);
            if ($param_count === 9) {
                $cache->addserver($server->host, $server->port, $server->persistent, $server->weight, $timeout, $server->retry_interval, $server->status, $server->failure_callback, $server->timeout);
            } else {
                $cache->addserver($server->host, $server->port, $server->persistent, $server->weight, $timeout, $server->retry_interval, $server->status, $server->failure_callback);
            }
        }
    }
    /**
     * Returns the underlying memcache (or memcached) object.
     * @return \Memcache|\Memcached the memcache (or memcached) object used by this cache component.
     * @throws InvalidConfigException if memcache or memcached extension is not loaded
     */
    public function get_memcache()
    {
        if ($this->_cache === null) {
            $extension = $this->use_memcached ? 'memcached' : 'memcache';
            if (!extension_loaded($extension)) {
                throw new Invalid_Config_Exception("MemCache requires PHP {$extension} extension to be loaded.");
            }
            if ($this->use_memcached) {
                $this->_cache = $this->persistent_id !== null ? new \Memcached($this->persistent_id) : new \Memcached();
                if ($this->username !== null || $this->password !== null) {
                    $this->_cache->set_option(\Memcached::OPT_BINARY_PROTOCOL, true);
                    $this->_cache->set_sasl_auth_data($this->username, $this->password);
                }
                if (!empty($this->options)) {
                    $this->_cache->set_options($this->options);
                }
            } else {
                $this->_cache = new \Memcache();
            }
        }
        return $this->_cache;
    }
    /**
     * Returns the memcache or memcached server configurations.
     * @return MemCacheServer[] list of memcache server configurations.
     */
    public function get_servers()
    {
        return $this->_servers;
    }
    /**
     * @param array $config list of memcache or memcached server configurations. Each element must be an array
     * with the following keys: host, port, persistent, weight, timeout, retryInterval, status.
     * @see https://www.php.net/manual/en/memcache.addserver.php
     * @see https://www.php.net/manual/en/memcached.addserver.php
     */
    public function set_servers($config): void
    {
        foreach ($config as $c) {
            $this->_servers[] = new Mem_Cache_Server($c);
        }
    }
    /**
     * Retrieves a value from cache with a specified key.
     * This is the implementation of the method declared in the parent class.
     * @param string $key a unique key identifying the cached value
     * @return mixed|false the value stored in cache, false if the value is not in the cache or expired.
     */
    protected function get_value($key)
    {
        return $this->_cache->get($key);
    }
    /**
     * Retrieves multiple values from cache with the specified keys.
     * @param array $keys a list of keys identifying the cached values
     * @return array a list of cached values indexed by the keys
     */
    protected function get_values($keys)
    {
        return $this->use_memcached ? $this->_cache->get_multi($keys) : $this->_cache->get($keys);
    }
    /**
     * Stores a value identified by a key in cache.
     * This is the implementation of the method declared in the parent class.
     *
     * @param string $key the key identifying the value to be cached
     * @param mixed $value the value to be cached.
     * @see [Memcache::set()](https://www.php.net/manual/en/memcache.set.php)
     * @param int $duration the number of seconds in which the cached value will expire. 0 means never expire.
     * @return bool true if the value is successfully stored into cache, false otherwise
     */
    protected function set_value($key, $value, $duration)
    {
        $expire = $this->normalize_duration($duration);
        return $this->use_memcached ? $this->_cache->set($key, $value, $expire) : $this->_cache->set($key, $value, 0, $expire);
    }
    /**
     * Stores multiple key-value pairs in cache.
     * @param array $data array where key corresponds to cache key while value is the value stored
     * @param int $duration the number of seconds in which the cached values will expire. 0 means never expire.
     * @return array array of failed keys.
     */
    protected function set_values($data, $duration)
    {
        if ($this->use_memcached) {
            $expire = $this->normalize_duration($duration);
            // Memcached::setMulti() returns boolean
            // @see https://www.php.net/manual/en/memcached.setmulti.php
            return $this->_cache->set_multi($data, $expire) ? [] : array_keys($data);
        }
        return parent::set_values($data, $duration);
    }
    /**
     * Stores a value identified by a key into cache if the cache does not contain this key.
     * This is the implementation of the method declared in the parent class.
     *
     * @param string $key the key identifying the value to be cached
     * @param mixed $value the value to be cached
     * @see [Memcache::set()](https://www.php.net/manual/en/memcache.set.php)
     * @param int $duration the number of seconds in which the cached value will expire. 0 means never expire.
     * @return bool true if the value is successfully stored into cache, false otherwise
     */
    protected function add_value($key, $value, $duration)
    {
        $expire = $this->normalize_duration($duration);
        return $this->use_memcached ? $this->_cache->add($key, $value, $expire) : $this->_cache->add($key, $value, 0, $expire);
    }
    /**
     * Deletes a value with the specified key from cache
     * This is the implementation of the method declared in the parent class.
     * @param string $key the key of the value to be deleted
     * @return bool if no error happens during deletion
     */
    protected function delete_value($key)
    {
        return $this->_cache->delete($key, 0);
    }
    /**
     * Deletes all values from cache.
     * This is the implementation of the method declared in the parent class.
     * @return bool whether the flush operation was successful.
     */
    protected function flush_values()
    {
        return $this->_cache->flush();
    }
    /**
     * Normalizes duration value
     *
     * @see https://github.com/yiisoft/yii2/issues/17710
     * @see https://www.php.net/manual/en/memcache.set.php
     * @see https://www.php.net/manual/en/memcached.expiration.php
     *
     * @since 2.0.31
     * @param int $duration
     * @return int
     */
    protected function normalize_duration($duration)
    {
        if ($duration < 0) {
            return 0;
        }
        if ($duration < 2592001) {
            return $duration;
        }
        return $duration + time();
    }
}