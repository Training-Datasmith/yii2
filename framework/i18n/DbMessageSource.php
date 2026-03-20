<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\i18n;

use yii\base\Invalid_Config_Exception;
use yii\caching\Cache_Interface;
use yii\db\Connection;
use yii\db\Expression;
use yii\db\Query;
use yii\di\Instance;
use yii\helpers\Array_Helper;
/**
 * DbMessageSource extends [[MessageSource]] and represents a message source that stores translated
 * messages in database.
 *
 * The database must contain the following two tables: source_message and message.
 *
 * The `source_message` table stores the messages to be translated, and the `message` table stores
 * the translated messages. The name of these two tables can be customized by setting [[sourceMessageTable]]
 * and [[messageTable]], respectively.
 *
 * The database connection is specified by [[db]]. Database schema could be initialized by applying migration:
 *
 * ```
 * yii migrate --migrationPath=@yii/i18n/migrations/
 * ```
 *
 * If you don't want to use migration and need SQL instead, files for all databases are in migrations directory.
 *
 * @author resurtm <resurtm@gmail.com>
 * @since 2.0
 */
class Db_Message_Source extends Message_Source
{
    /**
     * Prefix which would be used when generating cache key.
     * @deprecated This constant has never been used and will be removed in 2.1.0.
     */
    public const CACHE_KEY_PREFIX = 'DbMessageSource';
    /**
     * @var Connection|array|string the DB connection object or the application component ID of the DB connection.
     *
     * After the DbMessageSource object is created, if you want to change this property, you should only assign
     * it with a DB connection object.
     *
     * Starting from version 2.0.2, this can also be a configuration array for creating the object.
     */
    public $db = 'db';
    /**
     * @var CacheInterface|array|string the cache object or the application component ID of the cache object.
     * The messages data will be cached using this cache object.
     * Note, that to enable caching you have to set [[enableCaching]] to `true`, otherwise setting this property has no effect.
     *
     * After the DbMessageSource object is created, if you want to change this property, you should only assign
     * it with a cache object.
     *
     * Starting from version 2.0.2, this can also be a configuration array for creating the object.
     * @see cachingDuration
     * @see enableCaching
     */
    public $cache = 'cache';
    /**
     * @var string the name of the source message table.
     */
    public $source_message_table = '{{%source_message}}';
    /**
     * @var string the name of the translated message table.
     */
    public $message_table = '{{%message}}';
    /**
     * @var int the time in seconds that the messages can remain valid in cache.
     * Use 0 to indicate that the cached data will never expire.
     * @see enableCaching
     */
    public $caching_duration = 0;
    /**
     * @var bool whether to enable caching translated messages
     */
    public $enable_caching = false;
    /**
     * Initializes the DbMessageSource component.
     * This method will initialize the [[db]] property to make sure it refers to a valid DB connection.
     * Configured [[cache]] component would also be initialized.
     * @throws InvalidConfigException if [[db]] is invalid or [[cache]] is invalid.
     */
    public function init(): void
    {
        parent::init();
        $this->db = Instance::ensure($this->db, Connection::class_name());
        if ($this->enable_caching) {
            $this->cache = Instance::ensure($this->cache, 'yii\caching\CacheInterface');
        }
    }
    /**
     * Loads the message translation for the specified language and category.
     * If translation for specific locale code such as `en-US` isn't found it
     * tries more generic `en`.
     *
     * @param string $category the message category
     * @param string $language the target language
     * @return array the loaded messages. The keys are original messages, and the values
     * are translated messages.
     */
    protected function load_messages($category, $language)
    {
        if ($this->enable_caching) {
            $key = [self::class, $category, $language];
            $messages = $this->cache->get($key);
            if ($messages === false) {
                $messages = $this->load_messages_from_db($category, $language);
                $this->cache->set($key, $messages, $this->caching_duration);
            }
            return $messages;
        }
        return $this->load_messages_from_db($category, $language);
    }
    /**
     * Loads the messages from database.
     * You may override this method to customize the message storage in the database.
     * @param string $category the message category.
     * @param string $language the target language.
     * @return array the messages loaded from database.
     */
    protected function load_messages_from_db($category, $language)
    {
        $main_query = (new Query())->select(['message' => 't1.message', 'translation' => 't2.translation'])->from(['t1' => $this->source_message_table, 't2' => $this->message_table])->where(['t1.id' => new Expression('[[t2.id]]'), 't1.category' => $category, 't2.language' => $language]);
        $fallback_language = substr($language, 0, 2);
        $fallback_source_language = substr($this->source_language, 0, 2);
        if ($fallback_language !== $language) {
            $main_query->union($this->create_fallback_query($category, $language, $fallback_language), true);
        } elseif ($language === $fallback_source_language) {
            $main_query->union($this->create_fallback_query($category, $language, $fallback_source_language), true);
        }
        $messages = $main_query->create_command($this->db)->query_all();
        return Array_Helper::map($messages, 'message', 'translation');
    }
    /**
     * The method builds the [[Query]] object for the fallback language messages search.
     * Normally is called from [[loadMessagesFromDb]].
     *
     * @param string $category the message category
     * @param string $language the originally requested language
     * @param string $fallbackLanguage the target fallback language
     * @return Query
     * @see loadMessagesFromDb
     * @since 2.0.7
     */
    protected function create_fallback_query($category, $language, $fallback_language)
    {
        return (new Query())->select(['message' => 't1.message', 'translation' => 't2.translation'])->from(['t1' => $this->source_message_table, 't2' => $this->message_table])->where(['t1.id' => new Expression('[[t2.id]]'), 't1.category' => $category, 't2.language' => $fallback_language])->and_where(['NOT IN', 't2.id', (new Query())->select('[[id]]')->from($this->message_table)->where(['language' => $language])]);
    }
}