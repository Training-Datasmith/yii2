<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
use yii\base\Invalid_Config_Exception;
use yii\db\Migration;
use yii\db\Query;
use yii\rbac\Db_Manager;
/**
 * Fix MSSQL trigger.
 *
 * @see https://github.com/yiisoft/yii2/pull/17966
 *
 * @author Aurelien Chretien <chretien.aurelien@gmail.com>
 * @since 2.0.35
 */
class m200409_110543_rbac_update_mssql_trigger extends Migration
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
    protected function find_foreign_key_name($table, $column, $reference_table, $reference_column)
    {
        return (new Query())->select(['OBJECT_NAME(fkc.constraint_object_id)'])->from(['fkc' => 'sys.foreign_key_columns'])->inner_join(['c' => 'sys.columns'], 'fkc.parent_object_id = c.object_id AND fkc.parent_column_id = c.column_id')->inner_join(['r' => 'sys.columns'], 'fkc.referenced_object_id = r.object_id AND fkc.referenced_column_id = r.column_id')->and_where('fkc.parent_object_id=OBJECT_ID(:fkc_parent_object_id)', [':fkc_parent_object_id' => $this->db->schema->get_raw_table_name($table)])->and_where('fkc.referenced_object_id=OBJECT_ID(:fkc_referenced_object_id)', [':fkc_referenced_object_id' => $this->db->schema->get_raw_table_name($reference_table)])->and_where(['c.name' => $column])->and_where(['r.name' => $reference_column])->scalar($this->db);
    }
    protected function is_mssql(): bool
    {
        return $this->db->driver_name === 'mssql' || $this->db->driver_name === 'sqlsrv' || $this->db->driver_name === 'dblib';
    }
    /**
     * {@inheritdoc}
     */
    public function up(): ?bool
    {
        if ($this->is_mssql()) {
            $auth_manager = $this->get_auth_manager();
            $this->db = $auth_manager->db;
            $schema = $this->db->get_schema()->default_schema;
            $trigger_suffix = $this->db->schema->get_raw_table_name($auth_manager->item_child_table);
            $this->execute("IF (OBJECT_ID(N'{$schema}.trigger_{$trigger_suffix}') IS NOT NULL) DROP TRIGGER {$schema}.trigger_{$trigger_suffix};");
            $this->execute("IF (OBJECT_ID(N'{$schema}.trigger_auth_item_child') IS NOT NULL) DROP TRIGGER {$schema}.trigger_auth_item_child;");
            $this->execute("CREATE TRIGGER {$schema}.trigger_delete_{$trigger_suffix}\n            ON {$schema}.{$auth_manager->item_table}\n            INSTEAD OF DELETE\n            AS\n            BEGIN\n                  DELETE FROM {$schema}.{$auth_manager->item_child_table} WHERE parent IN (SELECT name FROM deleted) OR child IN (SELECT name FROM deleted);\n                  DELETE FROM {$schema}.{$auth_manager->item_table} WHERE name IN (SELECT name FROM deleted);\n            END;");
            $foreign_key = $this->find_foreign_key_name($auth_manager->item_child_table, 'child', $auth_manager->item_table, 'name');
            $this->execute("CREATE TRIGGER {$schema}.trigger_update_{$trigger_suffix}\n            ON {$schema}.{$auth_manager->item_table}\n            INSTEAD OF UPDATE\n            AS\n                DECLARE @old_name NVARCHAR(64) = (SELECT name FROM deleted)\n                DECLARE @new_name NVARCHAR(64) = (SELECT name FROM inserted)\n            BEGIN\n                IF @old_name <> @new_name\n                BEGIN\n                    ALTER TABLE {$auth_manager->item_child_table} NOCHECK CONSTRAINT {$foreign_key};\n                    UPDATE {$auth_manager->item_child_table} SET child = @new_name WHERE child = @old_name;\n                END\n            UPDATE {$auth_manager->item_table}\n            SET name = (SELECT name FROM inserted),\n            type = (SELECT type FROM inserted),\n            description = (SELECT description FROM inserted),\n            rule_name = (SELECT rule_name FROM inserted),\n            data = (SELECT data FROM inserted),\n            created_at = (SELECT created_at FROM inserted),\n            updated_at = (SELECT updated_at FROM inserted)\n            WHERE name IN (SELECT name FROM deleted)\n            IF @old_name <> @new_name\n                BEGIN\n                    ALTER TABLE {$auth_manager->item_child_table} CHECK CONSTRAINT {$foreign_key};\n                END\n            END;");
        }
    }
    /**
     * {@inheritdoc}
     */
    public function down(): ?bool
    {
        if ($this->is_mssql()) {
            $auth_manager = $this->get_auth_manager();
            $this->db = $auth_manager->db;
            $schema = $this->db->get_schema()->default_schema;
            $trigger_suffix = $this->db->schema->get_raw_table_name($auth_manager->item_child_table);
            $this->execute("DROP TRIGGER {$schema}.trigger_update_{$trigger_suffix};");
            $this->execute("DROP TRIGGER {$schema}.trigger_delete_{$trigger_suffix};");
            $this->execute("CREATE TRIGGER {$schema}.trigger_auth_item_child\n            ON {$schema}.{$auth_manager->item_table}\n            INSTEAD OF DELETE, UPDATE\n            AS\n            DECLARE @old_name VARCHAR (64) = (SELECT name FROM deleted)\n            DECLARE @new_name VARCHAR (64) = (SELECT name FROM inserted)\n            BEGIN\n            IF COLUMNS_UPDATED() > 0\n                BEGIN\n                    IF @old_name <> @new_name\n                    BEGIN\n                        ALTER TABLE {$auth_manager->item_child_table} NOCHECK CONSTRAINT FK__auth_item__child;\n                        UPDATE {$auth_manager->item_child_table} SET child = @new_name WHERE child = @old_name;\n                    END\n                UPDATE {$auth_manager->item_table}\n                SET name = (SELECT name FROM inserted),\n                type = (SELECT type FROM inserted),\n                description = (SELECT description FROM inserted),\n                rule_name = (SELECT rule_name FROM inserted),\n                data = (SELECT data FROM inserted),\n                created_at = (SELECT created_at FROM inserted),\n                updated_at = (SELECT updated_at FROM inserted)\n                WHERE name IN (SELECT name FROM deleted)\n                IF @old_name <> @new_name\n                    BEGIN\n                        ALTER TABLE {$auth_manager->item_child_table} CHECK CONSTRAINT FK__auth_item__child;\n                    END\n                END\n                ELSE\n                    BEGIN\n                        DELETE FROM {$schema}.{$auth_manager->item_child_table} WHERE parent IN (SELECT name FROM deleted) OR child IN (SELECT name FROM deleted);\n                        DELETE FROM {$schema}.{$auth_manager->item_table} WHERE name IN (SELECT name FROM deleted);\n                    END\n            END;");
        }
    }
}