<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\rest;

use Yii;
use yii\base\Invalid_Config_Exception;
use yii\helpers\Inflector;
use yii\web\Composite_Url_Rule;
use yii\web\Url_Rule as WebUrlRule;
use yii\web\Url_Rule_Interface;
/**
 * UrlRule is provided to simplify the creation of URL rules for RESTful API support.
 *
 * The simplest usage of UrlRule is to declare a rule like the following in the application configuration,
 *
 * ```
 * [
 *     'class' => 'yii\rest\UrlRule',
 *     'controller' => 'user',
 * ]
 * ```
 *
 * The above code will create a whole set of URL rules supporting the following RESTful API endpoints:
 *
 * - `'PUT,PATCH users/<id>' => 'user/update'`: update a user
 * - `'DELETE users/<id>' => 'user/delete'`: delete a user
 * - `'GET,HEAD users/<id>' => 'user/view'`: return the details/overview/options of a user
 * - `'POST users' => 'user/create'`: create a new user
 * - `'GET,HEAD users' => 'user/index'`: return a list/overview/options of users
 * - `'users/<id>' => 'user/options'`: process all unhandled verbs of a user
 * - `'users' => 'user/options'`: process all unhandled verbs of user collection
 *
 * You may configure [[only]] and/or [[except]] to disable some of the above rules.
 * You may configure [[patterns]] to completely redefine your own list of rules.
 * You may configure [[controller]] with multiple controller IDs to generate rules for all these controllers.
 * For example, the following code will disable the `delete` rule and generate rules for both `user` and `post` controllers:
 *
 * ```
 * [
 *     'class' => 'yii\rest\UrlRule',
 *     'controller' => ['user', 'post'],
 *     'except' => ['delete'],
 * ]
 * ```
 *
 * The property [[controller]] is required and should represent one or multiple controller IDs.
 * Each controller ID should be prefixed with the module ID if the controller is within a module.
 * The controller ID used in the pattern will be automatically pluralized (e.g. `user` becomes `users`
 * as shown in the above examples).
 *
 * For more details and usage information on UrlRule, see the [guide article on rest routing](guide:rest-routing).
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Url_Rule extends Composite_Url_Rule
{
    /**
     * @var string|null the common prefix string shared by all patterns.
     */
    public $prefix;
    /**
     * @var string the suffix that will be assigned to [[\yii\web\UrlRule::suffix]] for every generated rule.
     */
    public $suffix;
    /**
     * @var string|array the controller ID (e.g. `user`, `post-comment`) that the rules in this composite rule
     * are dealing with. It should be prefixed with the module ID if the controller is within a module (e.g. `admin/user`).
     *
     * By default, the controller ID will be pluralized automatically when it is put in the patterns of the
     * generated rules. If you want to explicitly specify how the controller ID should appear in the patterns,
     * you may use an array with the array key being as the controller ID in the pattern, and the array value
     * the actual controller ID. For example, `['u' => 'user']`.
     *
     * You may also pass multiple controller IDs as an array. If this is the case, this composite rule will
     * generate applicable URL rules for EVERY specified controller. For example, `['user', 'post']`.
     */
    public $controller;
    /**
     * @var array list of acceptable actions. If not empty, only the actions within this array
     * will have the corresponding URL rules created.
     * @see patterns
     */
    public $only = [];
    /**
     * @var array list of actions that should be excluded. Any action found in this array
     * will NOT have its URL rules created.
     * @see patterns
     */
    public $except = [];
    /**
     * @var array patterns for supporting extra actions in addition to those listed in [[patterns]].
     * The keys are the patterns and the values are the corresponding action IDs.
     * These extra patterns will take precedence over [[patterns]].
     */
    public $extra_patterns = [];
    /**
     * @var array list of tokens that should be replaced for each pattern. The keys are the token names,
     * and the values are the corresponding replacements.
     * @see patterns
     */
    public $tokens = ['{id}' => '<id:\d[\d,]*>'];
    /**
     * @var array list of possible patterns and the corresponding actions for creating the URL rules.
     * The keys are the patterns and the values are the corresponding actions.
     * The format of patterns is `Verbs Pattern`, where `Verbs` stands for a list of HTTP verbs separated
     * by comma (without space). If `Verbs` is not specified, it means all verbs are allowed.
     * `Pattern` is optional. It will be prefixed with [[prefix]]/[[controller]]/,
     * and tokens in it will be replaced by [[tokens]].
     */
    public $patterns = ['PUT,PATCH {id}' => 'update', 'DELETE {id}' => 'delete', 'GET,HEAD {id}' => 'view', 'POST' => 'create', 'GET,HEAD' => 'index', '{id}' => 'options', '' => 'options'];
    /**
     * @var array the default configuration for creating each URL rule contained by this rule.
     */
    public $rule_config = ['class' => 'yii\web\UrlRule'];
    /**
     * @var bool whether to automatically pluralize the URL names for controllers.
     * If true, a controller ID will appear in plural form in URLs. For example, `user` controller
     * will appear as `users` in URLs.
     * @see controller
     */
    public $pluralize = true;
    /**
     * {@inheritdoc}
     */
    public function init(): void
    {
        if (empty($this->controller)) {
            throw new Invalid_Config_Exception('"controller" must be set.');
        }
        $controllers = [];
        foreach ((array) $this->controller as $url_name => $controller) {
            if (is_int($url_name)) {
                $url_name = $this->pluralize ? Inflector::pluralize($controller) : $controller;
            }
            $controllers[$url_name] = $controller;
        }
        $this->controller = $controllers;
        $this->prefix = trim((string) $this->prefix, '/');
        parent::init();
    }
    /**
     * {@inheritdoc}
     * @return non-empty-list[]
     */
    protected function create_rules(): array
    {
        $only = array_flip($this->only);
        $except = array_flip($this->except);
        $patterns = $this->extra_patterns + $this->patterns;
        $rules = [];
        foreach ($this->controller as $url_name => $controller) {
            $prefix = trim($this->prefix . '/' . $url_name, '/');
            foreach ($patterns as $pattern => $action) {
                if (!isset($except[$action]) && (empty($only) || isset($only[$action]))) {
                    $rules[$url_name][] = $this->create_rule($pattern, $prefix, $controller . '/' . $action);
                }
            }
        }
        return $rules;
    }
    /**
     * Creates a URL rule using the given pattern and action.
     * @param string $pattern
     * @param string $action
     * @return UrlRuleInterface
     */
    protected function create_rule($pattern, string $prefix, $action)
    {
        $verbs = 'GET|HEAD|POST|PUT|PATCH|DELETE|OPTIONS';
        if (preg_match("/^((?:({$verbs}),)*({$verbs}))(?:\\s+(.*))?\$/", $pattern, $matches)) {
            $verbs = explode(',', $matches[1]);
            $pattern = $matches[4] ?? '';
        } else {
            $verbs = [];
        }
        $config = $this->rule_config;
        $config['verb'] = $verbs;
        $config['pattern'] = rtrim($prefix . '/' . strtr($pattern, $this->tokens), '/');
        $config['route'] = $action;
        $config['suffix'] = $this->suffix;
        return Yii::create_object($config);
    }
    /**
     * {@inheritdoc}
     */
    public function parse_request($manager, $request)
    {
        $path_info = $request->get_path_info();
        if ($this->prefix !== '' && strpos($this->prefix, '<') === false && strpos($path_info . '/', $this->prefix . '/') !== 0) {
            return false;
        }
        foreach ($this->rules as $url_name => $rules) {
            if (strpos($path_info, (string) $url_name) !== false) {
                foreach ($rules as $rule) {
                    /** @var WebUrlRule $rule */
                    $result = $rule->parse_request($manager, $request);
                    Yii::debug(['rule' => method_exists($rule, '__toString') ? $rule->__toString() : get_class($rule), 'match' => $result !== false, 'parent' => self::class_name()], __METHOD__);
                    if ($result !== false) {
                        return $result;
                    }
                }
            }
        }
        return false;
    }
    /**
     * {@inheritdoc}
     */
    public function create_url($manager, $route, $params)
    {
        $this->create_status = Web_Url_Rule::CREATE_STATUS_SUCCESS;
        foreach ($this->controller as $url_name => $controller) {
            if (strpos($route, (string) $controller) !== false) {
                /** @var UrlRuleInterface[] $rules */
                $rules = $this->rules[$url_name];
                $url = $this->iterate_rules($rules, $manager, $route, $params);
                if ($url !== false) {
                    return $url;
                }
            } else {
                $this->create_status |= Web_Url_Rule::CREATE_STATUS_ROUTE_MISMATCH;
            }
        }
        if ($this->create_status === Web_Url_Rule::CREATE_STATUS_SUCCESS) {
            // create status was not changed - there is no rules configured
            $this->create_status = Web_Url_Rule::CREATE_STATUS_PARSING_ONLY;
        }
        return false;
    }
}