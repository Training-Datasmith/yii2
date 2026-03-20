<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\web;

use Yii;
use yii\base\Base_Object;
use yii\base\Invalid_Config_Exception;
/**
 * UrlRule represents a rule used by [[UrlManager]] for parsing and generating URLs.
 *
 * To define your own URL parsing and creation logic you can extend from this class
 * and add it to [[UrlManager::rules]] like this:
 *
 * ```
 * 'rules' => [
 *     ['class' => 'MyUrlRule', 'pattern' => '...', 'route' => 'site/index', ...],
 *     // ...
 * ]
 * ```
 *
 * @property-read int|null $createUrlStatus Status of the URL creation after the last [[createUrl()]] call.
 * `null` if rule does not provide info about create status.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Url_Rule extends Base_Object implements Url_Rule_Interface
{
    /**
     * Set [[mode]] with this value to mark that this rule is for URL parsing only.
     */
    public const PARSING_ONLY = 1;
    /**
     * Set [[mode]] with this value to mark that this rule is for URL creation only.
     */
    public const CREATION_ONLY = 2;
    /**
     * Represents the successful URL generation by last [[createUrl()]] call.
     * @see createStatus
     * @since 2.0.12
     */
    public const CREATE_STATUS_SUCCESS = 0;
    /**
     * Represents the unsuccessful URL generation by last [[createUrl()]] call, because rule does not support
     * creating URLs.
     * @see createStatus
     * @since 2.0.12
     */
    public const CREATE_STATUS_PARSING_ONLY = 1;
    /**
     * Represents the unsuccessful URL generation by last [[createUrl()]] call, because of mismatched route.
     * @see createStatus
     * @since 2.0.12
     */
    public const CREATE_STATUS_ROUTE_MISMATCH = 2;
    /**
     * Represents the unsuccessful URL generation by last [[createUrl()]] call, because of mismatched
     * or missing parameters.
     * @see createStatus
     * @since 2.0.12
     */
    public const CREATE_STATUS_PARAMS_MISMATCH = 4;
    /**
     * @var string|null the name of this rule. If not set, it will use [[pattern]] as the name.
     */
    public $name;
    /**
     * On the rule initialization, the [[pattern]] matching parameters names will be replaced with [[placeholders]].
     * @var string the pattern used to parse and create the path info part of a URL.
     * @see host
     * @see placeholders
     */
    public $pattern;
    /**
     * @var string|null the pattern used to parse and create the host info part of a URL (e.g. `https://example.com`).
     * @see pattern
     */
    public $host;
    /**
     * @var string the route to the controller action
     */
    public $route;
    /**
     * @var array the default GET parameters (name => value) that this rule provides.
     * When this rule is used to parse the incoming request, the values declared in this property
     * will be injected into $_GET.
     */
    public $defaults = [];
    /**
     * @var string|null the URL suffix used for this rule.
     * For example, ".html" can be used so that the URL looks like pointing to a static HTML page.
     * If not set, the value of [[UrlManager::suffix]] will be used.
     * Default values should be strings. Non-string values will be automatically converted to
     * strings for comparison with URL parameters.
     */
    public $suffix;
    /**
     * @var string|array|null the HTTP verb (e.g. GET, POST, DELETE) that this rule should match.
     * Use array to represent multiple verbs that this rule may match.
     * If this property is not set, the rule can match any verb.
     * Note that this property is only used when parsing a request. It is ignored for URL creation.
     */
    public $verb;
    /**
     * @var int|null a value indicating if this rule should be used for both request parsing and URL creation,
     * parsing only, or creation only.
     * If not set or 0, it means the rule is both request parsing and URL creation.
     * If it is [[PARSING_ONLY]], the rule is for request parsing only.
     * If it is [[CREATION_ONLY]], the rule is for URL creation only.
     */
    public $mode;
    /**
     * @var bool a value indicating if parameters should be url encoded.
     */
    public $encode_params = true;
    /**
     * @var UrlNormalizer|array|false|null the configuration for [[UrlNormalizer]] used by this rule.
     * If `null`, [[UrlManager::normalizer]] will be used, if `false`, normalization will be skipped
     * for this rule.
     * @since 2.0.10
     */
    public $normalizer;
    /**
     * @var int|null status of the URL creation after the last [[createUrl()]] call.
     * @since 2.0.12
     */
    protected $create_status;
    /**
     * @var array list of placeholders for matching parameters names. Used in [[parseRequest()]], [[createUrl()]].
     * On the rule initialization, the [[pattern]] parameters names will be replaced with placeholders.
     * This array contains relations between the original parameters names and their placeholders.
     * The array keys are the placeholders and the values are the original names.
     *
     * @see parseRequest()
     * @see createUrl()
     * @since 2.0.7
     */
    protected $placeholders = [];
    /**
     * @var string the template for generating a new URL. This is derived from [[pattern]] and is used in generating URL.
     */
    private ?string $_template = null;
    /**
     * @var string the regex for matching the route part. This is used in generating URL.
     */
    private ?string $_route_rule = null;
    /**
     * @var array list of regex for matching parameters. This is used in generating URL.
     */
    private array $_param_rules = [];
    /**
     * @var array list of parameters used in the route.
     */
    private array $_route_params = [];
    /**
     * @since 2.0.11
     */
    public function __toString(): string
    {
        $str = '';
        if ($this->verb !== null) {
            $str .= implode(',', $this->verb) . ' ';
        }
        if ($this->host !== null && strrpos($this->name, $this->host) === false) {
            $str .= $this->host . '/';
        }
        $str .= $this->name;
        if ($str === '') {
            return '/';
        }
        return $str;
    }
    /**
     * Initializes this rule.
     */
    public function init(): void
    {
        if ($this->pattern === null) {
            throw new Invalid_Config_Exception('UrlRule::pattern must be set.');
        }
        if ($this->route === null) {
            throw new Invalid_Config_Exception('UrlRule::route must be set.');
        }
        if (is_array($this->normalizer)) {
            $normalizer_config = array_merge(['class' => Url_Normalizer::class_name()], $this->normalizer);
            $this->normalizer = Yii::create_object($normalizer_config);
        }
        if ($this->normalizer !== null && $this->normalizer !== false && !$this->normalizer instanceof Url_Normalizer) {
            throw new Invalid_Config_Exception('Invalid config for UrlRule::normalizer.');
        }
        if ($this->verb !== null) {
            if (is_array($this->verb)) {
                foreach ($this->verb as $i => $verb) {
                    $this->verb[$i] = strtoupper($verb);
                }
            } else {
                $this->verb = [strtoupper($this->verb)];
            }
        }
        if ($this->name === null) {
            $this->name = $this->pattern;
        }
        $this->prepare_pattern();
    }
    /**
     * Process [[$pattern]] on rule initialization.
     */
    private function prepare_pattern(): void
    {
        $this->pattern = $this->trim_slashes($this->pattern);
        $this->route = trim($this->route, '/');
        if ($this->host !== null) {
            $this->host = rtrim($this->host, '/');
            $this->pattern = rtrim($this->host . '/' . $this->pattern, '/');
        } elseif ($this->pattern === '') {
            $this->_template = '';
            $this->pattern = '#^$#u';
            return;
        } elseif (($pos = strpos($this->pattern, '://')) !== false) {
            if (($pos2 = strpos($this->pattern, '/', $pos + 3)) !== false) {
                $this->host = substr($this->pattern, 0, $pos2);
            } else {
                $this->host = $this->pattern;
            }
        } elseif (strncmp($this->pattern, '//', 2) === 0) {
            if (($pos2 = strpos($this->pattern, '/', 2)) !== false) {
                $this->host = substr($this->pattern, 0, $pos2);
            } else {
                $this->host = $this->pattern;
            }
        } else {
            $this->pattern = '/' . $this->pattern . '/';
        }
        if (strpos($this->route, '<') !== false && preg_match_all('/<([\w._-]+)>/', $this->route, $matches)) {
            foreach ($matches[1] as $name) {
                $this->_route_params[$name] = "<{$name}>";
            }
        }
        $this->translate_pattern(true);
    }
    /**
     * Prepares [[$pattern]] on rule initialization - replace parameter names by placeholders.
     *
     * @param bool $allowAppendSlash Defines position of slash in the param pattern in [[$pattern]].
     * If `false` slash will be placed at the beginning of param pattern. If `true` slash position will be detected
     * depending on non-optional pattern part.
     */
    private function translate_pattern(bool $allow_append_slash): void
    {
        $tr = ['.' => '\.', '*' => '\*', '$' => '\$', '[' => '\[', ']' => '\]', '(' => '\(', ')' => '\)'];
        $tr2 = [];
        $required_pattern_part = $this->pattern;
        $old_offset = 0;
        if (preg_match_all('/<([\w._-]+):?([^>]+)?>/', $this->pattern, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            $append_slash = false;
            foreach ($matches as $match) {
                $name = $match[1][0];
                $pattern = $match[2][0] ?? '[^\/]+';
                $placeholder = 'a' . hash('crc32b', $name);
                // placeholder must begin with a letter
                $this->placeholders[$placeholder] = $name;
                if (array_key_exists($name, $this->defaults)) {
                    $length = strlen($match[0][0]);
                    $offset = $match[0][1];
                    $required_pattern_part = str_replace("/{$match[0][0]}/", '//', $required_pattern_part);
                    if ($allow_append_slash && ($append_slash || $offset === 1) && $offset - $old_offset === 1 && isset($this->pattern[$offset + $length]) && $this->pattern[$offset + $length] === '/' && isset($this->pattern[$offset + $length + 1])) {
                        // if pattern starts from optional params, put slash at the end of param pattern
                        // @see https://github.com/yiisoft/yii2/issues/13086
                        $append_slash = true;
                        $tr["<{$name}>/"] = "((?P<{$placeholder}>{$pattern})/)?";
                    } elseif ($offset > 1 && $this->pattern[$offset - 1] === '/' && (!isset($this->pattern[$offset + $length]) || $this->pattern[$offset + $length] === '/')) {
                        $append_slash = false;
                        $tr["/<{$name}>"] = "(/(?P<{$placeholder}>{$pattern}))?";
                    }
                    $tr["<{$name}>"] = "(?P<{$placeholder}>{$pattern})?";
                    $old_offset = $offset + $length;
                } else {
                    $append_slash = false;
                    $tr["<{$name}>"] = "(?P<{$placeholder}>{$pattern})";
                }
                if (isset($this->_route_params[$name])) {
                    $tr2["<{$name}>"] = "(?P<{$placeholder}>{$pattern})";
                } else {
                    $this->_param_rules[$name] = $pattern === '[^\/]+' ? '' : "#^{$pattern}\$#u";
                }
            }
        }
        // we have only optional params in route - ensure slash position on param patterns
        if ($allow_append_slash && trim($required_pattern_part, '/') === '') {
            $this->translate_pattern(false);
            return;
        }
        $this->_template = preg_replace('/<([\w._-]+):?([^>]+)?>/', '<$1>', $this->pattern);
        $this->pattern = '#^' . trim(strtr($this->_template, $tr), '/') . '$#u';
        // if host starts with relative scheme, then insert pattern to match any
        if ($this->host !== null && strncmp($this->host, '//', 2) === 0) {
            $this->pattern = substr_replace($this->pattern, '[\w]+://', 2, 0);
        }
        if (!empty($this->_route_params)) {
            $this->_route_rule = '#^' . strtr($this->route, $tr2) . '$#u';
        }
    }
    /**
     * @param UrlManager $manager the URL manager
     * @return UrlNormalizer|null
     * @since 2.0.10
     */
    protected function get_normalizer($manager)
    {
        if ($this->normalizer === null) {
            return $manager->normalizer;
        }
        return $this->normalizer;
    }
    /**
     * @param UrlManager $manager the URL manager
     * @since 2.0.10
     */
    protected function has_normalizer($manager): bool
    {
        return $this->get_normalizer($manager) instanceof Url_Normalizer;
    }
    /**
     * Parses the given request and returns the corresponding route and parameters.
     * @param UrlManager $manager the URL manager
     * @param Request $request the request component
     * @return array|bool the parsing result. The route and the parameters are returned as an array.
     * If `false`, it means this rule cannot be used to parse this path info.
     */
    public function parse_request($manager, $request)
    {
        if ($this->mode === self::CREATION_ONLY) {
            return false;
        }
        if (!empty($this->verb) && !in_array($request->get_method(), $this->verb, true)) {
            return false;
        }
        $suffix = (string) ($this->suffix ?? $manager->suffix);
        $path_info = $request->get_path_info();
        $normalized = false;
        if ($this->has_normalizer($manager)) {
            $path_info = $this->get_normalizer($manager)->normalize_path_info($path_info, $suffix, $normalized);
        }
        if ($suffix !== '' && $path_info !== '') {
            $n = strlen($suffix);
            if (substr_compare($path_info, $suffix, -$n, $n) === 0) {
                $path_info = substr($path_info, 0, -$n);
                if ($path_info === '') {
                    // suffix alone is not allowed
                    return false;
                }
            } else {
                return false;
            }
        }
        if ($this->host !== null) {
            $path_info = strtolower($request->get_host_info()) . ($path_info === '' ? '' : '/' . $path_info);
        }
        if (!preg_match($this->pattern, $path_info, $matches)) {
            return false;
        }
        $matches = $this->substitute_placeholder_names($matches);
        foreach ($this->defaults as $name => $value) {
            if (!isset($matches[$name]) || $matches[$name] === '') {
                $matches[$name] = $value;
            }
        }
        $params = $this->defaults;
        $tr = [];
        foreach ($matches as $name => $value) {
            if (isset($this->_route_params[$name])) {
                $tr[$this->_route_params[$name]] = $value;
                unset($params[$name]);
            } elseif (isset($this->_param_rules[$name])) {
                $params[$name] = $value;
            }
        }
        if ($this->_route_rule !== null) {
            $route = strtr($this->route, $tr);
        } else {
            $route = $this->route;
        }
        Yii::debug("Request parsed with URL rule: {$this->name}", __METHOD__);
        if ($normalized) {
            // pathInfo was changed by normalizer - we need also normalize route
            return $this->get_normalizer($manager)->normalize_route([$route, $params]);
        }
        return [$route, $params];
    }
    /**
     * Creates a URL according to the given route and parameters.
     * @param UrlManager $manager the URL manager
     * @param string $route the route. It should not have slashes at the beginning or the end.
     * @param array $params the parameters
     * @return string|bool the created URL, or `false` if this rule cannot be used for creating this URL.
     */
    public function create_url($manager, $route, $params)
    {
        if ($this->mode === self::PARSING_ONLY) {
            $this->create_status = self::CREATE_STATUS_PARSING_ONLY;
            return false;
        }
        $tr = [];
        // match the route part first
        if ($route !== $this->route) {
            if ($this->_route_rule !== null && preg_match($this->_route_rule, $route, $matches)) {
                $matches = $this->substitute_placeholder_names($matches);
                foreach ($this->_route_params as $name => $token) {
                    if (isset($this->defaults[$name]) && strcmp($this->defaults[$name], $matches[$name]) === 0) {
                        $tr[$token] = '';
                    } else {
                        $tr[$token] = $matches[$name];
                    }
                }
            } else {
                $this->create_status = self::CREATE_STATUS_ROUTE_MISMATCH;
                return false;
            }
        }
        // match default params
        // if a default param is not in the route pattern, its value must also be matched
        foreach ($this->defaults as $name => $value) {
            if (isset($this->_route_params[$name])) {
                continue;
            }
            if (!isset($params[$name])) {
                // allow omit empty optional params
                // @see https://github.com/yiisoft/yii2/issues/10970
                if (in_array($name, $this->placeholders) && strcmp($value, '') === 0) {
                    $params[$name] = '';
                } else {
                    $this->create_status = self::CREATE_STATUS_PARAMS_MISMATCH;
                    return false;
                }
            }
            if (strcmp($params[$name], (string) $value) === 0) {
                unset($params[$name]);
                if (isset($this->_param_rules[$name])) {
                    $tr["<{$name}>"] = '';
                }
            } elseif (!isset($this->_param_rules[$name])) {
                $this->create_status = self::CREATE_STATUS_PARAMS_MISMATCH;
                return false;
            }
        }
        // match params in the pattern
        foreach ($this->_param_rules as $name => $rule) {
            if (isset($params[$name]) && !is_array($params[$name]) && ($rule === '' || preg_match($rule, $params[$name]))) {
                $tr["<{$name}>"] = $this->encode_params ? urlencode($params[$name]) : $params[$name];
                unset($params[$name]);
            } elseif (!isset($this->defaults[$name]) || isset($params[$name])) {
                $this->create_status = self::CREATE_STATUS_PARAMS_MISMATCH;
                return false;
            }
        }
        $url = $this->trim_slashes(strtr($this->_template, $tr));
        if ($this->host !== null) {
            $pos = strpos($url, '/', 8);
            if ($pos !== false) {
                $url = substr($url, 0, $pos) . preg_replace('#/+#', '/', substr($url, $pos));
            }
        } elseif (strpos($url, '//') !== false) {
            $url = preg_replace('#/+#', '/', trim($url, '/'));
        }
        if ($url !== '') {
            $url .= $this->suffix ?? $manager->suffix;
        }
        if (!empty($params) && ($query = http_build_query($params)) !== '') {
            $url .= '?' . $query;
        }
        $this->create_status = self::CREATE_STATUS_SUCCESS;
        return $url;
    }
    /**
     * Returns status of the URL creation after the last [[createUrl()]] call.
     *
     * @return int|null Status of the URL creation after the last [[createUrl()]] call. `null` if rule does not provide
     * info about create status.
     * @see createStatus
     * @since 2.0.12
     */
    public function get_create_url_status()
    {
        return $this->create_status;
    }
    /**
     * Returns list of regex for matching parameter.
     * @return array parameter keys and regexp rules.
     *
     * @since 2.0.6
     */
    protected function get_param_rules()
    {
        return $this->_param_rules;
    }
    /**
     * Iterates over [[placeholders]] and checks whether each placeholder exists as a key in $matches array.
     * When found - replaces this placeholder key with a appropriate name of matching parameter.
     * Used in [[parseRequest()]], [[createUrl()]].
     *
     * @param array $matches result of `preg_match()` call
     * @return array input array with replaced placeholder keys
     * @see placeholders
     * @since 2.0.7
     */
    protected function substitute_placeholder_names(array $matches): array
    {
        foreach ($this->placeholders as $placeholder => $name) {
            if (isset($matches[$placeholder])) {
                $matches[$name] = $matches[$placeholder];
                unset($matches[$placeholder]);
            }
        }
        return $matches;
    }
    /**
     * Trim slashes in passed string. If string begins with '//', two slashes are left as is
     * in the beginning of a string.
     *
     * @param string $string
     */
    private function trim_slashes($string): string
    {
        if (strncmp($string, '//', 2) === 0) {
            return '//' . trim($string, '/');
        }
        return trim($string, '/');
    }
}