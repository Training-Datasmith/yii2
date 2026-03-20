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
 * BooleanValidator checks if the attribute value is a boolean value.
 *
 * Possible boolean values can be configured via the [[trueValue]] and [[falseValue]] properties.
 * And the comparison can be either [[strict]] or not.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Boolean_Validator extends Validator
{
    /**
     * @var mixed the value representing true status. Defaults to '1'.
     */
    public $true_value = '1';
    /**
     * @var mixed the value representing false status. Defaults to '0'.
     */
    public $false_value = '0';
    /**
     * @var bool whether the comparison to [[trueValue]] and [[falseValue]] is strict.
     * When this is true, the attribute value and type must both match those of [[trueValue]] or [[falseValue]].
     * Defaults to false, meaning only the value needs to be matched.
     */
    public $strict = false;
    /**
     * {@inheritdoc}
     */
    public function init(): void
    {
        parent::init();
        if ($this->message === null) {
            $this->message = Yii::t('yii', '{attribute} must be either "{true}" or "{false}".');
        }
    }
    /**
     * {@inheritdoc}
     */
    protected function validate_value($value): ?array
    {
        if ($this->strict) {
            $valid = $value === $this->true_value || $value === $this->false_value;
        } else {
            $valid = $value == $this->true_value || $value == $this->false_value;
        }
        if (!$valid) {
            return [$this->message, ['true' => $this->true_value === true ? 'true' : $this->true_value, 'false' => $this->false_value === false ? 'false' : $this->false_value]];
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
        return 'yii.validation.boolean(value, messages, ' . Json::html_encode($options) . ');';
    }
    /**
     * {@inheritdoc}
     */
    public function get_client_options($model, $attribute): array
    {
        $options = ['trueValue' => $this->true_value, 'falseValue' => $this->false_value, 'message' => $this->format_message($this->message, ['attribute' => $model->get_attribute_label($attribute), 'true' => $this->true_value === true ? 'true' : $this->true_value, 'false' => $this->false_value === false ? 'false' : $this->false_value])];
        if ($this->skip_on_empty) {
            $options['skipOnEmpty'] = 1;
        }
        if ($this->strict) {
            $options['strict'] = 1;
        }
        return $options;
    }
}