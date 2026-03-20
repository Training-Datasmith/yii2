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
use yii\helpers\Html;
use yii\helpers\Json;
/**
 * CompareValidator compares the specified attribute value with another value.
 *
 * The value being compared with can be another attribute value
 * (specified via [[compareAttribute]]) or a constant (specified via
 * [[compareValue]]). When both are specified, the latter takes
 * precedence. If neither is specified, the attribute will be compared
 * with another attribute whose name is by appending "_repeat" to the source
 * attribute name.
 *
 * CompareValidator supports different comparison operators, specified
 * via the [[operator]] property.
 *
 * The default comparison function is based on string values, which means the values
 * are compared byte by byte. When comparing numbers, make sure to set the [[$type]]
 * to [[TYPE_NUMBER]] to enable numeric comparison.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Compare_Validator extends Validator
{
    /**
     * Constant for specifying the comparison [[type]] by numeric values.
     * @since 2.0.11
     * @see type
     */
    public const TYPE_STRING = 'string';
    /**
     * Constant for specifying the comparison [[type]] by numeric values.
     * @since 2.0.11
     * @see type
     */
    public const TYPE_NUMBER = 'number';
    /**
     * @var string the name of the attribute to be compared with. When both this property
     * and [[compareValue]] are set, the latter takes precedence. If neither is set,
     * it assumes the comparison is against another attribute whose name is formed by
     * appending '_repeat' to the attribute being validated. For example, if 'password' is
     * being validated, then the attribute to be compared would be 'password_repeat'.
     * @see compareValue
     */
    public $compare_attribute;
    /**
     * @var mixed the constant value to be compared with or an anonymous function
     * that returns the constant value. When both this property and
     * [[compareAttribute]] are set, this property takes precedence.
     * The signature of the anonymous function should be as follows,
     *
     * ```
     * function($model, $attribute) {
     *     // compute value to compare with
     *     return $value;
     * }
     * ```
     * @see compareAttribute
     */
    public $compare_value;
    /**
     * @var string the type of the values being compared. The follow types are supported:
     *
     * - [[TYPE_STRING|string]]: the values are being compared as strings. No conversion will be done before comparison.
     * - [[TYPE_NUMBER|number]]: the values are being compared as numbers. String values will be converted into numbers before comparison.
     */
    public $type = self::TYPE_STRING;
    /**
     * @var string the operator for comparison. The following operators are supported:
     *
     * - `==`: check if two values are equal. The comparison is done is non-strict mode.
     * - `===`: check if two values are equal. The comparison is done is strict mode.
     * - `!=`: check if two values are NOT equal. The comparison is done is non-strict mode.
     * - `!==`: check if two values are NOT equal. The comparison is done is strict mode.
     * - `>`: check if value being validated is greater than the value being compared with.
     * - `>=`: check if value being validated is greater than or equal to the value being compared with.
     * - `<`: check if value being validated is less than the value being compared with.
     * - `<=`: check if value being validated is less than or equal to the value being compared with.
     *
     * When you want to compare numbers, make sure to also set [[type]] to `number`.
     */
    public $operator = '==';
    /**
     * @var string the user-defined error message. It may contain the following placeholders which
     * will be replaced accordingly by the validator:
     *
     * - `{attribute}`: the label of the attribute being validated
     * - `{value}`: the value of the attribute being validated
     * - `{compareValue}`: the value or the attribute label to be compared with
     * - `{compareAttribute}`: the label of the attribute to be compared with
     * - `{compareValueOrAttribute}`: the value or the attribute label to be compared with
     */
    public $message;
    /**
     * {@inheritdoc}
     */
    public function init(): void
    {
        parent::init();
        if ($this->message === null) {
            switch ($this->operator) {
                case '==':
                case '===':
                    $this->message = Yii::t('yii', '{attribute} must be equal to "{compareValueOrAttribute}".');
                    break;
                case '!=':
                case '!==':
                    $this->message = Yii::t('yii', '{attribute} must not be equal to "{compareValueOrAttribute}".');
                    break;
                case '>':
                    $this->message = Yii::t('yii', '{attribute} must be greater than "{compareValueOrAttribute}".');
                    break;
                case '>=':
                    $this->message = Yii::t('yii', '{attribute} must be greater than or equal to "{compareValueOrAttribute}".');
                    break;
                case '<':
                    $this->message = Yii::t('yii', '{attribute} must be less than "{compareValueOrAttribute}".');
                    break;
                case '<=':
                    $this->message = Yii::t('yii', '{attribute} must be less than or equal to "{compareValueOrAttribute}".');
                    break;
                default:
                    throw new Invalid_Config_Exception("Unknown operator: {$this->operator}");
            }
        }
    }
    /**
     * {@inheritdoc}
     */
    public function validate_attribute($model, $attribute): void
    {
        $value = $model->{$attribute};
        if (is_array($value)) {
            $this->add_error($model, $attribute, Yii::t('yii', '{attribute} is invalid.'));
            return;
        }
        if ($this->compare_value !== null) {
            if ($this->compare_value instanceof \Closure) {
                $this->compare_value = call_user_func($this->compare_value, $model, $attribute);
            }
            $compare_label = $compare_value = $compare_value_or_attribute = $this->compare_value;
        } else {
            $compare_attribute = $this->compare_attribute ?? $attribute . '_repeat';
            $compare_value = $model->{$compare_attribute};
            $compare_label = $compare_value_or_attribute = $model->get_attribute_label($compare_attribute);
            if (!$this->skip_on_error && $model->has_errors($compare_attribute)) {
                $this->add_error($model, $attribute, Yii::t('yii', '{compareAttribute} is invalid.'), ['compareAttribute' => $compare_label]);
                return;
            }
        }
        if (!$this->compare_values($this->operator, $this->type, $value, $compare_value)) {
            $this->add_error($model, $attribute, $this->message, ['compareAttribute' => $compare_label, 'compareValue' => $compare_value, 'compareValueOrAttribute' => $compare_value_or_attribute]);
        }
    }
    /**
     * {@inheritdoc}
     */
    protected function validate_value($value): ?array
    {
        if ($this->compare_value === null) {
            throw new Invalid_Config_Exception('CompareValidator::compareValue must be set.');
        }
        if ($this->compare_value instanceof \Closure) {
            $this->compare_value = call_user_func($this->compare_value);
        }
        if (!$this->compare_values($this->operator, $this->type, $value, $this->compare_value)) {
            return [$this->message, ['compareAttribute' => $this->compare_value, 'compareValue' => $this->compare_value, 'compareValueOrAttribute' => $this->compare_value]];
        }
        return null;
    }
    /**
     * Compares two values with the specified operator.
     * @param string $operator the comparison operator
     * @param string $type the type of the values being compared
     * @param mixed $value the value being compared
     * @param mixed $compareValue another value being compared
     * @return bool whether the comparison using the specified operator is true.
     */
    protected function compare_values($operator, $type, $value, $compare_value)
    {
        if ($type === self::TYPE_NUMBER) {
            $value = (float) $value;
            $compare_value = (float) $compare_value;
        } else {
            $value = (string) $value;
            $compare_value = (string) $compare_value;
        }
        switch ($operator) {
            case '==':
                return $value == $compare_value;
            case '===':
                return $value === $compare_value;
            case '!=':
                return $value != $compare_value;
            case '!==':
                return $value !== $compare_value;
            case '>':
                return $value > $compare_value;
            case '>=':
                return $value >= $compare_value;
            case '<':
                return $value < $compare_value;
            case '<=':
                return $value <= $compare_value;
            default:
                return false;
        }
    }
    /**
     * {@inheritdoc}
     */
    public function client_validate_attribute($model, $attribute, $view): string
    {
        if ($this->compare_value != null && $this->compare_value instanceof \Closure) {
            $this->compare_value = call_user_func($this->compare_value);
        }
        Validation_Asset::register($view);
        $options = $this->get_client_options($model, $attribute);
        return 'yii.validation.compare(value, messages, ' . Json::html_encode($options) . ', $form);';
    }
    /**
     * {@inheritdoc}
     * @return mixed[]
     */
    public function get_client_options($model, $attribute): array
    {
        $options = ['operator' => $this->operator, 'type' => $this->type];
        if ($this->compare_value !== null) {
            $options['compareValue'] = $this->compare_value;
            $compare_label = $compare_value = $compare_value_or_attribute = $this->compare_value;
        } else {
            $compare_attribute = $this->compare_attribute ?? $attribute . '_repeat';
            $compare_value = $model->get_attribute_label($compare_attribute);
            $options['compareAttribute'] = Html::get_input_id($model, $compare_attribute);
            $options['compareAttributeName'] = Html::get_input_name($model, $compare_attribute);
            $compare_label = $compare_value_or_attribute = $model->get_attribute_label($compare_attribute);
        }
        if ($this->skip_on_empty) {
            $options['skipOnEmpty'] = 1;
        }
        $options['message'] = $this->format_message($this->message, ['attribute' => $model->get_attribute_label($attribute), 'compareAttribute' => $compare_label, 'compareValue' => $compare_value, 'compareValueOrAttribute' => $compare_value_or_attribute]);
        return $options;
    }
}