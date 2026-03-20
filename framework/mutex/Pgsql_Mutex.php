<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\mutex;

use yii\base\Invalid_Config_Exception;
/**
 * PgsqlMutex implements mutex "lock" mechanism via PgSQL locks.
 *
 * Application configuration example:
 *
 * ```
 * [
 *     'components' => [
 *         'db' => [
 *             'class' => 'yii\db\Connection',
 *             'dsn' => 'pgsql:host=127.0.0.1;dbname=demo',
 *         ]
 *         'mutex' => [
 *             'class' => 'yii\mutex\PgsqlMutex',
 *         ],
 *     ],
 * ]
 * ```
 *
 * @see Mutex
 *
 * @author nineinchnick <janek.jan@gmail.com>
 * @since 2.0.8
 */
class Pgsql_Mutex extends Db_Mutex
{
    use Retry_Acquire_Trait;
    /**
     * Initializes PgSQL specific mutex component implementation.
     * @throws InvalidConfigException if [[db]] is not PgSQL connection.
     */
    public function init(): void
    {
        parent::init();
        if ($this->db->driver_name !== 'pgsql') {
            throw new Invalid_Config_Exception('In order to use PgsqlMutex connection must be configured to use PgSQL database.');
        }
    }
    /**
     * Converts a string into two 16 bit integer keys using the SHA1 hash function.
     * @param string $name
     * @return array contains two 16 bit integer keys
     */
    private function get_keys_from_name($name)
    {
        return array_values(unpack('n2', sha1($name, true)));
    }
    /**
     * Acquires lock by given name.
     * @param string $name of the lock to be acquired.
     * @param int $timeout time (in seconds) to wait for lock to become released.
     * @return bool acquiring result.
     * @see https://www.postgresql.org/docs/9.0/functions-admin.html
     */
    protected function acquire_lock($name, $timeout = 0)
    {
        [$key1, $key2] = $this->get_keys_from_name($name);
        return $this->retry_acquire($timeout, fn() => $this->db->use_master(function ($db) use ($key1, $key2): bool {
            /** @var \yii\db\Connection $db */
            return (bool) $db->create_command('SELECT pg_try_advisory_lock(:key1, :key2)', [':key1' => $key1, ':key2' => $key2])->query_scalar();
        }));
    }
    /**
     * Releases lock by given name.
     * @param string $name of the lock to be released.
     * @return bool release result.
     * @see https://www.postgresql.org/docs/9.0/functions-admin.html
     */
    protected function release_lock($name)
    {
        [$key1, $key2] = $this->get_keys_from_name($name);
        return $this->db->use_master(function ($db) use ($key1, $key2): bool {
            /** @var \yii\db\Connection $db */
            return (bool) $db->create_command('SELECT pg_advisory_unlock(:key1, :key2)', [':key1' => $key1, ':key2' => $key2])->query_scalar();
        });
    }
}