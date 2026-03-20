<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\db;

use yii\base\InvalidArgumentException;
use yii\base\Invalid_Config_Exception;
/**
 * ActiveRelationTrait implements the common methods and properties for active record relational queries.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @author Carsten Brandt <mail@cebe.cc>
 * @since 2.0
 * @phpcs:disable Squiz.NamingConventions.ValidVariableName.PrivateNoUnderscore
 *
 * @method ActiveRecordInterface|array|null one($db = null) See [[ActiveQueryInterface::one()]] for more info.
 * @method ActiveRecordInterface[] all($db = null) See [[ActiveQueryInterface::all()]] for more info.
 * @property class-string<ActiveRecordInterface> $modelClass
 */
trait Active_Relation_Trait
{
    /**
     * @var bool whether this query represents a relation to more than one record.
     * This property is only used in relational context. If true, this relation will
     * populate all query results into AR instances using [[Query::all()|all()]].
     * If false, only the first row of the results will be retrieved using [[Query::one()|one()]].
     */
    public $multiple;
    /**
     * @var ActiveRecord the primary model of a relational query.
     * This is used only in lazy loading with dynamic query options.
     */
    public $primary_model;
    /**
     * @var array the columns of the primary and foreign tables that establish a relation.
     * The array keys must be columns of the table for this relation, and the array values
     * must be the corresponding columns from the primary table.
     * Do not prefix or quote the column names as this will be done automatically by Yii.
     * This property is only used in relational context.
     */
    public $link;
    /**
     * @var array|object|null the query associated with the junction table. Please call [[via()]]
     * to set this property instead of directly setting it.
     * This property is only used in relational context.
     * @see via()
     */
    public $via;
    /**
     * @var string the name of the relation that is the inverse of this relation.
     * For example, an order has a customer, which means the inverse of the "customer" relation
     * is the "orders", and the inverse of the "orders" relation is the "customer".
     * If this property is set, the primary record(s) will be referenced through the specified relation.
     * For example, `$customer->orders[0]->customer` and `$customer` will be the same object,
     * and accessing the customer of an order will not trigger new DB query.
     * This property is only used in relational context.
     * @see inverseOf()
     */
    public $inverse_of;
    private $via_map;
    /**
     * Clones internal objects.
     */
    public function __clone()
    {
        parent::__clone();
        // make a clone of "via" object so that the same query object can be reused multiple times
        if (is_object($this->via)) {
            $this->via = clone $this->via;
        } elseif (is_array($this->via)) {
            $this->via = [$this->via[0], clone $this->via[1], $this->via[2]];
        }
    }
    /**
     * Specifies the relation associated with the junction table.
     *
     * Use this method to specify a pivot record/table when declaring a relation in the [[ActiveRecord]] class:
     *
     * ```
     * class Order extends ActiveRecord
     * {
     *    public function getOrderItems() {
     *        return $this->hasMany(OrderItem::class, ['order_id' => 'id']);
     *    }
     *
     *    public function getItems() {
     *        return $this->hasMany(Item::class, ['id' => 'item_id'])
     *                    ->via('orderItems');
     *    }
     * }
     * ```
     *
     * @param string $relationName the relation name. This refers to a relation declared in [[primaryModel]].
     * @param callable|null $callable a PHP callback for customizing the relation associated with the junction table.
     * Its signature should be `function($query)`, where `$query` is the query to be customized.
     * @return $this the relation object itself.
     */
    public function via($relation_name, ?callable $callable = null)
    {
        $relation = $this->primary_model->get_relation($relation_name);
        $callable_used = $callable !== null;
        $this->via = [$relation_name, $relation, $callable_used];
        if ($callable !== null) {
            call_user_func($callable, $relation);
        }
        return $this;
    }
    /**
     * Sets the name of the relation that is the inverse of this relation.
     * For example, a customer has orders, which means the inverse of the "orders" relation is the "customer".
     * If this property is set, the primary record(s) will be referenced through the specified relation.
     * For example, `$customer->orders[0]->customer` and `$customer` will be the same object,
     * and accessing the customer of an order will not trigger a new DB query.
     *
     * Use this method when declaring a relation in the [[ActiveRecord]] class, e.g. in Customer model:
     *
     * ```
     * public function getOrders()
     * {
     *     return $this->hasMany(Order::class, ['customer_id' => 'id'])->inverseOf('customer');
     * }
     * ```
     *
     * This also may be used for Order model, but with caution:
     *
     * ```
     * public function getCustomer()
     * {
     *     return $this->hasOne(Customer::class, ['id' => 'customer_id'])->inverseOf('orders');
     * }
     * ```
     *
     * in this case result will depend on how order(s) was loaded.
     * Let's suppose customer has several orders. If only one order was loaded:
     *
     * ```
     * $orders = Order::find()->where(['id' => 1])->all();
     * $customerOrders = $orders[0]->customer->orders;
     * ```
     *
     * variable `$customerOrders` will contain only one order. If orders was loaded like this:
     *
     * ```
     * $orders = Order::find()->with('customer')->where(['customer_id' => 1])->all();
     * $customerOrders = $orders[0]->customer->orders;
     * ```
     *
     * variable `$customerOrders` will contain all orders of the customer.
     *
     * @param string $relationName the name of the relation that is the inverse of this relation.
     * @return $this the relation object itself.
     */
    public function inverse_of($relation_name)
    {
        $this->inverse_of = $relation_name;
        return $this;
    }
    /**
     * Finds the related records for the specified primary record.
     * This method is invoked when a relation of an ActiveRecord is being accessed lazily.
     * @param string $name the relation name
     * @param ActiveRecordInterface|BaseActiveRecord $model the primary model
     * @return mixed the related record(s)
     * @throws InvalidArgumentException if the relation is invalid
     */
    public function find_for(string $name, $model)
    {
        if (method_exists($model, 'get' . $name)) {
            $method = new \ReflectionMethod($model, 'get' . $name);
            $real_name = lcfirst(substr($method->get_name(), 3));
            if ($real_name !== $name) {
                throw new InvalidArgumentException('Relation names are case sensitive. ' . get_class($model) . " has a relation named \"{$real_name}\" instead of \"{$name}\".");
            }
        }
        return $this->multiple ? $this->all() : $this->one();
    }
    /**
     * If applicable, populate the query's primary model into the related records' inverse relationship.
     * @param array $result the array of related records as generated by [[populate()]]
     * @since 2.0.9
     */
    private function add_inverse_relations(array &$result): void
    {
        if ($this->inverse_of === null) {
            return;
        }
        foreach ($result as $i => $related_model) {
            if ($related_model instanceof Active_Record_Interface) {
                if (!isset($inverse_relation)) {
                    $inverse_relation = $related_model->get_relation($this->inverse_of);
                }
                $related_model->populate_relation($this->inverse_of, $inverse_relation->multiple ? [$this->primary_model] : $this->primary_model);
            } else {
                if (!isset($inverse_relation)) {
                    /** @var ActiveRecordInterface $modelClass */
                    $model_class = $this->model_class;
                    $inverse_relation = $model_class::instance()->get_relation($this->inverse_of);
                }
                $result[$i][$this->inverse_of] = $inverse_relation->multiple ? [$this->primary_model] : $this->primary_model;
            }
        }
    }
    /**
     * Finds the related records and populates them into the primary models.
     * @param string $name the relation name
     * @param array $primaryModels primary models
     * @return array the related models
     * @throws InvalidConfigException if [[link]] is invalid
     */
    public function populate_relation($name, array &$primary_models)
    {
        if (!is_array($this->link)) {
            throw new Invalid_Config_Exception('Invalid link: it must be an array of key-value pairs.');
        }
        if ($this->via instanceof self) {
            // via junction table
            /** @var self<ActiveRecord|array<string, mixed>> $viaQuery */
            $via_query = $this->via;
            $via_models = $via_query->find_junction_rows($primary_models);
            $this->filter_by_models($via_models);
        } elseif (is_array($this->via)) {
            // via relation
            /** @var self<ActiveRecord|array<string, mixed>>|ActiveQueryTrait $viaQuery */
            [$via_name, $via_query] = $this->via;
            if ($via_query->as_array === null) {
                // inherit asArray from primary query
                $via_query->as_array($this->as_array);
            }
            $via_query->primary_model = null;
            $via_models = array_filter($via_query->populate_relation($via_name, $primary_models));
            $this->filter_by_models($via_models);
        } else {
            $this->filter_by_models($primary_models);
        }
        if (!$this->multiple && count($primary_models) === 1) {
            $model = $this->one();
            $primary_model = reset($primary_models);
            if ($primary_model instanceof Active_Record_Interface) {
                $primary_model->populate_relation($name, $model);
            } else {
                $primary_models[key($primary_models)][$name] = $model;
            }
            if ($this->inverse_of !== null) {
                $this->populate_inverse_relation($primary_models, [$model], $name, $this->inverse_of);
            }
            return [$model];
        }
        // https://github.com/yiisoft/yii2/issues/3197
        // delay indexing related models after buckets are built
        $index_by = $this->index_by;
        $this->index_by = null;
        $models = $this->all();
        if (isset($via_models, $via_query)) {
            $buckets = $this->build_buckets($models, $this->link, $via_models, $via_query);
        } else {
            $buckets = $this->build_buckets($models, $this->link);
        }
        $this->index_by = $index_by;
        if ($this->index_by !== null && $this->multiple) {
            $buckets = $this->index_buckets($buckets, $this->index_by);
        }
        $link = array_values($this->link);
        if (isset($via_query)) {
            $deep_via_query = $via_query;
            while ($deep_via_query->via) {
                $deep_via_query = is_array($deep_via_query->via) ? $deep_via_query->via[1] : $deep_via_query->via;
            }
            $link = array_values($deep_via_query->link);
        }
        foreach ($primary_models as $i => $primary_model) {
            $keys = null;
            if ($this->multiple && count($link) === 1) {
                $primary_model_key = reset($link);
                $keys = $primary_model[$primary_model_key] ?? null;
            }
            if (is_array($keys)) {
                $value = [];
                foreach ($keys as $key) {
                    $key = $this->normalize_model_key($key);
                    if (isset($buckets[$key])) {
                        if ($this->index_by !== null) {
                            // if indexBy is set, array_merge will cause renumbering of numeric array
                            foreach ($buckets[$key] as $bucket_key => $bucket_value) {
                                $value[$bucket_key] = $bucket_value;
                            }
                        } else {
                            $value = array_merge($value, $buckets[$key]);
                        }
                    }
                }
            } else {
                $key = $this->get_model_key($primary_model, $link);
                $value = $buckets[$key] ?? ($this->multiple ? [] : null);
            }
            if ($primary_model instanceof Active_Record_Interface) {
                $primary_model->populate_relation($name, $value);
            } else {
                $primary_models[$i][$name] = $value;
            }
        }
        if ($this->inverse_of !== null) {
            $this->populate_inverse_relation($primary_models, $models, $name, $this->inverse_of);
        }
        return $models;
    }
    /**
     * @param ActiveRecordInterface[]|array<array-key, array<string, mixed>> $primaryModels primary models
     * @param ActiveRecordInterface[] $models models
     * @param string $primaryName the primary relation name
     * @param string $name the relation name
     */
    private function populate_inverse_relation(&$primary_models, $models, $primary_name, $name): void
    {
        if (empty($models) || empty($primary_models)) {
            return;
        }
        $model = reset($models);
        if ($model instanceof Active_Record_Interface) {
            $relation = $model->get_relation($name);
        } else {
            /** @var ActiveRecordInterface $modelClass */
            $model_class = $this->model_class;
            $relation = $model_class::instance()->get_relation($name);
        }
        /** @var ActiveQueryInterface|ActiveQuery $relation */
        if ($relation->multiple) {
            $buckets = $this->build_buckets($primary_models, $relation->link, null, null, false);
            if ($model instanceof Active_Record_Interface) {
                foreach ($models as $model) {
                    $key = $this->get_model_key($model, $relation->link);
                    $model->populate_relation($name, $buckets[$key] ?? []);
                }
            } else {
                foreach ($primary_models as $i => $primary_model) {
                    if ($this->multiple) {
                        foreach ($primary_model as $j => $m) {
                            $key = $this->get_model_key($m, $relation->link);
                            $primary_models[$i][$j][$name] = $buckets[$key] ?? [];
                        }
                    } elseif (!empty($primary_model[$primary_name])) {
                        $key = $this->get_model_key($primary_model[$primary_name], $relation->link);
                        $primary_models[$i][$primary_name][$name] = $buckets[$key] ?? [];
                    }
                }
            }
        } elseif ($this->multiple) {
            foreach ($primary_models as $i => $primary_model) {
                foreach ($primary_model[$primary_name] as $j => $m) {
                    if ($m instanceof Active_Record_Interface) {
                        $m->populate_relation($name, $primary_model);
                    } else {
                        $primary_models[$i][$primary_name][$j][$name] = $primary_model;
                    }
                }
            }
        } else {
            foreach ($primary_models as $i => $primary_model) {
                if ($primary_models[$i][$primary_name] instanceof Active_Record_Interface) {
                    $primary_models[$i][$primary_name]->populate_relation($name, $primary_model);
                } elseif (!empty($primary_models[$i][$primary_name])) {
                    $primary_models[$i][$primary_name][$name] = $primary_model;
                }
            }
        }
    }
    /**
     * @param array $models
     * @param array $link
     * @param array|null $viaModels
     * @param self<ActiveRecord|array<string, mixed>>|null $viaQuery
     * @param bool $checkMultiple
     */
    private function build_buckets($models, $link, $via_models = null, $via_query = null, $check_multiple = true): array
    {
        if ($via_models !== null) {
            $map = [];
            $via_link = $via_query->link;
            $via_link_keys = array_keys($via_link);
            $link_values = array_values($link);
            foreach ($via_models as $via_model) {
                $key1 = $this->get_model_key($via_model, $via_link_keys);
                $key2 = $this->get_model_key($via_model, $link_values);
                $map[$key2][$key1] = true;
            }
            $via_query->via_map = $map;
            $via_via = $via_query->via;
            while ($via_via) {
                $via_via_query = is_array($via_via) ? $via_via[1] : $via_via;
                $map = $this->map_via($map, $via_via_query->via_map);
                $via_via = $via_via_query->via;
            }
        }
        $buckets = [];
        $link_keys = array_keys($link);
        if (isset($map)) {
            foreach ($models as $model) {
                $key = $this->get_model_key($model, $link_keys);
                if (isset($map[$key])) {
                    foreach (array_keys($map[$key]) as $key2) {
                        $buckets[$key2][] = $model;
                    }
                }
            }
        } else {
            foreach ($models as $model) {
                $key = $this->get_model_key($model, $link_keys);
                $buckets[$key][] = $model;
            }
        }
        if ($check_multiple && !$this->multiple) {
            foreach ($buckets as $i => $bucket) {
                $buckets[$i] = reset($bucket);
            }
        }
        return $buckets;
    }
    /**
     * @param array $map
     */
    private function map_via($map, array $via_map): array
    {
        $result_map = [];
        foreach ($map as $key => $link_keys) {
            $result_map[$key] = [];
            foreach (array_keys($link_keys) as $link_key) {
                $result_map[$key] += $via_map[$link_key];
            }
        }
        return $result_map;
    }
    /**
     * Indexes buckets by column name.
     *
     * @param array $buckets
     * @param string|callable $indexBy the name of the column by which the query results should be indexed by.
     * This can also be a callable(e.g. anonymous function) that returns the index value based on the given row data.
     */
    private function index_buckets($buckets, $index_by): array
    {
        $result = [];
        foreach ($buckets as $key => $models) {
            $result[$key] = [];
            foreach ($models as $model) {
                $index = is_string($index_by) ? $model[$index_by] : call_user_func($index_by, $model);
                $result[$key][$index] = $model;
            }
        }
        return $result;
    }
    /**
     * @param array $attributes the attributes to prefix
     */
    private function prefix_key_columns(array $attributes): array
    {
        if ($this instanceof Active_Query && (!empty($this->join) || !empty($this->join_with))) {
            if (empty($this->from)) {
                /** @var ActiveRecord $modelClass */
                $model_class = $this->model_class;
                $alias = $model_class::table_name();
            } else {
                foreach ($this->from as $alias => $table) {
                    if (!is_string($alias)) {
                        $alias = $table;
                    }
                    break;
                }
            }
            if (isset($alias)) {
                foreach ($attributes as $i => $attribute) {
                    $attributes[$i] = "{$alias}.{$attribute}";
                }
            }
        }
        return $attributes;
    }
    /**
     * @param array $models
     */
    private function filter_by_models($models): void
    {
        $attributes = array_keys($this->link);
        $attributes = $this->prefix_key_columns($attributes);
        $values = [];
        if (count($attributes) === 1) {
            // single key
            $attribute = reset($this->link);
            foreach ($models as $model) {
                $value = isset($model[$attribute]) || is_object($model) && property_exists($model, $attribute) ? $model[$attribute] : null;
                if ($value !== null) {
                    if (is_array($value)) {
                        $values = array_merge($values, $value);
                    } elseif ($value instanceof Array_Expression && $value->get_dimension() === 1) {
                        $values = array_merge($values, $value->get_value());
                    } else {
                        $values[] = $value;
                    }
                }
            }
            if (empty($values)) {
                $this->emulate_execution();
            }
        } else {
            // composite keys
            // ensure keys of $this->link are prefixed the same way as $attributes
            $prefixed_link = array_combine($attributes, $this->link);
            foreach ($models as $model) {
                $v = [];
                foreach ($prefixed_link as $attribute => $link) {
                    $v[$attribute] = $model[$link];
                }
                $values[] = $v;
                if (empty($v)) {
                    $this->emulate_execution();
                }
            }
        }
        if (!empty($values)) {
            $scalar_values = [];
            $non_scalar_values = [];
            foreach ($values as $value) {
                if (is_scalar($value)) {
                    $scalar_values[] = $value;
                } else {
                    $non_scalar_values[] = $value;
                }
            }
            $scalar_values = array_unique($scalar_values);
            $values = array_merge($scalar_values, $non_scalar_values);
        }
        $this->and_where(['in', $attributes, $values]);
    }
    /**
     * @param ActiveRecordInterface|array $model
     * @param array $attributes
     * @return string|false
     */
    private function get_model_key($model, $attributes)
    {
        $key = [];
        foreach ($attributes as $attribute) {
            if (isset($model[$attribute]) || is_object($model) && property_exists($model, $attribute)) {
                $key[] = $this->normalize_model_key($model[$attribute]);
            }
        }
        if (count($key) > 1) {
            return serialize($key);
        }
        return reset($key);
    }
    /**
     * @param mixed $value raw key value. Since 2.0.40 non-string values must be convertible to string (like special
     * objects for cross-DBMS relations, for example: `|MongoId`).
     * @return string normalized key value.
     */
    private function normalize_model_key($value): string
    {
        try {
            return (string) $value;
        } catch (\Exception|\Throwable $e) {
            throw new Invalid_Config_Exception('Value must be convertable to string.');
        }
    }
    /**
     * @param array $primaryModels either array of AR instances or arrays
     * @return array
     */
    private function find_junction_rows($primary_models)
    {
        if (empty($primary_models)) {
            return [];
        }
        $this->filter_by_models($primary_models);
        /** @var ActiveRecord $primaryModel */
        $primary_model = reset($primary_models);
        if (!$primary_model instanceof Active_Record_Interface) {
            // when primaryModels are array of arrays (asArray case)
            $primary_model = $this->model_class;
        }
        return $this->as_array()->all($primary_model::get_db());
    }
}