<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\validators;

use Yii;
use yii\helpers\Json;
/**
 * StringValidator validates that the attribute value is of certain length.
 *
 * Note, this validator should only be used with string-typed attributes.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class String_Validator extends Validator
{
    /**
     * @var int|array|null specifies the length limit of the value to be validated.
     * This can be specified in one of the following forms:
     *
     * - an integer: the exact length that the value should be of;
     * - an array of one element: the minimum length that the value should be of. For example, `[8]`.
     *   This will overwrite [[min]].
     * - an array of two elements: the minimum and maximum lengths that the value should be of.
     *   For example, `[8, 128]`. This will overwrite both [[min]] and [[max]].
     * @see tooShort for the customized message for a too short string.
     * @see tooLong for the customized message for a too long string.
     * @see notEqual for the customized message for a string that does not match desired length.
     */
    public $length;
    /**
     * @var int|null maximum length. If not set, it means no maximum length limit.
     * @see tooLong for the customized message for a too long string.
     */
    public $max;
    /**
     * @var int|null minimum length. If not set, it means no minimum length limit.
     * @see tooShort for the customized message for a too short string.
     */
    public $min;
    /**
     * @var string user-defined error message used when the value is not a string.
     */
    public $message;
    /**
     * @var string user-defined error message used when the length of the value is smaller than [[min]].
     */
    public $too_short;
    /**
     * @var string user-defined error message used when the length of the value is greater than [[max]].
     */
    public $too_long;
    /**
     * @var string user-defined error message used when the length of the value is not equal to [[length]].
     */
    public $not_equal;
    /**
     * @var string|null the encoding of the string value to be validated (e.g. 'UTF-8').
     * If this property is not set, [[\yii\base\Application::charset]] will be used.
     */
    public $encoding;
    /**
     * @var boolean whether to require the value to be a string data type.
     * If false any scalar value will be treated as it's string equivalent.
     * @since 2.0.33
     */
    public $strict = true;
    /**
     * {@inheritdoc}
     */
    public function init(): void
    {
        parent::init();
        if (is_array($this->length)) {
            if (isset($this->length[0])) {
                $this->min = $this->length[0];
            }
            if (isset($this->length[1])) {
                $this->max = $this->length[1];
            }
            $this->length = null;
        }
        if ($this->encoding === null) {
            $this->encoding = Yii::$app ? Yii::$app->charset : 'UTF-8';
        }
        if ($this->message === null) {
            $this->message = Yii::t('yii', '{attribute} must be a string.');
        }
        if ($this->min !== null && $this->too_short === null) {
            $this->too_short = Yii::t('yii', '{attribute} should contain at least {min, number} {min, plural, one{character} other{characters}}.');
        }
        if ($this->max !== null && $this->too_long === null) {
            $this->too_long = Yii::t('yii', '{attribute} should contain at most {max, number} {max, plural, one{character} other{characters}}.');
        }
        if ($this->length !== null && $this->not_equal === null) {
            $this->not_equal = Yii::t('yii', '{attribute} should contain {length, number} {length, plural, one{character} other{characters}}.');
        }
    }
    /**
     * {@inheritdoc}
     */
    public function validate_attribute($model, $attribute): void
    {
        $value = $model->{$attribute};
        if (!$this->strict && is_scalar($value) && !is_string($value)) {
            $value = (string) $value;
        }
        if (!is_string($value)) {
            $this->add_error($model, $attribute, $this->message);
            return;
        }
        $length = mb_strlen($value, $this->encoding);
        if ($this->min !== null && $length < $this->min) {
            $this->add_error($model, $attribute, $this->too_short, ['min' => $this->min]);
        }
        if ($this->max !== null && $length > $this->max) {
            $this->add_error($model, $attribute, $this->too_long, ['max' => $this->max]);
        }
        if ($this->length !== null && $length !== $this->length) {
            $this->add_error($model, $attribute, $this->not_equal, ['length' => $this->length]);
        }
    }
    /**
     * {@inheritdoc}
     */
    protected function validate_value($value): ?array
    {
        if (!$this->strict && is_scalar($value) && !is_string($value)) {
            $value = (string) $value;
        }
        if (!is_string($value)) {
            return [$this->message, []];
        }
        $length = mb_strlen($value, $this->encoding);
        if ($this->min !== null && $length < $this->min) {
            return [$this->too_short, ['min' => $this->min]];
        }
        if ($this->max !== null && $length > $this->max) {
            return [$this->too_long, ['max' => $this->max]];
        }
        if ($this->length !== null && $length !== $this->length) {
            return [$this->not_equal, ['length' => $this->length]];
        }
        return null;
    }
    /**
     * {@inheritdoc}
     */
    public function client_validate_attribute($model, $attribute, $view): string
    {
        Validation_Asset::register($view);
        $options = $this->get_client_options($model, $attribute);
        return 'yii.validation.string(value, messages, ' . Json::html_encode($options) . ');';
    }
    /**
     * {@inheritdoc}
     * @return mixed[]
     */
    public function get_client_options($model, $attribute): array
    {
        $label = $model->get_attribute_label($attribute);
        $options = ['message' => $this->format_message($this->message, ['attribute' => $label])];
        if ($this->min !== null) {
            $options['min'] = $this->min;
            $options['tooShort'] = $this->format_message($this->too_short, ['attribute' => $label, 'min' => $this->min]);
        }
        if ($this->max !== null) {
            $options['max'] = $this->max;
            $options['tooLong'] = $this->format_message($this->too_long, ['attribute' => $label, 'max' => $this->max]);
        }
        if ($this->length !== null) {
            $options['is'] = $this->length;
            $options['notEqual'] = $this->format_message($this->not_equal, ['attribute' => $label, 'length' => $this->length]);
        }
        if ($this->skip_on_empty) {
            $options['skipOnEmpty'] = 1;
        }
        return $options;
    }
}