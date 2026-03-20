<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\helpers;

/**
 * BaseHtmlPurifier provides concrete implementation for [[HtmlPurifier]].
 *
 * Do not use BaseHtmlPurifier. Use [[HtmlPurifier]] instead.
 *
 * @author Alexander Makarov <sam@rmcreative.ru>
 * @since 2.0
 */
class Base_Html_Purifier
{
    /**
     * Passes markup through HTMLPurifier making it safe to output to end user.
     *
     * @param string $content The HTML content to purify
     * @param array|\Closure|null $config The config to use for HtmlPurifier.
     * If not specified or `null` the default config will be used.
     * You can use an array or an anonymous function to provide configuration options:
     *
     * - An array will be passed to the `HTMLPurifier_Config::create()` method.
     * - An anonymous function will be called after the config was created.
     *   The signature should be: `function($config)` where `$config` will be an
     *   instance of `HTMLPurifier_Config`.
     *
     *   Here is a usage example of such a function:
     *
     *   ```
     *   // Allow the HTML5 data attribute `data-type` on `img` elements.
     *   $content = HtmlPurifier::process($content, function ($config) {
     *     $config->getHTMLDefinition(true)
     *            ->addAttribute('img', 'data-type', 'Text');
     *   });
     * ```
     *
     * @return string the purified HTML content.
     */
    public static function process($content, $config = null)
    {
        $config_instance = \Html_Purifier_config::create($config instanceof \Closure ? null : $config);
        $config_instance->auto_finalize = false;
        $purifier = \Html_Purifier::instance($config_instance);
        $purifier->config->set('Cache.SerializerPath', \Yii::$app->get_runtime_path());
        $purifier->config->set('Cache.SerializerPermissions', 0775);
        static::configure($config_instance);
        if ($config instanceof \Closure) {
            call_user_func($config, $config_instance);
        }
        return $purifier->purify($content);
    }
    /**
     * Allow the extended HtmlPurifier class to set some default config options.
     * @param \HTMLPurifier_Config $config
     * @since 2.0.3
     */
    protected static function configure($config)
    {
    }
}