<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
use yii\base\Invalid_Config_Exception;
use yii\rbac\Db_Manager;
/**
 * Initializes RBAC tables.
 *
 * @author Alexander Kochetov <creocoder@gmail.com>
 * @since 2.0
 */
class m140506_102106_rbac_init extends \yii\db\Migration
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
    protected function is_mssql(): bool
    {
        return $this->db->driver_name === 'mssql' || $this->db->driver_name === 'sqlsrv' || $this->db->driver_name === 'dblib';
    }
    protected function is_oracle(): bool
    {
        return $this->db->driver_name === 'oci' || $this->db->driver_name === 'oci8';
    }
    /**
     * {@inheritdoc}
     */
    public function up(): ?bool
    {
        $auth_manager = $this->get_auth_manager();
        $this->db = $auth_manager->db;
        $schema = $this->db->get_schema()->default_schema;
        $table_options = null;
        if ($this->db->driver_name === 'mysql') {
            // https://stackoverflow.com/questions/766809/whats-the-difference-between-utf8-general-ci-and-utf8-unicode-ci
            $table_options = 'CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE=InnoDB';
        }
        $this->create_table($auth_manager->rule_table, ['name' => $this->string(64)->not_null(), 'data' => $this->binary(), 'created_at' => $this->integer(), 'updated_at' => $this->integer(), 'PRIMARY KEY ([[name]])'], $table_options);
        $this->create_table($auth_manager->item_table, ['name' => $this->string(64)->not_null(), 'type' => $this->small_integer()->not_null(), 'description' => $this->text(), 'rule_name' => $this->string(64), 'data' => $this->binary(), 'created_at' => $this->integer(), 'updated_at' => $this->integer(), 'PRIMARY KEY ([[name]])', 'FOREIGN KEY ([[rule_name]]) REFERENCES ' . $auth_manager->rule_table . ' ([[name]])' . $this->build_fk_clause('ON DELETE SET NULL', 'ON UPDATE CASCADE')], $table_options);
        $this->create_index('idx-auth_item-type', $auth_manager->item_table, 'type');
        $this->create_table($auth_manager->item_child_table, ['parent' => $this->string(64)->not_null(), 'child' => $this->string(64)->not_null(), 'PRIMARY KEY ([[parent]], [[child]])', 'FOREIGN KEY ([[parent]]) REFERENCES ' . $auth_manager->item_table . ' ([[name]])' . $this->build_fk_clause('ON DELETE CASCADE', 'ON UPDATE CASCADE'), 'FOREIGN KEY ([[child]]) REFERENCES ' . $auth_manager->item_table . ' ([[name]])' . $this->build_fk_clause('ON DELETE CASCADE', 'ON UPDATE CASCADE')], $table_options);
        $this->create_table($auth_manager->assignment_table, ['item_name' => $this->string(64)->not_null(), 'user_id' => $this->string(64)->not_null(), 'created_at' => $this->integer(), 'PRIMARY KEY ([[item_name]], [[user_id]])', 'FOREIGN KEY ([[item_name]]) REFERENCES ' . $auth_manager->item_table . ' ([[name]])' . $this->build_fk_clause('ON DELETE CASCADE', 'ON UPDATE CASCADE')], $table_options);
        if ($this->is_mssql()) {
            $this->execute("CREATE TRIGGER {$schema}.trigger_auth_item_child\n            ON {$schema}.{$auth_manager->item_table}\n            INSTEAD OF DELETE, UPDATE\n            AS\n            DECLARE @old_name VARCHAR (64) = (SELECT name FROM deleted)\n            DECLARE @new_name VARCHAR (64) = (SELECT name FROM inserted)\n            BEGIN\n            IF COLUMNS_UPDATED() > 0\n                BEGIN\n                    IF @old_name <> @new_name\n                    BEGIN\n                        ALTER TABLE {$auth_manager->item_child_table} NOCHECK CONSTRAINT FK__auth_item__child;\n                        UPDATE {$auth_manager->item_child_table} SET child = @new_name WHERE child = @old_name;\n                    END\n                UPDATE {$auth_manager->item_table}\n                SET name = (SELECT name FROM inserted),\n                type = (SELECT type FROM inserted),\n                description = (SELECT description FROM inserted),\n                rule_name = (SELECT rule_name FROM inserted),\n                data = (SELECT data FROM inserted),\n                created_at = (SELECT created_at FROM inserted),\n                updated_at = (SELECT updated_at FROM inserted)\n                WHERE name IN (SELECT name FROM deleted)\n                IF @old_name <> @new_name\n                    BEGIN\n                        ALTER TABLE {$auth_manager->item_child_table} CHECK CONSTRAINT FK__auth_item__child;\n                    END\n                END\n                ELSE\n                    BEGIN\n                        DELETE FROM {$schema}.{$auth_manager->item_child_table} WHERE parent IN (SELECT name FROM deleted) OR child IN (SELECT name FROM deleted);\n                        DELETE FROM {$schema}.{$auth_manager->item_table} WHERE name IN (SELECT name FROM deleted);\n                    END\n            END;");
        }
    }
    /**
     * {@inheritdoc}
     */
    public function down(): ?bool
    {
        $auth_manager = $this->get_auth_manager();
        $this->db = $auth_manager->db;
        $schema = $this->db->get_schema()->default_schema;
        if ($this->is_mssql()) {
            $this->execute("DROP TRIGGER {$schema}.trigger_auth_item_child;");
        }
        $this->drop_table($auth_manager->assignment_table);
        $this->drop_table($auth_manager->item_child_table);
        $this->drop_table($auth_manager->item_table);
        $this->drop_table($auth_manager->rule_table);
    }
    protected function build_fk_clause(string $delete = '', $update = ''): string
    {
        if ($this->is_mssql()) {
            return '';
        }
        if ($this->is_oracle()) {
            return ' ' . $delete;
        }
        return implode(' ', ['', $delete, $update]);
    }
}