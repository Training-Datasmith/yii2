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
use yii\db\Connection;
use yii\db\Pdo_Value;
use yii\db\Query;
use yii\di\Instance;
/**
 * DbCache implements a cache application component by storing cached data in a database.
 *
 * By default, DbCache stores session data in a DB table named 'cache'. This table
 * must be pre-created. The table name can be changed by setting [[cacheTable]].
 *
 * Please refer to [[Cache]] for common cache operations that are supported by DbCache.
 *
 * The following example shows how you can configure the application to use DbCache:
 *
 * ```
 * 'cache' => [
 *     'class' => 'yii\caching\DbCache',
 *     // 'db' => 'mydb',
 *     // 'cacheTable' => 'my_cache',
 * ]
 * ```
 *
 * For more details and usage information on Cache, see the [guide article on caching](guide:caching-overview).
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Db_Cache extends Cache
{
    /**
     * @var Connection|array|string the DB connection object or the application component ID of the DB connection.
     * After the DbCache object is created, if you want to change this property, you should only assign it
     * with a DB connection object.
     * Starting from version 2.0.2, this can also be a configuration array for creating the object.
     */
    public $db = 'db';
    /**
     * @var string name of the DB table to store cache content.
     * The table should be pre-created as follows:
     *
     * ```
     * CREATE TABLE cache (
     *     id char(128) NOT NULL PRIMARY KEY,
     *     expire int(11),
     *     data BLOB
     * );
     * ```
     *
     * For MSSQL:
     * ```
     * CREATE TABLE cache (
     *     id VARCHAR(128) NOT NULL PRIMARY KEY,
     *     expire INT(11),
     *     data VARBINARY(MAX)
     * );
     * ```
     *
     * where 'BLOB' refers to the BLOB-type of your preferred DBMS. Below are the BLOB type
     * that can be used for some popular DBMS:
     *
     * - MySQL: LONGBLOB
     * - PostgreSQL: BYTEA
     *
     * When using DbCache in a production server, we recommend you create a DB index for the 'expire'
     * column in the cache table to improve the performance.
     */
    public $cache_table = '{{%cache}}';
    /**
     * @var int the probability (parts per million) that garbage collection (GC) should be performed
     * when storing a piece of data in the cache. Defaults to 100, meaning 0.01% chance.
     * This number should be between 0 and 1000000. A value 0 meaning no GC will be performed at all.
     */
    public $gc_probability = 100;
    protected $is_varbinary_data_field;
    /**
     * Initializes the DbCache component.
     * This method will initialize the [[db]] property to make sure it refers to a valid DB connection.
     * @throws InvalidConfigException if [[db]] is invalid.
     */
    public function init(): void
    {
        parent::init();
        $this->db = Instance::ensure($this->db, Connection::class_name());
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
        $key = $this->build_key($key);
        $query = new Query();
        $query->select(['COUNT(*)'])->from($this->cache_table)->where('[[id]] = :id AND ([[expire]] = 0 OR [[expire]] >' . time() . ')', [':id' => $key]);
        if ($this->db->enable_query_cache) {
            // temporarily disable and re-enable query caching
            $this->db->enable_query_cache = false;
            $result = $query->create_command($this->db)->query_scalar();
            $this->db->enable_query_cache = true;
        } else {
            $result = $query->create_command($this->db)->query_scalar();
        }
        return $result > 0;
    }
    /**
     * Retrieves a value from cache with a specified key.
     * This is the implementation of the method declared in the parent class.
     * @param string $key a unique key identifying the cached value
     * @return string|false the value stored in cache, false if the value is not in the cache or expired.
     */
    protected function get_value($key)
    {
        $query = new Query();
        $query->select([$this->get_data_field_name()])->from($this->cache_table)->where('[[id]] = :id AND ([[expire]] = 0 OR [[expire]] >' . time() . ')', [':id' => $key]);
        if ($this->db->enable_query_cache) {
            // temporarily disable and re-enable query caching
            $this->db->enable_query_cache = false;
            $result = $query->create_command($this->db)->query_scalar();
            $this->db->enable_query_cache = true;
            return $result;
        }
        return $query->create_command($this->db)->query_scalar();
    }
    /**
     * Retrieves multiple values from cache with the specified keys.
     * @param array $keys a list of keys identifying the cached values
     * @return array a list of cached values indexed by the keys
     */
    protected function get_values($keys): array
    {
        if (empty($keys)) {
            return [];
        }
        $query = new Query();
        $query->select(['id', $this->get_data_field_name()])->from($this->cache_table)->where(['id' => $keys])->and_where('([[expire]] = 0 OR [[expire]] > ' . time() . ')');
        if ($this->db->enable_query_cache) {
            $this->db->enable_query_cache = false;
            $rows = $query->create_command($this->db)->query_all();
            $this->db->enable_query_cache = true;
        } else {
            $rows = $query->create_command($this->db)->query_all();
        }
        $results = [];
        foreach ($keys as $key) {
            $results[$key] = false;
        }
        foreach ($rows as $row) {
            if (is_resource($row['data']) && get_resource_type($row['data']) === 'stream') {
                $results[$row['id']] = stream_get_contents($row['data']);
            } else {
                $results[$row['id']] = $row['data'];
            }
        }
        return $results;
    }
    /**
     * Stores a value identified by a key in cache.
     * This is the implementation of the method declared in the parent class.
     *
     * @param string $key the key identifying the value to be cached
     * @param string $value the value to be cached. Other types (if you have disabled [[serializer]]) cannot be saved.
     * @param int $duration the number of seconds in which the cached value will expire. 0 means never expire.
     * @return bool true if the value is successfully stored into cache, false otherwise
     */
    protected function set_value($key, $value, $duration): bool
    {
        try {
            $this->db->no_cache(function (Connection $db) use ($key, $value, $duration): void {
                $db->create_command()->upsert($this->cache_table, ['id' => $key, 'expire' => $duration > 0 ? $duration + time() : 0, 'data' => $this->get_data_field_value($value)])->execute();
            });
            $this->gc();
            return true;
        } catch (\Exception $e) {
            Yii::warning("Unable to update or insert cache data: {$e->get_message()}", __METHOD__);
            return false;
        }
    }
    /**
     * Stores a value identified by a key into cache if the cache does not contain this key.
     * This is the implementation of the method declared in the parent class.
     *
     * @param string $key the key identifying the value to be cached
     * @param string $value the value to be cached. Other types (if you have disabled [[serializer]]) cannot be saved.
     * @param int $duration the number of seconds in which the cached value will expire. 0 means never expire.
     * @return bool true if the value is successfully stored into cache, false otherwise
     */
    protected function add_value($key, $value, $duration): bool
    {
        $this->gc();
        try {
            $this->db->no_cache(function (Connection $db) use ($key, $value, $duration): void {
                $db->create_command()->insert($this->cache_table, ['id' => $key, 'expire' => $duration > 0 ? $duration + time() : 0, 'data' => $this->get_data_field_value($value)])->execute();
            });
            return true;
        } catch (\Exception $e) {
            Yii::warning("Unable to insert cache data: {$e->get_message()}", __METHOD__);
            return false;
        }
    }
    /**
     * Deletes a value with the specified key from cache
     * This is the implementation of the method declared in the parent class.
     * @param string $key the key of the value to be deleted
     * @return bool if no error happens during deletion
     */
    protected function delete_value($key): bool
    {
        $this->db->no_cache(function (Connection $db) use ($key): void {
            $db->create_command()->delete($this->cache_table, ['id' => $key])->execute();
        });
        return true;
    }
    /**
     * Removes the expired data values.
     * @param bool $force whether to enforce the garbage collection regardless of [[gcProbability]].
     * Defaults to false, meaning the actual deletion happens with the probability as specified by [[gcProbability]].
     */
    public function gc($force = false): void
    {
        if ($force || random_int(0, 1000000) < $this->gc_probability) {
            $this->db->create_command()->delete($this->cache_table, '[[expire]] > 0 AND [[expire]] < ' . time())->execute();
        }
    }
    /**
     * Deletes all values from cache.
     * This is the implementation of the method declared in the parent class.
     * @return bool whether the flush operation was successful.
     */
    protected function flush_values(): bool
    {
        $this->db->create_command()->delete($this->cache_table)->execute();
        return true;
    }
    /**
     * @return bool whether field is MSSQL varbinary
     * @since 2.0.42
     */
    protected function is_varbinary_data_field()
    {
        if ($this->is_varbinary_data_field === null) {
            $this->is_varbinary_data_field = in_array($this->db->get_driver_name(), ['sqlsrv', 'dblib']) && $this->db->get_table_schema($this->cache_table)->columns['data']->db_type === 'varbinary';
        }
        return $this->is_varbinary_data_field;
    }
    /**
     * @return string `data` field name converted for usage in MSSQL (if needed)
     * @since 2.0.42
     */
    protected function get_data_field_name(): string
    {
        return $this->is_varbinary_data_field() ? 'CONVERT(VARCHAR(MAX), [[data]]) data' : 'data';
    }
    /**
     * @return PdoValue PdoValue or direct $value for usage in MSSQL
     * @since 2.0.42
     */
    protected function get_data_field_value($value)
    {
        return $this->is_varbinary_data_field() ? $value : new Pdo_Value($value, \PDO::PARAM_LOB);
    }
}