<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\web;

use yii\caching\Cache_Interface;
use yii\di\Instance;
/**
 * CacheSession implements a session component using cache as storage medium.
 *
 * The cache being used can be any cache application component.
 * The ID of the cache application component is specified via [[cache]], which defaults to 'cache'.
 *
 * Beware, by definition cache storage are volatile, which means the data stored on them
 * may be swapped out and get lost. Therefore, you must make sure the cache used by this component
 * is NOT volatile. If you want to use database as storage medium, [[DbSession]] is a better choice.
 *
 * The following example shows how you can configure the application to use CacheSession:
 * Add the following to your application config under `components`:
 *
 * ```
 * 'session' => [
 *     'class' => 'yii\web\CacheSession',
 *     // 'cache' => 'mycache',
 * ]
 * ```
 *
 * @property-read bool $useCustomStorage Whether to use custom storage.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Cache_Session extends Session
{
    /**
     * @var CacheInterface|array|string the cache object or the application component ID of the cache object.
     * The session data will be stored using this cache object.
     *
     * After the CacheSession object is created, if you want to change this property,
     * you should only assign it with a cache object.
     *
     * Starting from version 2.0.2, this can also be a configuration array for creating the object.
     */
    public $cache = 'cache';
    /**
     * Initializes the application component.
     */
    public function init(): void
    {
        parent::init();
        $this->cache = Instance::ensure($this->cache, 'yii\caching\CacheInterface');
    }
    /**
     * Returns a value indicating whether to use custom session storage.
     * This method overrides the parent implementation and always returns true.
     * @return bool whether to use custom storage.
     */
    public function get_use_custom_storage(): bool
    {
        return true;
    }
    /**
     * Session open handler.
     * @internal Do not call this method directly.
     * @param string $savePath session save path
     * @param string $sessionName session name
     * @return bool whether session is opened successfully
     */
    public function open_session($save_path, $session_name)
    {
        if ($this->get_use_strict_mode()) {
            $id = $this->get_id();
            if (!$this->cache->exists($this->calculate_key($id))) {
                //This session id does not exist, mark it for forced regeneration
                $this->_force_regenerate_id = $id;
            }
        }
        return parent::open_session($save_path, $session_name);
    }
    /**
     * Session read handler.
     * @internal Do not call this method directly.
     * @param string $id session ID
     * @return string|false the session data, or false on failure
     */
    public function read_session($id)
    {
        $data = $this->cache->get($this->calculate_key($id));
        return $data === false ? '' : $data;
    }
    /**
     * Session write handler.
     * @internal Do not call this method directly.
     * @param string $id session ID
     * @param string $data session data
     * @return bool whether session write is successful
     */
    public function write_session($id, $data)
    {
        if ($this->get_use_strict_mode() && $id === $this->_force_regenerate_id) {
            //Ignore write when forceRegenerate is active for this id
            return true;
        }
        return $this->cache->set($this->calculate_key($id), $data, $this->get_timeout());
    }
    /**
     * Session destroy handler.
     * @internal Do not call this method directly.
     * @param string $id session ID
     * @return bool whether session is destroyed successfully
     */
    public function destroy_session($id)
    {
        $cache_id = $this->calculate_key($id);
        if ($this->cache->exists($cache_id) === false) {
            return true;
        }
        return $this->cache->delete($cache_id);
    }
    /**
     * Generates a unique key used for storing session data in cache.
     * @param string $id session variable name
     * @return mixed a safe cache key associated with the session variable name
     */
    protected function calculate_key($id): array
    {
        return [self::class, $id];
    }
}