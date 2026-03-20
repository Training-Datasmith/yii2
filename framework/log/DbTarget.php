<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\log;

use yii\base\Invalid_Config_Exception;
use yii\db\Connection;
use yii\db\Exception;
use yii\di\Instance;
use yii\helpers\Var_Dumper;
/**
 * DbTarget stores log messages in a database table.
 *
 * The database connection is specified by [[db]]. Database schema could be initialized by applying migration:
 *
 * ```
 * yii migrate --migrationPath=@yii/log/migrations/
 * ```
 *
 * If you don't want to use migration and need SQL instead, files for all databases are in migrations directory.
 *
 * You may change the name of the table used to store the data by setting [[logTable]].
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Db_Target extends Target
{
    /**
     * @var Connection|array|string the DB connection object or the application component ID of the DB connection.
     * After the DbTarget object is created, if you want to change this property, you should only assign it
     * with a DB connection object.
     * Starting from version 2.0.2, this can also be a configuration array for creating the object.
     */
    public $db = 'db';
    /**
     * @var string name of the DB table to store cache content. Defaults to "log".
     */
    public $log_table = '{{%log}}';
    /**
     * Initializes the DbTarget component.
     * This method will initialize the [[db]] property to make sure it refers to a valid DB connection.
     * @throws InvalidConfigException if [[db]] is invalid.
     */
    public function init(): void
    {
        parent::init();
        $this->db = Instance::ensure($this->db, Connection::class_name());
    }
    /**
     * Stores log messages to DB.
     * Starting from version 2.0.14, this method throws LogRuntimeException in case the log can not be exported.
     * @throws Exception
     * @throws LogRuntimeException
     */
    public function export(): void
    {
        if ($this->db->get_transaction()) {
            // create new database connection, if there is an open transaction
            // to ensure insert statement is not affected by a rollback
            $this->db = clone $this->db;
        }
        $table_name = $this->db->quote_table_name($this->log_table);
        $sql = "INSERT INTO {$table_name} ([[level]], [[category]], [[log_time]], [[prefix]], [[message]])\n                VALUES (:level, :category, :log_time, :prefix, :message)";
        $command = $this->db->create_command($sql);
        foreach ($this->messages as $message) {
            [$text, $level, $category, $timestamp] = $message;
            if (!is_string($text)) {
                // exceptions may not be serializable if in the call stack somewhere is a Closure
                if ($text instanceof \Exception || $text instanceof \Throwable) {
                    $text = (string) $text;
                } else {
                    $text = Var_Dumper::export($text);
                }
            }
            if ($command->bind_values([':level' => $level, ':category' => $category, ':log_time' => $timestamp, ':prefix' => $this->get_message_prefix($message), ':message' => $text])->execute() > 0) {
                continue;
            }
            throw new Log_Runtime_Exception('Unable to export log through database!');
        }
    }
}