<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\db;

use yii\base\Invalid_Config_Exception;
/**
 * ActiveQuery represents a DB query associated with an Active Record class.
 *
 * An ActiveQuery can be a normal query or be used in a relational context.
 *
 * ActiveQuery instances are usually created by [[ActiveRecord::find()]] and [[ActiveRecord::findBySql()]].
 * Relational queries are created by [[ActiveRecord::hasOne()]] and [[ActiveRecord::hasMany()]].
 *
 * Normal Query
 * ------------
 *
 * ActiveQuery mainly provides the following methods to retrieve the query results:
 *
 * - [[one()]]: returns a single record populated with the first row of data.
 * - [[all()]]: returns all records based on the query results.
 * - [[count()]]: returns the number of records.
 * - [[sum()]]: returns the sum over the specified column.
 * - [[average()]]: returns the average over the specified column.
 * - [[min()]]: returns the min over the specified column.
 * - [[max()]]: returns the max over the specified column.
 * - [[scalar()]]: returns the value of the first column in the first row of the query result.
 * - [[column()]]: returns the value of the first column in the query result.
 * - [[exists()]]: returns a value indicating whether the query result has data or not.
 *
 * Because ActiveQuery extends from [[Query]], one can use query methods, such as [[where()]],
 * [[orderBy()]] to customize the query options.
 *
 * ActiveQuery also provides the following additional query options:
 *
 * - [[with()]]: list of relations that this query should be performed with.
 * - [[joinWith()]]: reuse a relation query definition to add a join to a query.
 * - [[indexBy()]]: the name of the column by which the query result should be indexed.
 * - [[asArray()]]: whether to return each record as an array.
 *
 * These options can be configured using methods of the same name. For example:
 *
 * ```
 * $customers = Customer::find()->with('orders')->asArray()->all();
 * ```
 *
 * Relational query
 * ----------------
 *
 * In relational context ActiveQuery represents a relation between two Active Record classes.
 *
 * Relational ActiveQuery instances are usually created by calling [[ActiveRecord::hasOne()]] and
 * [[ActiveRecord::hasMany()]]. An Active Record class declares a relation by defining
 * a getter method which calls one of the above methods and returns the created ActiveQuery object.
 *
 * A relation is specified by [[link]] which represents the association between columns
 * of different tables; and the multiplicity of the relation is indicated by [[multiple]].
 *
 * If a relation involves a junction table, it may be specified by [[via()]] or [[viaTable()]] method.
 * These methods may only be called in a relational context. Same is true for [[inverseOf()]], which
 * marks a relation as inverse of another relation and [[onCondition()]] which adds a condition that
 * is to be added to relational query join condition.
 *
 * @template T of ActiveRecord|array = ActiveRecord|array<array-key, mixed>
 *
 * @method T|null one($db = null) See [[ActiveQueryInterface::one()]] for more info.
 * @method T[] all($db = null) See [[ActiveQueryInterface::all()]] for more info.
 * @method ($value is true ? (T is array ? static<T> : static<array<string, mixed>>) : static<T>) asArray($value = true) Sets the [[asArray]] property.
 * @method BatchQueryResult<int, T[]> batch($batchSize = 100, $db = null) the batch query result. It implements the [[\Iterator]] interface
 * and can be traversed to retrieve the data in batches.
 * @method BatchQueryResult<int, T> each($batchSize = 100, $db = null) the batch query result. It implements the [[\Iterator]] interface
 * and can be traversed to retrieve the data in batches.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @author Carsten Brandt <mail@cebe.cc>
 * @since 2.0
 */
class Active_Query extends Query implements Active_Query_Interface
{
    use Active_Query_Trait;
    use Active_Relation_Trait;
    /**
     * @event Event an event that is triggered when the query is initialized via [[init()]].
     */
    public const EVENT_INIT = 'init';
    /**
     * @var string|null the SQL statement to be executed for retrieving AR records.
     * This is set by [[ActiveRecord::findBySql()]].
     */
    public $sql;
    /**
     * @var string|array|null the join condition to be used when this query is used in a relational context.
     * The condition will be used in the ON part when [[ActiveQuery::joinWith()]] is called.
     * Otherwise, the condition will be used in the WHERE part of a query.
     * Please refer to [[Query::where()]] on how to specify this parameter.
     * @see onCondition()
     */
    public $on;
    /**
     * @var array|null a list of relations that this query should be joined with
     */
    public $join_with;
    /**
     * Constructor.
     * @param class-string<ActiveRecordInterface> $modelClass the model class associated with this query
     * @param array $config configurations to be applied to the newly created query object
     */
    public function __construct($model_class, $config = [])
    {
        $this->model_class = $model_class;
        parent::__construct($config);
    }
    /**
     * Initializes the object.
     * This method is called at the end of the constructor. The default implementation will trigger
     * an [[EVENT_INIT]] event. If you override this method, make sure you call the parent implementation at the end
     * to ensure triggering of the event.
     */
    public function init()
    {
        parent::init();
        $this->trigger(self::EVENT_INIT);
    }
    /**
     * Executes query and returns all results as an array.
     * @param Connection|null $db the DB connection used to create the DB command.
     * If null, the DB connection returned by [[modelClass]] will be used.
     * @return T[] the query results. If the query results in nothing, an empty array will be returned.
     */
    public function all($db = null)
    {
        return parent::all($db);
    }
    /**
     * {@inheritdoc}
     */
    public function prepare($builder)
    {
        // NOTE: because the same ActiveQuery may be used to build different SQL statements
        // (e.g. by ActiveDataProvider, one for count query, the other for row data query,
        // it is important to make sure the same ActiveQuery can be used to build SQL statements
        // multiple times.
        if (!empty($this->join_with)) {
            $this->build_join_with();
            $this->join_with = null;
            // clean it up to avoid issue https://github.com/yiisoft/yii2/issues/2687
        }
        if (empty($this->from)) {
            $this->from = [$this->get_primary_table_name()];
        }
        if (empty($this->select) && !empty($this->join)) {
            list(, $alias) = $this->get_table_name_and_alias();
            $this->select = ["{$alias}.*"];
        }
        if ($this->primary_model === null) {
            // eager loading
            $query = Query::create($this);
        } else {
            // lazy loading of a relation
            $where = $this->where;
            if ($this->via instanceof self) {
                // via junction table
                $via_models = $this->via->find_junction_rows([$this->primary_model]);
                $this->filter_by_models($via_models);
            } elseif (is_array($this->via)) {
                // via relation
                /** @var self<ActiveRecord|array<string, mixed>> $viaQuery */
                list($via_name, $via_query, $via_callable_used) = $this->via;
                if ($via_query->multiple) {
                    if ($via_callable_used) {
                        $via_models = $via_query->all();
                    } elseif ($this->primary_model->is_relation_populated($via_name)) {
                        $via_models = $this->primary_model->{$via_name};
                    } else {
                        $via_models = $via_query->all();
                        $this->primary_model->populate_relation($via_name, $via_models);
                    }
                } else {
                    if ($via_callable_used) {
                        $model = $via_query->one();
                    } elseif ($this->primary_model->is_relation_populated($via_name)) {
                        $model = $this->primary_model->{$via_name};
                    } else {
                        $model = $via_query->one();
                        $this->primary_model->populate_relation($via_name, $model);
                    }
                    $via_models = $model === null ? [] : [$model];
                }
                $this->filter_by_models($via_models);
            } else {
                $this->filter_by_models([$this->primary_model]);
            }
            $query = Query::create($this);
            $this->where = $where;
        }
        if (!empty($this->on)) {
            $query->and_where($this->on);
        }
        return $query;
    }
    /**
     * {@inheritdoc}
     */
    public function populate($rows)
    {
        if (empty($rows)) {
            return [];
        }
        $models = $this->create_models($rows);
        if (!empty($this->join) && $this->index_by === null) {
            $models = $this->remove_duplicated_models($models);
        }
        if (!empty($this->with)) {
            $this->find_with($this->with, $models);
        }
        if ($this->inverse_of !== null) {
            $this->add_inverse_relations($models);
        }
        if (!$this->as_array) {
            foreach ($models as $model) {
                $model->after_find();
            }
        }
        return parent::populate($models);
    }
    /**
     * Removes duplicated models by checking their primary key values.
     * This method is mainly called when a join query is performed, which may cause duplicated rows being returned.
     * @param array $models the models to be checked
     * @throws InvalidConfigException if model primary key is empty
     * @return array the distinctive models
     */
    private function remove_duplicated_models($models)
    {
        $hash = [];
        /** @var class-string<ActiveRecord> $class */
        $class = $this->model_class;
        $pks = $class::primary_key();
        if (count($pks) > 1) {
            // composite primary key
            foreach ($models as $i => $model) {
                $key = [];
                foreach ($pks as $pk) {
                    if (!isset($model[$pk])) {
                        // do not continue if the primary key is not part of the result set
                        break 2;
                    }
                    $key[] = $model[$pk];
                }
                $key = serialize($key);
                if (isset($hash[$key])) {
                    unset($models[$i]);
                } else {
                    $hash[$key] = true;
                }
            }
        } elseif (empty($pks)) {
            throw new Invalid_Config_Exception("Primary key of '{$class}' can not be empty.");
        } else {
            // single column primary key
            $pk = reset($pks);
            foreach ($models as $i => $model) {
                if (!isset($model[$pk])) {
                    // do not continue if the primary key is not part of the result set
                    break;
                }
                $key = $model[$pk];
                if (isset($hash[$key])) {
                    unset($models[$i]);
                } elseif ($key !== null) {
                    $hash[$key] = true;
                }
            }
        }
        return array_values($models);
    }
    /**
     * Executes query and returns a single row of result.
     * @param Connection|null $db the DB connection used to create the DB command.
     * If `null`, the DB connection returned by [[modelClass]] will be used.
     * @return T|null a single row of query result. Depending on the setting of [[asArray]],
     * the query result may be either an array or an ActiveRecord object. `null` will be returned
     * if the query results in nothing.
     */
    public function one($db = null)
    {
        $row = parent::one($db);
        if ($row !== false) {
            $models = $this->populate([$row]);
            return reset($models) ?: null;
        }
        return null;
    }
    /**
     * Creates a DB command that can be used to execute this query.
     * @param Connection|null $db the DB connection used to create the DB command.
     * If `null`, the DB connection returned by [[modelClass]] will be used.
     * @return Command the created DB command instance.
     */
    public function create_command($db = null)
    {
        /** @var ActiveRecord $modelClass */
        $model_class = $this->model_class;
        if ($db === null) {
            $db = $model_class::get_db();
        }
        if ($this->sql === null) {
            list($sql, $params) = $db->get_query_builder()->build($this);
        } else {
            $sql = $this->sql;
            $params = $this->params;
        }
        $command = $db->create_command($sql, $params);
        $this->set_command_cache($command);
        return $command;
    }
    /**
     * {@inheritdoc}
     */
    protected function query_scalar($select_expression, $db)
    {
        /** @var ActiveRecord $modelClass */
        $model_class = $this->model_class;
        if ($db === null) {
            $db = $model_class::get_db();
        }
        if ($this->sql === null) {
            return parent::query_scalar($select_expression, $db);
        }
        $command = (new Query())->select([$select_expression])->from(['c' => "({$this->sql})"])->params($this->params)->create_command($db);
        $this->set_command_cache($command);
        return $command->query_scalar();
    }
    /**
     * Joins with the specified relations.
     *
     * This method allows you to reuse existing relation definitions to perform JOIN queries.
     * Based on the definition of the specified relation(s), the method will append one or multiple
     * JOIN statements to the current query.
     *
     * If the `$eagerLoading` parameter is true, the method will also perform eager loading for the specified relations,
     * which is equivalent to calling [[with()]] using the specified relations.
     *
     * Note that because a JOIN query will be performed, you are responsible to disambiguate column names.
     *
     * This method differs from [[with()]] in that it will build up and execute a JOIN SQL statement
     * for the primary table. And when `$eagerLoading` is true, it will call [[with()]] in addition with the specified relations.
     *
     * @param string|array $with the relations to be joined. This can either be a string, representing a relation name or
     * an array with the following semantics:
     *
     * - Each array element represents a single relation.
     * - You may specify the relation name as the array key and provide an anonymous functions that
     *   can be used to modify the relation queries on-the-fly as the array value.
     * - If a relation query does not need modification, you may use the relation name as the array value.
     *
     * The relation name may optionally contain an alias for the relation table (e.g. `books b`).
     *
     * Sub-relations can also be specified, see [[with()]] for the syntax.
     *
     * In the following you find some examples:
     *
     * ```
     * // find all orders that contain books, and eager loading "books"
     * Order::find()->joinWith('books', true, 'INNER JOIN')->all();
     * // find all orders, eager loading "books", and sort the orders and books by the book names.
     * Order::find()->joinWith([
     *     'books' => function (\yii\db\ActiveQuery $query) {
     *         $query->orderBy('item.name');
     *     }
     * ])->all();
     * // find all orders that contain books of the category 'Science fiction', using the alias "b" for the books table
     * Order::find()->joinWith(['books b'], true, 'INNER JOIN')->where(['b.category' => 'Science fiction'])->all();
     * ```
     *
     * The alias syntax is available since version 2.0.7.
     *
     * @param bool|array $eagerLoading whether to eager load the relations
     * specified in `$with`.  When this is a boolean, it applies to all
     * relations specified in `$with`. Use an array to explicitly list which
     * relations in `$with` need to be eagerly loaded.  Note, that this does
     * not mean, that the relations are populated from the query result. An
     * extra query will still be performed to bring in the related data.
     * Defaults to `true`.
     * @param string|array $joinType the join type of the relations specified in `$with`.
     * When this is a string, it applies to all relations specified in `$with`. Use an array
     * in the format of `relationName => joinType` to specify different join types for different relations.
     * @return $this the query object itself
     */
    public function join_with($with, $eager_loading = true, $join_type = 'LEFT JOIN')
    {
        $relations = [];
        foreach ((array) $with as $name => $callback) {
            if (is_int($name)) {
                $name = $callback;
                $callback = null;
            }
            if (preg_match('/^(.*?)(?:\s+AS\s+|\s+)(\w+)$/i', $name, $matches)) {
                // relation is defined with an alias, adjust callback to apply alias
                list(, $relation, $alias) = $matches;
                $name = $relation;
                $callback = function ($query) use ($callback, $alias) {
                    /** @var self<ActiveRecord|array<string, mixed>> $query */
                    $query->alias($alias);
                    if ($callback !== null) {
                        call_user_func($callback, $query);
                    }
                };
            }
            if ($callback === null) {
                $relations[] = $name;
            } else {
                $relations[$name] = $callback;
            }
        }
        $this->join_with[] = [$relations, $eager_loading, $join_type];
        return $this;
    }
    private function build_join_with()
    {
        $join = $this->join;
        $this->join = [];
        /** @var ActiveRecordInterface $modelClass */
        $model_class = $this->model_class;
        $model = $model_class::instance();
        foreach ($this->join_with as $config) {
            list($with, $eager_loading, $join_type) = $config;
            $this->join_with_relations($model, $with, $join_type);
            if (is_array($eager_loading)) {
                foreach ($with as $name => $callback) {
                    if (is_int($name)) {
                        if (!in_array($callback, $eager_loading, true)) {
                            unset($with[$name]);
                        }
                    } elseif (!in_array($name, $eager_loading, true)) {
                        unset($with[$name]);
                    }
                }
            } elseif (!$eager_loading) {
                $with = [];
            }
            $this->with($with);
        }
        // remove duplicated joins added by joinWithRelations that may be added
        // e.g. when joining a relation and a via relation at the same time
        $unique_joins = [];
        foreach ($this->join as $j) {
            $unique_joins[serialize($j)] = $j;
        }
        $this->join = array_values($unique_joins);
        // https://github.com/yiisoft/yii2/issues/16092
        $unique_joins_by_table_name = [];
        foreach ($this->join as $config) {
            $table_name = serialize($config[1]);
            if (!array_key_exists($table_name, $unique_joins_by_table_name)) {
                $unique_joins_by_table_name[$table_name] = $config;
            }
        }
        $this->join = array_values($unique_joins_by_table_name);
        if (!empty($join)) {
            // append explicit join to joinWith()
            // https://github.com/yiisoft/yii2/issues/2880
            $this->join = empty($this->join) ? $join : array_merge($this->join, $join);
        }
    }
    /**
     * Inner joins with the specified relations.
     * This is a shortcut method to [[joinWith()]] with the join type set as "INNER JOIN".
     * Please refer to [[joinWith()]] for detailed usage of this method.
     * @param string|array $with the relations to be joined with.
     * @param bool|array $eagerLoading whether to eager load the relations.
     * Note, that this does not mean, that the relations are populated from the
     * query result. An extra query will still be performed to bring in the
     * related data.
     * @return $this the query object itself
     * @see joinWith()
     */
    public function inner_join_with($with, $eager_loading = true)
    {
        return $this->join_with($with, $eager_loading, 'INNER JOIN');
    }
    /**
     * Modifies the current query by adding join fragments based on the given relations.
     * @param ActiveRecord $model the primary model
     * @param array $with the relations to be joined
     * @param string|array $joinType the join type
     */
    private function join_with_relations($model, $with, $join_type)
    {
        $relations = [];
        foreach ($with as $name => $callback) {
            if (is_int($name)) {
                $name = $callback;
                $callback = null;
            }
            $primary_model = $model;
            $parent = $this;
            $prefix = '';
            while (($pos = strpos($name, '.')) !== false) {
                $child_name = substr($name, $pos + 1);
                $name = substr($name, 0, $pos);
                $full_name = $prefix === '' ? $name : "{$prefix}.{$name}";
                if (!isset($relations[$full_name])) {
                    $relations[$full_name] = $relation = $primary_model->get_relation($name);
                    $this->join_with_relation($parent, $relation, $this->get_join_type($join_type, $full_name));
                } else {
                    $relation = $relations[$full_name];
                }
                /** @var ActiveRecordInterface $relationModelClass */
                $relation_model_class = $relation->model_class;
                $primary_model = $relation_model_class::instance();
                $parent = $relation;
                $prefix = $full_name;
                $name = $child_name;
            }
            $full_name = $prefix === '' ? $name : "{$prefix}.{$name}";
            if (!isset($relations[$full_name])) {
                $relations[$full_name] = $relation = $primary_model->get_relation($name);
                if ($callback !== null) {
                    call_user_func($callback, $relation);
                }
                if (!empty($relation->join_with)) {
                    $relation->build_join_with();
                }
                $this->join_with_relation($parent, $relation, $this->get_join_type($join_type, $full_name));
            }
        }
    }
    /**
     * Returns the join type based on the given join type parameter and the relation name.
     * @param string|array $joinType the given join type(s)
     * @param string $name relation name
     * @return string the real join type
     */
    private function get_join_type($join_type, $name)
    {
        if (is_array($join_type) && isset($join_type[$name])) {
            return $join_type[$name];
        }
        return is_string($join_type) ? $join_type : 'INNER JOIN';
    }
    /**
     * Returns the table name and the table alias for [[modelClass]].
     * @return array the table name and the table alias.
     * @since 2.0.16
     */
    protected function get_table_name_and_alias()
    {
        if (empty($this->from)) {
            $table_name = $this->get_primary_table_name();
        } else {
            $table_name = '';
            // if the first entry in "from" is an alias-tablename-pair return it directly
            foreach ($this->from as $alias => $table_name) {
                if (is_string($alias)) {
                    return [$table_name, $alias];
                }
                break;
            }
        }
        if (preg_match('/^(.*?)\s+({{\w+}}|\w+)$/', $table_name, $matches)) {
            $alias = $matches[2];
        } else {
            $alias = $table_name;
        }
        return [$table_name, $alias];
    }
    /**
     * Joins a parent query with a child query.
     * The current query object will be modified accordingly.
     * @param ActiveQuery $parent
     * @param ActiveQuery $child
     * @param string $joinType
     */
    private function join_with_relation($parent, $child, $join_type)
    {
        $via = $child->via;
        $child->via = null;
        if ($via instanceof self) {
            // via table
            $this->join_with_relation($parent, $via, $join_type);
            $this->join_with_relation($via, $child, $join_type);
            return;
        } elseif (is_array($via)) {
            // via relation
            $this->join_with_relation($parent, $via[1], $join_type);
            $this->join_with_relation($via[1], $child, $join_type);
            return;
        }
        list($parent_table, $parent_alias) = $parent->get_table_name_and_alias();
        list($child_table, $child_alias) = $child->get_table_name_and_alias();
        if (!empty($child->link)) {
            if (strpos($parent_alias, '{{') === false) {
                $parent_alias = '{{' . $parent_alias . '}}';
            }
            if (strpos($child_alias, '{{') === false) {
                $child_alias = '{{' . $child_alias . '}}';
            }
            $on = [];
            foreach ($child->link as $child_column => $parent_column) {
                $on[] = "{$parent_alias}.[[{$parent_column}]] = {$child_alias}.[[{$child_column}]]";
            }
            $on = implode(' AND ', $on);
            if (!empty($child->on)) {
                $on = ['and', $on, $child->on];
            }
        } else {
            $on = $child->on;
        }
        $this->join($join_type, empty($child->from) ? $child_table : $child->from, $on);
        if (!empty($child->where)) {
            $this->and_where($child->where);
        }
        if (!empty($child->having)) {
            $this->and_having($child->having);
        }
        if (!empty($child->order_by)) {
            $this->add_order_by($child->order_by);
        }
        if (!empty($child->group_by)) {
            $this->add_group_by($child->group_by);
        }
        if (!empty($child->params)) {
            $this->add_params($child->params);
        }
        if (!empty($child->join)) {
            foreach ($child->join as $join) {
                $this->join[] = $join;
            }
        }
        if (!empty($child->union)) {
            foreach ($child->union as $union) {
                $this->union[] = $union;
            }
        }
    }
    /**
     * Sets the ON condition for a relational query.
     * The condition will be used in the ON part when [[ActiveQuery::joinWith()]] is called.
     * Otherwise, the condition will be used in the WHERE part of a query.
     *
     * Use this method to specify additional conditions when declaring a relation in the [[ActiveRecord]] class:
     *
     * ```
     * public function getActiveUsers()
     * {
     *     return $this->hasMany(User::class, ['id' => 'user_id'])
     *                 ->onCondition(['active' => true]);
     * }
     * ```
     *
     * Note that this condition is applied in case of a join as well as when fetching the related records.
     * Thus only fields of the related table can be used in the condition. Trying to access fields of the primary
     * record will cause an error in a non-join-query.
     *
     * @param string|array $condition the ON condition. Please refer to [[Query::where()]] on how to specify this parameter.
     * @param array $params the parameters (name => value) to be bound to the query.
     * @return $this the query object itself
     */
    public function on_condition($condition, $params = [])
    {
        $this->on = $condition;
        $this->add_params($params);
        return $this;
    }
    /**
     * Adds an additional ON condition to the existing one.
     * The new condition and the existing one will be joined using the 'AND' operator.
     * @param string|array $condition the new ON condition. Please refer to [[where()]]
     * on how to specify this parameter.
     * @param array $params the parameters (name => value) to be bound to the query.
     * @return $this the query object itself
     * @see onCondition()
     * @see orOnCondition()
     */
    public function and_on_condition($condition, $params = [])
    {
        if ($this->on === null) {
            $this->on = $condition;
        } else {
            $this->on = ['and', $this->on, $condition];
        }
        $this->add_params($params);
        return $this;
    }
    /**
     * Adds an additional ON condition to the existing one.
     * The new condition and the existing one will be joined using the 'OR' operator.
     * @param string|array $condition the new ON condition. Please refer to [[where()]]
     * on how to specify this parameter.
     * @param array $params the parameters (name => value) to be bound to the query.
     * @return $this the query object itself
     * @see onCondition()
     * @see andOnCondition()
     */
    public function or_on_condition($condition, $params = [])
    {
        if ($this->on === null) {
            $this->on = $condition;
        } else {
            $this->on = ['or', $this->on, $condition];
        }
        $this->add_params($params);
        return $this;
    }
    /**
     * Specifies the junction table for a relational query.
     *
     * Use this method to specify a junction table when declaring a relation in the [[ActiveRecord]] class:
     *
     * ```
     * public function getItems()
     * {
     *     return $this->hasMany(Item::class, ['id' => 'item_id'])
     *                 ->viaTable('order_item', ['order_id' => 'id']);
     * }
     * ```
     *
     * @param string $tableName the name of the junction table.
     * @param array $link the link between the junction table and the table associated with [[primaryModel]].
     * The keys of the array represent the columns in the junction table, and the values represent the columns
     * in the [[primaryModel]] table.
     * @param callable|null $callable a PHP callback for customizing the relation associated with the junction table.
     * Its signature should be `function($query)`, where `$query` is the query to be customized.
     * @return $this the query object itself
     * @throws InvalidConfigException when query is not initialized properly
     * @see via()
     */
    public function via_table($table_name, $link, ?callable $callable = null)
    {
        $model_class = $this->primary_model ? get_class($this->primary_model) : $this->model_class;
        $relation = new self($model_class, ['from' => [$table_name], 'link' => $link, 'multiple' => true, 'asArray' => true]);
        $this->via = $relation;
        if ($callable !== null) {
            call_user_func($callable, $relation);
        }
        return $this;
    }
    /**
     * Define an alias for the table defined in [[modelClass]].
     *
     * This method will adjust [[from]] so that an already defined alias will be overwritten.
     * If none was defined, [[from]] will be populated with the given alias.
     *
     * @param string $alias the table alias.
     * @return $this the query object itself
     * @since 2.0.7
     */
    public function alias($alias)
    {
        if (empty($this->from) || count($this->from) < 2) {
            list($table_name) = $this->get_table_name_and_alias();
            $this->from = [$alias => $table_name];
        } else {
            $table_name = $this->get_primary_table_name();
            foreach ($this->from as $key => $table) {
                if ($table === $table_name) {
                    unset($this->from[$key]);
                    $this->from[$alias] = $table_name;
                }
            }
        }
        return $this;
    }
    /**
     * {@inheritdoc}
     * @since 2.0.12
     */
    public function get_tables_used_in_from()
    {
        if (empty($this->from)) {
            return $this->clean_up_table_names([$this->get_primary_table_name()]);
        }
        return parent::get_tables_used_in_from();
    }
    /**
     * @return string primary table name
     * @since 2.0.12
     */
    protected function get_primary_table_name()
    {
        /** @var ActiveRecord $modelClass */
        $model_class = $this->model_class;
        return $model_class::table_name();
    }
}