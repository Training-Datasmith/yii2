<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\mutex;

use yii\base\Invalid_Config_Exception;
use yii\db\Expression;
/**
 * MysqlMutex implements mutex "lock" mechanism via MySQL locks.
 *
 * Application configuration example:
 *
 * ```
 * [
 *     'components' => [
 *         'db' => [
 *             'class' => 'yii\db\Connection',
 *             'dsn' => 'mysql:host=127.0.0.1;dbname=demo',
 *         ]
 *         'mutex' => [
 *             'class' => 'yii\mutex\MysqlMutex',
 *         ],
 *     ],
 * ]
 * ```
 *
 * @see Mutex
 *
 * @author resurtm <resurtm@gmail.com>
 * @since 2.0
 */
class Mysql_Mutex extends Db_Mutex
{
    /**
     * @var Expression|string|null prefix value. If null (by default) then connection's current database name is used.
     * @since 2.0.47
     */
    public $key_prefix;
    /**
     * Initializes MySQL specific mutex component implementation.
     * @throws InvalidConfigException if [[db]] is not MySQL connection.
     */
    public function init(): void
    {
        parent::init();
        if ($this->db->driver_name !== 'mysql') {
            throw new Invalid_Config_Exception('In order to use MysqlMutex connection must be configured to use MySQL database.');
        }
        if ($this->key_prefix === null) {
            $this->key_prefix = new Expression('DATABASE()');
        }
    }
    /**
     * Acquires lock by given name.
     * @param string $name of the lock to be acquired.
     * @param int $timeout time (in seconds) to wait for lock to become released.
     * @return bool acquiring result.
     * @see https://dev.mysql.com/doc/refman/8.0/en/miscellaneous-functions.html#function_get-lock
     */
    protected function acquire_lock($name, $timeout = 0)
    {
        return $this->db->use_master(function ($db) use ($name, $timeout): bool {
            /** @var \yii\db\Connection $db */
            $name_data = $this->prepare_name();
            return (bool) $db->create_command('SELECT GET_LOCK(' . $name_data[0] . ', :timeout), :prefix', array_merge([':name' => $this->hash_lock_name($name), ':timeout' => $timeout, ':prefix' => $this->key_prefix], $name_data[1]))->query_scalar();
        });
    }
    /**
     * Releases lock by given name.
     * @param string $name of the lock to be released.
     * @return bool release result.
     * @see https://dev.mysql.com/doc/refman/8.0/en/miscellaneous-functions.html#function_release-lock
     */
    protected function release_lock($name)
    {
        return $this->db->use_master(function ($db) use ($name): bool {
            /** @var \yii\db\Connection $db */
            $name_data = $this->prepare_name();
            return (bool) $db->create_command('SELECT RELEASE_LOCK(' . $name_data[0] . '), :prefix', array_merge([':name' => $this->hash_lock_name($name), ':prefix' => $this->key_prefix], $name_data[1]))->query_scalar();
        });
    }
    /**
     * Prepare lock name
     * @return array expression and params
     * @since 2.0.48
     */
    protected function prepare_name(): array
    {
        $params = [];
        $expression = 'SUBSTRING(CONCAT(:prefix, :name), 1, 64)';
        if ($this->key_prefix instanceof Expression) {
            $expression = strtr($expression, [':prefix' => $this->key_prefix->expression]);
            $params = $this->key_prefix->params;
        }
        return [$expression, $params];
    }
    /**
     * Generate hash for lock name to avoid exceeding lock name length limit.
     *
     * @param string $name
     * @since 2.0.16
     * @see https://github.com/yiisoft/yii2/pull/16836
     */
    protected function hash_lock_name($name): string
    {
        return sha1($name);
    }
}