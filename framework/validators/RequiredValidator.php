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
 * RequiredValidator validates that the specified attribute does not have null or empty value.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Required_Validator extends Validator
{
    /**
     * @var bool whether to skip this validator if the value being validated is empty.
     */
    public $skip_on_empty = false;
    /**
     * @var mixed the desired value that the attribute must have.
     * If this is null, the validator will validate that the specified attribute is not empty.
     * If this is set as a value that is not null, the validator will validate that
     * the attribute has a value that is the same as this property value.
     * Defaults to null.
     * @see strict
     */
    public $required_value;
    /**
     * @var bool whether the comparison between the attribute value and [[requiredValue]] is strict.
     * When this is true, both the values and types must match.
     * Defaults to false, meaning only the values need to match.
     *
     * Note that behavior for when [[requiredValue]] is null is the following:
     *
     * - In strict mode, the validator will check if the attribute value is null
     * - In non-strict mode validation will fail
     */
    public $strict = false;
    /**
     * @var string the user-defined error message. It may contain the following placeholders which
     * will be replaced accordingly by the validator:
     *
     * - `{attribute}`: the label of the attribute being validated
     * - `{value}`: the value of the attribute being validated
     * - `{requiredValue}`: the value of [[requiredValue]]
     */
    public $message;
    /**
     * {@inheritdoc}
     */
    public function init(): void
    {
        parent::init();
        if ($this->message === null) {
            $this->message = $this->required_value === null ? Yii::t('yii', '{attribute} cannot be blank.') : Yii::t('yii', '{attribute} must be "{requiredValue}".');
        }
    }
    /**
     * {@inheritdoc}
     */
    protected function validate_value($value): ?array
    {
        if ($this->required_value === null) {
            if ($this->strict && $value !== null || !$this->strict && !$this->is_empty(is_string($value) ? trim($value) : $value)) {
                return null;
            }
        } elseif (!$this->strict && $value == $this->required_value || $this->strict && $value === $this->required_value) {
            return null;
        }
        if ($this->required_value === null) {
            return [$this->message, []];
        }
        return [$this->message, ['requiredValue' => $this->required_value]];
    }
    /**
     * {@inheritdoc}
     */
    public function client_validate_attribute($model, $attribute, $view): string
    {
        Validation_Asset::register($view);
        $options = $this->get_client_options($model, $attribute);
        return 'yii.validation.required(value, messages, ' . Json::html_encode($options) . ');';
    }
    /**
     * {@inheritdoc}
     */
    public function get_client_options($model, $attribute): array
    {
        $options = [];
        if ($this->required_value !== null) {
            $options['message'] = $this->format_message($this->message, ['requiredValue' => $this->required_value]);
            $options['requiredValue'] = $this->required_value;
        } else {
            $options['message'] = $this->message;
        }
        if ($this->strict) {
            $options['strict'] = 1;
        }
        $options['message'] = $this->format_message($options['message'], ['attribute' => $model->get_attribute_label($attribute)]);
        return $options;
    }
}