<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\web;

use Yii;
use yii\base\Component;
use yii\base\Invalid_Config_Exception;
use yii\caching\Cache_Interface;
use yii\di\Instance;
use yii\helpers\Url;
/**
 * UrlManager handles HTTP request parsing and creation of URLs based on a set of rules.
 *
 * UrlManager is configured as an application component in [[\yii\base\Application]] by default.
 * You can access that instance via `Yii::$app->urlManager`.
 *
 * You can modify its configuration by adding an array to your application config under `components`
 * as it is shown in the following example:
 *
 * ```
 * 'urlManager' => [
 *     'enablePrettyUrl' => true,
 *     'rules' => [
 *         // your rules go here
 *     ],
 *     // ...
 * ]
 * ```
 *
 * Rules are classes implementing the [[UrlRuleInterface]], by default that is [[UrlRule]].
 * For nesting rules, there is also a [[GroupUrlRule]] class.
 *
 * For more details and usage information on UrlManager, see the [guide article on routing](guide:runtime-routing).
 *
 * @property string $baseUrl The base URL that is used by [[createUrl()]] to prepend to created URLs.
 * @property string $hostInfo The host info (e.g. `https://www.example.com`) that is used by
 * [[createAbsoluteUrl()]] to prepend to created URLs.
 * @property string $scriptUrl The entry script URL that is used by [[createUrl()]] to prepend to created
 * URLs.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Url_Manager extends Component
{
    /**
     * @var bool whether to enable pretty URLs. Instead of putting all parameters in the query
     * string part of a URL, pretty URLs allow using path info to represent some of the parameters
     * and can thus produce more user-friendly URLs, such as "/news/Yii-is-released", instead of
     * "/index.php?r=news%2Fview&id=100".
     */
    public $enable_pretty_url = false;
    /**
     * @var bool whether to enable strict parsing. If strict parsing is enabled, the incoming
     * requested URL must match at least one of the [[rules]] in order to be treated as a valid request.
     * Otherwise, the path info part of the request will be treated as the requested route.
     * This property is used only when [[enablePrettyUrl]] is `true`.
     */
    public $enable_strict_parsing = false;
    /**
     * @var array the rules for creating and parsing URLs when [[enablePrettyUrl]] is `true`.
     * This property is used only if [[enablePrettyUrl]] is `true`. Each element in the array
     * is the configuration array for creating a single URL rule. The configuration will
     * be merged with [[ruleConfig]] first before it is used for creating the rule object.
     *
     * A special shortcut format can be used if a rule only specifies [[UrlRule::pattern|pattern]]
     * and [[UrlRule::route|route]]: `'pattern' => 'route'`. That is, instead of using a configuration
     * array, one can use the key to represent the pattern and the value the corresponding route.
     * For example, `'post/<id:\d+>' => 'post/view'`.
     *
     * For RESTful routing the mentioned shortcut format also allows you to specify the
     * [[UrlRule::verb|HTTP verb]] that the rule should apply for.
     * You can do that  by prepending it to the pattern, separated by space.
     * For example, `'PUT post/<id:\d+>' => 'post/update'`.
     * You may specify multiple verbs by separating them with comma
     * like this: `'POST,PUT post/index' => 'post/create'`.
     * The supported verbs in the shortcut format are: GET, HEAD, POST, PUT, PATCH and DELETE.
     * Note that [[UrlRule::mode|mode]] will be set to PARSING_ONLY when specifying verb in this way
     * so you normally would not specify a verb for normal GET request.
     *
     * Here is an example configuration for RESTful CRUD controller:
     *
     * ```
     * [
     *     'dashboard' => 'site/index',
     *
     *     'POST <controller:[\w-]+>' => '<controller>/create',
     *     '<controller:[\w-]+>s' => '<controller>/index',
     *
     *     'PUT <controller:[\w-]+>/<id:\d+>'    => '<controller>/update',
     *     'DELETE <controller:[\w-]+>/<id:\d+>' => '<controller>/delete',
     *     '<controller:[\w-]+>/<id:\d+>'        => '<controller>/view',
     * ];
     * ```
     *
     * Note that if you modify this property after the UrlManager object is created, make sure
     * you populate the array with rule objects instead of rule configurations.
     */
    public $rules = [];
    /**
     * @var string the URL suffix used when [[enablePrettyUrl]] is `true`.
     * For example, ".html" can be used so that the URL looks like pointing to a static HTML page.
     * This property is used only if [[enablePrettyUrl]] is `true`.
     */
    public $suffix;
    /**
     * @var bool whether to show entry script name in the constructed URL. Defaults to `true`.
     * This property is used only if [[enablePrettyUrl]] is `true`.
     */
    public $show_script_name = true;
    /**
     * @var string the GET parameter name for route. This property is used only if [[enablePrettyUrl]] is `false`.
     */
    public $route_param = 'r';
    /**
     * @var CacheInterface|array|string|bool|null the cache object or the application component ID of the cache object.
     * This can also be an array that is used to create a [[CacheInterface]] instance in case you do not want to use
     * an application component.
     * Compiled URL rules will be cached through this cache object, if it is available.
     *
     * After the UrlManager object is created, if you want to change this property,
     * you should only assign it with a cache object.
     * Set this property to `false` or `null` if you do not want to cache the URL rules.
     *
     * Cache entries are stored for the time set by [[\yii\caching\Cache::$defaultDuration|$defaultDuration]] in
     * the cache configuration, which is unlimited by default. You may want to tune this value if your [[rules]]
     * change frequently.
     */
    public $cache = 'cache';
    /**
     * @var array the default configuration of URL rules. Individual rule configurations
     * specified via [[rules]] will take precedence when the same property of the rule is configured.
     */
    public $rule_config = ['class' => 'yii\web\UrlRule'];
    /**
     * @var UrlNormalizer|array|string|false the configuration for [[UrlNormalizer]] used by this UrlManager.
     * The default value is `false`, which means normalization will be skipped.
     * If you wish to enable URL normalization, you should configure this property manually.
     * For example:
     *
     * ```
     * [
     *     'class' => 'yii\web\UrlNormalizer',
     *     'collapseSlashes' => true,
     *     'normalizeTrailingSlash' => true,
     * ]
     * ```
     *
     * @since 2.0.10
     */
    public $normalizer = false;
    /**
     * @var string the cache key for cached rules
     * @since 2.0.8
     */
    protected $cache_key = self::class;
    private $_base_url;
    private $_script_url;
    private $_host_info;
    private ?array $_rule_cache = null;
    /**
     * Initializes UrlManager.
     */
    public function init(): void
    {
        parent::init();
        if ($this->normalizer !== false) {
            $this->normalizer = Yii::create_object($this->normalizer);
            if (!$this->normalizer instanceof Url_Normalizer) {
                throw new Invalid_Config_Exception('`' . get_class($this) . '::normalizer` should be an instance of `' . Url_Normalizer::class_name() . '` or its DI compatible configuration.');
            }
        }
        if (!$this->enable_pretty_url) {
            return;
        }
        if (!empty($this->rules)) {
            $this->rules = $this->build_rules($this->rules);
        }
    }
    /**
     * Adds additional URL rules.
     *
     * This method will call [[buildRules()]] to parse the given rule declarations and then append or insert
     * them to the existing [[rules]].
     *
     * Note that if [[enablePrettyUrl]] is `false`, this method will do nothing.
     *
     * @param array $rules the new rules to be added. Each array element represents a single rule declaration.
     * Please refer to [[rules]] for the acceptable rule format.
     * @param bool $append whether to add the new rules by appending them to the end of the existing rules.
     */
    public function add_rules($rules, $append = true): void
    {
        if (!$this->enable_pretty_url) {
            return;
        }
        $rules = $this->build_rules($rules);
        if ($append) {
            $this->rules = array_merge($this->rules, $rules);
        } else {
            $this->rules = array_merge($rules, $this->rules);
        }
    }
    /**
     * Builds URL rule objects from the given rule declarations.
     *
     * @param array $ruleDeclarations the rule declarations. Each array element represents a single rule declaration.
     * Please refer to [[rules]] for the acceptable rule formats.
     * @return UrlRuleInterface[] the rule objects built from the given rule declarations
     * @throws InvalidConfigException if a rule declaration is invalid
     */
    protected function build_rules($rule_declarations)
    {
        $built_rules = $this->get_built_rules_from_cache($rule_declarations);
        if ($built_rules !== false) {
            return $built_rules;
        }
        $built_rules = [];
        $verbs = 'GET|HEAD|POST|PUT|PATCH|DELETE|OPTIONS';
        foreach ($rule_declarations as $key => $rule) {
            if (is_string($rule)) {
                $rule = ['route' => $rule];
                if (preg_match("/^((?:({$verbs}),)*({$verbs}))\\s+(.*)\$/", $key, $matches)) {
                    $rule['verb'] = explode(',', $matches[1]);
                    $key = $matches[4];
                }
                $rule['pattern'] = $key;
            }
            if (is_array($rule)) {
                $rule = Yii::create_object(array_merge($this->rule_config, $rule));
            }
            if (!$rule instanceof Url_Rule_Interface) {
                throw new Invalid_Config_Exception('URL rule class must implement UrlRuleInterface.');
            }
            $built_rules[] = $rule;
        }
        $this->set_built_rules_cache($rule_declarations, $built_rules);
        return $built_rules;
    }
    /**
     * @return CacheInterface|null|bool
     */
    private function ensure_cache()
    {
        if (!$this->cache instanceof Cache_Interface && $this->cache !== false && $this->cache !== null) {
            try {
                $this->cache = Instance::ensure($this->cache, 'yii\caching\CacheInterface');
            } catch (Invalid_Config_Exception $e) {
                Yii::warning('Unable to use cache for URL manager: ' . $e->get_message());
                $this->cache = null;
            }
        }
        return $this->cache;
    }
    /**
     * Stores $builtRules to cache, using $rulesDeclaration as a part of cache key.
     *
     * @param array $ruleDeclarations the rule declarations. Each array element represents a single rule declaration.
     * Please refer to [[rules]] for the acceptable rule formats.
     * @param UrlRuleInterface[] $builtRules the rule objects built from the given rule declarations.
     * @return bool whether the value is successfully stored into cache
     * @since 2.0.14
     */
    protected function set_built_rules_cache($rule_declarations, $built_rules)
    {
        $cache = $this->ensure_cache();
        if (!$cache) {
            return false;
        }
        return $cache->set([$this->cache_key, $this->rule_config, $rule_declarations], $built_rules);
    }
    /**
     * Provides the built URL rules that are associated with the $ruleDeclarations from cache.
     *
     * @param array $ruleDeclarations the rule declarations. Each array element represents a single rule declaration.
     * Please refer to [[rules]] for the acceptable rule formats.
     * @return UrlRuleInterface[]|false the rule objects built from the given rule declarations or boolean `false` when
     * there are no cache items for this definition exists.
     * @since 2.0.14
     */
    protected function get_built_rules_from_cache($rule_declarations)
    {
        $cache = $this->ensure_cache();
        if (!$cache) {
            return false;
        }
        return $cache->get([$this->cache_key, $this->rule_config, $rule_declarations]);
    }
    /**
     * Parses the user request.
     * @param Request $request the request component
     * @return array|bool the route and the associated parameters. The latter is always empty
     * if [[enablePrettyUrl]] is `false`. `false` is returned if the current request cannot be successfully parsed.
     */
    public function parse_request($request)
    {
        if ($this->enable_pretty_url) {
            /** @var UrlRule $rule */
            foreach ($this->rules as $rule) {
                $result = $rule->parse_request($this, $request);
                Yii::debug(['rule' => method_exists($rule, '__toString') ? $rule->__toString() : get_class($rule), 'match' => $result !== false, 'parent' => null], __METHOD__);
                if ($result !== false) {
                    return $result;
                }
            }
            if ($this->enable_strict_parsing) {
                return false;
            }
            Yii::debug('No matching URL rules. Using default URL parsing logic.', __METHOD__);
            $suffix = (string) $this->suffix;
            $path_info = $request->get_path_info();
            $normalized = false;
            if ($this->normalizer !== false) {
                $path_info = $this->normalizer->normalize_path_info($path_info, $suffix, $normalized);
            }
            if ($suffix !== '' && $path_info !== '') {
                $n = strlen($this->suffix);
                if (substr_compare($path_info, $this->suffix, -$n, $n) === 0) {
                    $path_info = substr($path_info, 0, -$n);
                    if ($path_info === '') {
                        // suffix alone is not allowed
                        return false;
                    }
                } else {
                    // suffix doesn't match
                    return false;
                }
            }
            if ($normalized) {
                // pathInfo was changed by normalizer - we need also normalize route
                return $this->normalizer->normalize_route([$path_info, []]);
            }
            return [$path_info, []];
        }
        Yii::debug('Pretty URL not enabled. Using default URL parsing logic.', __METHOD__);
        $route = $request->get_query_param($this->route_param, '');
        if (is_array($route)) {
            $route = '';
        }
        return [(string) $route, []];
    }
    /**
     * Creates a URL using the given route and query parameters.
     *
     * You may specify the route as a string, e.g., `site/index`. You may also use an array
     * if you want to specify additional query parameters for the URL being created. The
     * array format must be:
     *
     * ```
     * // generates: /index.php?r=site%2Findex&param1=value1&param2=value2
     * ['site/index', 'param1' => 'value1', 'param2' => 'value2']
     * ```
     *
     * If you want to create a URL with an anchor, you can use the array format with a `#` parameter.
     * For example,
     *
     * ```
     * // generates: /index.php?r=site%2Findex&param1=value1#name
     * ['site/index', 'param1' => 'value1', '#' => 'name']
     * ```
     *
     * The URL created is a relative one. Use [[createAbsoluteUrl()]] to create an absolute URL.
     *
     * Note that unlike [[\yii\helpers\Url::toRoute()]], this method always treats the given route
     * as an absolute route.
     *
     * @param string|array $params use a string to represent a route (e.g. `site/index`),
     * or an array to represent a route with query parameters (e.g. `['site/index', 'param1' => 'value1']`).
     * @return string the created URL
     */
    public function create_url($params): string
    {
        $params = (array) $params;
        $anchor = isset($params['#']) ? '#' . $params['#'] : '';
        unset($params['#'], $params[$this->route_param]);
        $route = trim($params[0] ?? '', '/');
        unset($params[0]);
        $base_url = $this->show_script_name || !$this->enable_pretty_url ? $this->get_script_url() : $this->get_base_url();
        if ($this->enable_pretty_url) {
            $cache_key = $route . '?';
            foreach ($params as $key => $value) {
                if ($value !== null) {
                    $cache_key .= $key . '&';
                }
            }
            $url = $this->get_url_from_cache($cache_key, $route, $params);
            if ($url === false) {
                /** @var UrlRule $rule */
                foreach ($this->rules as $rule) {
                    if (in_array($rule, $this->_rule_cache[$cache_key], true)) {
                        // avoid redundant calls of `UrlRule::createUrl()` for rules checked in `getUrlFromCache()`
                        // @see https://github.com/yiisoft/yii2/issues/14094
                        continue;
                    }
                    $url = $rule->create_url($this, $route, $params);
                    if ($this->can_be_cached($rule)) {
                        $this->set_rule_to_cache($cache_key, $rule);
                    }
                    if ($url !== false) {
                        break;
                    }
                }
            }
            if ($url !== false) {
                if (strpos($url, '://') !== false) {
                    if ($base_url !== '' && ($pos = strpos($url, '/', 8)) !== false) {
                        return substr($url, 0, $pos) . $base_url . substr($url, $pos) . $anchor;
                    }
                    return $url . $base_url . $anchor;
                }
                if (strncmp($url, '//', 2) === 0) {
                    if ($base_url !== '' && ($pos = strpos($url, '/', 2)) !== false) {
                        return substr($url, 0, $pos) . $base_url . substr($url, $pos) . $anchor;
                    }
                    return $url . $base_url . $anchor;
                }
                $url = ltrim($url, '/');
                return "{$base_url}/{$url}{$anchor}";
            }
            if ($this->suffix !== null) {
                $route .= $this->suffix;
            }
            if (!empty($params) && ($query = http_build_query($params)) !== '') {
                $route .= '?' . $query;
            }
            $route = ltrim($route, '/');
            return "{$base_url}/{$route}{$anchor}";
        }
        $url = "{$base_url}?{$this->route_param}=" . urlencode($route);
        if (!empty($params) && ($query = http_build_query($params)) !== '') {
            $url .= '&' . $query;
        }
        return $url . $anchor;
    }
    /**
     * Returns the value indicating whether result of [[createUrl()]] of rule should be cached in internal cache.
     *
     * @return bool `true` if result should be cached, `false` if not.
     * @since 2.0.12
     * @see getUrlFromCache()
     * @see setRuleToCache()
     * @see UrlRule::getCreateUrlStatus()
     */
    protected function can_be_cached(Url_Rule_Interface $rule): bool
    {
        return !method_exists($rule, 'getCreateUrlStatus') || ($status = $rule->get_create_url_status()) === null || $status === Url_Rule::CREATE_STATUS_SUCCESS || $status & Url_Rule::CREATE_STATUS_PARAMS_MISMATCH;
    }
    /**
     * Get URL from internal cache if exists.
     * @param string $cacheKey generated cache key to store data.
     * @param string $route the route (e.g. `site/index`).
     * @param array $params rule params.
     * @return bool|string the created URL
     * @see createUrl()
     * @since 2.0.8
     */
    protected function get_url_from_cache($cache_key, $route, $params)
    {
        if (!empty($this->_rule_cache[$cache_key])) {
            foreach ($this->_rule_cache[$cache_key] as $rule) {
                /** @var UrlRule $rule */
                if (($url = $rule->create_url($this, $route, $params)) !== false) {
                    return $url;
                }
            }
        } else {
            $this->_rule_cache[$cache_key] = [];
        }
        return false;
    }
    /**
     * Store rule (e.g. [[UrlRule]]) to internal cache.
     * @param $cacheKey
     * @since 2.0.8
     */
    protected function set_rule_to_cache($cache_key, Url_Rule_Interface $rule)
    {
        $this->_rule_cache[$cache_key][] = $rule;
    }
    /**
     * Creates an absolute URL using the given route and query parameters.
     *
     * This method prepends the URL created by [[createUrl()]] with the [[hostInfo]].
     *
     * Note that unlike [[\yii\helpers\Url::toRoute()]], this method always treats the given route
     * as an absolute route.
     *
     * @param string|array $params use a string to represent a route (e.g. `site/index`),
     * or an array to represent a route with query parameters (e.g. `['site/index', 'param1' => 'value1']`).
     * @param string|null $scheme the scheme to use for the URL (either `http`, `https` or empty string
     * for protocol-relative URL).
     * If not specified the scheme of the current request will be used.
     * @return string the created URL
     * @see createUrl()
     */
    public function create_absolute_url($params, $scheme = null)
    {
        $params = (array) $params;
        $url = $this->create_url($params);
        if (strpos($url, '://') === false) {
            $host_info = $this->get_host_info();
            if (strncmp($url, '//', 2) === 0) {
                $url = substr($host_info, 0, strpos($host_info, '://')) . ':' . $url;
            } else {
                $url = $host_info . $url;
            }
        }
        return Url::ensure_scheme($url, $scheme);
    }
    /**
     * Returns the base URL that is used by [[createUrl()]] to prepend to created URLs.
     * It defaults to [[Request::baseUrl]].
     * This is mainly used when [[enablePrettyUrl]] is `true` and [[showScriptName]] is `false`.
     * @return string the base URL that is used by [[createUrl()]] to prepend to created URLs.
     * @throws InvalidConfigException if running in console application and [[baseUrl]] is not configured.
     */
    public function get_base_url()
    {
        if ($this->_base_url === null) {
            $request = Yii::$app->get_request();
            if ($request instanceof Request) {
                $this->_base_url = $request->get_base_url();
            } else {
                throw new Invalid_Config_Exception('Please configure UrlManager::baseUrl correctly as you are running a console application.');
            }
        }
        return $this->_base_url;
    }
    /**
     * Sets the base URL that is used by [[createUrl()]] to prepend to created URLs.
     * This is mainly used when [[enablePrettyUrl]] is `true` and [[showScriptName]] is `false`.
     * @param string $value the base URL that is used by [[createUrl()]] to prepend to created URLs.
     */
    public function set_base_url($value): void
    {
        $this->_base_url = $value === null ? null : rtrim(Yii::get_alias($value), '/');
    }
    /**
     * Returns the entry script URL that is used by [[createUrl()]] to prepend to created URLs.
     * It defaults to [[Request::scriptUrl]].
     * This is mainly used when [[enablePrettyUrl]] is `false` or [[showScriptName]] is `true`.
     * @return string the entry script URL that is used by [[createUrl()]] to prepend to created URLs.
     * @throws InvalidConfigException if running in console application and [[scriptUrl]] is not configured.
     */
    public function get_script_url()
    {
        if ($this->_script_url === null) {
            $request = Yii::$app->get_request();
            if ($request instanceof Request) {
                $this->_script_url = $request->get_script_url();
            } else {
                throw new Invalid_Config_Exception('Please configure UrlManager::scriptUrl correctly as you are running a console application.');
            }
        }
        return $this->_script_url;
    }
    /**
     * Sets the entry script URL that is used by [[createUrl()]] to prepend to created URLs.
     * This is mainly used when [[enablePrettyUrl]] is `false` or [[showScriptName]] is `true`.
     * @param string $value the entry script URL that is used by [[createUrl()]] to prepend to created URLs.
     */
    public function set_script_url($value): void
    {
        $this->_script_url = $value;
    }
    /**
     * Returns the host info that is used by [[createAbsoluteUrl()]] to prepend to created URLs.
     * @return string the host info (e.g. `https://www.example.com`) that is used by [[createAbsoluteUrl()]] to prepend to created URLs.
     * @throws InvalidConfigException if running in console application and [[hostInfo]] is not configured.
     */
    public function get_host_info()
    {
        if ($this->_host_info === null) {
            $request = Yii::$app->get_request();
            if ($request instanceof \yii\web\Request) {
                $this->_host_info = $request->get_host_info();
            } else {
                throw new Invalid_Config_Exception('Please configure UrlManager::hostInfo correctly as you are running a console application.');
            }
        }
        return $this->_host_info;
    }
    /**
     * Sets the host info that is used by [[createAbsoluteUrl()]] to prepend to created URLs.
     * @param string $value the host info (e.g. "https://www.example.com") that is used by [[createAbsoluteUrl()]] to prepend to created URLs.
     */
    public function set_host_info($value): void
    {
        $this->_host_info = $value === null ? null : rtrim($value, '/');
    }
}