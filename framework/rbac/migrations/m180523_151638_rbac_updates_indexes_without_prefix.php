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
 * Updates indexes without a prefix.
 *
 * @see https://github.com/yiisoft/yii2/pull/15548
 *
 * @author Sergey Gonimar <sergey.gonimar@gmail.com>
 * @since 2.0.16
 */
class m180523_151638_rbac_updates_indexes_without_prefix extends Migration
{
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
        $this->drop_index('auth_assignment_user_id_idx', $auth_manager->assignment_table);
        $this->create_index('{{%idx-auth_assignment-user_id}}', $auth_manager->assignment_table, 'user_id');
        $this->drop_index('idx-auth_item-type', $auth_manager->item_table);
        $this->create_index('{{%idx-auth_item-type}}', $auth_manager->item_table, 'type');
    }
    /**
     * {@inheritdoc}
     */
    public function down(): ?bool
    {
        $auth_manager = $this->get_auth_manager();
        $this->db = $auth_manager->db;
        $this->drop_index('{{%idx-auth_assignment-user_id}}', $auth_manager->assignment_table);
        $this->create_index('auth_assignment_user_id_idx', $auth_manager->assignment_table, 'user_id');
        $this->drop_index('{{%idx-auth_item-type}}', $auth_manager->item_table);
        $this->create_index('idx-auth_item-type', $auth_manager->item_table, 'type');
    }
}