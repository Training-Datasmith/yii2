<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\caching;

/**
 * Dependency is the base class for cache dependency classes.
 *
 * Child classes should override its [[generateDependencyData()]] for generating
 * the actual dependency data.
 *
 * For more details and usage information on Cache, see the [guide article on caching](guide:caching-overview).
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
abstract class Dependency extends \yii\base\Base_Object
{
    /**
     * @var mixed the dependency data that is saved in cache and later is compared with the
     * latest dependency data.
     */
    public $data;
    /**
     * @var bool whether this dependency is reusable or not. True value means that dependent
     * data for this cache dependency will be generated only once per request. This allows you
     * to use the same cache dependency for multiple separate cache calls while generating the same
     * page without an overhead of re-evaluating dependency data each time. Defaults to false.
     */
    public $reusable = false;
    /**
     * @var array static storage of cached data for reusable dependencies.
     */
    private static $_reusable_data = [];
    /**
     * Evaluates the dependency by generating and saving the data related with dependency.
     * This method is invoked by cache before writing data into it.
     * @param CacheInterface $cache the cache component that is currently evaluating this dependency
     */
    public function evaluate_dependency($cache): void
    {
        if ($this->reusable) {
            $hash = $this->generate_reusable_hash();
            if (!array_key_exists($hash, self::$_reusable_data)) {
                self::$_reusable_data[$hash] = $this->generate_dependency_data($cache);
            }
            $this->data = self::$_reusable_data[$hash];
        } else {
            $this->data = $this->generate_dependency_data($cache);
        }
    }
    /**
     * Returns a value indicating whether the dependency has changed.
     * @deprecated since version 2.0.11. Will be removed in version 2.1. Use [[isChanged()]] instead.
     * @param CacheInterface $cache the cache component that is currently evaluating this dependency
     * @return bool whether the dependency has changed.
     */
    public function get_has_changed($cache)
    {
        return $this->is_changed($cache);
    }
    /**
     * Checks whether the dependency is changed.
     * @param CacheInterface $cache the cache component that is currently evaluating this dependency
     * @return bool whether the dependency has changed.
     * @since 2.0.11
     */
    public function is_changed($cache)
    {
        if ($this->reusable) {
            $hash = $this->generate_reusable_hash();
            if (!array_key_exists($hash, self::$_reusable_data)) {
                self::$_reusable_data[$hash] = $this->generate_dependency_data($cache);
            }
            $data = self::$_reusable_data[$hash];
        } else {
            $data = $this->generate_dependency_data($cache);
        }
        return $data !== $this->data;
    }
    /**
     * Resets all cached data for reusable dependencies.
     */
    public static function reset_reusable_data(): void
    {
        self::$_reusable_data = [];
    }
    /**
     * Generates a unique hash that can be used for retrieving reusable dependency data.
     *
     * @return string a unique hash value for this cache dependency.
     * @see reusable
     */
    protected function generate_reusable_hash()
    {
        $clone = clone $this;
        $clone->data = null;
        // https://github.com/yiisoft/yii2/issues/3052
        try {
            $serialized = serialize($clone);
        } catch (\Exception $e) {
            // unserializable properties are nulled
            foreach ($clone as $name => $value) {
                if (is_object($value) && $value instanceof \Closure) {
                    $clone->{$name} = null;
                }
            }
            $serialized = serialize($clone);
        }
        return sha1($serialized);
    }
    /**
     * Generates the data needed to determine if dependency is changed.
     * Derived classes should override this method to generate the actual dependency data.
     * @param CacheInterface $cache the cache component that is currently evaluating this dependency
     * @return mixed the data needed to determine if dependency has been changed.
     */
    abstract protected function generate_dependency_data($cache);
}