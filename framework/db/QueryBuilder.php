<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\db;

use yii\base\InvalidArgumentException;
use yii\base\Not_Supported_Exception;
use yii\db\conditions\Condition_Interface;
use yii\db\conditions\Hash_Condition;
use yii\helpers\String_Helper;
/**
 * QueryBuilder builds a SELECT SQL statement based on the specification given as a [[Query]] object.
 *
 * SQL statements are created from [[Query]] objects using the [[build()]]-method.
 *
 * QueryBuilder is also used by [[Command]] to build SQL statements such as INSERT, UPDATE, DELETE, CREATE TABLE.
 *
 * For more details and usage information on QueryBuilder, see the [guide article on query builders](guide:db-query-builder).
 *
 * @property-write string[] $conditionClasses Map of condition aliases to condition classes. For example:
 *
 * ```
 * ['LIKE' => yii\db\condition\LikeCondition::class]
 * ```
 * @property-write string[] $expressionBuilders Array of builders that should be merged with the pre-defined
 * ones in [[expressionBuilders]] property.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Query_Builder extends \yii\base\Base_Object
{
    /**
     * The prefix for automatically generated query binding parameters.
     */
    public const PARAM_PREFIX = ':qp';
    /**
     * @var Connection the database connection.
     */
    public $db;
    /**
     * @var string the separator between different fragments of a SQL statement.
     * Defaults to an empty space. This is mainly used by [[build()]] when generating a SQL statement.
     */
    public $separator = ' ';
    /**
     * @var array the abstract column types mapped to physical column types.
     * This is mainly used to support creating/modifying tables using DB-independent data type specifications.
     * Child classes should override this property to declare supported type mappings.
     */
    public $type_map = [];
    /**
     * @var array map of query condition to builder methods.
     * These methods are used by [[buildCondition]] to build SQL conditions from array syntax.
     * @deprecated since 2.0.14. Is not used, will be dropped in 2.1.0.
     */
    protected $condition_builders = [];
    /**
     * @var array map of condition aliases to condition classes. For example:
     *
     * ```
     * return [
     *     'LIKE' => yii\db\condition\LikeCondition::class,
     * ];
     * ```
     *
     * This property is used by [[createConditionFromArray]] method.
     * See default condition classes list in [[defaultConditionClasses()]] method.
     *
     * In case you want to add custom conditions support, use the [[setConditionClasses()]] method.
     *
     * @see setConditionClasses()
     * @see defaultConditionClasses()
     * @since 2.0.14
     */
    protected $condition_classes = [];
    /**
     * @var string[]|ExpressionBuilderInterface[] maps expression class to expression builder class.
     * For example:
     *
     * ```
     * [
     *    yii\db\Expression::class => yii\db\ExpressionBuilder::class
     * ]
     * ```
     * This property is mainly used by [[buildExpression()]] to build SQL expressions form expression objects.
     * See default values in [[defaultExpressionBuilders()]] method.
     *
     *
     * To override existing builders or add custom, use [[setExpressionBuilder()]] method. New items will be added
     * to the end of this array.
     *
     * To find a builder, [[buildExpression()]] will check the expression class for its exact presence in this map.
     * In case it is NOT present, the array will be iterated in reverse direction, checking whether the expression
     * extends the class, defined in this map.
     *
     * @see setExpressionBuilders()
     * @see defaultExpressionBuilders()
     * @since 2.0.14
     */
    protected $expression_builders = [];
    /**
     * Constructor.
     * @param Connection $connection the database connection.
     * @param array $config name-value pairs that will be used to initialize the object properties
     */
    public function __construct($connection, $config = [])
    {
        $this->db = $connection;
        parent::__construct($config);
    }
    /**
     * {@inheritdoc}
     */
    public function init(): void
    {
        parent::init();
        $this->expression_builders = array_merge($this->default_expression_builders(), $this->expression_builders);
        $this->condition_classes = array_merge($this->default_condition_classes(), $this->condition_classes);
    }
    /**
     * Contains array of default condition classes. Extend this method, if you want to change
     * default condition classes for the query builder. See [[conditionClasses]] docs for details.
     *
     * @see conditionClasses
     * @since 2.0.14
     */
    protected function default_condition_classes(): array
    {
        return ['NOT' => 'yii\db\conditions\NotCondition', 'AND' => 'yii\db\conditions\AndCondition', 'OR' => 'yii\db\conditions\OrCondition', 'BETWEEN' => 'yii\db\conditions\BetweenCondition', 'NOT BETWEEN' => 'yii\db\conditions\BetweenCondition', 'IN' => 'yii\db\conditions\InCondition', 'NOT IN' => 'yii\db\conditions\InCondition', 'LIKE' => 'yii\db\conditions\LikeCondition', 'NOT LIKE' => 'yii\db\conditions\LikeCondition', 'OR LIKE' => 'yii\db\conditions\LikeCondition', 'OR NOT LIKE' => 'yii\db\conditions\LikeCondition', 'EXISTS' => 'yii\db\conditions\ExistsCondition', 'NOT EXISTS' => 'yii\db\conditions\ExistsCondition'];
    }
    /**
     * Contains array of default expression builders. Extend this method and override it, if you want to change
     * default expression builders for this query builder. See [[expressionBuilders]] docs for details.
     *
     * @see expressionBuilders
     * @since 2.0.14
     */
    protected function default_expression_builders(): array
    {
        return ['yii\db\Query' => 'yii\db\QueryExpressionBuilder', 'yii\db\PdoValue' => 'yii\db\PdoValueBuilder', 'yii\db\Expression' => 'yii\db\ExpressionBuilder', 'yii\db\conditions\ConjunctionCondition' => 'yii\db\conditions\ConjunctionConditionBuilder', 'yii\db\conditions\NotCondition' => 'yii\db\conditions\NotConditionBuilder', 'yii\db\conditions\AndCondition' => 'yii\db\conditions\ConjunctionConditionBuilder', 'yii\db\conditions\OrCondition' => 'yii\db\conditions\ConjunctionConditionBuilder', 'yii\db\conditions\BetweenCondition' => 'yii\db\conditions\BetweenConditionBuilder', 'yii\db\conditions\InCondition' => 'yii\db\conditions\InConditionBuilder', 'yii\db\conditions\LikeCondition' => 'yii\db\conditions\LikeConditionBuilder', 'yii\db\conditions\ExistsCondition' => 'yii\db\conditions\ExistsConditionBuilder', 'yii\db\conditions\SimpleCondition' => 'yii\db\conditions\SimpleConditionBuilder', 'yii\db\conditions\HashCondition' => 'yii\db\conditions\HashConditionBuilder', 'yii\db\conditions\BetweenColumnsCondition' => 'yii\db\conditions\BetweenColumnsConditionBuilder'];
    }
    /**
     * Setter for [[expressionBuilders]] property.
     *
     * @param string[] $builders array of builders that should be merged with the pre-defined ones
     * in [[expressionBuilders]] property.
     * @since 2.0.14
     * @see expressionBuilders
     */
    public function set_expression_builders($builders): void
    {
        $this->expression_builders = array_merge($this->expression_builders, $builders);
    }
    /**
     * Setter for [[conditionClasses]] property.
     *
     * @param string[] $classes map of condition aliases to condition classes. For example:
     *
     * ```
     * ['LIKE' => yii\db\condition\LikeCondition::class]
     * ```
     *
     * @since 2.0.14.2
     * @see conditionClasses
     */
    public function set_condition_classes($classes): void
    {
        $this->condition_classes = array_merge($this->condition_classes, $classes);
    }
    /**
     * Generates a SELECT SQL statement from a [[Query]] object.
     *
     * @param Query $query the [[Query]] object from which the SQL statement will be generated.
     * @param array $params the parameters to be bound to the generated SQL statement. These parameters will
     * be included in the result with the additional parameters generated during the query building process.
     * @return array the generated SQL statement (the first array element) and the corresponding
     * parameters to be bound to the SQL statement (the second array element). The parameters returned
     * include those provided in `$params`.
     */
    public function build($query, $params = []): array
    {
        $query = $query->prepare($this);
        $params = empty($params) ? $query->params : array_merge($params, $query->params);
        $clauses = [$this->build_select($query->select, $params, $query->distinct, $query->select_option), $this->build_from($query->from, $params), $this->build_join($query->join, $params), $this->build_where($query->where, $params), $this->build_group_by($query->group_by), $this->build_having($query->having, $params)];
        $sql = implode($this->separator, array_filter($clauses));
        $sql = $this->build_order_by_and_limit($sql, $query->order_by, $query->limit, $query->offset);
        if (!empty($query->order_by)) {
            foreach ($query->order_by as $expression) {
                if ($expression instanceof Expression_Interface) {
                    $this->build_expression($expression, $params);
                }
            }
        }
        if (!empty($query->group_by)) {
            foreach ($query->group_by as $expression) {
                if ($expression instanceof Expression_Interface) {
                    $this->build_expression($expression, $params);
                }
            }
        }
        $union = $this->build_union($query->union, $params);
        if ($union !== '') {
            $sql = "({$sql}){$this->separator}{$union}";
        }
        $with = $this->build_with_queries($query->with_queries, $params);
        if ($with !== '') {
            $sql = "{$with}{$this->separator}{$sql}";
        }
        return [$sql, $params];
    }
    /**
     * Builds given $expression
     *
     * @param ExpressionInterface $expression the expression to be built
     * @param array $params the parameters to be bound to the generated SQL statement. These parameters will
     * be included in the result with the additional parameters generated during the expression building process.
     * @return string the SQL statement that will not be neither quoted nor encoded before passing to DBMS
     * @throws InvalidArgumentException when $expression building is not supported by this QueryBuilder.
     * @see ExpressionBuilderInterface
     * @see expressionBuilders
     * @since 2.0.14
     * @see ExpressionInterface
     */
    public function build_expression(Expression_Interface $expression, array &$params = [])
    {
        $builder = $this->get_expression_builder($expression);
        return $builder->build($expression, $params);
    }
    /**
     * Gets object of [[ExpressionBuilderInterface]] that is suitable for $expression.
     * Uses [[expressionBuilders]] array to find a suitable builder class.
     *
     * @return ExpressionBuilderInterface
     * @throws InvalidArgumentException when $expression building is not supported by this QueryBuilder.
     * @since 2.0.14
     * @see expressionBuilders
     */
    public function get_expression_builder(Expression_Interface $expression)
    {
        $class_name = get_class($expression);
        if (!isset($this->expression_builders[$class_name])) {
            foreach (array_reverse($this->expression_builders) as $expression_class => $builder_class) {
                if (is_subclass_of($expression, $expression_class)) {
                    $this->expression_builders[$class_name] = $builder_class;
                    break;
                }
            }
            if (!isset($this->expression_builders[$class_name])) {
                throw new InvalidArgumentException('Expression of class ' . $class_name . ' can not be built in ' . get_class($this));
            }
        }
        if ($this->expression_builders[$class_name] === self::class) {
            /** @var $this&ExpressionBuilderInterface $result */
            $result = $this;
            return $result;
        }
        if (!is_object($this->expression_builders[$class_name])) {
            $this->expression_builders[$class_name] = new $this->expression_builders[$class_name]($this);
        }
        return $this->expression_builders[$class_name];
    }
    /**
     * Creates an INSERT SQL statement.
     * For example,
     * ```
     * $sql = $queryBuilder->insert('user', [
     *     'name' => 'Sam',
     *     'age' => 30,
     * ], $params);
     * ```
     * The method will properly escape the table and column names.
     *
     * @param string $table the table that new rows will be inserted into.
     * @param array|Query $columns the column data (name => value) to be inserted into the table or instance
     * of [[yii\db\Query|Query]] to perform INSERT INTO ... SELECT SQL statement.
     * Passing of [[yii\db\Query|Query]] is available since version 2.0.11.
     * @param array $params the binding parameters that will be generated by this method.
     * They should be bound to the DB command later.
     * @return string the INSERT SQL
     */
    public function insert($table, $columns, &$params): string
    {
        [$names, $placeholders, $values, $params] = $this->prepare_insert_values($table, $columns, $params);
        return 'INSERT INTO ' . $this->db->quote_table_name($table) . (!empty($names) ? ' (' . implode(', ', $names) . ')' : '') . (!empty($placeholders) ? ' VALUES (' . implode(', ', $placeholders) . ')' : $values);
    }
    /**
     * Prepares a `VALUES` part for an `INSERT` SQL statement.
     *
     * @param string $table the table that new rows will be inserted into.
     * @param array|Query $columns the column data (name => value) to be inserted into the table or instance
     * of [[yii\db\Query|Query]] to perform INSERT INTO ... SELECT SQL statement.
     * @param array $params the binding parameters that will be generated by this method.
     * They should be bound to the DB command later.
     * @return array array of column names, placeholders, values and params.
     * @since 2.0.14
     */
    protected function prepare_insert_values($table, $columns, $params = []): array
    {
        $schema = $this->db->get_schema();
        $table_schema = $schema->get_table_schema($table);
        $column_schemas = $table_schema !== null ? $table_schema->columns : [];
        $names = [];
        $placeholders = [];
        $values = ' DEFAULT VALUES';
        if ($columns instanceof Query) {
            [$names, $values, $params] = $this->prepare_insert_select_sub_query($columns, $schema, $params);
        } else {
            foreach ($columns as $name => $value) {
                $names[] = $schema->quote_column_name($name);
                $value = isset($column_schemas[$name]) ? $column_schemas[$name]->db_typecast($value) : $value;
                if ($value instanceof Expression_Interface) {
                    $placeholders[] = $this->build_expression($value, $params);
                } elseif ($value instanceof \yii\db\Query) {
                    [$sql, $params] = $this->build($value, $params);
                    $placeholders[] = "({$sql})";
                } else {
                    $placeholders[] = $this->bind_param($value, $params);
                }
            }
        }
        return [$names, $placeholders, $values, $params];
    }
    /**
     * Prepare select-subquery and field names for INSERT INTO ... SELECT SQL statement.
     *
     * @param Query $columns Object, which represents select query.
     * @param Schema $schema Schema object to quote column name.
     * @param array $params the parameters to be bound to the generated SQL statement. These parameters will
     * be included in the result with the additional parameters generated during the query building process.
     * @return array array of column names, values and params.
     * @throws InvalidArgumentException if query's select does not contain named parameters only.
     * @since 2.0.11
     */
    protected function prepare_insert_select_sub_query($columns, $schema, $params = []): array
    {
        if (!is_array($columns->select) || empty($columns->select) || in_array('*', $columns->select)) {
            throw new InvalidArgumentException('Expected select query object with enumerated (named) parameters');
        }
        [$values, $params] = $this->build($columns, $params);
        $names = [];
        $values = ' ' . $values;
        foreach ($columns->select as $title => $field) {
            if (is_string($title)) {
                $names[] = $schema->quote_column_name($title);
            } elseif (preg_match('/^(.*?)(?i:\s+as\s+|\s+)([\w\-_\.]+)$/', $field, $matches)) {
                $names[] = $schema->quote_column_name($matches[2]);
            } else {
                $names[] = $schema->quote_column_name($field);
            }
        }
        return [$names, $values, $params];
    }
    /**
     * Generates a batch INSERT SQL statement.
     *
     * For example,
     *
     * ```
     * $sql = $queryBuilder->batchInsert('user', ['name', 'age'], [
     *     ['Tom', 30],
     *     ['Jane', 20],
     *     ['Linda', 25],
     * ]);
     * ```
     *
     * Note that the values in each row must match the corresponding column names.
     *
     * The method will properly escape the column names, and quote the values to be inserted.
     *
     * @param string $table the table that new rows will be inserted into.
     * @param array $columns the column names
     * @param array|\Generator $rows the rows to be batch inserted into the table
     * @param array $params the binding parameters. This parameter exists since 2.0.14
     * @return string the batch INSERT SQL statement
     */
    public function batch_insert($table, array $columns, $rows, &$params = []): string
    {
        if (empty($rows)) {
            return '';
        }
        $schema = $this->db->get_schema();
        if (($table_schema = $schema->get_table_schema($table)) !== null) {
            $column_schemas = $table_schema->columns;
        } else {
            $column_schemas = [];
        }
        $values = [];
        foreach ($rows as $row) {
            $vs = [];
            foreach ($row as $i => $value) {
                if (isset($columns[$i], $column_schemas[$columns[$i]])) {
                    $value = $column_schemas[$columns[$i]]->db_typecast($value);
                }
                if (is_string($value)) {
                    $value = $schema->quote_value($value);
                } elseif (is_float($value)) {
                    // ensure type cast always has . as decimal separator in all locales
                    $value = String_Helper::float_to_string($value);
                } elseif ($value === false) {
                    $value = 0;
                } elseif ($value === null) {
                    $value = 'NULL';
                } elseif ($value instanceof Expression_Interface) {
                    $value = $this->build_expression($value, $params);
                }
                $vs[] = $value;
            }
            $values[] = '(' . implode(', ', $vs) . ')';
        }
        if (empty($values)) {
            return '';
        }
        foreach ($columns as $i => $name) {
            $columns[$i] = $schema->quote_column_name($name);
        }
        return 'INSERT INTO ' . $schema->quote_table_name($table) . ' (' . implode(', ', $columns) . ') VALUES ' . implode(', ', $values);
    }
    /**
     * Creates an SQL statement to insert rows into a database table if
     * they do not already exist (matching unique constraints),
     * or update them if they do.
     *
     * For example,
     *
     * ```
     * $sql = $queryBuilder->upsert('pages', [
     *     'name' => 'Front page',
     *     'url' => 'https://example.com/', // url is unique
     *     'visits' => 0,
     * ], [
     *     'visits' => new \yii\db\Expression('visits + 1'),
     * ], $params);
     * ```
     *
     * The method will properly escape the table and column names.
     *
     * @param string $table the table that new rows will be inserted into/updated in.
     * @param array|Query $insertColumns the column data (name => value) to be inserted into the table or instance
     * of [[Query]] to perform `INSERT INTO ... SELECT` SQL statement.
     * @param array|bool $updateColumns the column data (name => value) to be updated if they already exist.
     * If `true` is passed, the column data will be updated to match the insert column data.
     * If `false` is passed, no update will be performed if the column data already exists.
     * @param array $params the binding parameters that will be generated by this method.
     * They should be bound to the DB command later.
     * @return string the resulting SQL.
     * @throws NotSupportedException if this is not supported by the underlying DBMS.
     * @since 2.0.14
     */
    public function upsert($table, $insert_columns, $update_columns, &$params)
    {
        throw new Not_Supported_Exception($this->db->get_driver_name() . ' does not support upsert statements.');
    }
    /**
     * @param string $table
     * @param array|Query $insertColumns
     * @param array|bool $updateColumns
     * @param Constraint[] $constraints this parameter recieves a matched constraint list.
     * The constraints will be unique by their column names.
     * @since 2.0.14
     */
    protected function prepare_upsert_columns($table, $insert_columns, $update_columns, &$constraints = []): array
    {
        if ($insert_columns instanceof Query) {
            [$insert_names] = $this->prepare_insert_select_sub_query($insert_columns, $this->db->get_schema());
        } else {
            $insert_names = array_map([$this->db, 'quoteColumnName'], array_keys($insert_columns));
        }
        $unique_names = $this->get_table_unique_column_names($table, $insert_names, $constraints);
        $unique_names = array_map([$this->db, 'quoteColumnName'], $unique_names);
        if ($update_columns !== true) {
            return [$unique_names, $insert_names, null];
        }
        return [$unique_names, $insert_names, array_diff($insert_names, $unique_names)];
    }
    /**
     * Returns all column names belonging to constraints enforcing uniqueness (`PRIMARY KEY`, `UNIQUE INDEX`, etc.)
     * for the named table removing constraints which did not cover the specified column list.
     * The column list will be unique by column names.
     *
     * @param string $name table name. The table name may contain schema name if any. Do not quote the table name.
     * @param string[] $columns source column list.
     * @param Constraint[] $constraints this parameter optionally recieves a matched constraint list.
     * The constraints will be unique by their column names.
     * @return string[] column list.
     */
    private function get_table_unique_column_names($name, array $columns, &$constraints = []): array
    {
        $schema = $this->db->get_schema();
        if (!$schema instanceof Constraint_Finder_Interface) {
            return [];
        }
        $constraints = [];
        $primary_key = $schema->get_table_primary_key($name);
        if ($primary_key !== null) {
            $constraints[] = $primary_key;
        }
        foreach ($schema->get_table_indexes($name) as $constraint) {
            if ($constraint->is_unique) {
                $constraints[] = $constraint;
            }
        }
        $constraints = array_merge($constraints, $schema->get_table_uniques($name));
        // Remove duplicates
        $constraints = array_combine(array_map(function (Constraint $constraint) {
            $columns = $constraint->column_names;
            sort($columns, SORT_STRING);
            return json_encode($columns);
        }, $constraints), $constraints);
        $column_names = [];
        // Remove all constraints which do not cover the specified column list
        $constraints = array_values(array_filter($constraints, function (Constraint $constraint) use ($schema, $columns, &$column_names): bool {
            $constraint_column_names = array_map([$schema, 'quoteColumnName'], $constraint->column_names);
            $result = !array_diff($constraint_column_names, $columns);
            if ($result) {
                $column_names = array_merge($column_names, $constraint_column_names);
            }
            return $result;
        }));
        return array_unique($column_names);
    }
    /**
     * Creates an UPDATE SQL statement.
     *
     * For example,
     *
     * ```
     * $params = [];
     * $sql = $queryBuilder->update('user', ['status' => 1], 'age > 30', $params);
     * ```
     *
     * The method will properly escape the table and column names.
     *
     * @param string $table the table to be updated.
     * @param array $columns the column data (name => value) to be updated.
     * @param array|string $condition the condition that will be put in the WHERE part. Please
     * refer to [[Query::where()]] on how to specify condition.
     * @param array $params the binding parameters that will be modified by this method
     * so that they can be bound to the DB command later.
     * @return string the UPDATE SQL
     */
    public function update($table, $columns, $condition, &$params): string
    {
        [$lines, $params] = $this->prepare_update_sets($table, $columns, $params);
        $sql = 'UPDATE ' . $this->db->quote_table_name($table) . ' SET ' . implode(', ', $lines);
        $where = $this->build_where($condition, $params);
        return $where === '' ? $sql : $sql . ' ' . $where;
    }
    /**
     * Prepares a `SET` parts for an `UPDATE` SQL statement.
     * @param string $table the table to be updated.
     * @param array $columns the column data (name => value) to be updated.
     * @param array $params the binding parameters that will be modified by this method
     * so that they can be bound to the DB command later.
     * @return array an array `SET` parts for an `UPDATE` SQL statement (the first array element) and params (the second array element).
     * @since 2.0.14
     */
    protected function prepare_update_sets($table, $columns, $params = []): array
    {
        $table_schema = $this->db->get_table_schema($table);
        $column_schemas = $table_schema !== null ? $table_schema->columns : [];
        $sets = [];
        foreach ($columns as $name => $value) {
            $value = isset($column_schemas[$name]) ? $column_schemas[$name]->db_typecast($value) : $value;
            if ($value instanceof Expression_Interface) {
                $placeholder = $this->build_expression($value, $params);
            } else {
                $placeholder = $this->bind_param($value, $params);
            }
            $sets[] = $this->db->quote_column_name($name) . '=' . $placeholder;
        }
        return [$sets, $params];
    }
    /**
     * Creates a DELETE SQL statement.
     *
     * For example,
     *
     * ```
     * $sql = $queryBuilder->delete('user', 'status = 0');
     * ```
     *
     * The method will properly escape the table and column names.
     *
     * @param string $table the table where the data will be deleted from.
     * @param array|string $condition the condition that will be put in the WHERE part. Please
     * refer to [[Query::where()]] on how to specify condition.
     * @param array $params the binding parameters that will be modified by this method
     * so that they can be bound to the DB command later.
     * @return string the DELETE SQL
     */
    public function delete($table, $condition, &$params): string
    {
        $sql = 'DELETE FROM ' . $this->db->quote_table_name($table);
        $where = $this->build_where($condition, $params);
        return $where === '' ? $sql : $sql . ' ' . $where;
    }
    /**
     * Builds a SQL statement for creating a new DB table.
     *
     * The columns in the new table should be specified as name-definition pairs (e.g. 'name' => 'string'),
     * where name stands for a column name which will be properly quoted by the method, and definition
     * stands for the column type which must contain an abstract DB type.
     * The [[getColumnType()]] method will be invoked to convert any abstract type into a physical one.
     *
     * If a column is specified with definition only (e.g. 'PRIMARY KEY (name, type)'), it will be directly
     * inserted into the generated SQL.
     *
     * For example,
     *
     * ```
     * $sql = $queryBuilder->createTable('user', [
     *  'id' => 'pk',
     *  'name' => 'string',
     *  'age' => 'integer',
     *  'column_name double precision null default null', # definition only example
     * ]);
     * ```
     *
     * @param string $table the name of the table to be created. The name will be properly quoted by the method.
     * @param array $columns the columns (name => definition) in the new table.
     * @param string|null $options additional SQL fragment that will be appended to the generated SQL.
     * @return string the SQL statement for creating a new DB table.
     */
    public function create_table($table, $columns, $options = null): string
    {
        $cols = [];
        foreach ($columns as $name => $type) {
            if (is_string($name)) {
                $cols[] = "\t" . $this->db->quote_column_name($name) . ' ' . $this->get_column_type($type);
            } else {
                $cols[] = "\t" . $type;
            }
        }
        $sql = 'CREATE TABLE ' . $this->db->quote_table_name($table) . " (\n" . implode(",\n", $cols) . "\n)";
        return $options === null ? $sql : $sql . ' ' . $options;
    }
    /**
     * Builds a SQL statement for renaming a DB table.
     * @param string $oldName the table to be renamed. The name will be properly quoted by the method.
     * @param string $newName the new table name. The name will be properly quoted by the method.
     * @return string the SQL statement for renaming a DB table.
     */
    public function rename_table($old_name, $new_name): string
    {
        return 'RENAME TABLE ' . $this->db->quote_table_name($old_name) . ' TO ' . $this->db->quote_table_name($new_name);
    }
    /**
     * Builds a SQL statement for dropping a DB table.
     * @param string $table the table to be dropped. The name will be properly quoted by the method.
     * @return string the SQL statement for dropping a DB table.
     */
    public function drop_table($table): string
    {
        return 'DROP TABLE ' . $this->db->quote_table_name($table);
    }
    /**
     * Builds a SQL statement for adding a primary key constraint to an existing table.
     * @param string $name the name of the primary key constraint.
     * @param string $table the table that the primary key constraint will be added to.
     * @param string|array $columns comma separated string or array of columns that the primary key will consist of.
     * @return string the SQL statement for adding a primary key constraint to an existing table.
     */
    public function add_primary_key($name, $table, $columns): string
    {
        if (is_string($columns)) {
            $columns = preg_split('/\s*,\s*/', $columns, -1, PREG_SPLIT_NO_EMPTY);
        }
        foreach ($columns as $i => $col) {
            $columns[$i] = $this->db->quote_column_name($col);
        }
        return 'ALTER TABLE ' . $this->db->quote_table_name($table) . ' ADD CONSTRAINT ' . $this->db->quote_column_name($name) . ' PRIMARY KEY (' . implode(', ', $columns) . ')';
    }
    /**
     * Builds a SQL statement for removing a primary key constraint to an existing table.
     * @param string $name the name of the primary key constraint to be removed.
     * @param string $table the table that the primary key constraint will be removed from.
     * @return string the SQL statement for removing a primary key constraint from an existing table.
     */
    public function drop_primary_key($name, $table): string
    {
        return 'ALTER TABLE ' . $this->db->quote_table_name($table) . ' DROP CONSTRAINT ' . $this->db->quote_column_name($name);
    }
    /**
     * Builds a SQL statement for truncating a DB table.
     * @param string $table the table to be truncated. The name will be properly quoted by the method.
     * @return string the SQL statement for truncating a DB table.
     */
    public function truncate_table($table): string
    {
        return 'TRUNCATE TABLE ' . $this->db->quote_table_name($table);
    }
    /**
     * Builds a SQL statement for adding a new DB column.
     * @param string $table the table that the new column will be added to. The table name will be properly quoted by the method.
     * @param string $column the name of the new column. The name will be properly quoted by the method.
     * @param string $type the column type. The [[getColumnType()]] method will be invoked to convert abstract column type (if any)
     * into the physical one. Anything that is not recognized as abstract type will be kept in the generated SQL.
     * For example, 'string' will be turned into 'varchar(255)', while 'string not null' will become 'varchar(255) not null'.
     * @return string the SQL statement for adding a new column.
     */
    public function add_column($table, $column, $type): string
    {
        return 'ALTER TABLE ' . $this->db->quote_table_name($table) . ' ADD ' . $this->db->quote_column_name($column) . ' ' . $this->get_column_type($type);
    }
    /**
     * Builds a SQL statement for dropping a DB column.
     * @param string $table the table whose column is to be dropped. The name will be properly quoted by the method.
     * @param string $column the name of the column to be dropped. The name will be properly quoted by the method.
     * @return string the SQL statement for dropping a DB column.
     */
    public function drop_column($table, $column): string
    {
        return 'ALTER TABLE ' . $this->db->quote_table_name($table) . ' DROP COLUMN ' . $this->db->quote_column_name($column);
    }
    /**
     * Builds a SQL statement for renaming a column.
     * @param string $table the table whose column is to be renamed. The name will be properly quoted by the method.
     * @param string $oldName the old name of the column. The name will be properly quoted by the method.
     * @param string $newName the new name of the column. The name will be properly quoted by the method.
     * @return string the SQL statement for renaming a DB column.
     */
    public function rename_column($table, $old_name, $new_name): string
    {
        return 'ALTER TABLE ' . $this->db->quote_table_name($table) . ' RENAME COLUMN ' . $this->db->quote_column_name($old_name) . ' TO ' . $this->db->quote_column_name($new_name);
    }
    /**
     * Builds a SQL statement for changing the definition of a column.
     * @param string $table the table whose column is to be changed. The table name will be properly quoted by the method.
     * @param string $column the name of the column to be changed. The name will be properly quoted by the method.
     * @param string $type the new column type. The [[getColumnType()]] method will be invoked to convert abstract
     * column type (if any) into the physical one. Anything that is not recognized as abstract type will be kept
     * in the generated SQL. For example, 'string' will be turned into 'varchar(255)', while 'string not null'
     * will become 'varchar(255) not null'.
     * @return string the SQL statement for changing the definition of a column.
     */
    public function alter_column($table, $column, $type): string
    {
        return 'ALTER TABLE ' . $this->db->quote_table_name($table) . ' CHANGE ' . $this->db->quote_column_name($column) . ' ' . $this->db->quote_column_name($column) . ' ' . $this->get_column_type($type);
    }
    /**
     * Builds a SQL statement for adding a foreign key constraint to an existing table.
     * The method will properly quote the table and column names.
     * @param string $name the name of the foreign key constraint.
     * @param string $table the table that the foreign key constraint will be added to.
     * @param string|array $columns the name of the column to that the constraint will be added on.
     * If there are multiple columns, separate them with commas or use an array to represent them.
     * @param string $refTable the table that the foreign key references to.
     * @param string|array $refColumns the name of the column that the foreign key references to.
     * If there are multiple columns, separate them with commas or use an array to represent them.
     * @param string|null $delete the ON DELETE option. Most DBMS support these options: RESTRICT, CASCADE, NO ACTION, SET DEFAULT, SET NULL
     * @param string|null $update the ON UPDATE option. Most DBMS support these options: RESTRICT, CASCADE, NO ACTION, SET DEFAULT, SET NULL
     * @return string the SQL statement for adding a foreign key constraint to an existing table.
     */
    public function add_foreign_key($name, $table, $columns, $ref_table, $ref_columns, $delete = null, $update = null): string
    {
        $sql = 'ALTER TABLE ' . $this->db->quote_table_name($table) . ' ADD CONSTRAINT ' . $this->db->quote_column_name($name) . ' FOREIGN KEY (' . $this->build_columns($columns) . ')' . ' REFERENCES ' . $this->db->quote_table_name($ref_table) . ' (' . $this->build_columns($ref_columns) . ')';
        if ($delete !== null) {
            $sql .= ' ON DELETE ' . $delete;
        }
        if ($update !== null) {
            $sql .= ' ON UPDATE ' . $update;
        }
        return $sql;
    }
    /**
     * Builds a SQL statement for dropping a foreign key constraint.
     * @param string $name the name of the foreign key constraint to be dropped. The name will be properly quoted by the method.
     * @param string $table the table whose foreign is to be dropped. The name will be properly quoted by the method.
     * @return string the SQL statement for dropping a foreign key constraint.
     */
    public function drop_foreign_key($name, $table): string
    {
        return 'ALTER TABLE ' . $this->db->quote_table_name($table) . ' DROP CONSTRAINT ' . $this->db->quote_column_name($name);
    }
    /**
     * Builds a SQL statement for creating a new index.
     * @param string $name the name of the index. The name will be properly quoted by the method.
     * @param string $table the table that the new index will be created for. The table name will be properly quoted by the method.
     * @param string|array $columns the column(s) that should be included in the index. If there are multiple columns,
     * separate them with commas or use an array to represent them. Each column name will be properly quoted
     * by the method, unless a parenthesis is found in the name.
     * @param bool $unique whether to add UNIQUE constraint on the created index.
     * @return string the SQL statement for creating a new index.
     */
    public function create_index($name, $table, $columns, $unique = false): string
    {
        return ($unique ? 'CREATE UNIQUE INDEX ' : 'CREATE INDEX ') . $this->db->quote_table_name($name) . ' ON ' . $this->db->quote_table_name($table) . ' (' . $this->build_columns($columns) . ')';
    }
    /**
     * Builds a SQL statement for dropping an index.
     * @param string $name the name of the index to be dropped. The name will be properly quoted by the method.
     * @param string $table the table whose index is to be dropped. The name will be properly quoted by the method.
     * @return string the SQL statement for dropping an index.
     */
    public function drop_index($name, $table): string
    {
        return 'DROP INDEX ' . $this->db->quote_table_name($name) . ' ON ' . $this->db->quote_table_name($table);
    }
    /**
     * Creates a SQL command for adding an unique constraint to an existing table.
     * @param string $name the name of the unique constraint.
     * The name will be properly quoted by the method.
     * @param string $table the table that the unique constraint will be added to.
     * The name will be properly quoted by the method.
     * @param string|array $columns the name of the column to that the constraint will be added on.
     * If there are multiple columns, separate them with commas.
     * The name will be properly quoted by the method.
     * @return string the SQL statement for adding an unique constraint to an existing table.
     * @since 2.0.13
     */
    public function add_unique($name, $table, $columns): string
    {
        if (is_string($columns)) {
            $columns = preg_split('/\s*,\s*/', $columns, -1, PREG_SPLIT_NO_EMPTY);
        }
        foreach ($columns as $i => $col) {
            $columns[$i] = $this->db->quote_column_name($col);
        }
        return 'ALTER TABLE ' . $this->db->quote_table_name($table) . ' ADD CONSTRAINT ' . $this->db->quote_column_name($name) . ' UNIQUE (' . implode(', ', $columns) . ')';
    }
    /**
     * Creates a SQL command for dropping an unique constraint.
     * @param string $name the name of the unique constraint to be dropped.
     * The name will be properly quoted by the method.
     * @param string $table the table whose unique constraint is to be dropped.
     * The name will be properly quoted by the method.
     * @return string the SQL statement for dropping an unique constraint.
     * @since 2.0.13
     */
    public function drop_unique($name, $table): string
    {
        return 'ALTER TABLE ' . $this->db->quote_table_name($table) . ' DROP CONSTRAINT ' . $this->db->quote_column_name($name);
    }
    /**
     * Creates a SQL command for adding a check constraint to an existing table.
     * @param string $name the name of the check constraint.
     * The name will be properly quoted by the method.
     * @param string $table the table that the check constraint will be added to.
     * The name will be properly quoted by the method.
     * @param string $expression the SQL of the `CHECK` constraint.
     * @return string the SQL statement for adding a check constraint to an existing table.
     * @since 2.0.13
     */
    public function add_check($name, $table, $expression): string
    {
        return 'ALTER TABLE ' . $this->db->quote_table_name($table) . ' ADD CONSTRAINT ' . $this->db->quote_column_name($name) . ' CHECK (' . $this->db->quote_sql($expression) . ')';
    }
    /**
     * Creates a SQL command for dropping a check constraint.
     * @param string $name the name of the check constraint to be dropped.
     * The name will be properly quoted by the method.
     * @param string $table the table whose check constraint is to be dropped.
     * The name will be properly quoted by the method.
     * @return string the SQL statement for dropping a check constraint.
     * @since 2.0.13
     */
    public function drop_check($name, $table): string
    {
        return 'ALTER TABLE ' . $this->db->quote_table_name($table) . ' DROP CONSTRAINT ' . $this->db->quote_column_name($name);
    }
    /**
     * Creates a SQL command for adding a default value constraint to an existing table.
     * @param string $name the name of the default value constraint.
     * The name will be properly quoted by the method.
     * @param string $table the table that the default value constraint will be added to.
     * The name will be properly quoted by the method.
     * @param string $column the name of the column to that the constraint will be added on.
     * The name will be properly quoted by the method.
     * @param mixed $value default value.
     * @return string the SQL statement for adding a default value constraint to an existing table.
     * @throws NotSupportedException if this is not supported by the underlying DBMS.
     * @since 2.0.13
     */
    public function add_default_value($name, $table, $column, $value)
    {
        throw new Not_Supported_Exception($this->db->get_driver_name() . ' does not support adding default value constraints.');
    }
    /**
     * Creates a SQL command for dropping a default value constraint.
     * @param string $name the name of the default value constraint to be dropped.
     * The name will be properly quoted by the method.
     * @param string $table the table whose default value constraint is to be dropped.
     * The name will be properly quoted by the method.
     * @return string the SQL statement for dropping a default value constraint.
     * @throws NotSupportedException if this is not supported by the underlying DBMS.
     * @since 2.0.13
     */
    public function drop_default_value($name, $table)
    {
        throw new Not_Supported_Exception($this->db->get_driver_name() . ' does not support dropping default value constraints.');
    }
    /**
     * Creates a SQL statement for resetting the sequence value of a table's primary key.
     * The sequence will be reset such that the primary key of the next new row inserted
     * will have the specified value or the maximum existing value +1.
     * @param string $tableName the name of the table whose primary key sequence will be reset
     * @param array|string|null $value the value for the primary key of the next new row inserted. If this is not set,
     * the next new row's primary key will have the maximum existing value +1.
     * @return string the SQL statement for resetting sequence
     * @throws NotSupportedException if this is not supported by the underlying DBMS
     */
    public function reset_sequence($table_name, $value = null)
    {
        throw new Not_Supported_Exception($this->db->get_driver_name() . ' does not support resetting sequence.');
    }
    /**
     * Execute a SQL statement for resetting the sequence value of a table's primary key.
     * Reason for execute is that some databases (Oracle) need several queries to do so.
     * The sequence is reset such that the primary key of the next new row inserted
     * will have the specified value or the maximum existing value +1.
     * @param string $table the name of the table whose primary key sequence is reset
     * @param array|string|null $value the value for the primary key of the next new row inserted. If this is not set,
     * the next new row's primary key will have the maximum existing value +1.
     * @throws NotSupportedException if this is not supported by the underlying DBMS
     * @since 2.0.16
     */
    public function execute_reset_sequence($table, $value = null): void
    {
        $this->db->create_command()->reset_sequence($table, $value)->execute();
    }
    /**
     * Builds a SQL statement for enabling or disabling integrity check.
     * @param bool $check whether to turn on or off the integrity check.
     * @param string $schema the schema of the tables. Defaults to empty string, meaning the current or default schema.
     * @param string $table the table name. Defaults to empty string, meaning that no table will be changed.
     * @return string the SQL statement for checking integrity
     * @throws NotSupportedException if this is not supported by the underlying DBMS
     */
    public function check_integrity($check = true, $schema = '', $table = '')
    {
        throw new Not_Supported_Exception($this->db->get_driver_name() . ' does not support enabling/disabling integrity check.');
    }
    /**
     * Builds a SQL command for adding comment to column.
     *
     * @param string $table the table whose column is to be commented. The table name will be properly quoted by the method.
     * @param string $column the name of the column to be commented. The column name will be properly quoted by the method.
     * @param string $comment the text of the comment to be added. The comment will be properly quoted by the method.
     * @return string the SQL statement for adding comment on column
     * @since 2.0.8
     */
    public function add_comment_on_column($table, $column, $comment): string
    {
        return 'COMMENT ON COLUMN ' . $this->db->quote_table_name($table) . '.' . $this->db->quote_column_name($column) . ' IS ' . $this->db->quote_value($comment);
    }
    /**
     * Builds a SQL command for adding comment to table.
     *
     * @param string $table the table whose column is to be commented. The table name will be properly quoted by the method.
     * @param string $comment the text of the comment to be added. The comment will be properly quoted by the method.
     * @return string the SQL statement for adding comment on table
     * @since 2.0.8
     */
    public function add_comment_on_table($table, $comment): string
    {
        return 'COMMENT ON TABLE ' . $this->db->quote_table_name($table) . ' IS ' . $this->db->quote_value($comment);
    }
    /**
     * Builds a SQL command for adding comment to column.
     *
     * @param string $table the table whose column is to be commented. The table name will be properly quoted by the method.
     * @param string $column the name of the column to be commented. The column name will be properly quoted by the method.
     * @return string the SQL statement for adding comment on column
     * @since 2.0.8
     */
    public function drop_comment_from_column($table, $column): string
    {
        return 'COMMENT ON COLUMN ' . $this->db->quote_table_name($table) . '.' . $this->db->quote_column_name($column) . ' IS NULL';
    }
    /**
     * Builds a SQL command for adding comment to table.
     *
     * @param string $table the table whose column is to be commented. The table name will be properly quoted by the method.
     * @return string the SQL statement for adding comment on column
     * @since 2.0.8
     */
    public function drop_comment_from_table($table): string
    {
        return 'COMMENT ON TABLE ' . $this->db->quote_table_name($table) . ' IS NULL';
    }
    /**
     * Creates a SQL View.
     *
     * @param string $viewName the name of the view to be created.
     * @param string|Query $subQuery the select statement which defines the view.
     * This can be either a string or a [[Query]] object.
     * @return string the `CREATE VIEW` SQL statement.
     * @since 2.0.14
     */
    public function create_view($view_name, $sub_query): string
    {
        if ($sub_query instanceof Query) {
            [$raw_query, $params] = $this->build($sub_query);
            array_walk($params, function (&$param): void {
                $param = $this->db->quote_value($param);
            });
            $sub_query = strtr($raw_query, $params);
        }
        return 'CREATE VIEW ' . $this->db->quote_table_name($view_name) . ' AS ' . $sub_query;
    }
    /**
     * Drops a SQL View.
     *
     * @param string $viewName the name of the view to be dropped.
     * @return string the `DROP VIEW` SQL statement.
     * @since 2.0.14
     */
    public function drop_view($view_name): string
    {
        return 'DROP VIEW ' . $this->db->quote_table_name($view_name);
    }
    /**
     * Converts an abstract column type into a physical column type.
     *
     * The conversion is done using the type map specified in [[typeMap]].
     * The following abstract column types are supported (using MySQL as an example to explain the corresponding
     * physical types):
     *
     * - `pk`: an auto-incremental primary key type, will be converted into "int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY"
     * - `bigpk`: an auto-incremental primary key type, will be converted into "bigint(20) NOT NULL AUTO_INCREMENT PRIMARY KEY"
     * - `upk`: an unsigned auto-incremental primary key type, will be converted into "int(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY"
     * - `char`: char type, will be converted into "char(1)"
     * - `string`: string type, will be converted into "varchar(255)"
     * - `text`: a long string type, will be converted into "text"
     * - `smallint`: a small integer type, will be converted into "smallint(6)"
     * - `integer`: integer type, will be converted into "int(11)"
     * - `bigint`: a big integer type, will be converted into "bigint(20)"
     * - `boolean`: boolean type, will be converted into "tinyint(1)"
     * - `float``: float number type, will be converted into "float"
     * - `decimal`: decimal number type, will be converted into "decimal"
     * - `datetime`: datetime type, will be converted into "datetime"
     * - `timestamp`: timestamp type, will be converted into "timestamp"
     * - `time`: time type, will be converted into "time"
     * - `date`: date type, will be converted into "date"
     * - `money`: money type, will be converted into "decimal(19,4)"
     * - `binary`: binary data type, will be converted into "blob"
     *
     * If the abstract type contains two or more parts separated by spaces (e.g. "string NOT NULL"), then only
     * the first part will be converted, and the rest of the parts will be appended to the converted result.
     * For example, 'string NOT NULL' is converted to 'varchar(255) NOT NULL'.
     *
     * For some of the abstract types you can also specify a length or precision constraint
     * by appending it in round brackets directly to the type.
     * For example `string(32)` will be converted into "varchar(32)" on a MySQL database.
     * If the underlying DBMS does not support these kind of constraints for a type it will
     * be ignored.
     *
     * If a type cannot be found in [[typeMap]], it will be returned without any change.
     * @param string|ColumnSchemaBuilder $type abstract column type
     * @return string physical column type.
     */
    public function get_column_type($type)
    {
        if ($type instanceof Column_Schema_Builder) {
            $type = $type->__toString();
        }
        if (isset($this->type_map[$type])) {
            return $this->type_map[$type];
        }
        if (preg_match('/^(\w+)\((.+?)\)(.*)$/', $type, $matches)) {
            if (isset($this->type_map[$matches[1]])) {
                return preg_replace('/\(.+\)/', '(' . $matches[2] . ')', $this->type_map[$matches[1]]) . $matches[3];
            }
        } elseif (preg_match('/^(\w+)\s+/', $type, $matches)) {
            if (isset($this->type_map[$matches[1]])) {
                return preg_replace('/^\w+/', $this->type_map[$matches[1]], $type);
            }
        }
        return $type;
    }
    /**
     * @param array $columns
     * @param array $params the binding parameters to be populated
     * @param bool $distinct
     * @param string|null $selectOption
     * @return string the SELECT clause built from [[Query::$select]].
     */
    public function build_select($columns, &$params, $distinct = false, $select_option = null): string
    {
        $select = $distinct ? 'SELECT DISTINCT' : 'SELECT';
        if ($select_option !== null) {
            $select .= ' ' . $select_option;
        }
        if (empty($columns)) {
            return $select . ' *';
        }
        foreach ($columns as $i => $column) {
            if ($column instanceof Expression_Interface) {
                if (is_int($i)) {
                    $columns[$i] = $this->build_expression($column, $params);
                } else {
                    $columns[$i] = $this->build_expression($column, $params) . ' AS ' . $this->db->quote_column_name($i);
                }
            } elseif ($column instanceof Query) {
                [$sql, $params] = $this->build($column, $params);
                $columns[$i] = "({$sql}) AS " . $this->db->quote_column_name($i);
            } elseif (is_string($i) && $i !== $column) {
                if (strpos($column, '(') === false) {
                    $column = $this->db->quote_column_name($column);
                }
                $columns[$i] = "{$column} AS " . $this->db->quote_column_name($i);
            } elseif (strpos($column, '(') === false) {
                if (preg_match('/^(.*?)(?i:\s+as\s+|\s+)([\w\-_\.]+)$/', $column, $matches)) {
                    $columns[$i] = $this->db->quote_column_name($matches[1]) . ' AS ' . $this->db->quote_column_name($matches[2]);
                } else {
                    $columns[$i] = $this->db->quote_column_name($column);
                }
            }
        }
        return $select . ' ' . implode(', ', $columns);
    }
    /**
     * @param array $tables
     * @param array $params the binding parameters to be populated
     * @return string the FROM clause built from [[Query::$from]].
     */
    public function build_from($tables, &$params): string
    {
        if (empty($tables)) {
            return '';
        }
        $tables = $this->quote_table_names($tables, $params);
        return 'FROM ' . implode(', ', $tables);
    }
    /**
     * @param array $joins
     * @param array $params the binding parameters to be populated
     * @return string the JOIN clause built from [[Query::$join]].
     * @throws Exception if the $joins parameter is not in proper format
     */
    public function build_join($joins, &$params): string
    {
        if (empty($joins)) {
            return '';
        }
        foreach ($joins as $i => $join) {
            if (!is_array($join) || !isset($join[0], $join[1])) {
                throw new Exception('A join clause must be specified as an array of join type, join table, and optionally join condition.');
            }
            // 0:join type, 1:join table, 2:on-condition (optional)
            [$join_type, $table] = $join;
            $tables = $this->quote_table_names((array) $table, $params);
            $table = reset($tables);
            $joins[$i] = "{$join_type} {$table}";
            if (isset($join[2])) {
                $condition = $this->build_condition($join[2], $params);
                if ($condition !== '') {
                    $joins[$i] .= ' ON ' . $condition;
                }
            }
        }
        return implode($this->separator, $joins);
    }
    /**
     * Quotes table names passed.
     *
     * @param array $params
     */
    private function quote_table_names(array $tables, &$params): array
    {
        foreach ($tables as $i => $table) {
            if ($table instanceof Query) {
                [$sql, $params] = $this->build($table, $params);
                $tables[$i] = "({$sql}) " . $this->db->quote_table_name($i);
            } elseif (is_string($i)) {
                if (strpos($table, '(') === false) {
                    $table = $this->db->quote_table_name($table);
                }
                $tables[$i] = "{$table} " . $this->db->quote_table_name($i);
            } elseif (strpos($table, '(') === false) {
                if ($table_with_alias = $this->extract_alias($table)) {
                    // with alias
                    $tables[$i] = $this->db->quote_table_name($table_with_alias[1]) . ' ' . $this->db->quote_table_name($table_with_alias[2]);
                } else {
                    $tables[$i] = $this->db->quote_table_name($table);
                }
            }
        }
        return $tables;
    }
    /**
     * @param string|array $condition
     * @param array $params the binding parameters to be populated
     * @return string the WHERE clause built from [[Query::$where]].
     */
    public function build_where($condition, &$params): string
    {
        $where = $this->build_condition($condition, $params);
        return $where === '' ? '' : 'WHERE ' . $where;
    }
    /**
     * @param array $columns
     * @return string the GROUP BY clause
     */
    public function build_group_by($columns): string
    {
        if (empty($columns)) {
            return '';
        }
        foreach ($columns as $i => $column) {
            if ($column instanceof Expression_Interface) {
                $columns[$i] = $this->build_expression($column);
            } elseif (strpos($column, '(') === false) {
                $columns[$i] = $this->db->quote_column_name($column);
            }
        }
        return 'GROUP BY ' . implode(', ', $columns);
    }
    /**
     * @param string|array $condition
     * @param array $params the binding parameters to be populated
     * @return string the HAVING clause built from [[Query::$having]].
     */
    public function build_having($condition, &$params): string
    {
        $having = $this->build_condition($condition, $params);
        return $having === '' ? '' : 'HAVING ' . $having;
    }
    /**
     * Builds the ORDER BY and LIMIT/OFFSET clauses and appends them to the given SQL.
     * @param string $sql the existing SQL (without ORDER BY/LIMIT/OFFSET)
     * @param array $orderBy the order by columns. See [[Query::orderBy]] for more details on how to specify this parameter.
     * @param int $limit the limit number. See [[Query::limit]] for more details.
     * @param int $offset the offset number. See [[Query::offset]] for more details.
     * @return string the SQL completed with ORDER BY/LIMIT/OFFSET (if any)
     */
    public function build_order_by_and_limit(string $sql, $order_by, $limit, $offset): string
    {
        $order_by = $this->build_order_by($order_by);
        if ($order_by !== '') {
            $sql .= $this->separator . $order_by;
        }
        $limit = $this->build_limit($limit, $offset);
        if ($limit !== '') {
            $sql .= $this->separator . $limit;
        }
        return $sql;
    }
    /**
     * @param array $columns
     * @return string the ORDER BY clause built from [[Query::$orderBy]].
     */
    public function build_order_by($columns): string
    {
        if (empty($columns)) {
            return '';
        }
        $orders = [];
        foreach ($columns as $name => $direction) {
            if ($direction instanceof Expression_Interface) {
                $orders[] = $this->build_expression($direction);
            } else {
                $orders[] = $this->db->quote_column_name($name) . ($direction === SORT_DESC ? ' DESC' : '');
            }
        }
        return 'ORDER BY ' . implode(', ', $orders);
    }
    /**
     * @param int $limit
     * @param int $offset
     * @return string the LIMIT and OFFSET clauses
     */
    public function build_limit($limit, $offset): string
    {
        $sql = '';
        if ($this->has_limit($limit)) {
            $sql = 'LIMIT ' . $limit;
        }
        if ($this->has_offset($offset)) {
            $sql .= ' OFFSET ' . $offset;
        }
        return ltrim($sql);
    }
    /**
     * Checks to see if the given limit is effective.
     * @param mixed $limit the given limit
     * @return bool whether the limit is effective
     */
    protected function has_limit($limit): bool
    {
        return $limit instanceof Expression_Interface || ctype_digit((string) $limit);
    }
    /**
     * Checks to see if the given offset is effective.
     * @param mixed $offset the given offset
     * @return bool whether the offset is effective
     */
    protected function has_offset($offset): bool
    {
        return $offset instanceof Expression_Interface || ctype_digit((string) $offset) && (string) $offset !== '0';
    }
    /**
     * @param array $unions
     * @param array $params the binding parameters to be populated
     * @return string the UNION clause built from [[Query::$union]].
     */
    public function build_union($unions, &$params): string
    {
        if (empty($unions)) {
            return '';
        }
        $result = '';
        foreach ($unions as $i => $union) {
            $query = $union['query'];
            if ($query instanceof Query) {
                [$unions[$i]['query'], $params] = $this->build($query, $params);
            }
            $result .= 'UNION ' . ($union['all'] ? 'ALL ' : '') . '( ' . $unions[$i]['query'] . ' ) ';
        }
        return trim($result);
    }
    /**
     * @param array $withs of configurations for each WITH query
     * @param array $params the binding parameters to be populated
     * @return string compiled WITH prefix of query including nested queries
     * @see Query::withQuery()
     * @since 2.0.35
     */
    public function build_with_queries($withs, &$params): string
    {
        if (empty($withs)) {
            return '';
        }
        $recursive = false;
        $result = [];
        foreach ($withs as $with) {
            if ($with['recursive']) {
                $recursive = true;
            }
            $query = $with['query'];
            if ($query instanceof Query) {
                [$with['query'], $params] = $this->build($query, $params);
            }
            $result[] = $with['alias'] . ' AS (' . $with['query'] . ')';
        }
        return 'WITH ' . ($recursive ? 'RECURSIVE ' : '') . implode(', ', $result);
    }
    /**
     * Processes columns and properly quotes them if necessary.
     * It will join all columns into a string with comma as separators.
     * @param string|array $columns the columns to be processed
     * @return string the processing result
     */
    public function build_columns($columns): string
    {
        if (!is_array($columns)) {
            if (strpos($columns, '(') !== false) {
                return $columns;
            }
            $raw_columns = $columns;
            $columns = preg_split('/\s*,\s*/', $columns, -1, PREG_SPLIT_NO_EMPTY);
            if ($columns === false) {
                throw new InvalidArgumentException("{$raw_columns} is not valid columns.");
            }
        }
        foreach ($columns as $i => $column) {
            if ($column instanceof Expression_Interface) {
                $columns[$i] = $this->build_expression($column);
            } elseif (strpos($column, '(') === false) {
                $columns[$i] = $this->db->quote_column_name($column);
            }
        }
        return implode(', ', $columns);
    }
    /**
     * Parses the condition specification and generates the corresponding SQL expression.
     * @param string|array|ExpressionInterface $condition the condition specification. Please refer to [[Query::where()]]
     * on how to specify a condition.
     * @param array $params the binding parameters to be populated
     * @return string the generated SQL expression
     */
    public function build_condition($condition, &$params)
    {
        if (is_array($condition)) {
            if (empty($condition)) {
                return '';
            }
            $condition = $this->create_condition_from_array($condition);
        }
        if ($condition instanceof Expression_Interface) {
            return $this->build_expression($condition, $params);
        }
        return (string) $condition;
    }
    /**
     * Transforms $condition defined in array format (as described in [[Query::where()]]
     * to instance of [[yii\db\condition\ConditionInterface|ConditionInterface]] according to
     * [[conditionClasses]] map.
     *
     * @param string|array $condition
     * @return ConditionInterface
     * @see conditionClasses
     * @since 2.0.14
     */
    public function create_condition_from_array($condition)
    {
        if (isset($condition[0])) {
            // operator format: operator, operand 1, operand 2, ...
            $operator = strtoupper(array_shift($condition));
            if (isset($this->condition_classes[$operator])) {
                $class_name = $this->condition_classes[$operator];
            } else {
                $class_name = 'yii\db\conditions\SimpleCondition';
            }
            /** @var ConditionInterface $className */
            return $class_name::from_array_definition($operator, $condition);
        }
        // hash format: 'column1' => 'value1', 'column2' => 'value2', ...
        return new Hash_Condition($condition);
    }
    /**
     * Creates a condition based on column-value pairs.
     * @param array $condition the condition specification.
     * @param array $params the binding parameters to be populated
     * @return string the generated SQL expression
     * @deprecated since 2.0.14. Use `buildCondition()` instead.
     */
    public function build_hash_condition($condition, &$params)
    {
        return $this->build_condition(new Hash_Condition($condition), $params);
    }
    /**
     * Connects two or more SQL expressions with the `AND` or `OR` operator.
     * @param string $operator the operator to use for connecting the given operands
     * @param array $operands the SQL expressions to connect.
     * @param array $params the binding parameters to be populated
     * @return string the generated SQL expression
     * @deprecated since 2.0.14. Use `buildCondition()` instead.
     */
    public function build_and_condition($operator, $operands, &$params)
    {
        array_unshift($operands, $operator);
        return $this->build_condition($operands, $params);
    }
    /**
     * Inverts an SQL expressions with `NOT` operator.
     * @param string $operator the operator to use for connecting the given operands
     * @param array $operands the SQL expressions to connect.
     * @param array $params the binding parameters to be populated
     * @return string the generated SQL expression
     * @throws InvalidArgumentException if wrong number of operands have been given.
     * @deprecated since 2.0.14. Use `buildCondition()` instead.
     */
    public function build_not_condition($operator, $operands, &$params)
    {
        array_unshift($operands, $operator);
        return $this->build_condition($operands, $params);
    }
    /**
     * Creates an SQL expressions with the `BETWEEN` operator.
     * @param string $operator the operator to use (e.g. `BETWEEN` or `NOT BETWEEN`)
     * @param array $operands the first operand is the column name. The second and third operands
     * describe the interval that column value should be in.
     * @param array $params the binding parameters to be populated
     * @return string the generated SQL expression
     * @throws InvalidArgumentException if wrong number of operands have been given.
     * @deprecated since 2.0.14. Use `buildCondition()` instead.
     */
    public function build_between_condition($operator, $operands, &$params)
    {
        array_unshift($operands, $operator);
        return $this->build_condition($operands, $params);
    }
    /**
     * Creates an SQL expressions with the `IN` operator.
     * @param string $operator the operator to use (e.g. `IN` or `NOT IN`)
     * @param array $operands the first operand is the column name. If it is an array
     * a composite IN condition will be generated.
     * The second operand is an array of values that column value should be among.
     * If it is an empty array the generated expression will be a `false` value if
     * operator is `IN` and empty if operator is `NOT IN`.
     * @param array $params the binding parameters to be populated
     * @return string the generated SQL expression
     * @throws Exception if wrong number of operands have been given.
     * @deprecated since 2.0.14. Use `buildCondition()` instead.
     */
    public function build_in_condition($operator, $operands, &$params)
    {
        array_unshift($operands, $operator);
        return $this->build_condition($operands, $params);
    }
    /**
     * Creates an SQL expressions with the `LIKE` operator.
     * @param string $operator the operator to use (e.g. `LIKE`, `NOT LIKE`, `OR LIKE` or `OR NOT LIKE`)
     * @param array $operands an array of two or three operands
     *
     * - The first operand is the column name.
     * - The second operand is a single value or an array of values that column value
     *   should be compared with. If it is an empty array the generated expression will
     *   be a `false` value if operator is `LIKE` or `OR LIKE`, and empty if operator
     *   is `NOT LIKE` or `OR NOT LIKE`.
     * - An optional third operand can also be provided to specify how to escape special characters
     *   in the value(s). The operand should be an array of mappings from the special characters to their
     *   escaped counterparts. If this operand is not provided, a default escape mapping will be used.
     *   You may use `false` or an empty array to indicate the values are already escaped and no escape
     *   should be applied. Note that when using an escape mapping (or the third operand is not provided),
     *   the values will be automatically enclosed within a pair of percentage characters.
     * @param array $params the binding parameters to be populated
     * @return string the generated SQL expression
     * @throws InvalidArgumentException if wrong number of operands have been given.
     * @deprecated since 2.0.14. Use `buildCondition()` instead.
     */
    public function build_like_condition($operator, $operands, &$params)
    {
        array_unshift($operands, $operator);
        return $this->build_condition($operands, $params);
    }
    /**
     * Creates an SQL expressions with the `EXISTS` operator.
     * @param string $operator the operator to use (e.g. `EXISTS` or `NOT EXISTS`)
     * @param array $operands contains only one element which is a [[Query]] object representing the sub-query.
     * @param array $params the binding parameters to be populated
     * @return string the generated SQL expression
     * @throws InvalidArgumentException if the operand is not a [[Query]] object.
     * @deprecated since 2.0.14. Use `buildCondition()` instead.
     */
    public function build_exists_condition($operator, $operands, &$params)
    {
        array_unshift($operands, $operator);
        return $this->build_condition($operands, $params);
    }
    /**
     * Creates an SQL expressions like `"column" operator value`.
     * @param string $operator the operator to use. Anything could be used e.g. `>`, `<=`, etc.
     * @param array $operands contains two column names.
     * @param array $params the binding parameters to be populated
     * @return string the generated SQL expression
     * @throws InvalidArgumentException if wrong number of operands have been given.
     * @deprecated since 2.0.14. Use `buildCondition()` instead.
     */
    public function build_simple_condition($operator, $operands, &$params)
    {
        array_unshift($operands, $operator);
        return $this->build_condition($operands, $params);
    }
    /**
     * Creates a SELECT EXISTS() SQL statement.
     * @param string $rawSql the subquery in a raw form to select from.
     * @return string the SELECT EXISTS() SQL statement.
     * @since 2.0.8
     */
    public function select_exists(string $raw_sql): string
    {
        return 'SELECT EXISTS(' . $raw_sql . ')';
    }
    /**
     * Helper method to add $value to $params array using [[PARAM_PREFIX]].
     *
     * @param string|null $value
     * @param array $params passed by reference
     * @return string the placeholder name in $params array
     *
     * @since 2.0.14
     */
    public function bind_param($value, array &$params): string
    {
        $ph_name = self::PARAM_PREFIX . count($params);
        $params[$ph_name] = $value;
        return $ph_name;
    }
    /**
     * Extracts table alias if there is one or returns false
     * @param $table
     * @return bool|array
     * @since 2.0.24
     */
    protected function extract_alias($table)
    {
        if (preg_match('/^(.*?)(?i:\s+as|)\s+([^ ]+)$/', $table, $matches)) {
            return $matches;
        }
        return false;
    }
}