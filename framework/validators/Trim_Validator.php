<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\validators;

use yii\helpers\Json;
/**
 * This class converts the attribute value(s) to string(s) and strip characters.
 *
 * @since 2.0.46
 */
class Trim_Validator extends Validator
{
    /**
     * @var string The list of characters to strip, with `..` can specify a range of characters.
     * For example, set '\/ ' to normalize path or namespace.
     */
    public $chars;
    /**
     * @var bool Whether the filter should be skipped if an array input is given.
     * If true and an array input is given, the filter will not be applied.
     */
    public $skip_on_array = false;
    /**
     * @inheritDoc
     */
    public $skip_on_empty = false;
    /**
     * @inheritDoc
     */
    public function validate_attribute($model, $attribute): void
    {
        $value = $model->{$attribute};
        if (!$this->skip_on_array || !is_array($value)) {
            $model->{$attribute} = is_array($value) ? array_map([$this, 'trimValue'], $value) : $this->trim_value($value);
        }
    }
    /**
     * Converts given value to string and strips declared characters.
     *
     * @param mixed $value the value to strip
     */
    protected function trim_value($value): string
    {
        return $this->is_empty($value) ? '' : trim((string) $value, $this->chars ?: " \n\r\t\v\x00");
    }
    /**
     * @inheritDoc
     */
    public function client_validate_attribute($model, $attribute, $view): ?string
    {
        if ($this->skip_on_array && is_array($model->{$attribute})) {
            return null;
        }
        Validation_Asset::register($view);
        $options = $this->get_client_options($model, $attribute);
        return 'value = yii.validation.trim($form, attribute, ' . Json::html_encode($options) . ', value);';
    }
    /**
     * @inheritDoc
     */
    public function get_client_options($model, $attribute): array
    {
        return ['skipOnArray' => (bool) $this->skip_on_array, 'skipOnEmpty' => (bool) $this->skip_on_empty, 'chars' => $this->chars ?: false];
    }
}