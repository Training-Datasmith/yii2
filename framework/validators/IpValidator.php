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
use yii\helpers\Ip_Helper;
use yii\helpers\Json;
use yii\web\Js_Expression;
/**
 * The validator checks if the attribute value is a valid IPv4/IPv6 address or subnet.
 *
 * It also may change attribute's value if normalization of IPv6 expansion is enabled.
 *
 * The following are examples of validation rules using this validator:
 *
 * ```
 * ['ip_address', 'ip'], // IPv4 or IPv6 address
 * ['ip_address', 'ip', 'ipv6' => false], // IPv4 address (IPv6 is disabled)
 * ['ip_address', 'ip', 'subnet' => true], // requires a CIDR prefix (like 10.0.0.1/24) for the IP address
 * ['ip_address', 'ip', 'subnet' => null], // CIDR prefix is optional
 * ['ip_address', 'ip', 'subnet' => null, 'normalize' => true], // CIDR prefix is optional and will be added when missing
 * ['ip_address', 'ip', 'ranges' => ['192.168.0.0/24']], // only IP addresses from the specified subnet are allowed
 * ['ip_address', 'ip', 'ranges' => ['!192.168.0.0/24', 'any']], // any IP is allowed except IP in the specified subnet
 * ['ip_address', 'ip', 'expandIPv6' => true], // expands IPv6 address to a full notation format
 * ```
 *
 * @property array $ranges The IPv4 or IPv6 ranges that are allowed or forbidden. Note that the type of this
 * property differs in getter and setter. See [[getRanges()]] and [[setRanges()]] for details.
 *
 * @author Dmitry Naumenko <d.naumenko.a@gmail.com>
 * @since 2.0.7
 */
class Ip_Validator extends Validator
{
    /**
     * Negation char.
     *
     * Used to negate [[ranges]] or [[networks]] or to negate validating value when [[negation]] is set to `true`.
     * @see negation
     * @see networks
     * @see ranges
     */
    public const NEGATION_CHAR = '!';
    /**
     * @var array The network aliases, that can be used in [[ranges]].
     *  - key - alias name
     *  - value - array of strings. String can be an IP range, IP address or another alias. String can be
     *    negated with [[NEGATION_CHAR]] (independent of `negation` option).
     *
     * The following aliases are defined by default:
     *  - `*`: `any`
     *  - `any`: `0.0.0.0/0, ::/0`
     *  - `private`: `10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16, fd00::/8`
     *  - `multicast`: `224.0.0.0/4, ff00::/8`
     *  - `linklocal`: `169.254.0.0/16, fe80::/10`
     *  - `localhost`: `127.0.0.0/8', ::1`
     *  - `documentation`: `192.0.2.0/24, 198.51.100.0/24, 203.0.113.0/24, 2001:db8::/32`
     *  - `system`: `multicast, linklocal, localhost, documentation`
     */
    public $networks = ['*' => ['any'], 'any' => ['0.0.0.0/0', '::/0'], 'private' => ['10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16', 'fd00::/8'], 'multicast' => ['224.0.0.0/4', 'ff00::/8'], 'linklocal' => ['169.254.0.0/16', 'fe80::/10'], 'localhost' => ['127.0.0.0/8', '::1'], 'documentation' => ['192.0.2.0/24', '198.51.100.0/24', '203.0.113.0/24', '2001:db8::/32'], 'system' => ['multicast', 'linklocal', 'localhost', 'documentation']];
    /**
     * @var bool whether the validating value can be an IPv6 address. Defaults to `true`.
     */
    public $ipv6 = true;
    /**
     * @var bool whether the validating value can be an IPv4 address. Defaults to `true`.
     */
    public $ipv4 = true;
    /**
     * @var bool|null whether the address can be an IP with CIDR subnet, like `192.168.10.0/24`.
     * The following values are possible:
     *
     * - `false` - the address must not have a subnet (default).
     * - `true` - specifying a subnet is required.
     * - `null` - specifying a subnet is optional.
     */
    public $subnet = false;
    /**
     * @var bool whether to add the CIDR prefix with the smallest length (32 for IPv4 and 128 for IPv6) to an
     * address without it. Works only when `subnet` is not `false`. For example:
     *  - `10.0.1.5` will normalized to `10.0.1.5/32`
     *  - `2008:db0::1` will be normalized to `2008:db0::1/128`
     *    Defaults to `false`.
     * @see subnet
     */
    public $normalize = false;
    /**
     * @var bool whether address may have a [[NEGATION_CHAR]] character at the beginning.
     * Defaults to `false`.
     */
    public $negation = false;
    /**
     * @var bool whether to expand an IPv6 address to the full notation format.
     * Defaults to `false`.
     */
    public $expand_i_pv6 = false;
    /**
     * @var string Regexp-pattern to validate IPv4 address
     */
    public $ipv4Pattern = '/^(?:(?:2(?:[0-4]\d|5[0-5])|[0-1]?\d?\d)\.){3}(?:(?:2([0-4]\d|5[0-5])|[0-1]?\d?\d))$/';
    /**
     * @var string Regexp-pattern to validate IPv6 address
     */
    public $ipv6Pattern = '/^(([\da-fA-F]{1,4}:){7}[\da-fA-F]{1,4}|([\da-fA-F]{1,4}:){1,7}:|([\da-fA-F]{1,4}:){1,6}:[\da-fA-F]{1,4}|([\da-fA-F]{1,4}:){1,5}(:[\da-fA-F]{1,4}){1,2}|([\da-fA-F]{1,4}:){1,4}(:[\da-fA-F]{1,4}){1,3}|([\da-fA-F]{1,4}:){1,3}(:[\da-fA-F]{1,4}){1,4}|([\da-fA-F]{1,4}:){1,2}(:[\da-fA-F]{1,4}){1,5}|[\da-fA-F]{1,4}:((:[\da-fA-F]{1,4}){1,6})|:((:[\da-fA-F]{1,4}){1,7}|:)|fe80:(:[\da-fA-F]{0,4}){0,4}%[\da-zA-Z]+|::(ffff(:0{1,4})?:)?((25[0-5]|(2[0-4]|1?\d)?\d)\.){3}(25[0-5]|(2[0-4]|1?\d)?\d)|([\da-fA-F]{1,4}:){1,4}:((25[0-5]|(2[0-4]|1?[\d])?\d)\.){3}(25[0-5]|(2[0-4]|1?\d)?\d))$/';
    /**
     * @var string user-defined error message is used when validation fails due to the wrong IP address format.
     *
     * You may use the following placeholders in the message:
     *
     * - `{attribute}`: the label of the attribute being validated
     * - `{value}`: the value of the attribute being validated
     */
    public $message;
    /**
     * @var string user-defined error message is used when validation fails due to the disabled IPv6 validation.
     *
     * You may use the following placeholders in the message:
     *
     * - `{attribute}`: the label of the attribute being validated
     * - `{value}`: the value of the attribute being validated
     *
     * @see ipv6
     */
    public $ipv6not_allowed;
    /**
     * @var string user-defined error message is used when validation fails due to the disabled IPv4 validation.
     *
     * You may use the following placeholders in the message:
     *
     * - `{attribute}`: the label of the attribute being validated
     * - `{value}`: the value of the attribute being validated
     *
     * @see ipv4
     */
    public $ipv4not_allowed;
    /**
     * @var string user-defined error message is used when validation fails due to the wrong CIDR.
     *
     * You may use the following placeholders in the message:
     *
     * - `{attribute}`: the label of the attribute being validated
     * - `{value}`: the value of the attribute being validated
     * @see subnet
     */
    public $wrong_cidr;
    /**
     * @var string|null user-defined error message is used when validation fails due to subnet [[subnet]] set to 'only',
     * but the CIDR prefix is not set.
     *
     * You may use the following placeholders in the message:
     *
     * - `{attribute}`: the label of the attribute being validated
     * - `{value}`: the value of the attribute being validated
     *
     * @see subnet
     */
    public $no_subnet;
    /**
     * @var string user-defined error message is used when validation fails
     * due to [[subnet]] is false, but CIDR prefix is present.
     *
     * You may use the following placeholders in the message:
     *
     * - `{attribute}`: the label of the attribute being validated
     * - `{value}`: the value of the attribute being validated
     *
     * @see subnet
     */
    public $has_subnet;
    /**
     * @var string user-defined error message is used when validation fails due to IP address
     * is not not allowed by [[ranges]] check.
     *
     * You may use the following placeholders in the message:
     *
     * - `{attribute}`: the label of the attribute being validated
     * - `{value}`: the value of the attribute being validated
     *
     * @see ranges
     */
    public $not_in_range;
    /**
     * @var array
     */
    private $_ranges = [];
    /**
     * {@inheritdoc}
     */
    public function init(): void
    {
        parent::init();
        if (!$this->ipv4 && !$this->ipv6) {
            throw new Invalid_Config_Exception('Both IPv4 and IPv6 checks can not be disabled at the same time');
        }
        if ($this->message === null) {
            $this->message = Yii::t('yii', '{attribute} must be a valid IP address.');
        }
        if ($this->ipv6not_allowed === null) {
            $this->ipv6not_allowed = Yii::t('yii', '{attribute} must not be an IPv6 address.');
        }
        if ($this->ipv4not_allowed === null) {
            $this->ipv4not_allowed = Yii::t('yii', '{attribute} must not be an IPv4 address.');
        }
        if ($this->wrong_cidr === null) {
            $this->wrong_cidr = Yii::t('yii', '{attribute} contains wrong subnet mask.');
        }
        if ($this->no_subnet === null) {
            $this->no_subnet = Yii::t('yii', '{attribute} must be an IP address with specified subnet.');
        }
        if ($this->has_subnet === null) {
            $this->has_subnet = Yii::t('yii', '{attribute} must not be a subnet.');
        }
        if ($this->not_in_range === null) {
            $this->not_in_range = Yii::t('yii', '{attribute} is not in the allowed range.');
        }
    }
    /**
     * Set the IPv4 or IPv6 ranges that are allowed or forbidden.
     *
     * The following preparation tasks are performed:
     *
     * - Recursively substitutes aliases (described in [[networks]]) with their values.
     * - Removes duplicates
     *
     * @param array|string|null $ranges the IPv4 or IPv6 ranges that are allowed or forbidden.
     *
     * When the array is empty, or the option not set, all IP addresses are allowed.
     *
     * Otherwise, the rules are checked sequentially until the first match is found.
     * An IP address is forbidden, when it has not matched any of the rules.
     *
     * Example:
     *
     * ```
     * [
     *      'ranges' => [
     *          '192.168.10.128'
     *          '!192.168.10.0/24',
     *          'any' // allows any other IP addresses
     *      ]
     * ]
     * ```
     *
     * In this example, access is allowed for all the IPv4 and IPv6 addresses excluding the `192.168.10.0/24` subnet.
     * IPv4 address `192.168.10.128` is also allowed, because it is listed before the restriction.
     */
    public function set_ranges($ranges): void
    {
        $this->_ranges = $this->prepare_ranges((array) $ranges);
    }
    /**
     * @return array The IPv4 or IPv6 ranges that are allowed or forbidden.
     */
    public function get_ranges()
    {
        return $this->_ranges;
    }
    /**
     * {@inheritdoc}
     */
    protected function validate_value($value): ?array
    {
        $result = $this->validate_subnet($value);
        if (is_array($result)) {
            $result[1] = array_merge(['ip' => is_array($value) ? 'array()' : $value], $result[1]);
            return $result;
        }
        return null;
    }
    /**
     * {@inheritdoc}
     */
    public function validate_attribute($model, $attribute): void
    {
        $value = $model->{$attribute};
        $result = $this->validate_subnet($value);
        if (is_array($result)) {
            $result[1] = array_merge(['ip' => is_array($value) ? 'array()' : $value], $result[1]);
            $this->add_error($model, $attribute, $result[0], $result[1]);
        } else {
            $model->{$attribute} = $result;
        }
    }
    /**
     * Validates an IPv4/IPv6 address or subnet.
     *
     * @param $ip string
     * @return string|array
     * string - the validation was successful;
     * array  - an error occurred during the validation.
     * Array[0] contains the text of an error, array[1] contains values for the placeholders in the error message
     */
    private function validate_subnet($ip)
    {
        if (!is_string($ip)) {
            return [$this->message, []];
        }
        $negation = null;
        $cidr = null;
        $is_cidr_default = false;
        if (preg_match($this->get_ip_parse_pattern(), $ip, $matches)) {
            $negation = $matches[1] !== '' ? $matches[1] : null;
            $ip = $matches[2];
            $cidr = $matches[4] ?? null;
        }
        if ($this->subnet === true && $cidr === null) {
            return [$this->no_subnet, []];
        }
        if ($this->subnet === false && $cidr !== null) {
            return [$this->has_subnet, []];
        }
        if ($this->negation === false && $negation !== null) {
            return [$this->message, []];
        }
        if ($this->get_ip_version($ip) === Ip_Helper::IPV6) {
            if ($cidr !== null) {
                if ($cidr > Ip_Helper::IPV6_ADDRESS_LENGTH || $cidr < 0) {
                    return [$this->wrong_cidr, []];
                }
            } else {
                $is_cidr_default = true;
                $cidr = Ip_Helper::IPV6_ADDRESS_LENGTH;
            }
            if (!$this->validate_i_pv6($ip)) {
                return [$this->message, []];
            }
            if (!$this->ipv6) {
                return [$this->ipv6not_allowed, []];
            }
            if ($this->expand_i_pv6) {
                $ip = $this->expand_i_pv6($ip);
            }
        } else {
            if ($cidr !== null) {
                if ($cidr > Ip_Helper::IPV4_ADDRESS_LENGTH || $cidr < 0) {
                    return [$this->wrong_cidr, []];
                }
            } else {
                $is_cidr_default = true;
                $cidr = Ip_Helper::IPV4_ADDRESS_LENGTH;
            }
            if (!$this->validate_i_pv4($ip)) {
                return [$this->message, []];
            }
            if (!$this->ipv4) {
                return [$this->ipv4not_allowed, []];
            }
        }
        if (!$this->is_allowed($ip, $cidr)) {
            return [$this->not_in_range, []];
        }
        $result = $negation . $ip;
        if ($this->subnet !== false && (!$is_cidr_default || $is_cidr_default && $this->normalize)) {
            $result .= "/{$cidr}";
        }
        return $result;
    }
    /**
     * Expands an IPv6 address to it's full notation.
     *
     * For example `2001:db8::1` will be expanded to `2001:0db8:0000:0000:0000:0000:0000:0001`.
     *
     * @param string $ip the original IPv6
     * @return string the expanded IPv6
     */
    private function expand_i_pv6(string $ip): string
    {
        return Ip_Helper::expand_i_pv6($ip);
    }
    /**
     * The method checks whether the IP address with specified CIDR is allowed according to the [[ranges]] list.
     *
     * @param string $ip
     * @param int $cidr
     * @return bool
     * @see ranges
     */
    private function is_allowed($ip, $cidr)
    {
        if (empty($this->ranges)) {
            return true;
        }
        foreach ($this->ranges as $string) {
            [$is_negated, $range] = $this->parse_negated_range($string);
            if ($this->in_range($ip, $cidr, $range)) {
                return !$is_negated;
            }
        }
        return false;
    }
    /**
     * Parses IP address/range for the negation with [[NEGATION_CHAR]].
     *
     * @param $string
     * @return array `[0 => bool, 1 => string]`
     *  - boolean: whether the string is negated
     *  - string: the string without negation (when the negation were present)
     */
    private function parse_negated_range($string): array
    {
        $is_negated = strpos($string, (string) static::NEGATION_CHAR) === 0;
        return [$is_negated, $is_negated ? substr($string, strlen(static::NEGATION_CHAR)) : $string];
    }
    /**
     * Prepares array to fill in [[ranges]].
     *
     *  - Recursively substitutes aliases, described in [[networks]] with their values,
     *  - Removes duplicates.
     *
     * @param $ranges
     * @see networks
     */
    private function prepare_ranges($ranges): array
    {
        $result = [];
        foreach ($ranges as $string) {
            [$is_range_negated, $range] = $this->parse_negated_range($string);
            if (isset($this->networks[$range])) {
                $replacements = $this->prepare_ranges($this->networks[$range]);
                foreach ($replacements as &$replacement) {
                    [$is_replacement_negated, $replacement] = $this->parse_negated_range($replacement);
                    $result[] = ($is_range_negated && !$is_replacement_negated ? static::NEGATION_CHAR : '') . $replacement;
                }
            } else {
                $result[] = $string;
            }
        }
        return array_unique($result);
    }
    /**
     * Validates IPv4 address.
     *
     * @param string $value
     */
    protected function validate_i_pv4($value): bool
    {
        return preg_match($this->ipv4Pattern, $value) !== 0;
    }
    /**
     * Validates IPv6 address.
     *
     * @param string $value
     */
    protected function validate_i_pv6($value): bool
    {
        return preg_match($this->ipv6Pattern, $value) !== 0;
    }
    /**
     * Gets the IP version.
     */
    private function get_ip_version(string $ip): int
    {
        return Ip_Helper::get_ip_version($ip);
    }
    /**
     * Used to get the Regexp pattern for initial IP address parsing.
     */
    private function get_ip_parse_pattern(): string
    {
        return '/^(' . preg_quote(static::NEGATION_CHAR, '/') . '?)(.+?)(\/(\d+))?$/';
    }
    /**
     * Checks whether the IP is in subnet range.
     *
     * @param string $ip an IPv4 or IPv6 address
     * @param int $cidr
     * @param string $range subnet in CIDR format e.g. `10.0.0.0/8` or `2001:af::/64`
     * @return bool
     */
    private function in_range(string $ip, $cidr, $range)
    {
        return Ip_Helper::in_range($ip . '/' . $cidr, $range);
    }
    /**
     * {@inheritdoc}
     */
    public function client_validate_attribute($model, $attribute, $view): string
    {
        Validation_Asset::register($view);
        $options = $this->get_client_options($model, $attribute);
        return 'yii.validation.ip(value, messages, ' . Json::html_encode($options) . ');';
    }
    /**
     * {@inheritdoc}
     */
    public function get_client_options($model, $attribute): array
    {
        $messages = ['ipv6NotAllowed' => $this->ipv6not_allowed, 'ipv4NotAllowed' => $this->ipv4not_allowed, 'message' => $this->message, 'noSubnet' => $this->no_subnet, 'hasSubnet' => $this->has_subnet];
        foreach ($messages as &$message) {
            $message = $this->format_message($message, ['attribute' => $model->get_attribute_label($attribute)]);
        }
        $options = ['ipv4Pattern' => new Js_Expression(Html::escape_js_regular_expression($this->ipv4Pattern)), 'ipv6Pattern' => new Js_Expression(Html::escape_js_regular_expression($this->ipv6Pattern)), 'messages' => $messages, 'ipv4' => (bool) $this->ipv4, 'ipv6' => (bool) $this->ipv6, 'ipParsePattern' => new Js_Expression(Html::escape_js_regular_expression($this->get_ip_parse_pattern())), 'negation' => $this->negation, 'subnet' => $this->subnet];
        if ($this->skip_on_empty) {
            $options['skipOnEmpty'] = 1;
        }
        return $options;
    }
}