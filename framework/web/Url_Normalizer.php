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
 * UrlNormalizer normalizes URLs for [[UrlManager]] and [[UrlRule]].
 *
 * @author Robert Korulczyk <robert@korulczyk.pl>
 * @author Cronfy <cronfy@gmail.com>
 * @since 2.0.10
 */
class Url_Normalizer extends Base_Object
{
    /**
     * Represents permament redirection during route normalization.
     * @see https://en.wikipedia.org/wiki/HTTP_301
     */
    public const ACTION_REDIRECT_PERMANENT = 301;
    /**
     * Represents temporary redirection during route normalization.
     * @see https://en.wikipedia.org/wiki/HTTP_302
     */
    public const ACTION_REDIRECT_TEMPORARY = 302;
    /**
     * Represents showing 404 error page during route normalization.
     * @see https://en.wikipedia.org/wiki/HTTP_404
     */
    public const ACTION_NOT_FOUND = 404;
    /**
     * @var bool whether slashes should be collapsed, for example `site///index` will be
     * converted into `site/index`
     */
    public $collapse_slashes = true;
    /**
     * @var bool whether trailing slash should be normalized according to the suffix settings
     * of the rule
     */
    public $normalize_trailing_slash = true;
    /**
     * @var int|callable|null action to perform during route normalization.
     * Available options are:
     * - `null` - no special action will be performed
     * - `301` - the request should be redirected to the normalized URL using
     *   permanent redirection
     * - `302` - the request should be redirected to the normalized URL using
     *   temporary redirection
     * - `404` - [[NotFoundHttpException]] will be thrown
     * - `callable` - custom user callback, for example:
     *
     *   ```
     *   function ($route, $normalizer) {
     *       // use custom action for redirections
     *       $route[1]['oldRoute'] = $route[0];
     *       $route[0] = 'site/redirect';
     *       return $route;
     *   }
     *   ```
     */
    public $action = self::ACTION_REDIRECT_PERMANENT;
    /**
     * Performs normalization action for the specified $route.
     * @param array $route route for normalization
     * @return array normalized route
     * @throws InvalidConfigException if invalid normalization action is used.
     * @throws UrlNormalizerRedirectException if normalization requires redirection.
     * @throws NotFoundHttpException if normalization suggests action matching route does not exist.
     */
    public function normalize_route(array $route)
    {
        if ($this->action === null) {
            return $route;
        }
        if ($this->action === static::ACTION_REDIRECT_PERMANENT || $this->action === static::ACTION_REDIRECT_TEMPORARY) {
            throw new Url_Normalizer_Redirect_Exception([$route[0]] + $route[1], $this->action);
        }
        if ($this->action === static::ACTION_NOT_FOUND) {
            throw new Not_Found_Http_Exception(Yii::t('yii', 'Page not found.'));
        }
        if (is_callable($this->action)) {
            return call_user_func($this->action, $route, $this);
        }
        throw new Invalid_Config_Exception('Invalid normalizer action.');
    }
    /**
     * Normalizes specified pathInfo.
     * @param string $pathInfo pathInfo for normalization
     * @param string $suffix current rule suffix
     * @param bool $normalized if specified, this variable will be set to `true` if $pathInfo
     * was changed during normalization
     * @return string normalized pathInfo
     */
    public function normalize_path_info($path_info, $suffix, &$normalized = false)
    {
        if (empty($path_info)) {
            return $path_info;
        }
        $source_path_info = $path_info;
        if ($this->collapse_slashes) {
            $path_info = $this->collapse_slashes($path_info);
        }
        if ($this->normalize_trailing_slash === true) {
            $path_info = $this->normalize_trailing_slash($path_info, $suffix);
        }
        $normalized = $source_path_info !== $path_info;
        return $path_info;
    }
    /**
     * Collapse consecutive slashes in $pathInfo, for example converts `site///index` into `site/index`.
     * @param string $pathInfo raw path info.
     * @return string normalized path info.
     */
    protected function collapse_slashes($path_info): string
    {
        return ltrim(preg_replace('#/{2,}#', '/', $path_info), '/');
    }
    /**
     * Adds or removes trailing slashes from $pathInfo depending on whether the $suffix has a
     * trailing slash or not.
     * @param string $pathInfo raw path info.
     * @param string $suffix
     * @return string normalized path info.
     */
    protected function normalize_trailing_slash($path_info, $suffix)
    {
        if (substr($suffix, -1) === '/' && substr($path_info, -1) !== '/') {
            $path_info .= '/';
        } elseif (substr($suffix, -1) !== '/' && substr($path_info, -1) === '/') {
            $path_info = rtrim($path_info, '/');
        }
        return $path_info;
    }
}