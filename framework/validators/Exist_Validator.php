<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\validators;

use Yii;
use yii\base\Invalid_Config_Exception;
use yii\base\Model;
use yii\db\Active_Query;
use yii\db\Active_Record;
use yii\db\Query_Interface;
/**
 * ExistValidator validates that the attribute value exists in a table.
 *
 * ExistValidator checks if the value being validated can be found in the table column specified by
 * the ActiveRecord class [[targetClass]] and the attribute [[targetAttribute]].
 * Since version 2.0.14 you can use more convenient attribute [[targetRelation]]
 *
 * This validator is often used to verify that a foreign key contains a value
 * that can be found in the foreign table.
 *
 * The following are examples of validation rules using this validator:
 *
 * ```
 * // a1 needs to exist
 * ['a1', 'exist']
 * // a1 needs to exist, but its value will use a2 to check for the existence
 * ['a1', 'exist', 'targetAttribute' => 'a2']
 * // a1 and a2 need to exist together, and they both will receive error message
 * [['a1', 'a2'], 'exist', 'targetAttribute' => ['a1', 'a2']]
 * // a1 and a2 need to exist together, only a1 will receive error message
 * ['a1', 'exist', 'targetAttribute' => ['a1', 'a2']]
 * // a1 needs to exist by checking the existence of both a2 and a3 (using a1 value)
 * ['a1', 'exist', 'targetAttribute' => ['a2', 'a1' => 'a3']]
 * // type_id needs to exist in the column "id" in the table defined in ProductType class
 * ['type_id', 'exist', 'targetClass' => ProductType::class, 'targetAttribute' => ['type_id' => 'id']],
 * // the same as the previous, but using already defined relation "type"
 * ['type_id', 'exist', 'targetRelation' => 'type'],
 * ```
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Exist_Validator extends Validator
{
    /**
     * @var string|null the name of the ActiveRecord class that should be used to validate the existence
     * of the current attribute value. If not set, it will use the ActiveRecord class of the attribute being validated.
     * @see targetAttribute
     */
    public $target_class;
    /**
     * @var string|array|null the name of the ActiveRecord attribute that should be used to
     * validate the existence of the current attribute value. If not set, it will use the name
     * of the attribute currently being validated. You may use an array to validate the existence
     * of multiple columns at the same time. The array key is the name of the attribute with the value to validate,
     * the array value is the name of the database field to search.
     */
    public $target_attribute;
    /**
     * @var string the name of the relation that should be used to validate the existence of the current attribute value
     * This param overwrites $targetClass and $targetAttribute
     * @since 2.0.14
     */
    public $target_relation;
    /**
     * @var string|array|\Closure additional filter to be applied to the DB query used to check the existence of the attribute value.
     * This can be a string or an array representing the additional query condition (refer to [[\yii\db\Query::where()]]
     * on the format of query condition), or an anonymous function with the signature `function ($query)`, where `$query`
     * is the [[\yii\db\Query|Query]] object that you can modify in the function.
     */
    public $filter;
    /**
     * @var bool whether to allow array type attribute.
     */
    public $allow_array = false;
    /**
     * @var string and|or define how target attributes are related
     * @since 2.0.11
     */
    public $target_attribute_junction = 'and';
    /**
     * @var bool whether this validator is forced to always use master DB
     * @since 2.0.14
     */
    public $force_master_db = true;
    /**
     * {@inheritdoc}
     */
    public function init(): void
    {
        parent::init();
        if ($this->message === null) {
            $this->message = Yii::t('yii', '{attribute} is invalid.');
        }
    }
    /**
     * {@inheritdoc}
     */
    public function validate_attribute($model, $attribute): void
    {
        if (!empty($this->target_relation)) {
            $this->check_target_relation_existence($model, $attribute);
        } else {
            $this->check_target_attribute_existence($model, $attribute);
        }
    }
    /**
     * Validates existence of the current attribute based on relation name
     * @param ActiveRecord $model the data model to be validated
     * @param string $attribute the name of the attribute to be validated.
     */
    private function check_target_relation_existence($model, $attribute): void
    {
        $exists = false;
        /** @var ActiveQuery<ActiveRecord> $relationQuery */
        $relation_query = $model->{'get' . ucfirst($this->target_relation)}();
        if ($this->filter instanceof \Closure) {
            call_user_func($this->filter, $relation_query);
        } elseif ($this->filter !== null) {
            $relation_query->and_where($this->filter);
        }
        $connection = $model::get_db();
        if ($this->force_master_db && method_exists($connection, 'useMaster')) {
            $exists = $connection->use_master(fn() => $relation_query->exists());
        } else {
            $exists = $relation_query->exists();
        }
        if (!$exists) {
            $this->add_error($model, $attribute, $this->message);
        }
    }
    /**
     * Validates existence of the current attribute based on targetAttribute
     * @param \yii\base\Model $model the data model to be validated
     * @param string $attribute the name of the attribute to be validated.
     */
    private function check_target_attribute_existence($model, $attribute): void
    {
        $target_attribute = $this->target_attribute ?? $attribute;
        if ($this->skip_on_error) {
            foreach ((array) $target_attribute as $k => $v) {
                if ($model->has_errors(is_int($k) ? $v : $k)) {
                    return;
                }
            }
        }
        $params = $this->prepare_conditions($target_attribute, $model, $attribute);
        $conditions = [$this->target_attribute_junction === 'or' ? 'or' : 'and'];
        if (!$this->allow_array) {
            foreach ($params as $key => $value) {
                if (is_array($value)) {
                    $this->add_error($model, $attribute, Yii::t('yii', '{attribute} is invalid.'));
                    return;
                }
                $conditions[] = [$key => $value];
            }
        } else {
            $conditions[] = $params;
        }
        $target_class = $this->get_target_class($model);
        $query = $this->create_query($target_class, $conditions);
        if (!$this->value_exists($target_class, $query, $model->{$attribute})) {
            $this->add_error($model, $attribute, $this->message);
        }
    }
    /**
     * Processes attributes' relations described in $targetAttribute parameter into conditions, compatible with
     * [[\yii\db\Query::where()|Query::where()]] key-value format.
     *
     * @param $targetAttribute array|string|null $attribute the name of the ActiveRecord attribute that should be used to
     * validate the existence of the current attribute value. If not set, it will use the name
     * of the attribute currently being validated. You may use an array to validate the existence
     * of multiple columns at the same time. The array key is the name of the attribute with the value to validate,
     * the array value is the name of the database field to search.
     * If the key and the value are the same, you can just specify the value.
     * @param \yii\base\Model $model the data model to be validated
     * @param string $attribute the name of the attribute to be validated in the $model
     * @return array conditions, compatible with [[\yii\db\Query::where()|Query::where()]] key-value format.
     * @throws InvalidConfigException
     */
    private function prepare_conditions($target_attribute, $model, $attribute)
    {
        if (is_array($target_attribute)) {
            if ($this->allow_array) {
                throw new Invalid_Config_Exception('The "targetAttribute" property must be configured as a string.');
            }
            $conditions = [];
            foreach ($target_attribute as $k => $v) {
                $conditions[$v] = is_int($k) ? $model->{$v} : $model->{$k};
            }
        } else {
            $conditions = [$target_attribute => $model->{$attribute}];
        }
        $target_model_class = $this->get_target_class($model);
        if (!is_subclass_of($target_model_class, 'yii\db\ActiveRecord')) {
            return $conditions;
        }
        /** @var ActiveRecord $targetModelClass */
        return $this->apply_table_alias($target_model_class::find(), $conditions);
    }
    /**
     * @param Model $model the data model to be validated
     * @return string Target class name
     */
    private function get_target_class($model)
    {
        return $this->target_class ?? get_class($model);
    }
    /**
     * {@inheritdoc}
     */
    protected function validate_value($value): ?array
    {
        if ($this->target_class === null) {
            throw new Invalid_Config_Exception('The "targetClass" property must be set.');
        }
        if (!is_string($this->target_attribute)) {
            throw new Invalid_Config_Exception('The "targetAttribute" property must be configured as a string.');
        }
        if (is_array($value) && !$this->allow_array) {
            return [$this->message, []];
        }
        $query = $this->create_query($this->target_class, [$this->target_attribute => $value]);
        return $this->value_exists($this->target_class, $query, $value) ? null : [$this->message, []];
    }
    /**
     * Check whether value exists in target table.
     *
     * @param string $targetClass the model
     * @param QueryInterface $query
     * @param mixed $value the value want to be checked
     * @return bool
     */
    private function value_exists($target_class, $query, $value)
    {
        $db = $target_class::get_db();
        if ($this->force_master_db && method_exists($db, 'useMaster')) {
            return $db->use_master(fn() => $this->query_value_exists($query, $value));
        }
        return $this->query_value_exists($query, $value);
    }
    /**
     * Run query to check if value exists.
     *
     * @param QueryInterface $query
     * @param mixed $value the value to be checked
     * @return bool
     */
    private function query_value_exists($query, $value)
    {
        if (is_array($value)) {
            return $query->count("DISTINCT [[{$this->target_attribute}]]") == count(array_unique($value));
        }
        return $query->exists();
    }
    /**
     * Creates a query instance with the given condition.
     * @param string $targetClass the target AR class
     * @param mixed $condition query condition
     * @return \yii\db\ActiveQueryInterface the query instance
     */
    protected function create_query($target_class, $condition)
    {
        /** @var \yii\db\ActiveRecordInterface $targetClass */
        $query = $target_class::find()->and_where($condition);
        if ($this->filter instanceof \Closure) {
            call_user_func($this->filter, $query);
        } elseif ($this->filter !== null) {
            $query->and_where($this->filter);
        }
        return $query;
    }
    /**
     * Returns conditions with alias.
     * @param ActiveQuery<ActiveRecord> $query
     * @param array $conditions array of condition, keys to be modified
     * @param string|null $alias set empty string for no apply alias. Set null for apply primary table alias
     */
    private function apply_table_alias($query, array $conditions, $alias = null): array
    {
        if ($alias === null) {
            $alias = array_keys($query->get_tables_used_in_from())[0];
        }
        $prefixed_conditions = [];
        foreach ($conditions as $column_name => $column_value) {
            if (strpos($column_name, '(') === false) {
                $prefixed_column = "{$alias}.[[" . preg_replace('/^' . preg_quote($alias, '/') . '\.(.*)$/', '$1', $column_name) . ']]';
            } else {
                // there is an expression, can't prefix it reliably
                $prefixed_column = $column_name;
            }
            $prefixed_conditions[$prefixed_column] = $column_value;
        }
        return $prefixed_conditions;
    }
}