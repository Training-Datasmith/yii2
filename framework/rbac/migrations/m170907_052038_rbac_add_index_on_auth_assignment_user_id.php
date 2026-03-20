<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
use yii\base\Invalid_Config_Exception;
use yii\db\Migration;
use yii\rbac\Db_Manager;
/**
 * Adds index on `user_id` column in `auth_assignment` table for performance reasons.
 *
 * @see https://github.com/yiisoft/yii2/pull/14765
 *
 * @author Ivan Buttinoni <ivan.buttinoni@cibi.it>
 * @since 2.0.13
 */
class m170907_052038_rbac_add_index_on_auth_assignment_user_id extends Migration
{
    public $column = 'user_id';
    public $index = 'auth_assignment_user_id_idx';
    /**
     * @throws yii\base\InvalidConfigException
     * @return DbManager
     */
    protected function get_auth_manager()
    {
        $auth_manager = Yii::$app->get_auth_manager();
        if (!$auth_manager instanceof Db_Manager) {
            throw new Invalid_Config_Exception('You should configure "authManager" component to use database before executing this migration.');
        }
        return $auth_manager;
    }
    /**
     * {@inheritdoc}
     */
    public function up(): ?bool
    {
        $auth_manager = $this->get_auth_manager();
        $this->db = $auth_manager->db;
        $this->create_index($this->index, $auth_manager->assignment_table, $this->column);
    }
    /**
     * {@inheritdoc}
     */
    public function down(): ?bool
    {
        $auth_manager = $this->get_auth_manager();
        $this->db = $auth_manager->db;
        $this->drop_index($this->index, $auth_manager->assignment_table);
    }
}