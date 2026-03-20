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
use yii\helpers\String_Helper;
use yii\web\Js_Expression;
/**
 * NumberValidator validates that the attribute value is a number.
 *
 * The format of the number must match the regular expression specified in [[integerPattern]] or [[numberPattern]].
 * Optionally, you may configure the [[max]] and [[min]] properties to ensure the number
 * is within certain range.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Number_Validator extends Validator
{
    /**
     * @var bool whether to allow array type attribute. Defaults to false.
     * @since 2.0.42
     */
    public $allow_array = false;
    /**
     * @var bool whether the attribute value can only be an integer. Defaults to false.
     */
    public $integer_only = false;
    /**
     * @var int|float|null upper limit of the number. Defaults to null, meaning no upper limit.
     * @see tooBig for the customized message used when the number is too big.
     */
    public $max;
    /**
     * @var int|float|null lower limit of the number. Defaults to null, meaning no lower limit.
     * @see tooSmall for the customized message used when the number is too small.
     */
    public $min;
    /**
     * @var string user-defined error message used when the value is bigger than [[max]].
     */
    public $too_big;
    /**
     * @var string user-defined error message used when the value is smaller than [[min]].
     */
    public $too_small;
    /**
     * @var string the regular expression for matching integers.
     */
    public $integer_pattern = '/^[+-]?\d+$/';
    /**
     * @var string the regular expression for matching numbers. It defaults to a pattern
     * that matches floating numbers with optional exponential part (e.g. -1.23e-10).
     */
    public $number_pattern = '/^[-+]?[0-9]*\.?[0-9]+([eE][-+]?[0-9]+)?$/';
    /**
     * {@inheritdoc}
     */
    public function init(): void
    {
        parent::init();
        if ($this->message === null) {
            $this->message = $this->integer_only ? Yii::t('yii', '{attribute} must be an integer.') : Yii::t('yii', '{attribute} must be a number.');
        }
        if ($this->min !== null && $this->too_small === null) {
            $this->too_small = Yii::t('yii', '{attribute} must be no less than {min}.');
        }
        if ($this->max !== null && $this->too_big === null) {
            $this->too_big = Yii::t('yii', '{attribute} must be no greater than {max}.');
        }
    }
    /**
     * {@inheritdoc}
     */
    public function validate_attribute($model, $attribute): void
    {
        $value = $model->{$attribute};
        if (is_array($value) && !$this->allow_array) {
            $this->add_error($model, $attribute, $this->message);
            return;
        }
        $values = !is_array($value) ? [$value] : $value;
        foreach ($values as $value) {
            if ($this->is_not_number($value)) {
                $this->add_error($model, $attribute, $this->message);
                return;
            }
            $pattern = $this->integer_only ? $this->integer_pattern : $this->number_pattern;
            if (!preg_match($pattern, String_Helper::normalize_number($value))) {
                $this->add_error($model, $attribute, $this->message);
            }
            if ($this->min !== null && $value < $this->min) {
                $this->add_error($model, $attribute, $this->too_small, ['min' => $this->min]);
            }
            if ($this->max !== null && $value > $this->max) {
                $this->add_error($model, $attribute, $this->too_big, ['max' => $this->max]);
            }
        }
    }
    /**
     * {@inheritdoc}
     */
    protected function validate_value($value): ?array
    {
        if (is_array($value) && !$this->allow_array) {
            return [$this->message, []];
        }
        $values = !is_array($value) ? [$value] : $value;
        foreach ($values as $sample) {
            if ($this->is_not_number($sample)) {
                return [$this->message, []];
            }
            $pattern = $this->integer_only ? $this->integer_pattern : $this->number_pattern;
            if (!preg_match($pattern, String_Helper::normalize_number($sample))) {
                return [$this->message, []];
            }
            if ($this->min !== null && $sample < $this->min) {
                return [$this->too_small, ['min' => $this->min]];
            }
            if ($this->max !== null && $sample > $this->max) {
                return [$this->too_big, ['max' => $this->max]];
            }
        }
        return null;
    }
    /**
     * @param mixed $value the data value to be checked.
     */
    private function is_not_number($value): bool
    {
        return is_array($value) || is_bool($value) || is_object($value) && !method_exists($value, '__toString') || !is_object($value) && !is_scalar($value) && $value !== null;
    }
    /**
     * {@inheritdoc}
     */
    public function client_validate_attribute($model, $attribute, $view): string
    {
        Validation_Asset::register($view);
        $options = $this->get_client_options($model, $attribute);
        return 'yii.validation.number(value, messages, ' . Json::html_encode($options) . ');';
    }
    /**
     * {@inheritdoc}
     * @return mixed[]
     */
    public function get_client_options($model, $attribute): array
    {
        $label = $model->get_attribute_label($attribute);
        $options = ['pattern' => new Js_Expression($this->integer_only ? $this->integer_pattern : $this->number_pattern), 'message' => $this->format_message($this->message, ['attribute' => $label])];
        if ($this->min !== null) {
            // ensure numeric value to make javascript comparison equal to PHP comparison
            // https://github.com/yiisoft/yii2/issues/3118
            $options['min'] = is_string($this->min) ? (float) $this->min : $this->min;
            $options['tooSmall'] = $this->format_message($this->too_small, ['attribute' => $label, 'min' => $this->min]);
        }
        if ($this->max !== null) {
            // ensure numeric value to make javascript comparison equal to PHP comparison
            // https://github.com/yiisoft/yii2/issues/3118
            $options['max'] = is_string($this->max) ? (float) $this->max : $this->max;
            $options['tooBig'] = $this->format_message($this->too_big, ['attribute' => $label, 'max' => $this->max]);
        }
        if ($this->skip_on_empty) {
            $options['skipOnEmpty'] = 1;
        }
        return $options;
    }
}