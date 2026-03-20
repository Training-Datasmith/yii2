<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\filters;

use Yii;
use yii\base\Action;
use yii\base\Action_Filter;
use yii\base\Component;
use yii\base\Controller;
use yii\base\Invalid_Config_Exception;
use yii\base\Module;
use yii\web\Request;
use yii\web\Response;
/**
 * Cors filter implements [Cross Origin Resource Sharing](https://en.wikipedia.org/wiki/Cross-origin_resource_sharing).
 *
 * Make sure to read carefully what CORS does and does not. CORS do not secure your API,
 * but allow the developer to grant access to third party code (ajax calls from external domain).
 *
 * You may use CORS filter by attaching it as a behavior to a controller or module, like the following,
 *
 * ```
 * public function behaviors()
 * {
 *     return [
 *         'corsFilter' => [
 *             'class' => \yii\filters\Cors::class,
 *         ],
 *     ];
 * }
 * ```
 *
 * The CORS filter can be specialized to restrict parameters, like this,
 * [MDN CORS Information](https://developer.mozilla.org/en-US/docs/Web/HTTP/Access_control_CORS)
 *
 * ```
 * public function behaviors()
 * {
 *     return [
 *         'corsFilter' => [
 *             'class' => \yii\filters\Cors::class,
 *             'cors' => [
 *                 // restrict access to
 *                 'Origin' => ['http://www.myserver.com', 'https://www.myserver.com'],
 *                 // Allow only POST and PUT methods
 *                 'Access-Control-Request-Method' => ['POST', 'PUT'],
 *                 // Allow only headers 'X-Wsse'
 *                 'Access-Control-Request-Headers' => ['X-Wsse'],
 *                 // Allow credentials (cookies, authorization headers, etc.) to be exposed to the browser
 *                 'Access-Control-Allow-Credentials' => true,
 *                 // Allow OPTIONS caching
 *                 'Access-Control-Max-Age' => 3600,
 *                 // Allow the X-Pagination-Current-Page header to be exposed to the browser.
 *                 'Access-Control-Expose-Headers' => ['X-Pagination-Current-Page'],
 *             ],
 *
 *         ],
 *     ];
 * }
 * ```
 *
 * For more information on how to add the CORS filter to a controller, see
 * the [Guide on REST controllers](guide:rest-controllers#cors).
 *
 * @author Philippe Gaultier <pgaultier@gmail.com>
 * @since 2.0
 *
 * @template T of Component = Component
 * @extends ActionFilter<T>
 */
class Cors extends Action_Filter
{
    /**
     * @var Request|null the current request. If not set, the `request` application component will be used.
     */
    public $request;
    /**
     * @var Response|null the response to be sent. If not set, the `response` application component will be used.
     */
    public $response;
    /**
     * @var array define specific CORS rules for specific actions
     */
    public $actions = [];
    /**
     * @var array Basic headers handled for the CORS requests.
     */
    public $cors = ['Origin' => ['*'], 'Access-Control-Request-Method' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'], 'Access-Control-Request-Headers' => ['*'], 'Access-Control-Allow-Credentials' => null, 'Access-Control-Max-Age' => 86400, 'Access-Control-Expose-Headers' => []];
    /**
     * {@inheritdoc}
     */
    public function before_action($action): bool
    {
        $this->request = $this->request ?: Yii::$app->get_request();
        $this->response = $this->response ?: Yii::$app->get_response();
        $this->override_default_settings($action);
        $request_cors_headers = $this->extract_headers();
        $response_cors_headers = $this->prepare_headers($request_cors_headers);
        $this->add_cors_headers($this->response, $response_cors_headers);
        if ($this->request->is_options && $this->request->headers->has('Access-Control-Request-Method')) {
            // it is CORS preflight request, respond with 200 OK without further processing
            $this->response->set_status_code(200);
            return false;
        }
        return true;
    }
    /**
     * Override settings for specific action.
     * @param Action $action the action settings to override
     */
    public function override_default_settings($action): void
    {
        $action_id = $this->get_action_id($action);
        if (isset($this->actions[$action_id])) {
            $action_params = $this->actions[$action_id];
            $action_params_keys = array_keys($action_params);
            foreach ($this->cors as $header_field => $header_value) {
                if (in_array($header_field, $action_params_keys)) {
                    $this->cors[$header_field] = $action_params[$header_field];
                }
            }
        }
    }
    /**
     * Extract CORS headers from the request.
     * @return array CORS headers to handle
     */
    public function extract_headers(): array
    {
        $headers = [];
        foreach (array_keys($this->cors) as $header_field) {
            $server_field = $this->headerize_to_php($header_field);
            $header_data = $_SERVER[$server_field] ?? null;
            if ($header_data !== null) {
                $headers[$header_field] = $header_data;
            }
        }
        return $headers;
    }
    /**
     * For each CORS headers create the specific response.
     * @param array $requestHeaders CORS headers we have detected
     * @return array CORS headers ready to be sent
     */
    public function prepare_headers(array $request_headers): array
    {
        $response_headers = [];
        // handle Origin
        if (isset($request_headers['Origin'], $this->cors['Origin'])) {
            if (in_array($request_headers['Origin'], $this->cors['Origin'], true)) {
                $response_headers['Access-Control-Allow-Origin'] = $request_headers['Origin'];
            }
            if (in_array('*', $this->cors['Origin'], true)) {
                // Per CORS standard (https://fetch.spec.whatwg.org), wildcard origins shouldn't be used together with credentials
                if (isset($this->cors['Access-Control-Allow-Credentials']) && $this->cors['Access-Control-Allow-Credentials']) {
                    throw new Invalid_Config_Exception("Allowing credentials for wildcard origins is insecure. Please specify more restrictive origins or set 'credentials' to false in your CORS configuration.");
                }
                $response_headers['Access-Control-Allow-Origin'] = '*';
            }
        }
        $this->prepare_allow_headers('Headers', $request_headers, $response_headers);
        if (isset($request_headers['Access-Control-Request-Method'])) {
            $response_headers['Access-Control-Allow-Methods'] = implode(', ', $this->cors['Access-Control-Request-Method']);
        }
        if (isset($this->cors['Access-Control-Allow-Credentials'])) {
            $response_headers['Access-Control-Allow-Credentials'] = $this->cors['Access-Control-Allow-Credentials'] ? 'true' : 'false';
        }
        if (isset($this->cors['Access-Control-Max-Age']) && $this->request->get_is_options()) {
            $response_headers['Access-Control-Max-Age'] = $this->cors['Access-Control-Max-Age'];
        }
        if (isset($this->cors['Access-Control-Expose-Headers'])) {
            $response_headers['Access-Control-Expose-Headers'] = implode(', ', $this->cors['Access-Control-Expose-Headers']);
        }
        if (isset($this->cors['Access-Control-Allow-Headers'])) {
            $response_headers['Access-Control-Allow-Headers'] = implode(', ', $this->cors['Access-Control-Allow-Headers']);
        }
        return $response_headers;
    }
    /**
     * Handle classic CORS request to avoid duplicate code.
     * @param string $type the kind of headers we would handle
     * @param array $requestHeaders CORS headers request by client
     * @param array $responseHeaders CORS response headers sent to the client
     */
    protected function prepare_allow_headers(string $type, array $request_headers, array &$response_headers)
    {
        $request_header_field = 'Access-Control-Request-' . $type;
        $response_header_field = 'Access-Control-Allow-' . $type;
        if (!isset($request_headers[$request_header_field], $this->cors[$request_header_field])) {
            return;
        }
        if (in_array('*', $this->cors[$request_header_field])) {
            $response_headers[$response_header_field] = $this->headerize($request_headers[$request_header_field]);
        } else {
            $requested_data = preg_split('/[\s,]+/', $request_headers[$request_header_field], -1, PREG_SPLIT_NO_EMPTY);
            $accepted_data = array_uintersect($requested_data, $this->cors[$request_header_field], 'strcasecmp');
            if (!empty($accepted_data)) {
                $response_headers[$response_header_field] = implode(', ', $accepted_data);
            }
        }
    }
    /**
     * Adds the CORS headers to the response.
     * @param Response $response
     * @param array $headers CORS headers which have been computed
     */
    public function add_cors_headers($response, $headers): void
    {
        if (empty($headers) === false) {
            $response_headers = $response->get_headers();
            foreach ($headers as $field => $value) {
                $response_headers->set($field, $value);
            }
        }
    }
    /**
     * Convert any string (including php headers with HTTP prefix) to header format.
     *
     * Example:
     *  - X-PINGOTHER -> X-Pingother
     *  - X_PINGOTHER -> X-Pingother
     * @param string $string string to convert
     * @return string the result in "header" format
     */
    protected function headerize($string): string
    {
        $headers = preg_split('/[\s,]+/', $string, -1, PREG_SPLIT_NO_EMPTY);
        $headers = array_map(fn($element) => str_replace(' ', '-', ucwords(strtolower(str_replace(['_', '-'], [' ', ' '], $element)))), $headers);
        return implode(', ', $headers);
    }
    /**
     * Convert any string (including php headers with HTTP prefix) to header format.
     *
     * Example:
     *  - X-Pingother -> HTTP_X_PINGOTHER
     *  - X PINGOTHER -> HTTP_X_PINGOTHER
     * @param string $string string to convert
     * @return string the result in "php $_SERVER header" format
     */
    protected function headerize_to_php($string): string
    {
        return 'HTTP_' . strtoupper(str_replace([' ', '-'], ['_', '_'], $string));
    }
}