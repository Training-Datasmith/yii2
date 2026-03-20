<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\validators;

use yii\base\Invalid_Config_Exception;
use yii\helpers\Json;
/**
 * FilterValidator converts the attribute value according to a filter.
 *
 * FilterValidator is actually not a validator but a data processor.
 * It invokes the specified filter callback to process the attribute value
 * and save the processed value back to the attribute. The filter must be
 * a valid PHP callback with the following signature:
 *
 * ```
 * function foo($value) {
 *     // compute $newValue here
 *     return $newValue;
 * }
 * ```
 *
 * Many PHP functions qualify this signature (e.g. `trim()`).
 * If the callback function requires non-null argument (important since PHP 8.1)
 * remember to set [[skipOnEmpty]] to `true` otherwise you may trigger an error.
 *
 * To specify the filter, set [[filter]] property to be the callback.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Filter_Validator extends Validator
{
    /**
     * @var callable the filter. This can be a global function name, anonymous function, etc.
     * The function signature must be as follows,
     *
     * ```
     * function foo($value) {
     *     // compute $newValue here
     *     return $newValue;
     * }
     * ```
     */
    public $filter;
    /**
     * @var bool whether the filter should be skipped if an array input is given.
     * If true and an array input is given, the filter will not be applied.
     */
    public $skip_on_array = false;
    /**
     * @var bool this property is overwritten to be false so that this validator will
     * be applied when the value being validated is empty.
     */
    public $skip_on_empty = false;
    /**
     * {@inheritdoc}
     */
    public function init(): void
    {
        parent::init();
        if ($this->filter === null) {
            throw new Invalid_Config_Exception('The "filter" property must be set.');
        }
    }
    /**
     * {@inheritdoc}
     */
    public function validate_attribute($model, $attribute): void
    {
        $value = $model->{$attribute};
        if (!$this->skip_on_array || !is_array($value)) {
            $model->{$attribute} = call_user_func($this->filter, $value);
        }
    }
    /**
     * {@inheritdoc}
     */
    public function client_validate_attribute($model, $attribute, $view): ?string
    {
        if ($this->filter !== 'trim') {
            return null;
        }
        Validation_Asset::register($view);
        $options = $this->get_client_options($model, $attribute);
        return 'value = yii.validation.trim($form, attribute, ' . Json::html_encode($options) . ', value);';
    }
    /**
     * {@inheritdoc}
     * @return int[]
     */
    public function get_client_options($model, $attribute): array
    {
        $options = [];
        if ($this->skip_on_empty) {
            $options['skipOnEmpty'] = 1;
        }
        return $options;
    }
}