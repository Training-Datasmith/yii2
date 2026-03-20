<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\caching;

use yii\base\Invalid_Config_Exception;
use yii\db\Query_Interface;
use yii\di\Instance;
/**
 * DbQueryDependency represents a dependency based on the query result of an [[QueryInterface]] instance.
 *
 * If the query result changes, the dependency is considered as changed.
 * The query is specified via the [[query]] property.
 *
 * Object of any class which matches [[QueryInterface]] can be used, so this dependency can be used not only
 * with regular relational databases but with MongoDB, Redis and so on as well.
 *
 * For more details and usage information on Cache, see the [guide article on caching](guide:caching-overview).
 *
 * @see QueryInterface
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 2.0.12
 */
class Db_Query_Dependency extends Dependency
{
    /**
     * @var string|array|object the application component ID of the database connection, connection object or
     * its array configuration.
     * This field can be left blank, allowing query to determine connection automatically.
     */
    public $db;
    /**
     * @var QueryInterface the query which result is used to determine if the dependency has been changed.
     * Actual query method to be invoked is determined by [[method]].
     */
    public $query;
    /**
     * @var string|callable|null method which should be invoked in over the [[query]] object.
     *
     * If specified as a string an own query method with such name will be invoked, passing [[db]] value as its
     * first argument. For example: `exists`, `all`.
     *
     * This field can be specified as a PHP callback of following signature:
     *
     * ```
     * function (QueryInterface $query, mixed $db) {
     *     //return mixed;
     * }
     * ```
     *
     * If not set - [[QueryInterface::one()]] will be used.
     */
    public $method;
    /**
     * Generates the data needed to determine if dependency is changed.
     *
     * This method returns the query result.
     * @param CacheInterface $cache the cache component that is currently evaluating this dependency
     * @return mixed the data needed to determine if dependency has been changed.
     * @throws InvalidConfigException on invalid configuration.
     */
    protected function generate_dependency_data($cache)
    {
        $db = $this->db;
        if ($db !== null) {
            $db = Instance::ensure($db);
        }
        if (!$this->query instanceof Query_Interface) {
            throw new Invalid_Config_Exception('"' . get_class($this) . '::$query" should be an instance of "yii\db\QueryInterface".');
        }
        if (!empty($db->enable_query_cache)) {
            // temporarily disable and re-enable query caching
            $origin_enable_query_cache = $db->enable_query_cache;
            $db->enable_query_cache = false;
            $result = $this->execute_query($this->query, $db);
            $db->enable_query_cache = $origin_enable_query_cache;
        } else {
            $result = $this->execute_query($this->query, $db);
        }
        return $result;
    }
    /**
     * Executes the query according to [[method]] specification.
     * @param QueryInterface $query query to be executed.
     * @param mixed $db connection.
     * @return mixed query result.
     */
    private function execute_query(\yii\db\Query_Interface $query, $db)
    {
        if ($this->method === null) {
            return $query->one($db);
        }
        if (is_string($this->method)) {
            return call_user_func([$query, $this->method], $db);
        }
        return call_user_func($this->method, $query, $db);
    }
}