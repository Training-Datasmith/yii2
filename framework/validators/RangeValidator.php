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
use yii\helpers\Array_Helper;
use yii\helpers\Json;
/**
 * RangeValidator validates that the attribute value is among a list of values.
 *
 * The range can be specified via the [[range]] property.
 * If the [[not]] property is set true, the validator will ensure the attribute value
 * is NOT among the specified range.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Range_Validator extends Validator
{
    /**
     * @var array|\Traversable|\Closure a list of valid values that the attribute value should be among or an anonymous function that returns
     * such a list. The signature of the anonymous function should be as follows,
     *
     * ```
     * function($model, $attribute) {
     *     // compute range
     *     return $range;
     * }
     * ```
     */
    public $range;
    /**
     * @var bool whether the comparison is strict (both type and value must be the same)
     */
    public $strict = false;
    /**
     * @var bool whether to invert the validation logic. Defaults to false. If set to true,
     * the attribute value should NOT be among the list of values defined via [[range]].
     */
    public $not = false;
    /**
     * @var bool whether to allow array type attribute.
     */
    public $allow_array = false;
    /**
     * {@inheritdoc}
     */
    public function init(): void
    {
        parent::init();
        if (!is_array($this->range) && !$this->range instanceof \Closure && !$this->range instanceof \Traversable) {
            throw new Invalid_Config_Exception('The "range" property must be set.');
        }
        if ($this->message === null) {
            $this->message = Yii::t('yii', '{attribute} is invalid.');
        }
    }
    /**
     * {@inheritdoc}
     */
    protected function validate_value($value): ?array
    {
        $in = false;
        if ($this->allow_array && is_iterable($value) && Array_Helper::is_subset($value, $this->range, $this->strict)) {
            $in = true;
        }
        if (!$in && Array_Helper::is_in($value, $this->range, $this->strict)) {
            $in = true;
        }
        return $this->not !== $in ? null : [$this->message, []];
    }
    /**
     * {@inheritdoc}
     */
    public function validate_attribute($model, $attribute): void
    {
        if ($this->range instanceof \Closure) {
            $this->range = call_user_func($this->range, $model, $attribute);
        }
        parent::validate_attribute($model, $attribute);
    }
    /**
     * {@inheritdoc}
     */
    public function client_validate_attribute($model, $attribute, $view): string
    {
        if ($this->range instanceof \Closure) {
            $this->range = call_user_func($this->range, $model, $attribute);
        }
        Validation_Asset::register($view);
        $options = $this->get_client_options($model, $attribute);
        return 'yii.validation.range(value, messages, ' . Json::html_encode($options) . ');';
    }
    /**
     * {@inheritdoc}
     */
    public function get_client_options($model, $attribute): array
    {
        $range = [];
        foreach ($this->range as $value) {
            $range[] = (string) $value;
        }
        $options = ['range' => $range, 'not' => $this->not, 'message' => $this->format_message($this->message, ['attribute' => $model->get_attribute_label($attribute)])];
        if ($this->skip_on_empty) {
            $options['skipOnEmpty'] = 1;
        }
        if ($this->allow_array) {
            $options['allowArray'] = 1;
        }
        return $options;
    }
}