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
use yii\helpers\Array_Helper;
use yii\helpers\Url;
/**
 * AssetBundle represents a collection of asset files, such as CSS, JS, images.
 *
 * Each asset bundle has a unique name that globally identifies it among all asset bundles used in an application.
 * The name is the [fully qualified class name](https://www.php.net/manual/en/language.namespaces.rules.php)
 * of the class representing it.
 *
 * An asset bundle can depend on other asset bundles. When registering an asset bundle
 * with a view, all its dependent asset bundles will be automatically registered.
 *
 * For more details and usage information on AssetBundle, see the [guide article on assets](guide:structure-assets).
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 *
 * @phpstan-import-type RegisterJsFileOptions from View
 * @phpstan-import-type RegisterCssFileOptions from View
 * @phpstan-import-type PublishOptions from AssetManager
 */
class Asset_Bundle extends Base_Object
{
    /**
     * @var string|null the directory that contains the source asset files for this asset bundle.
     * A source asset file is a file that is part of your source code repository of your Web application.
     *
     * You must set this property if the directory containing the source asset files is not Web accessible.
     * By setting this property, [[AssetManager]] will publish the source asset files
     * to a Web-accessible directory automatically when the asset bundle is registered on a page.
     *
     * If you do not set this property, it means the source asset files are located under [[basePath]].
     *
     * You can use either a directory or an alias of the directory.
     * @see publishOptions
     */
    public $source_path;
    /**
     * @var string|null the Web-accessible directory that contains the asset files in this bundle.
     *
     * If [[sourcePath]] is set, this property will be *overwritten* by [[AssetManager]]
     * when it publishes the asset files from [[sourcePath]].
     *
     * You can use either a directory or an alias of the directory.
     */
    public $base_path;
    /**
     * @var string|null the base URL for the relative asset files listed in [[js]] and [[css]].
     *
     * If [[sourcePath]] is set, this property will be *overwritten* by [[AssetManager]]
     * when it publishes the asset files from [[sourcePath]].
     *
     * You can use either a URL or an alias of the URL.
     */
    public $base_url;
    /**
     * @var class-string[] list of bundle class names that this bundle depends on.
     *
     * For example:
     *
     * ```
     * public $depends = [
     *    'yii\web\YiiAsset',
     *    'yii\bootstrap\BootstrapAsset',
     * ];
     * ```
     */
    public $depends = [];
    /**
     * @var (string|array<array-key, mixed>)[] list of JavaScript files that this bundle contains. Each JavaScript file can be
     * specified in one of the following formats:
     *
     * - an absolute URL representing an external asset. For example,
     *   `https://ajax.googleapis.com/ajax/libs/jquery/2.1.1/jquery.min.js` or
     *   `//ajax.googleapis.com/ajax/libs/jquery/2.1.1/jquery.min.js`.
     * - a relative path representing a local asset (e.g. `js/main.js`). The actual file path of a local
     *   asset can be determined by prefixing [[basePath]] to the relative path, and the actual URL
     *   of the asset can be determined by prefixing [[baseUrl]] to the relative path.
     * - an array, with the first entry being the URL or relative path as described before, and a list of key => value pairs
     *   that will be used to overwrite [[jsOptions]] settings for this entry.
     *   This functionality is available since version 2.0.7.
     *
     * Note that only a forward slash "/" should be used as directory separator.
     */
    public $js = [];
    /**
     * @var (string|array<array-key, mixed>)[] list of CSS files that this bundle contains. Each CSS file can be specified
     * in one of the three formats as explained in [[js]].
     *
     * Note that only a forward slash "/" should be used as directory separator.
     */
    public $css = [];
    /**
     * @var RegisterJsFileOptions the options that will be passed to [[View::registerJsFile()]]
     * when registering the JS files in this bundle.
     */
    public $js_options = [];
    /**
     * @var RegisterCssFileOptions the options that will be passed to [[View::registerCssFile()]]
     * when registering the CSS files in this bundle.
     */
    public $css_options = [];
    /**
     * @var PublishOptions the options to be passed to [[AssetManager::publish()]] when the asset bundle
     * is being published. This property is used only when [[sourcePath]] is set.
     */
    public $publish_options = [];
    /**
     * Registers this asset bundle with a view.
     * @param View $view the view to be registered with
     * @return static the registered asset bundle instance
     */
    public static function register($view)
    {
        /** @var static $result */
        $result = $view->register_asset_bundle(static::class);
        return $result;
    }
    /**
     * Initializes the bundle.
     * If you override this method, make sure you call the parent implementation in the last.
     */
    public function init(): void
    {
        if ($this->source_path !== null) {
            $this->source_path = rtrim(Yii::get_alias($this->source_path), '/\\');
        }
        if ($this->base_path !== null) {
            $this->base_path = rtrim(Yii::get_alias($this->base_path), '/\\');
        }
        if ($this->base_url !== null) {
            $this->base_url = rtrim(Yii::get_alias($this->base_url), '/');
        }
    }
    /**
     * Registers the CSS and JS files with the given view.
     * @param \yii\web\View $view the view that the asset files are to be registered with.
     */
    public function register_asset_files($view): void
    {
        $manager = $view->get_asset_manager();
        foreach ($this->js as $js) {
            if (is_array($js)) {
                $file = array_shift($js);
                $options = Array_Helper::merge($this->js_options, $js);
                $view->register_js_file($manager->get_asset_url($this, $file, Array_Helper::get_value($options, 'appendTimestamp')), $options);
            } elseif ($js !== null) {
                $view->register_js_file($manager->get_asset_url($this, $js), $this->js_options);
            }
        }
        foreach ($this->css as $css) {
            if (is_array($css)) {
                $file = array_shift($css);
                $options = Array_Helper::merge($this->css_options, $css);
                $view->register_css_file($manager->get_asset_url($this, $file, Array_Helper::get_value($options, 'appendTimestamp')), $options);
            } elseif ($css !== null) {
                $view->register_css_file($manager->get_asset_url($this, $css), $this->css_options);
            }
        }
    }
    /**
     * Publishes the asset bundle if its source code is not under Web-accessible directory.
     * It will also try to convert non-CSS or JS files (e.g. LESS, Sass) into the corresponding
     * CSS or JS files using [[AssetManager::converter|asset converter]].
     * @param AssetManager $am the asset manager to perform the asset publishing
     */
    public function publish($am): void
    {
        if ($this->source_path !== null && !isset($this->base_path, $this->base_url)) {
            [$this->base_path, $this->base_url] = $am->publish($this->source_path, $this->publish_options);
        }
        if (isset($this->base_path, $this->base_url) && ($converter = $am->get_converter()) !== null) {
            foreach ($this->js as $i => $js) {
                if (is_array($js)) {
                    $file = array_shift($js);
                    if (Url::is_relative($file)) {
                        $js = Array_Helper::merge($this->js_options, $js);
                        array_unshift($js, $converter->convert($file, $this->base_path));
                        $this->js[$i] = $js;
                    }
                } elseif (Url::is_relative($js)) {
                    $this->js[$i] = $converter->convert($js, $this->base_path);
                }
            }
            foreach ($this->css as $i => $css) {
                if (is_array($css)) {
                    $file = array_shift($css);
                    if (Url::is_relative($file)) {
                        $css = Array_Helper::merge($this->css_options, $css);
                        array_unshift($css, $converter->convert($file, $this->base_path));
                        $this->css[$i] = $css;
                    }
                } elseif (Url::is_relative($css)) {
                    $this->css[$i] = $converter->convert($css, $this->base_path);
                }
            }
        }
    }
}