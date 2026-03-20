<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\caching;

/**
 * CallbackDependency represents a dependency based on the result of a callback function.
 *
 * Callback function should return a value that serves as the dependency data.
 *
 * For more details and usage information on Cache, see the [guide article on caching](guide:caching-overview).
 *
 * @author Vlad Varlamov <vlad@varlamov.dev>
 * @since 2.0.50
 */
class Callback_Dependency extends Dependency
{
    /**
     * @var callable the PHP callback that will be called to determine if the dependency has been changed.
     */
    public $callback;
    /**
     * Generates the data needed to determine if dependency has been changed.
     * This method returns the result of the callback function.
     * @param CacheInterface $cache the cache component that is currently evaluating this dependency
     * @return mixed the data needed to determine if dependency has been changed.
     */
    protected function generate_dependency_data($cache)
    {
        return ($this->callback)();
    }
}