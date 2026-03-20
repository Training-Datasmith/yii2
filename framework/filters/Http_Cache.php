<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\filters;

use Yii;
use yii\base\Action_Filter;
use yii\base\Component;
/**
 * HttpCache implements client-side caching by utilizing the `Last-Modified` and `ETag` HTTP headers.
 *
 * It is an action filter that can be added to a controller and handles the `beforeAction` event.
 *
 * To use HttpCache, declare it in the `behaviors()` method of your controller class.
 * In the following example the filter will be applied to the `index` action and
 * the Last-Modified header will contain the date of the last update to the user table in the database.
 *
 * ```
 * public function behaviors()
 * {
 *     return [
 *         [
 *             'class' => 'yii\filters\HttpCache',
 *             'only' => ['index'],
 *             'lastModified' => function ($action, $params) {
 *                 $q = new \yii\db\Query();
 *                 return $q->from('user')->max('updated_at');
 *             },
 * //            'etagSeed' => function ($action, $params) {
 * //                return // generate ETag seed here
 * //            }
 *         ],
 *     ];
 * }
 * ```
 *
 * @author Da:Sourcerer <webmaster@dasourcerer.net>
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 *
 * @template T of Component = Component
 * @extends ActionFilter<T>
 */
class Http_Cache extends Action_Filter
{
    /**
     * @var callable a PHP callback that returns the UNIX timestamp of the last modification time.
     * The callback's signature should be:
     *
     * ```
     * function ($action, $params)
     * ```
     *
     * where `$action` is the [[Action]] object that this filter is currently handling;
     * `$params` takes the value of [[params]]. The callback should return a UNIX timestamp.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc7232#section-2.2
     */
    public $last_modified;
    /**
     * @var callable a PHP callback that generates the ETag seed string.
     * The callback's signature should be:
     *
     * ```
     * function ($action, $params)
     * ```
     *
     * where `$action` is the [[Action]] object that this filter is currently handling;
     * `$params` takes the value of [[params]]. The callback should return a string serving
     * as the seed for generating an ETag.
     */
    public $etag_seed;
    /**
     * @var bool whether to generate weak ETags.
     *
     * Weak ETags should be used if the content should be considered semantically equivalent, but not byte-equal.
     *
     * @since 2.0.8
     * @see https://datatracker.ietf.org/doc/html/rfc7232#section-2.3
     */
    public $weak_etag = false;
    /**
     * @var mixed additional parameters that should be passed to the [[lastModified]] and [[etagSeed]] callbacks.
     */
    public $params;
    /**
     * @var string|null the value of the `Cache-Control` HTTP header. If null, the header will not be sent.
     * @see https://datatracker.ietf.org/doc/html/rfc2616#section-14.9
     */
    public $cache_control_header = 'public, max-age=3600';
    /**
     * @var string|null the name of the cache limiter to be set when [session_cache_limiter()](https://www.php.net/manual/en/function.session-cache-limiter.php)
     * is called. The default value is an empty string, meaning turning off automatic sending of cache headers entirely.
     * You may set this property to be `public`, `private`, `private_no_expire`, and `nocache`.
     * Please refer to [session_cache_limiter()](https://www.php.net/manual/en/function.session-cache-limiter.php)
     * for detailed explanation of these values.
     *
     * If this property is `null`, then `session_cache_limiter()` will not be called. As a result,
     * PHP will send headers according to the `session.cache_limiter` PHP ini setting.
     */
    public $session_cache_limiter = '';
    /**
     * @var bool a value indicating whether this filter should be enabled.
     */
    public $enabled = true;
    /**
     * {@inheritdoc}
     */
    public function before_action($action): bool
    {
        if (!$this->enabled) {
            return true;
        }
        $verb = Yii::$app->get_request()->get_method();
        if ($verb !== 'GET' && $verb !== 'HEAD' || $this->last_modified === null && $this->etag_seed === null) {
            return true;
        }
        $last_modified = $etag = null;
        if ($this->last_modified !== null) {
            $last_modified = call_user_func($this->last_modified, $action, $this->params);
        }
        if ($this->etag_seed !== null) {
            $seed = call_user_func($this->etag_seed, $action, $this->params);
            if ($seed !== null) {
                $etag = $this->generate_etag($seed);
            }
        }
        $this->send_cache_control_header();
        $response = Yii::$app->get_response();
        if ($etag !== null) {
            $response->get_headers()->set('Etag', $etag);
        }
        $cache_valid = $this->validate_cache($last_modified, $etag);
        // https://tools.ietf.org/html/rfc7232#section-4.1
        if ($last_modified !== null && (!$cache_valid || $cache_valid && $etag === null)) {
            $response->get_headers()->set('Last-Modified', gmdate('D, d M Y H:i:s', $last_modified) . ' GMT');
        }
        if ($cache_valid) {
            $response->set_status_code(304);
            return false;
        }
        return true;
    }
    /**
     * Validates if the HTTP cache contains valid content.
     * If both Last-Modified and ETag are null, returns false.
     * @param int|null $lastModified the calculated Last-Modified value in terms of a UNIX timestamp.
     * If null, the Last-Modified header will not be validated.
     * @param string|null $etag the calculated ETag value. If null, the ETag header will not be validated.
     * @return bool whether the HTTP cache is still valid.
     */
    protected function validate_cache($last_modified, $etag)
    {
        if (Yii::$app->request->headers->has('If-None-Match')) {
            // HTTP_IF_NONE_MATCH takes precedence over HTTP_IF_MODIFIED_SINCE
            // https://datatracker.ietf.org/doc/html/rfc7232#section-3.3
            return $etag !== null && in_array($etag, Yii::$app->request->get_e_tags(), true);
        }
        if (Yii::$app->request->headers->has('If-Modified-Since')) {
            return $last_modified !== null && @strtotime(Yii::$app->request->headers->get('If-Modified-Since')) >= $last_modified;
        }
        return false;
    }
    /**
     * Sends the cache control header to the client.
     * @see cacheControlHeader
     */
    protected function send_cache_control_header()
    {
        if ($this->session_cache_limiter !== null) {
            if ($this->session_cache_limiter === '' && !headers_sent() && Yii::$app->get_session()->get_is_active()) {
                header_remove('Expires');
                header_remove('Cache-Control');
                header_remove('Last-Modified');
                header_remove('Pragma');
            }
            Yii::$app->get_session()->set_cache_limiter($this->session_cache_limiter);
        }
        $headers = Yii::$app->get_response()->get_headers();
        if ($this->cache_control_header !== null) {
            $headers->set('Cache-Control', $this->cache_control_header);
        }
    }
    /**
     * Generates an ETag from the given seed string.
     * @param string $seed Seed for the ETag
     * @return string the generated ETag
     */
    protected function generate_etag($seed): string
    {
        $etag = '"' . rtrim(base64_encode(sha1($seed, true)), '=') . '"';
        return $this->weak_etag ? 'W/' . $etag : $etag;
    }
}