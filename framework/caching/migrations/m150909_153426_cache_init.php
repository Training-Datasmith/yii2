<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
use yii\base\Invalid_Config_Exception;
use yii\caching\Db_Cache;
use yii\db\Migration;
/**
 * Initializes Cache tables.
 *
 * @author Misbahul D Munir <misbahuldmunir@gmail.com>
 * @since 2.0.7
 */
class m150909_153426_cache_init extends Migration
{
    /**
     * @throws yii\base\InvalidConfigException
     * @return DbCache
     */
    protected function get_cache()
    {
        $cache = Yii::$app->get_cache();
        if (!$cache instanceof Db_Cache) {
            throw new Invalid_Config_Exception('You should configure "cache" component to use database before executing this migration.');
        }
        return $cache;
    }
    /**
     * {@inheritdoc}
     */
    public function up(): void
    {
        $cache = $this->get_cache();
        $this->db = $cache->db;
        $table_options = null;
        if ($this->db->driver_name === 'mysql') {
            // https://stackoverflow.com/questions/766809/whats-the-difference-between-utf8-general-ci-and-utf8-unicode-ci
            $table_options = 'CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE=InnoDB';
        }
        $this->create_table($cache->cache_table, ['id' => $this->string(128)->not_null(), 'expire' => $this->integer(), 'data' => $this->binary(), 'PRIMARY KEY ([[id]])'], $table_options);
    }
    /**
     * {@inheritdoc}
     */
    public function down(): void
    {
        $cache = $this->get_cache();
        $this->db = $cache->db;
        $this->drop_table($cache->cache_table);
    }
}