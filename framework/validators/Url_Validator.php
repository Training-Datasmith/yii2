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
use yii\helpers\Json;
use yii\web\Js_Expression;
/**
 * UrlValidator validates that the attribute value is a valid http or https URL.
 *
 * Note that this validator only checks if the URL scheme and host part are correct.
 * It does not check the remaining parts of a URL.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Url_Validator extends Validator
{
    /**
     * @var string the regular expression used to validate the attribute value.
     * The pattern may contain a `{schemes}` token that will be replaced
     * by a regular expression which represents the [[validSchemes]].
     */
    public $pattern = '/^{schemes}:\/\/(([A-Z0-9][A-Z0-9_-]*)(\.[A-Z0-9][A-Z0-9_-]*)+)(?::\d{1,5})?(?:$|[?\/#])/i';
    /**
     * @var array list of URI schemes which should be considered valid. By default, http and https
     * are considered to be valid schemes.
     */
    public $valid_schemes = ['http', 'https'];
    /**
     * @var string|null the default URI scheme. If the input doesn't contain the scheme part, the default
     * scheme will be prepended to it (thus changing the input). Defaults to null, meaning a URL must
     * contain the scheme part.
     */
    public $default_scheme;
    /**
     * @var bool whether validation process should take into account IDN (internationalized
     * domain names). Defaults to false meaning that validation of URLs containing IDN will always
     * fail. Note that in order to use IDN validation you have to install and enable `intl` PHP
     * extension, otherwise an exception would be thrown.
     */
    public $enable_idn = false;
    /**
     * {@inheritdoc}
     */
    public function init(): void
    {
        parent::init();
        if ($this->enable_idn && !function_exists('idn_to_ascii')) {
            throw new Invalid_Config_Exception('In order to use IDN validation intl extension must be installed and enabled.');
        }
        if ($this->message === null) {
            $this->message = Yii::t('yii', '{attribute} is not a valid URL.');
        }
    }
    /**
     * {@inheritdoc}
     */
    public function validate_attribute($model, $attribute): void
    {
        $value = $model->{$attribute};
        $result = $this->validate_value($value);
        if (!empty($result)) {
            $this->add_error($model, $attribute, $result[0], $result[1]);
        } elseif ($this->default_scheme !== null && strpos($value, '://') === false) {
            $model->{$attribute} = $this->default_scheme . '://' . $value;
        }
    }
    /**
     * {@inheritdoc}
     */
    protected function validate_value($value): ?array
    {
        // make sure the length is limited to avoid DOS attacks
        if (is_string($value) && strlen($value) < 2000) {
            if ($this->default_scheme !== null && strpos($value, '://') === false) {
                $value = $this->default_scheme . '://' . $value;
            }
            if (strpos($this->pattern, '{schemes}') !== false) {
                $pattern = str_replace('{schemes}', '(' . implode('|', $this->valid_schemes) . ')', $this->pattern);
            } else {
                $pattern = $this->pattern;
            }
            if ($this->enable_idn) {
                $value = preg_replace_callback('/:\/\/([^\/]+)/', fn($matches) => '://' . $this->idn_to_ascii($matches[1]), $value);
            }
            if (preg_match($pattern, $value)) {
                return null;
            }
        }
        return [$this->message, []];
    }
    private function idn_to_ascii(string $idn)
    {
        return idn_to_ascii($idn, IDNA_NONTRANSITIONAL_TO_ASCII, INTL_IDNA_VARIANT_UTS46);
    }
    /**
     * {@inheritdoc}
     */
    public function client_validate_attribute($model, $attribute, $view): string
    {
        Validation_Asset::register($view);
        if ($this->enable_idn) {
            Punycode_Asset::register($view);
        }
        $options = $this->get_client_options($model, $attribute);
        return 'yii.validation.url(value, messages, ' . Json::html_encode($options) . ');';
    }
    /**
     * {@inheritdoc}
     */
    public function get_client_options($model, $attribute): array
    {
        if (strpos($this->pattern, '{schemes}') !== false) {
            $pattern = str_replace('{schemes}', '(' . implode('|', $this->valid_schemes) . ')', $this->pattern);
        } else {
            $pattern = $this->pattern;
        }
        $options = ['pattern' => new Js_Expression($pattern), 'message' => $this->format_message($this->message, ['attribute' => $model->get_attribute_label($attribute)]), 'enableIDN' => (bool) $this->enable_idn];
        if ($this->skip_on_empty) {
            $options['skipOnEmpty'] = 1;
        }
        if ($this->default_scheme !== null) {
            $options['defaultScheme'] = $this->default_scheme;
        }
        return $options;
    }
}