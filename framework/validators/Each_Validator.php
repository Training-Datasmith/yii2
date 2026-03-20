<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\validators;

use Yii;
use yii\base\Dynamic_Model;
use yii\base\Invalid_Config_Exception;
use yii\base\Model;
/**
 * EachValidator validates an array by checking each of its elements against an embedded validation rule.
 *
 * ```
 * class MyModel extends Model
 * {
 *     public $categoryIDs = [];
 *
 *     public function rules()
 *     {
 *         return [
 *             // checks if every category ID is an integer
 *             ['categoryIDs', 'each', 'rule' => ['integer']],
 *         ]
 *     }
 * }
 * ```
 *
 * > Note: This validator will not work with inline validation rules in case of usage outside the model scope,
 *   e.g. via [[validate()]] method.
 *
 * > Note: EachValidator is meant to be used only in basic cases, you should consider usage of tabular input,
 *   using several models for the more complex case.
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 2.0.4
 */
class Each_Validator extends Validator
{
    /**
     * @var array|Validator definition of the validation rule, which should be used on array values.
     * It should be specified in the same format as at [[\yii\base\Model::rules()]], except it should not
     * contain attribute list as the first element.
     * For example:
     *
     * ```
     * ['integer']
     * ['match', 'pattern' => '/[a-z]/is']
     * ```
     *
     * Please refer to [[\yii\base\Model::rules()]] for more details.
     */
    public $rule;
    /**
     * @var bool whether to use error message composed by validator declared via [[rule]] if its validation fails.
     * If enabled, error message specified for this validator itself will appear only if attribute value is not an array.
     * If disabled, own error message value will be used always.
     */
    public $allow_message_from_rule = true;
    /**
     * @var bool whether to stop validation once first error among attribute value elements is detected.
     * When enabled validation will produce single error message on attribute, when disabled - multiple
     * error messages mya appear: one per each invalid value.
     * Note that this option will affect only [[validateAttribute()]] value, while [[validateValue()]] will
     * not be affected.
     * @since 2.0.11
     */
    public $stop_on_first_error = true;
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
     * Creates validator object based on the validation rule specified in [[rule]].
     * @param Model|null $model model in which context validator should be created.
     * @param mixed|null $current value being currently validated.
     * @throws \yii\base\InvalidConfigException
     * @return Validator validator instance
     */
    private function create_embedded_validator($model = null, $current = null)
    {
        $rule = $this->rule;
        if ($rule instanceof Validator) {
            return $rule;
        }
        if (is_array($rule) && isset($rule[0])) {
            // validator type
            if (!is_object($model)) {
                $model = new Model();
                // mock up context model
            }
            $params = array_slice($rule, 1);
            $params['current'] = $current;
            return Validator::create_validator($rule[0], $model, $this->attributes, $params);
        }
        throw new Invalid_Config_Exception('Invalid validation rule: a rule must be an array specifying validator type.');
    }
    /**
     * {@inheritdoc}
     */
    public function validate_attribute($model, $attribute): void
    {
        $array_of_values = $model->{$attribute};
        if (!is_array($array_of_values) && !$array_of_values instanceof \ArrayAccess) {
            $this->add_error($model, $attribute, $this->message, []);
            return;
        }
        foreach ($array_of_values as $k => $v) {
            $dynamic_model = new Dynamic_Model($model->get_attributes());
            $dynamic_model->set_attribute_labels($model->attribute_labels());
            $dynamic_model->add_rule($attribute, $this->create_embedded_validator($model, $v));
            $dynamic_model->define_attribute($attribute, $v);
            $dynamic_model->validate();
            $array_of_values[$k] = $dynamic_model->{$attribute};
            // filtered values like 'trim'
            if (!$dynamic_model->has_errors($attribute)) {
                continue;
            }
            if ($this->allow_message_from_rule) {
                $validation_errors = $dynamic_model->get_errors($attribute);
                $model->add_errors([$attribute => $validation_errors]);
            } else {
                $this->add_error($model, $attribute, $this->message, ['value' => $v]);
            }
            if ($this->stop_on_first_error) {
                break;
            }
        }
        $model->{$attribute} = $array_of_values;
    }
    /**
     * {@inheritdoc}
     */
    protected function validate_value($value)
    {
        if (!is_array($value) && !$value instanceof \ArrayAccess) {
            return [$this->message, []];
        }
        $validator = $this->create_embedded_validator();
        foreach ($value as $v) {
            if ($validator->skip_on_empty && $validator->is_empty($v)) {
                continue;
            }
            $result = $validator->validate_value($v);
            if ($result !== null) {
                if ($this->allow_message_from_rule) {
                    $result[1]['value'] = $v;
                    return $result;
                }
                return [$this->message, ['value' => $v]];
            }
        }
        return null;
    }
}