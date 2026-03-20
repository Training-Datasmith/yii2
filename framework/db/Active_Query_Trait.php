<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\db;

/**
 * ActiveQueryTrait implements the common methods and properties for active record query classes.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @author Carsten Brandt <mail@cebe.cc>
 * @since 2.0
 */
trait Active_Query_Trait
{
    /**
     * @var class-string<ActiveRecordInterface> the name of the ActiveRecord class.
     */
    public $model_class;
    /**
     * @var array|null a list of relations that this query should be performed with
     */
    public $with;
    /**
     * @var bool whether to return each record as an array. If false (default), an object
     * of [[modelClass]] will be created to represent each record.
     */
    public $as_array;
    /**
     * Sets the [[asArray]] property.
     * @param bool $value whether to return the query results in terms of arrays instead of Active Records.
     * @return $this the query object itself
     */
    public function as_array($value = true)
    {
        $this->as_array = $value;
        return $this;
    }
    /**
     * Specifies the relations with which this query should be performed.
     *
     * The parameters to this method can be either one or multiple strings, or a single array
     * of relation names and the optional callbacks to customize the relations.
     *
     * A relation name can refer to a relation defined in [[modelClass]]
     * or a sub-relation that stands for a relation of a related record.
     * For example, `orders.address` means the `address` relation defined
     * in the model class corresponding to the `orders` relation.
     *
     * The following are some usage examples:
     *
     * ```
     * // find customers together with their orders and country
     * Customer::find()->with('orders', 'country')->all();
     * // find customers together with their orders and the orders' shipping address
     * Customer::find()->with('orders.address')->all();
     * // find customers together with their country and orders of status 1
     * Customer::find()->with([
     *     'orders' => function (\yii\db\ActiveQuery $query) {
     *         $query->andWhere('status = 1');
     *     },
     *     'country',
     * ])->all();
     * ```
     *
     * You can call `with()` multiple times. Each call will add relations to the existing ones.
     * For example, the following two statements are equivalent:
     *
     * ```
     * Customer::find()->with('orders', 'country')->all();
     * Customer::find()->with('orders')->with('country')->all();
     * ```
     *
     * @return $this the query object itself
     */
    public function with()
    {
        $with = func_get_args();
        if (isset($with[0]) && is_array($with[0])) {
            // the parameter is given as an array
            $with = $with[0];
        }
        if (empty($this->with)) {
            $this->with = $with;
        } elseif (!empty($with)) {
            foreach ($with as $name => $value) {
                if (is_int($name)) {
                    // repeating relation is fine as normalizeRelations() handle it well
                    $this->with[] = $value;
                } else {
                    $this->with[$name] = $value;
                }
            }
        }
        return $this;
    }
    /**
     * Converts found rows into model instances.
     * @param array $rows
     * @return array|ActiveRecord[]
     * @since 2.0.11
     */
    protected function create_models($rows)
    {
        if ($this->as_array) {
            return $rows;
        }
        $models = [];
        /** @var ActiveRecord $class */
        $class = $this->model_class;
        foreach ($rows as $row) {
            $model = $class::instantiate($row);
            $model_class = get_class($model);
            $model_class::populate_record($model, $row);
            $models[] = $model;
        }
        return $models;
    }
    /**
     * Finds records corresponding to one or multiple relations and populates them into the primary models.
     * @param array $with a list of relations that this query should be performed with. Please
     * refer to [[with()]] for details about specifying this parameter.
     * @param array|ActiveRecord[] $models the primary models (can be either AR instances or arrays)
     */
    public function find_with($with, &$models): void
    {
        if (empty($models)) {
            return;
        }
        $primary_model = reset($models);
        if (!$primary_model instanceof Active_Record_Interface) {
            /** @var ActiveRecordInterface $modelClass */
            $model_class = $this->model_class;
            $primary_model = $model_class::instance();
        }
        $relations = $this->normalize_relations($primary_model, $with);
        /** @var ActiveQuery $relation */
        foreach ($relations as $name => $relation) {
            if ($relation->as_array === null) {
                // inherit asArray from primary query
                $relation->as_array($this->as_array);
            }
            $relation->populate_relation($name, $models);
        }
    }
    /**
     * @param ActiveRecord $model
     * @param array $with
     * @return ActiveQueryInterface[]
     */
    private function normalize_relations($model, $with): array
    {
        $relations = [];
        foreach ($with as $name => $callback) {
            if (is_int($name)) {
                $name = $callback;
                $callback = null;
            }
            if (($pos = strpos($name, '.')) !== false) {
                // with sub-relations
                $child_name = substr($name, $pos + 1);
                $name = substr($name, 0, $pos);
            } else {
                $child_name = null;
            }
            if (!isset($relations[$name])) {
                $relation = $model->get_relation($name);
                $relation->primary_model = null;
                $relations[$name] = $relation;
            } else {
                $relation = $relations[$name];
            }
            if (isset($child_name)) {
                $relation->with[$child_name] = $callback;
            } elseif ($callback !== null) {
                call_user_func($callback, $relation);
            }
        }
        return $relations;
    }
}