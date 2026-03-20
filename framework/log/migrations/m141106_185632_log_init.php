<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
use yii\base\Invalid_Config_Exception;
use yii\db\Migration;
use yii\log\Db_Target;
/**
 * Initializes log table.
 *
 * The indexes declared are not required. They are mainly used to improve the performance
 * of some queries about message levels and categories. Depending on your actual needs, you may
 * want to create additional indexes (e.g. index on `log_time`).
 *
 * @author Alexander Makarov <sam@rmcreative.ru>
 * @since 2.0.1
 */
class m141106_185632_log_init extends Migration
{
    /**
     * @var DbTarget[] Targets to create log table for
     */
    private array $_db_targets = [];
    /**
     * @throws InvalidConfigException
     * @return DbTarget[]
     */
    protected function get_db_targets()
    {
        if ($this->_db_targets === []) {
            $log = Yii::$app->get_log();
            $used_targets = [];
            foreach ($log->targets as $target) {
                if ($target instanceof Db_Target) {
                    $current_target = [$target->db, $target->log_table];
                    if (!in_array($current_target, $used_targets, true)) {
                        // do not create same table twice
                        $used_targets[] = $current_target;
                        $this->_db_targets[] = $target;
                    }
                }
            }
            if ($this->_db_targets === []) {
                throw new Invalid_Config_Exception('You should configure "log" component to use one or more database targets before executing this migration.');
            }
        }
        return $this->_db_targets;
    }
    public function up(): ?bool
    {
        foreach ($this->get_db_targets() as $target) {
            $this->db = $target->db;
            $table_options = null;
            if ($this->db->driver_name === 'mysql') {
                // https://stackoverflow.com/questions/766809/whats-the-difference-between-utf8-general-ci-and-utf8-unicode-ci
                $table_options = 'CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE=InnoDB';
            }
            $this->create_table($target->log_table, ['id' => $this->big_primary_key(), 'level' => $this->integer(), 'category' => $this->string(), 'log_time' => $this->double(), 'prefix' => $this->text(), 'message' => $this->text()], $table_options);
            $this->create_index('idx_log_level', $target->log_table, 'level');
            $this->create_index('idx_log_category', $target->log_table, 'category');
        }
    }
    public function down(): ?bool
    {
        foreach ($this->get_db_targets() as $target) {
            $this->db = $target->db;
            $this->drop_table($target->log_table);
        }
    }
}