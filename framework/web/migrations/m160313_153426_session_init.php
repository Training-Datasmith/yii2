<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
use yii\db\Migration;
/**
 * Initializes Session tables.
 *
 * @author Misbahul D Munir <misbahuldmunir@gmail.com>
 * @since 2.0.8
 */
class m160313_153426_session_init extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up(): ?bool
    {
        $data_type = $this->binary();
        $table_options = null;
        switch ($this->db->driver_name) {
            case 'mysql':
                // https://stackoverflow.com/questions/766809/whats-the-difference-between-utf8-general-ci-and-utf8-unicode-ci
                $table_options = 'CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE=InnoDB';
                break;
            case 'sqlsrv':
            case 'mssql':
            case 'dblib':
                $data_type = $this->text();
                break;
        }
        $this->create_table('{{%session}}', ['id' => $this->string()->not_null(), 'expire' => $this->integer(), 'data' => $data_type, 'PRIMARY KEY ([[id]])'], $table_options);
    }
    /**
     * {@inheritdoc}
     */
    public function down(): ?bool
    {
        $this->drop_table('{{%session}}');
    }
}