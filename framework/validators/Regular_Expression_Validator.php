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
use yii\web\Js_Expression;
/**
 * RegularExpressionValidator validates that the attribute value matches the specified [[pattern]].
 *
 * If the [[not]] property is set true, the validator will ensure the attribute value do NOT match the [[pattern]].
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Regular_Expression_Validator extends Validator
{
    /**
     * @var string the regular expression to be matched with
     */
    public $pattern;
    /**
     * @var bool whether to invert the validation logic. Defaults to false. If set to true,
     * the regular expression defined via [[pattern]] should NOT match the attribute value.
     */
    public $not = false;
    /**
     * {@inheritdoc}
     */
    public function init(): void
    {
        parent::init();
        if ($this->pattern === null) {
            throw new Invalid_Config_Exception('The "pattern" property must be set.');
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
        $valid = !is_array($value) && (!$this->not && preg_match($this->pattern, $value) || $this->not && !preg_match($this->pattern, $value));
        return $valid ? null : [$this->message, []];
    }
    /**
     * {@inheritdoc}
     */
    public function client_validate_attribute($model, $attribute, $view): string
    {
        Validation_Asset::register($view);
        $options = $this->get_client_options($model, $attribute);
        return 'yii.validation.regularExpression(value, messages, ' . Json::html_encode($options) . ');';
    }
    /**
     * {@inheritdoc}
     */
    public function get_client_options($model, $attribute): array
    {
        $pattern = Html::escape_js_regular_expression($this->pattern);
        $options = ['pattern' => new Js_Expression($pattern), 'not' => $this->not, 'message' => $this->format_message($this->message, ['attribute' => $model->get_attribute_label($attribute)])];
        if ($this->skip_on_empty) {
            $options['skipOnEmpty'] = 1;
        }
        return $options;
    }
}