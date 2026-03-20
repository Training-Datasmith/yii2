<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\web;

use Yii;
use yii\base\Invalid_Config_Exception;
/**
 * GroupUrlRule represents a collection of URL rules sharing the same prefix in their patterns and routes.
 *
 * GroupUrlRule is best used by a module which often uses module ID as the prefix for the URL rules.
 * For example, the following code creates a rule for the `admin` module:
 *
 * ```
 * new GroupUrlRule([
 *     'prefix' => 'admin',
 *     'rules' => [
 *         'login' => 'user/login',
 *         'logout' => 'user/logout',
 *         'dashboard' => 'default/dashboard',
 *     ],
 * ]);
 *
 * // the above rule is equivalent to the following three rules:
 *
 * [
 *     'admin/login' => 'admin/user/login',
 *     'admin/logout' => 'admin/user/logout',
 *     'admin/dashboard' => 'admin/default/dashboard',
 * ]
 * ```
 *
 * The above example assumes the prefix for patterns and routes are the same. They can be made different
 * by configuring [[prefix]] and [[routePrefix]] separately.
 *
 * Using a GroupUrlRule is more efficient than directly declaring the individual rules it contains.
 * This is because GroupUrlRule can quickly determine if it should process a URL parsing or creation request
 * by simply checking if the prefix matches.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Group_Url_Rule extends Composite_Url_Rule
{
    /**
     * @var UrlRuleInterface[]|array[]|string[] the rules contained within this composite rule. Please refer to [[UrlManager::rules]]
     * for the format of this property.
     * @see prefix
     * @see routePrefix
     */
    public $rules = [];
    /**
     * @var string the prefix for the pattern part of every rule declared in [[rules]].
     * The prefix and the pattern will be separated with a slash.
     */
    public $prefix;
    /**
     * @var string|null the prefix for the route part of every rule declared in [[rules]].
     * The prefix and the route will be separated with a slash.
     * If this property is not set, it will take the value of [[prefix]].
     */
    public $route_prefix;
    /**
     * @var array the default configuration of URL rules. Individual rule configurations
     * specified via [[rules]] will take precedence when the same property of the rule is configured.
     */
    public $rule_config = ['class' => 'yii\web\UrlRule'];
    /**
     * {@inheritdoc}
     */
    public function init(): void
    {
        $this->prefix = trim((string) $this->prefix, '/');
        $this->route_prefix = $this->route_prefix === null ? $this->prefix : trim($this->route_prefix, '/');
        parent::init();
    }
    /**
     * {@inheritdoc}
     * @return \yii\web\UrlRuleInterface[]
     */
    protected function create_rules(): array
    {
        $rules = [];
        foreach ($this->rules as $key => $rule) {
            if (!is_array($rule)) {
                $verbs = 'GET|HEAD|POST|PUT|PATCH|DELETE|OPTIONS';
                $verb = null;
                if (preg_match("/^((?:(?:{$verbs}),)*(?:{$verbs}))\\s+(.*)\$/", $key, $matches)) {
                    $verb = explode(',', $matches[1]);
                    $key = $matches[2];
                }
                $rule = ['pattern' => ltrim($this->prefix . '/' . $key, '/'), 'route' => ltrim($this->route_prefix . '/' . $rule, '/'), 'verb' => $verb];
            } elseif (isset($rule['pattern'], $rule['route'])) {
                $rule['pattern'] = ltrim($this->prefix . '/' . $rule['pattern'], '/');
                $rule['route'] = ltrim($this->route_prefix . '/' . $rule['route'], '/');
            }
            $rule = Yii::create_object(array_merge($this->rule_config, $rule));
            if (!$rule instanceof Url_Rule_Interface) {
                throw new Invalid_Config_Exception('URL rule class must implement UrlRuleInterface.');
            }
            $rules[] = $rule;
        }
        return $rules;
    }
    /**
     * {@inheritdoc}
     */
    public function parse_request($manager, $request)
    {
        $path_info = $request->get_path_info();
        if ($this->prefix === '' || strpos($path_info . '/', $this->prefix . '/') === 0) {
            return parent::parse_request($manager, $request);
        }
        return false;
    }
    /**
     * {@inheritdoc}
     */
    public function create_url($manager, $route, $params)
    {
        if ($this->route_prefix === '' || strpos($route, $this->route_prefix . '/') === 0) {
            return parent::create_url($manager, $route, $params);
        }
        $this->create_status = Url_Rule::CREATE_STATUS_ROUTE_MISMATCH;
        return false;
    }
}