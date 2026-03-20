<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\validators;

use Yii;
use yii\base\Model;
use yii\db\Active_Query;
use yii\db\Active_Query_Interface;
use yii\db\Active_Record;
use yii\db\Active_Record_Interface;
use yii\helpers\Inflector;
/**
 * UniqueValidator validates that the attribute value is unique in the specified database table.
 *
 * UniqueValidator checks if the value being validated is unique in the table column specified by
 * the ActiveRecord class [[targetClass]] and the attribute [[targetAttribute]].
 *
 * The following are examples of validation rules using this validator:
 *
 * ```
 * // a1 needs to be unique
 * ['a1', 'unique']
 * // a1 needs to be unique, but column a2 will be used to check the uniqueness of the a1 value
 * ['a1', 'unique', 'targetAttribute' => 'a2']
 * // a1 and a2 need to be unique together, and they both will receive error message
 * [['a1', 'a2'], 'unique', 'targetAttribute' => ['a1', 'a2']]
 * // a1 and a2 need to be unique together, only a1 will receive error message
 * ['a1', 'unique', 'targetAttribute' => ['a1', 'a2']]
 * // a1 needs to be unique by checking the uniqueness of both a2 and a3 (using a1 value)
 * ['a1', 'unique', 'targetAttribute' => ['a2', 'a1' => 'a3']]
 * ```
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Unique_Validator extends Validator
{
    /**
     * @var string|null the name of the ActiveRecord class that should be used to validate the uniqueness
     * of the current attribute value. If not set, it will use the ActiveRecord class of the attribute being validated.
     * @see targetAttribute
     */
    public $target_class;
    /**
     * @var string|array|null the name of the [[\yii\db\ActiveRecord|ActiveRecord]] attribute that should be used to
     * validate the uniqueness of the current attribute value. If not set, it will use the name
     * of the attribute currently being validated. You may use an array to validate the uniqueness
     * of multiple columns at the same time. The array values are the attributes that will be
     * used to validate the uniqueness, while the array keys are the attributes whose values are to be validated.
     */
    public $target_attribute;
    /**
     * @var string|array|\Closure additional filter to be applied to the DB query used to check the uniqueness of the attribute value.
     * This can be a string or an array representing the additional query condition (refer to [[\yii\db\Query::where()]]
     * on the format of query condition), or an anonymous function with the signature `function ($query)`, where `$query`
     * is the [[\yii\db\Query|Query]] object that you can modify in the function.
     */
    public $filter;
    /**
     * @var string the user-defined error message.
     *
     * When validating single attribute, it may contain
     * the following placeholders which will be replaced accordingly by the validator:
     *
     * - `{attribute}`: the label of the attribute being validated
     * - `{value}`: the value of the attribute being validated
     *
     * When validating mutliple attributes, it may contain the following placeholders:
     *
     * - `{attributes}`: the labels of the attributes being validated.
     * - `{values}`: the values of the attributes being validated.
     */
    public $message;
    /**
     * @var string
     * @since 2.0.9
     * @deprecated since version 2.0.10, to be removed in 2.1. Use [[message]] property
     * to setup custom message for multiple target attributes.
     */
    public $combo_not_unique;
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
        if ($this->message !== null) {
            return;
        }
        if (is_array($this->target_attribute) && count($this->target_attribute) > 1) {
            // fallback for deprecated `comboNotUnique` property - use it as message if is set
            if ($this->combo_not_unique === null) {
                $this->message = Yii::t('yii', 'The combination {values} of {attributes} has already been taken.');
            } else {
                $this->message = $this->combo_not_unique;
            }
        } else {
            $this->message = Yii::t('yii', '{attribute} "{value}" has already been taken.');
        }
    }
    /**
     * {@inheritdoc}
     */
    public function validate_attribute($model, $attribute): void
    {
        $target_attribute = $this->target_attribute ?? $attribute;
        if ($this->skip_on_error) {
            foreach ((array) $target_attribute as $k => $v) {
                if ($model->has_errors(is_int($k) ? $v : $k)) {
                    return;
                }
            }
        }
        $raw_conditions = $this->prepare_conditions($target_attribute, $model, $attribute);
        $conditions = [$this->target_attribute_junction === 'or' ? 'or' : 'and'];
        foreach ($raw_conditions as $key => $value) {
            if (is_array($value)) {
                $this->add_error($model, $attribute, Yii::t('yii', '{attribute} is invalid.'));
                return;
            }
            $conditions[] = [$key => $value];
        }
        /** @var ActiveRecordInterface $targetClass */
        $target_class = $this->get_target_class($model);
        $db = $target_class::get_db();
        $model_exists = false;
        if ($this->force_master_db && method_exists($db, 'useMaster')) {
            $db->use_master(function () use ($target_class, $conditions, $model, &$model_exists): void {
                $model_exists = $this->model_exists($target_class, $conditions, $model);
            });
        } else {
            $model_exists = $this->model_exists($target_class, $conditions, $model);
        }
        if ($model_exists) {
            if (is_array($target_attribute) && count($target_attribute) > 1) {
                $this->add_combo_not_unique_error($model, $attribute);
            } else {
                $this->add_error($model, $attribute, $this->message);
            }
        }
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
     * Checks whether the $model exists in the database.
     *
     * @param string $targetClass the name of the ActiveRecord class that should be used to validate the uniqueness
     * of the current attribute value.
     * @param array $conditions conditions, compatible with [[\yii\db\Query::where()|Query::where()]] key-value format.
     * @param Model $model the data model to be validated
     *
     * @return bool whether the model already exists
     */
    private function model_exists($target_class, array $conditions, $model)
    {
        /** @var ActiveRecordInterface|\yii\base\BaseObject $targetClass $query */
        $query = $this->prepare_query($target_class, $conditions);
        if (!$model instanceof Active_Record_Interface || $model->get_is_new_record() || $model::class_name() !== $target_class::class_name()) {
            // if current $model isn't in the database yet, then it's OK just to call exists()
            // also there's no need to run check based on primary keys, when $targetClass is not the same as $model's class
            $exists = $query->exists();
        } else {
            // if current $model is in the database already we can't use exists()
            if ($query instanceof \yii\db\Active_Query) {
                // only select primary key to optimize query
                $columns_condition = array_flip($target_class::primary_key());
                $query->select(array_flip($this->apply_table_alias($query, $columns_condition)));
                // any with relation can't be loaded because related fields are not selected
                $query->with = null;
                if (is_array($query->join_with)) {
                    // any joinWiths need to have eagerLoading turned off to prevent related fields being loaded
                    foreach ($query->join_with as &$join_with) {
                        // \yii\db\ActiveQuery::joinWith adds eagerLoading at key 1
                        $join_with[1] = false;
                    }
                    unset($join_with);
                }
            }
            $models = $query->limit(2)->as_array()->all();
            $n = count($models);
            if ($n === 1) {
                // if there is one record, check if it is the currently validated model
                $db_model = reset($models);
                $pks = $target_class::primary_key();
                $pk = [];
                foreach ($pks as $pk_attribute) {
                    $pk[$pk_attribute] = $db_model[$pk_attribute];
                }
                $exists = $pk != $model->get_old_primary_key(true);
            } else {
                // if there is more than one record, the value is not unique
                $exists = $n > 1;
            }
        }
        return $exists;
    }
    /**
     * Prepares a query by applying filtering conditions defined in $conditions method property
     * and [[filter]] class property.
     *
     * @param ActiveRecordInterface $targetClass the name of the ActiveRecord class that should be used to validate
     * the uniqueness of the current attribute value.
     * @param array $conditions conditions, compatible with [[\yii\db\Query::where()|Query::where()]] key-value format
     * @return ActiveQueryInterface|ActiveQuery<ActiveRecord>
     */
    private function prepare_query($target_class, $conditions)
    {
        $query = $target_class::find();
        $query->and_where($conditions);
        if ($this->filter instanceof \Closure) {
            call_user_func($this->filter, $query);
        } elseif ($this->filter !== null) {
            $query->and_where($this->filter);
        }
        return $query;
    }
    /**
     * Processes attributes' relations described in $targetAttribute parameter into conditions, compatible with
     * [[\yii\db\Query::where()|Query::where()]] key-value format.
     *
     * @param string|array $targetAttribute the name of the [[\yii\db\ActiveRecord|ActiveRecord]] attribute that
     * should be used to validate the uniqueness of the current attribute value. You may use an array to validate
     * the uniqueness of multiple columns at the same time. The array values are the attributes that will be
     * used to validate the uniqueness, while the array keys are the attributes whose values are to be validated.
     * If the key and the value are the same, you can just specify the value.
     * @param Model $model the data model to be validated
     * @param string $attribute the name of the attribute to be validated in the $model
     *
     * @return array conditions, compatible with [[\yii\db\Query::where()|Query::where()]] key-value format.
     */
    private function prepare_conditions($target_attribute, $model, $attribute)
    {
        if (is_array($target_attribute)) {
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
     * Builds and adds [[comboNotUnique]] error message to the specified model attribute.
     * @param \yii\base\Model $model the data model.
     * @param string $attribute the name of the attribute.
     */
    private function add_combo_not_unique_error($model, $attribute): void
    {
        $attribute_combo = [];
        $value_combo = [];
        foreach ($this->target_attribute as $key => $value) {
            if (is_int($key)) {
                $attribute_combo[] = $model->get_attribute_label($value);
                $value_combo[] = '"' . $model->{$value} . '"';
            } else {
                $attribute_combo[] = $model->get_attribute_label($key);
                $value_combo[] = '"' . $model->{$key} . '"';
            }
        }
        $this->add_error($model, $attribute, $this->message, ['attributes' => Inflector::sentence($attribute_combo), 'values' => implode('-', $value_combo)]);
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
                $column_name = preg_replace('/^' . preg_quote($alias, '/') . '\.(.*)$/', '$1', $column_name);
                if (strncmp($column_name, '[[', 2) === 0) {
                    $prefixed_column = "{$alias}.{$column_name}";
                } else {
                    $prefixed_column = "{$alias}.[[{$column_name}]]";
                }
            } else {
                // there is an expression, can't prefix it reliably
                $prefixed_column = $column_name;
            }
            $prefixed_conditions[$prefixed_column] = $column_value;
        }
        return $prefixed_conditions;
    }
}