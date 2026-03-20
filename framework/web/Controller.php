<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\web;

use Yii;
use yii\base\Action;
use yii\base\Controller as BaseController;
use yii\base\Exception;
use yii\base\Inline_Action;
use yii\base\Module;
use yii\helpers\Url;
/**
 * Controller is the base class of web controllers.
 *
 * For more details and usage information on Controller, see the [guide article on controllers](guide:structure-controllers).
 *
 * @property Request $request The request object.
 * @property Response $response The response object.
 * @property View $view The view object that can be used to render views or view files.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 *
 * @template T of Module = Module
 * @extends BaseController<T>
 */
class Controller extends Base_Controller
{
    /**
     * @var bool whether to enable CSRF validation for the actions in this controller.
     * CSRF validation is enabled only when both this property and [[\yii\web\Request::enableCsrfValidation]] are true.
     */
    public $enable_csrf_validation = true;
    /**
     * @var array the parameters bound to the current action.
     */
    public $action_params = [];
    /**
     * Renders a view in response to an AJAX request.
     *
     * This method is similar to [[renderPartial()]] except that it will inject into
     * the rendering result with JS/CSS scripts and files which are registered with the view.
     * For this reason, you should use this method instead of [[renderPartial()]] to render
     * a view to respond to an AJAX request.
     *
     * @param string $view the view name. Please refer to [[render()]] on how to specify a view name.
     * @param array $params the parameters (name-value pairs) that should be made available in the view.
     * @return string the rendering result.
     */
    public function render_ajax($view, $params = [])
    {
        /** @var View $viewComponent */
        $view_component = $this->get_view();
        return $view_component->render_ajax($view, $params, $this);
    }
    /**
     * Send data formatted as JSON.
     *
     * This method is a shortcut for sending data formatted as JSON. It will return
     * the [[Application::getResponse()|response]] application component after configuring
     * the [[Response::$format|format]] and setting the [[Response::$data|data]] that should
     * be formatted. A common usage will be:
     *
     * ```
     * return $this->asJson($data);
     * ```
     *
     * @param mixed $data the data that should be formatted.
     * @return Response a response that is configured to send `$data` formatted as JSON.
     * @since 2.0.11
     * @see Response::$format
     * @see Response::FORMAT_JSON
     * @see JsonResponseFormatter
     */
    public function as_json($data)
    {
        $this->response->format = Response::FORMAT_JSON;
        $this->response->data = $data;
        return $this->response;
    }
    /**
     * Send data formatted as XML.
     *
     * This method is a shortcut for sending data formatted as XML. It will return
     * the [[Application::getResponse()|response]] application component after configuring
     * the [[Response::$format|format]] and setting the [[Response::$data|data]] that should
     * be formatted. A common usage will be:
     *
     * ```
     * return $this->asXml($data);
     * ```
     *
     * @param mixed $data the data that should be formatted.
     * @return Response a response that is configured to send `$data` formatted as XML.
     * @since 2.0.11
     * @see Response::$format
     * @see Response::FORMAT_XML
     * @see XmlResponseFormatter
     */
    public function as_xml($data)
    {
        $this->response->format = Response::FORMAT_XML;
        $this->response->data = $data;
        return $this->response;
    }
    /**
     * Binds the parameters to the action.
     * This method is invoked by [[Action]] when it begins to run with the given parameters.
     * This method will check the parameter names that the action requires and return
     * the provided parameters according to the requirement. If there is any missing parameter,
     * an exception will be thrown.
     * @param Action<static> $action the action to be bound with parameters
     * @param array<array-key, mixed> $params the parameters to be bound to the action
     * @return mixed[] the valid parameters that the action can run with.
     * @throws BadRequestHttpException if there are missing or invalid parameters.
     *
     * @phpstan-param Action<static> $action
     * @psalm-param Action<self> $action
     */
    public function bind_action_params($action, $params): array
    {
        if ($action instanceof Inline_Action) {
            $method = new \ReflectionMethod($this, $action->action_method);
        } else {
            $method = new \ReflectionMethod($action, 'run');
        }
        $args = [];
        $missing = [];
        $action_params = [];
        $requested_params = [];
        foreach ($method->get_parameters() as $param) {
            $name = $param->get_name();
            if (array_key_exists($name, $params)) {
                $is_valid = true;
                $type = $param->get_type();
                if ($type instanceof \ReflectionNamedType) {
                    [$result, $is_valid] = $this->filter_single_type_action_param($params[$name], $type);
                    $params[$name] = $result;
                } elseif ($type instanceof \ReflectionUnionType) {
                    [$result, $is_valid] = $this->filter_union_type_action_param($params[$name], $type);
                    $params[$name] = $result;
                }
                if (!$is_valid) {
                    throw new Bad_Request_Http_Exception(Yii::t('yii', 'Invalid data received for parameter "{param}".', ['param' => $name]));
                }
                $args[] = $action_params[$name] = $params[$name];
                unset($params[$name]);
            } elseif (PHP_VERSION_ID >= 70100 && ($type = $param->get_type()) !== null && $type instanceof \ReflectionNamedType && !$type->is_builtin()) {
                try {
                    $this->bind_injected_params($type, $name, $args, $requested_params);
                } catch (Http_Exception $e) {
                    throw $e;
                } catch (Exception $e) {
                    throw new Server_Error_Http_Exception($e->get_message(), 0, $e);
                }
            } elseif ($param->is_default_value_available()) {
                $args[] = $action_params[$name] = $param->get_default_value();
            } else {
                $missing[] = $name;
            }
        }
        if (!empty($missing)) {
            throw new Bad_Request_Http_Exception(Yii::t('yii', 'Missing required parameters: {params}', ['params' => implode(', ', $missing)]));
        }
        $this->action_params = $action_params;
        // We use a different array here, specifically one that doesn't contain service instances but descriptions instead.
        if (Yii::$app->requested_params === null) {
            Yii::$app->requested_params = array_merge($action_params, $requested_params);
        }
        return $args;
    }
    /**
     * The logic for [[bindActionParam]] to validate whether a given parameter matches the action's typing
     * if the function parameter has a single named type.
     * @param mixed $param The parameter value.
     * @return array{mixed, bool} The resulting parameter value and a boolean indicating whether the value is valid.
     */
    private function filter_single_type_action_param($param, \ReflectionNamedType $type): array
    {
        $is_array = $type->get_name() === 'array';
        if ($is_array) {
            return [(array) $param, true];
        }
        $is_mixed = $type->get_name() === 'mixed';
        if ($is_mixed) {
            return [$param, true];
        }
        if (is_array($param)) {
            return [$param, false];
        }
        if (PHP_VERSION_ID >= 70000 && method_exists($type, 'isBuiltin') && $type->is_builtin() && ($param !== null || !$type->allows_null())) {
            $type_name = PHP_VERSION_ID >= 70100 ? $type->get_name() : (string) $type;
            if ($param === '' && $type->allows_null()) {
                if ($type_name !== 'string') {
                    // for old string behavior compatibility
                    return [null, true];
                }
                return ['', true];
            }
            if ($type_name === 'string') {
                return [$param, true];
            }
            $filter_result = $this->filter_param_by_type($param, $type_name);
            return [$filter_result, $filter_result !== null];
        }
        return [$param, true];
    }
    /**
     * The logic for [[bindActionParam]] to validate whether a given parameter matches the action's typing
     * if the function parameter has a union type.
     * @param mixed $param The parameter value.
     * @return array{mixed, bool} The resulting parameter value and a boolean indicating whether the value is valid.
     */
    private function filter_union_type_action_param($param, \ReflectionUnionType $type): array
    {
        $types = $type->get_types();
        if ($param === '' && $type->allows_null()) {
            // check if type can be string for old string behavior compatibility
            foreach ($types as $partial_type) {
                if ($partial_type === null) {
                    continue;
                }
                if (!method_exists($partial_type, 'isBuiltin')) {
                    continue;
                }
                if (!$partial_type->is_builtin()) {
                    continue;
                }
                $type_name = PHP_VERSION_ID >= 70100 ? $partial_type->get_name() : (string) $partial_type;
                if ($type_name === 'string') {
                    return ['', true];
                }
            }
            return [null, true];
        }
        // if we found a built-in type but didn't return out, its validation failed
        $found_builtin_type = false;
        // we save returning out an array or string for later because other types should take precedence
        $can_be_array = false;
        $can_be_string = false;
        foreach ($types as $partial_type) {
            if ($partial_type === null) {
                continue;
            }
            if (!method_exists($partial_type, 'isBuiltin')) {
                continue;
            }
            if (!$partial_type->is_builtin()) {
                continue;
            }
            $found_builtin_type = true;
            $type_name = PHP_VERSION_ID >= 70100 ? $partial_type->get_name() : (string) $partial_type;
            $can_be_array |= $type_name === 'array';
            $can_be_string |= $type_name === 'string';
            if (is_array($param)) {
                if ($can_be_array) {
                    break;
                }
                continue;
            }
            $filter_result = $this->filter_param_by_type($param, $type_name);
            if ($filter_result !== null) {
                return [$filter_result, true];
            }
        }
        if (!is_array($param) && $can_be_string) {
            return [$param, true];
        }
        if ($can_be_array) {
            return [(array) $param, true];
        }
        return [$param, $can_be_string || !$found_builtin_type];
    }
    /**
     * Run the according filter_var logic for teh given type.
     * @param string $param The value to filter.
     * @param string $typeName The type name.
     * @return mixed|null The resulting value, or null if validation failed or the type can't be validated.
     */
    private function filter_param_by_type(string $param, string $type_name)
    {
        switch ($type_name) {
            case 'int':
                return filter_var($param, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
            case 'float':
                return filter_var($param, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
            case 'bool':
                return filter_var($param, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }
        return null;
    }
    /**
     * {@inheritdoc}
     */
    public function before_action($action): bool
    {
        if (parent::before_action($action)) {
            if ($this->enable_csrf_validation && Yii::$app->get_error_handler()->exception === null && !$this->request->validate_csrf_token()) {
                throw new Bad_Request_Http_Exception(Yii::t('yii', 'Unable to verify your data submission.'));
            }
            return true;
        }
        return false;
    }
    /**
     * Redirects the browser to the specified URL.
     * This method is a shortcut to [[Response::redirect()]].
     *
     * You can use it in an action by returning the [[Response]] directly:
     *
     * ```
     * // stop executing this action and redirect to login page
     * return $this->redirect(['login']);
     * ```
     *
     * @param string|array $url the URL to be redirected to. This can be in one of the following formats:
     *
     * - a string representing a URL (e.g. "https://example.com")
     * - a string representing a URL alias (e.g. "@example.com")
     * - an array in the format of `[$route, ...name-value pairs...]` (e.g. `['site/index', 'ref' => 1]`)
     *   [[Url::to()]] will be used to convert the array into a URL.
     *
     * Any relative URL that starts with a single forward slash "/" will be converted
     * into an absolute one by prepending it with the host info of the current request.
     *
     * @param int $statusCode the HTTP status code. Defaults to 302.
     * See <https://tools.ietf.org/html/rfc2616#section-10>
     * for details about HTTP status code
     * @return Response the current response object
     */
    public function redirect($url, $status_code = 302)
    {
        // calling Url::to() here because Response::redirect() modifies route before calling Url::to()
        return $this->response->redirect(Url::to($url), $status_code);
    }
    /**
     * Redirects the browser to the home page.
     *
     * You can use this method in an action by returning the [[Response]] directly:
     *
     * ```
     * // stop executing this action and redirect to home page
     * return $this->goHome();
     * ```
     *
     * @return Response the current response object
     */
    public function go_home()
    {
        return $this->response->redirect(Yii::$app->get_home_url());
    }
    /**
     * Redirects the browser to the last visited page.
     *
     * You can use this method in an action by returning the [[Response]] directly:
     *
     * ```
     * // stop executing this action and redirect to last visited page
     * return $this->goBack();
     * ```
     *
     * For this function to work you have to [[User::setReturnUrl()|set the return URL]] in appropriate places before.
     *
     * @param string|array|null $defaultUrl the default return URL in case it was not set previously.
     * If this is null and the return URL was not set previously, [[Application::homeUrl]] will be redirected to.
     * Please refer to [[User::setReturnUrl()]] on accepted format of the URL.
     * @return Response the current response object
     * @see User::getReturnUrl()
     */
    public function go_back($default_url = null)
    {
        return $this->response->redirect(Yii::$app->get_user()->get_return_url($default_url));
    }
    /**
     * Refreshes the current page.
     * This method is a shortcut to [[Response::refresh()]].
     *
     * You can use it in an action by returning the [[Response]] directly:
     *
     * ```
     * // stop executing this action and refresh the current page
     * return $this->refresh();
     * ```
     *
     * @param string $anchor the anchor that should be appended to the redirection URL.
     * Defaults to empty. Make sure the anchor starts with '#' if you want to specify it.
     * @return Response the response object itself
     */
    public function refresh(string $anchor = '')
    {
        return $this->response->redirect($this->request->get_url() . $anchor);
    }
}