<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
use yii\db\Migration;
/**
 * Initializes i18n messages tables.
 *
 *
 *
 * @author Dmitry Naumenko <d.naumenko.a@gmail.com>
 * @since 2.0.7
 */
class m150207_210500_i18n_init extends Migration
{
    public function up(): ?bool
    {
        $table_options = null;
        if ($this->db->driver_name === 'mysql') {
            // https://stackoverflow.com/questions/766809/whats-the-difference-between-utf8-general-ci-and-utf8-unicode-ci
            $table_options = 'CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE=InnoDB';
        }
        $this->create_table('{{%source_message}}', ['id' => $this->primary_key(), 'category' => $this->string(), 'message' => $this->text()], $table_options);
        $this->create_table('{{%message}}', ['id' => $this->integer()->not_null(), 'language' => $this->string(16)->not_null(), 'translation' => $this->text()], $table_options);
        $this->add_primary_key('pk_message_id_language', '{{%message}}', ['id', 'language']);
        $on_update_constraint = 'RESTRICT';
        if ($this->db->driver_name === 'sqlsrv') {
            // 'NO ACTION' is equivalent to 'RESTRICT' in MSSQL
            $on_update_constraint = 'NO ACTION';
        }
        $this->add_foreign_key('fk_message_source_message', '{{%message}}', 'id', '{{%source_message}}', 'id', 'CASCADE', $on_update_constraint);
        $this->create_index('idx_source_message_category', '{{%source_message}}', 'category');
        $this->create_index('idx_message_language', '{{%message}}', 'language');
    }
    public function down(): ?bool
    {
        $this->drop_foreign_key('fk_message_source_message', '{{%message}}');
        $this->drop_table('{{%message}}');
        $this->drop_table('{{%source_message}}');
    }
}