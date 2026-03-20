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
/**
 * CompositeUrlRule is the base class for URL rule classes that consist of multiple simpler rules.
 *
 * @property-read int|null $createUrlStatus Status of the URL creation after the last [[createUrl()]] call.
 * `null` if rule does not provide info about create status.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
abstract class Composite_Url_Rule extends Base_Object implements Url_Rule_Interface
{
    /**
     * @var UrlRuleInterface[]|UrlRuleInterface[][]|array[]|string[] the URL rules contained in this composite rule.
     * This property is set in [[init()]] by the return value of [[createRules()]].
     */
    protected $rules = [];
    /**
     * @var int|null status of the URL creation after the last [[createUrl()]] call.
     * @since 2.0.12
     */
    protected $create_status;
    /**
     * Creates the URL rules that should be contained within this composite rule.
     * @return UrlRuleInterface[]|UrlRuleInterface[][] the URL rules
     */
    abstract protected function create_rules();
    /**
     * {@inheritdoc}
     */
    public function init(): void
    {
        parent::init();
        $this->rules = $this->create_rules();
    }
    /**
     * {@inheritdoc}
     */
    public function parse_request($manager, $request)
    {
        foreach ($this->rules as $rule) {
            /** @var UrlRule $rule */
            $result = $rule->parse_request($manager, $request);
            Yii::debug(['rule' => method_exists($rule, '__toString') ? $rule->__toString() : get_class($rule), 'match' => $result !== false, 'parent' => self::class_name()], __METHOD__);
            if ($result !== false) {
                return $result;
            }
        }
        return false;
    }
    /**
     * {@inheritdoc}
     */
    public function create_url($manager, $route, $params)
    {
        $this->create_status = Url_Rule::CREATE_STATUS_SUCCESS;
        $url = $this->iterate_rules($this->rules, $manager, $route, $params);
        if ($url !== false) {
            return $url;
        }
        if ($this->create_status === Url_Rule::CREATE_STATUS_SUCCESS) {
            // create status was not changed - there is no rules configured
            $this->create_status = Url_Rule::CREATE_STATUS_PARSING_ONLY;
        }
        return false;
    }
    /**
     * Iterates through specified rules and calls [[createUrl()]] for each of them.
     *
     * @param UrlRuleInterface[] $rules rules to iterate.
     * @param UrlManager $manager the URL manager
     * @param string $route the route. It should not have slashes at the beginning or the end.
     * @param array $params the parameters
     * @return bool|string the created URL, or `false` if none of specified rules cannot be used for creating this URL.
     * @see createUrl()
     * @since 2.0.12
     */
    protected function iterate_rules($rules, $manager, $route, $params)
    {
        /** @var UrlRule $rule */
        foreach ($rules as $rule) {
            $url = $rule->create_url($manager, $route, $params);
            if ($url !== false) {
                $this->create_status = Url_Rule::CREATE_STATUS_SUCCESS;
                return $url;
            }
            if ($this->create_status === null || !method_exists($rule, 'getCreateUrlStatus') || $rule->get_create_url_status() === null) {
                $this->create_status = null;
            } else {
                $this->create_status |= $rule->get_create_url_status();
            }
        }
        return false;
    }
    /**
     * Returns status of the URL creation after the last [[createUrl()]] call.
     *
     * For multiple rules statuses will be combined by bitwise `or` operator
     * (e.g. `UrlRule::CREATE_STATUS_PARSING_ONLY | UrlRule::CREATE_STATUS_PARAMS_MISMATCH`).
     *
     * @return int|null Status of the URL creation after the last [[createUrl()]] call. `null` if rule does not provide
     * info about create status.
     * @see createStatus
     * @see https://www.php.net/manual/en/language.operators.bitwise.php
     * @since 2.0.12
     */
    public function get_create_url_status()
    {
        return $this->create_status;
    }
}