<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\behaviors;

use yii\base\Behavior;
use yii\base\Invalid_Config_Exception;
use yii\base\Widget;
use yii\base\Widget_Event;
use yii\caching\Cache_Interface;
use yii\caching\Dependency;
use yii\di\Instance;
/**
 * Cacheable widget behavior automatically caches widget contents according to duration and dependencies specified.
 *
 * The behavior may be used without any configuration if an application has `cache` component configured.
 * By default the widget will be cached for one minute.
 *
 * The following example will cache the posts widget for an indefinite duration until any post is modified.
 *
 * ```
 * use yii\behaviors\CacheableWidgetBehavior;
 *
 * public function behaviors()
 * {
 *     return [
 *         [
 *             'class' => CacheableWidgetBehavior::class,
 *             'cacheDuration' => 0,
 *             'cacheDependency' => [
 *                 'class' => 'yii\caching\DbDependency',
 *                 'sql' => 'SELECT MAX(updated_at) FROM posts',
 *             ],
 *         ],
 *     ];
 * }
 * ```
 *
 * @author Nikolay Oleynikov <oleynikovny@mail.ru>
 * @since 2.0.14
 *
 * @template T of Widget = Widget
 * @extends Behavior<Widget>
 */
class Cacheable_Widget_Behavior extends Behavior
{
    /**
     * @var CacheInterface|string|array a cache object or a cache component ID
     * or a configuration array for creating a cache object.
     * Defaults to the `cache` application component.
     */
    public $cache = 'cache';
    /**
     * @var int cache duration in seconds.
     * Set to `0` to indicate that the cached data will never expire.
     * Defaults to 60 seconds or 1 minute.
     */
    public $cache_duration = 60;
    /**
     * @var Dependency|array|null a cache dependency or a configuration array
     * for creating a cache dependency or `null` meaning no cache dependency.
     *
     * For example,
     *
     * ```
     * [
     *     'class' => 'yii\caching\DbDependency',
     *     'sql' => 'SELECT MAX(updated_at) FROM posts',
     * ]
     * ```
     *
     * would make the widget cache depend on the last modified time of all posts.
     * If any post has its modification time changed, the cached content would be invalidated.
     */
    public $cache_dependency;
    /**
     * @var string[]|string an array of strings or a single string which would cause
     * the variation of the content being cached (e.g. an application language, a GET parameter).
     *
     * The following variation setting will cause the content to be cached in different versions
     * according to the current application language:
     *
     * ```
     * [
     *     Yii::$app->language,
     * ]
     * ```
     */
    public $cache_key_variations = [];
    /**
     * @var bool whether to enable caching or not. Allows to turn the widget caching
     * on and off according to specific conditions.
     * The following configuration will disable caching when a special GET parameter is passed:
     *
     * ```
     * empty(Yii::$app->request->get('disable-caching'))
     * ```
     */
    public $cache_enabled = true;
    /**
     * {@inheritdoc}
     */
    public function attach($owner): void
    {
        parent::attach($owner);
        $this->initialize_event_handlers();
    }
    /**
     * Begins fragment caching. Prevents owner widget from execution
     * if its contents can be retrieved from the cache.
     *
     * @param WidgetEvent $event `Widget::EVENT_BEFORE_RUN` event.
     */
    public function before_run($event): void
    {
        $cache_key = $this->get_cache_key();
        $fragment_cache_configuration = $this->get_fragment_cache_configuration();
        if (!$this->owner->view->begin_cache($cache_key, $fragment_cache_configuration)) {
            $event->is_valid = false;
        }
    }
    /**
     * Outputs widget contents and ends fragment caching.
     *
     * @param WidgetEvent $event `Widget::EVENT_AFTER_RUN` event.
     */
    public function after_run($event): void
    {
        echo $event->result;
        $event->result = null;
        $this->owner->view->end_cache();
    }
    /**
     * Initializes widget event handlers.
     */
    private function initialize_event_handlers(): void
    {
        $this->owner->on(Widget::EVENT_BEFORE_RUN, [$this, 'beforeRun']);
        $this->owner->on(Widget::EVENT_AFTER_RUN, [$this, 'afterRun']);
    }
    /**
     * Returns the cache instance.
     *
     * @return CacheInterface cache instance.
     * @throws InvalidConfigException if cache instance instantiation fails.
     */
    private function get_cache_instance()
    {
        $cache_interface = 'yii\caching\CacheInterface';
        return Instance::ensure($this->cache, $cache_interface);
    }
    /**
     * Returns the widget cache key.
     *
     * @return string[] an array of strings representing the cache key.
     */
    private function get_cache_key(): array
    {
        // `$cacheKeyVariations` may be a `string` and needs to be cast to an `array`.
        $cache_key = array_merge((array) ($this->owner !== null ? get_class($this->owner) : self::class), (array) $this->cache_key_variations);
        return $cache_key;
    }
    /**
     * Returns a fragment cache widget configuration array.
     *
     * @return array a fragment cache widget configuration array.
     */
    private function get_fragment_cache_configuration(): array
    {
        $cache = $this->get_cache_instance();
        return ['cache' => $cache, 'duration' => $this->cache_duration, 'dependency' => $this->cache_dependency, 'enabled' => $this->cache_enabled];
    }
}