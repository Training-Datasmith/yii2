<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\mutex;

use PDO;
use yii\base\Invalid_Config_Exception;
/**
 * OracleMutex implements mutex "lock" mechanism via Oracle locks.
 *
 * Application configuration example:
 *
 * ```
 * [
 *     'components' => [
 *         'db' => [
 *             'class' => 'yii\db\Connection',
 *             'dsn' => 'oci:dbname=LOCAL_XE',
 *              ...
 *         ]
 *         'mutex' => [
 *             'class' => 'yii\mutex\OracleMutex',
 *             'lockMode' => 'NL_MODE',
 *             'releaseOnCommit' => true,
 *              ...
 *         ],
 *     ],
 * ]
 * ```
 *
 * @see https://docs.oracle.com/cd/B19306_01/appdev.102/b14258/d_lock.htm#ARPLS021
 * @see Mutex
 *
 * @author Alexander Zlakomanov <zlakomanoff@gmail.com>
 * @since 2.0.10
 */
class Oracle_Mutex extends Db_Mutex
{
    /** available lock modes */
    public const MODE_X = 'X_MODE';
    public const MODE_NL = 'NL_MODE';
    public const MODE_S = 'S_MODE';
    public const MODE_SX = 'SX_MODE';
    public const MODE_SS = 'SS_MODE';
    public const MODE_SSX = 'SSX_MODE';
    /**
     * @var string lock mode to be used.
     * @see https://docs.oracle.com/cd/B19306_01/appdev.102/b14258/d_lock.htm#ARPLS021#CHDBCFDI
     */
    public $lock_mode = self::MODE_X;
    /**
     * @var bool whether to release lock on commit.
     */
    public $release_on_commit = false;
    /**
     * Initializes Oracle specific mutex component implementation.
     * @throws InvalidConfigException if [[db]] is not Oracle connection.
     */
    public function init(): void
    {
        parent::init();
        if (strncmp($this->db->driver_name, 'oci', 3) !== 0 && strncmp($this->db->driver_name, 'odbc', 4) !== 0) {
            throw new Invalid_Config_Exception('In order to use OracleMutex connection must be configured to use Oracle database.');
        }
    }
    /**
     * Acquires lock by given name.
     * @see https://docs.oracle.com/cd/B19306_01/appdev.102/b14258/d_lock.htm#ARPLS021
     * @param string $name of the lock to be acquired.
     * @param int $timeout time (in seconds) to wait for lock to become released.
     * @return bool acquiring result.
     */
    protected function acquire_lock($name, $timeout = 0): bool
    {
        $lock_status = null;
        // clean vars before using
        $release_on_commit = $this->release_on_commit ? 'TRUE' : 'FALSE';
        $timeout = abs((int) $timeout);
        // inside pl/sql scopes pdo binding not working correctly :(
        $this->db->use_master(function ($db) use ($name, $timeout, $release_on_commit, &$lock_status): void {
            /** @var \yii\db\Connection $db */
            $db->create_command('DECLARE
    handle VARCHAR2(128);
BEGIN
    DBMS_LOCK.ALLOCATE_UNIQUE(:name, handle);
    :lockStatus := DBMS_LOCK.REQUEST(handle, DBMS_LOCK.' . $this->lock_mode . ', ' . $timeout . ', ' . $release_on_commit . ');
END;', [':name' => $name])->bind_param(':lockStatus', $lock_status, PDO::PARAM_INT, 1)->execute();
        });
        return $lock_status === 0 || $lock_status === '0';
    }
    /**
     * Releases lock by given name.
     * @param string $name of the lock to be released.
     * @return bool release result.
     * @see https://docs.oracle.com/cd/B19306_01/appdev.102/b14258/d_lock.htm#ARPLS021
     */
    protected function release_lock($name): bool
    {
        $release_status = null;
        $this->db->use_master(function ($db) use ($name, &$release_status): void {
            /** @var \yii\db\Connection $db */
            $db->create_command('DECLARE
    handle VARCHAR2(128);
BEGIN
    DBMS_LOCK.ALLOCATE_UNIQUE(:name, handle);
    :result := DBMS_LOCK.RELEASE(handle);
END;', [':name' => $name])->bind_param(':result', $release_status, PDO::PARAM_INT, 1)->execute();
        });
        return $release_status === 0 || $release_status === '0';
    }
}