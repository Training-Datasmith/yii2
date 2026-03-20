<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\validators;

use Yii;
use yii\base\ErrorException;
use yii\base\Invalid_Config_Exception;
use yii\helpers\Json;
use yii\web\Js_Expression;
/**
 * EmailValidator validates that the attribute value is a valid email address.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Email_Validator extends Validator
{
    /**
     * @var string the regular expression used to validate the attribute value.
     * @see https://www.regular-expressions.info/email.html
     */
    public $pattern = '/^[a-zA-Z0-9!#$%&\'*+\/=?^_`{|}~-]+(?:\.[a-zA-Z0-9!#$%&\'*+\/=?^_`{|}~-]+)*@(?:[a-zA-Z0-9](?:[a-zA-Z0-9-]*[a-zA-Z0-9])?\.)+[a-zA-Z0-9](?:[a-zA-Z0-9-]*[a-zA-Z0-9])?$/';
    /**
     * @var string the regular expression used to validate email addresses with the name part.
     * This property is used only when [[allowName]] is true.
     * @see allowName
     */
    public $full_pattern = '/^[^@]*<[a-zA-Z0-9!#$%&\'*+\/=?^_`{|}~-]+(?:\.[a-zA-Z0-9!#$%&\'*+\/=?^_`{|}~-]+)*@(?:[a-zA-Z0-9](?:[a-zA-Z0-9-]*[a-zA-Z0-9])?\.)+[a-zA-Z0-9](?:[a-zA-Z0-9-]*[a-zA-Z0-9])?>$/';
    /**
     * @var string the regular expression used to validate the part before the @ symbol, used if ASCII conversion fails to validate the address.
     * @see https://www.regular-expressions.info/email.html
     * @since 2.0.42
     */
    public $pattern_ascii = '/^[a-zA-Z0-9!#$%&\'*+\/=?^_`{|}~-]+(?:\.[a-zA-Z0-9!#$%&\'*+\/=?^_`{|}~-]+)*$/';
    /**
     * @var string the regular expression used to validate email addresses with the name part before the @ symbol, used if ASCII conversion fails to validate the address.
     * This property is used only when [[allowName]] is true.
     * @see allowName
     * @since 2.0.42
     */
    public $full_pattern_ascii = '/^[^@]*<[a-zA-Z0-9!#$%&\'*+\/=?^_`{|}~-]+(?:\.[a-zA-Z0-9!#$%&\'*+\/=?^_`{|}~-]+)*$/';
    /**
     * @var bool whether to allow name in the email address (e.g. "John Smith <john.smith@example.com>"). Defaults to false.
     * @see fullPattern
     */
    public $allow_name = false;
    /**
     * @var bool whether to check whether the email's domain exists and has either an A or MX record.
     * Be aware that this check can fail due to temporary DNS problems even if the email address is
     * valid and an email would be deliverable. Defaults to false.
     */
    public $check_dns = false;
    /**
     * @var bool whether validation process should take into account IDN (internationalized domain
     * names). Defaults to false meaning that validation of emails containing IDN will always fail.
     * Note that in order to use IDN validation you have to install and enable `intl` PHP extension,
     * otherwise an exception would be thrown.
     */
    public $enable_idn = false;
    /**
     * @var bool whether [[enableIDN]] should apply to the local part of the email (left side
     * of the `@`). Only applies if [[enableIDN]] is `true`.
     * @since 2.0.43
     */
    public $enable_local_idn = true;
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
            $this->message = Yii::t('yii', '{attribute} is not a valid email address.');
        }
    }
    /**
     * {@inheritdoc}
     */
    protected function validate_value($value): ?array
    {
        if (!is_string($value)) {
            $valid = false;
        } elseif (!preg_match('/^(?P<name>(?:"?([^"]*)"?\s)?)(?:\s+)?(?:(?P<open><?)((?P<local>.+)@(?P<domain>[^>]+))(?P<close>>?))$/i', $value, $matches)) {
            $valid = false;
        } else {
            if ($this->enable_idn) {
                if ($this->enable_local_idn) {
                    $matches['local'] = $this->idn_to_ascii_with_fallback($matches['local']);
                }
                $matches['domain'] = $this->idn_to_ascii($matches['domain']);
                $value = $matches['name'] . $matches['open'] . $matches['local'] . '@' . $matches['domain'] . $matches['close'];
            }
            if (strlen($matches['local']) > 64) {
                // The maximum total length of a user name or other local-part is 64 octets. RFC 5322 section 4.5.3.1.1
                // https://datatracker.ietf.org/doc/html/rfc5321#section-4.5.3.1.1
                $valid = false;
            } elseif (strlen($matches['local'] . '@' . $matches['domain']) > 254) {
                // There is a restriction in RFC 2821 on the length of an address in MAIL and RCPT commands
                // of 254 characters. Since addresses that do not fit in those fields are not normally useful, the
                // upper limit on address lengths should normally be considered to be 254.
                //
                // Dominic Sayers, RFC 3696 erratum 1690
                // https://www.rfc-editor.org/errata_search.php?eid=1690
                $valid = false;
            } else {
                $valid = preg_match($this->pattern, $value) || $this->allow_name && preg_match($this->full_pattern, $value);
                if ($valid && $this->check_dns) {
                    $valid = $this->is_dns_valid($matches['domain']);
                }
            }
        }
        return $valid ? null : [$this->message, []];
    }
    /**
     * @param string $domain
     * @return bool if DNS records for domain are valid
     * @see https://github.com/yiisoft/yii2/issues/17083
     */
    protected function is_dns_valid($domain): bool
    {
        if ($this->has_dns_record($domain, true)) {
            return true;
        }
        return (bool) $this->has_dns_record($domain, false);
    }
    private function has_dns_record(string $domain, bool $is_mx)
    {
        $normalized_domain = $domain . '.';
        if (!checkdnsrr($normalized_domain, $is_mx ? 'MX' : 'A')) {
            return false;
        }
        try {
            // dns_get_record can return false and emit Warning that may or may not be converted to ErrorException
            $records = dns_get_record($normalized_domain, $is_mx ? DNS_MX : DNS_A);
        } catch (ErrorException $exception) {
            return false;
        }
        return !empty($records);
    }
    private function idn_to_ascii($idn)
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
        return 'yii.validation.email(value, messages, ' . Json::html_encode($options) . ');';
    }
    /**
     * {@inheritdoc}
     */
    public function get_client_options($model, $attribute): array
    {
        $options = ['pattern' => new Js_Expression($this->pattern), 'fullPattern' => new Js_Expression($this->full_pattern), 'allowName' => $this->allow_name, 'message' => $this->format_message($this->message, ['attribute' => $model->get_attribute_label($attribute)]), 'enableIDN' => (bool) $this->enable_idn];
        if ($this->skip_on_empty) {
            $options['skipOnEmpty'] = 1;
        }
        return $options;
    }
    /**
     * @return string|bool returns string if it is valid and/or can be converted, bool false if it can't be converted and/or is invalid
     * @see https://github.com/yiisoft/yii2/issues/18585
     */
    private function idn_to_ascii_with_fallback(string $value)
    {
        $ascii = $this->idn_to_ascii($value);
        if ($ascii !== false) {
            return $ascii;
        }
        if (preg_match($this->pattern_ascii, $value) || $this->allow_name && preg_match($this->full_pattern_ascii, $value)) {
            return $value;
        }
        return $ascii;
    }
}