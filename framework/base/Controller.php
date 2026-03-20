<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\base;

use Yii;
use yii\di\Instance;
use yii\di\Not_Instantiable_Exception;
/**
 * Controller is the base class for classes containing controller logic.
 *
 * For more details and usage information on Controller, see the [guide article on controllers](guide:structure-controllers).
 *
 * @property-read Module[] $modules All ancestor modules that this controller is located within.
 * @property-read string $route The route (module ID, controller ID and action ID) of the current request.
 * @property-read string $uniqueId The controller ID that is prefixed with the module ID (if any).
 * @property View|\yii\web\View $view The view object that can be used to render views or view files.
 * @property string $viewPath The directory containing the view files for this controller.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 *
 * @template T of Module = Module
 */
class Controller extends Component implements View_Context_Interface
{
    /**
     * @event ActionEvent an event raised right before executing a controller action.
     * You may set [[ActionEvent::isValid]] to be false to cancel the action execution.
     */
    public const EVENT_BEFORE_ACTION = 'beforeAction';
    /**
     * @event ActionEvent an event raised right after executing a controller action.
     */
    public const EVENT_AFTER_ACTION = 'afterAction';
    /**
     * @var string the ID of this controller.
     */
    public $id;
    /**
     * @var T the module that this controller belongs to.
     */
    public $module;
    /**
     * @var string the ID of the action that is used when the action ID is not specified
     * in the request. Defaults to 'index'.
     */
    public $default_action = 'index';
    /**
     * @var string|null|false the name of the layout to be applied to this controller's views.
     * This property mainly affects the behavior of [[render()]].
     * Defaults to null, meaning the actual layout value should inherit that from [[module]]'s layout value.
     * If false, no layout will be applied.
     */
    public $layout;
    /**
     * @var Action<covariant static>|null the action that is currently being executed. This property will be set
     * by [[run()]] when it is called by [[Application]] to run an action.
     *
     * @phpstan-var Action<covariant static>|null
     * @psalm-var Action<self>|null
     */
    public $action;
    /**
     * @var Request|array|string The request.
     * @since 2.0.36
     */
    public $request = 'request';
    /**
     * @var Response|array|string The response.
     * @since 2.0.36
     */
    public $response = 'response';
    /**
     * @var View|null the view object that can be used to render views or view files.
     */
    private $_view;
    /**
     * @var string|null the root directory that contains view files for this controller.
     */
    private $_view_path;
    /**
     * @param string $id the ID of this controller.
     * @param T $module the module that this controller belongs to.
     * @param array<string, mixed> $config name-value pairs that will be used to initialize the object properties.
     */
    public function __construct($id, $module, $config = [])
    {
        $this->id = $id;
        $this->module = $module;
        parent::__construct($config);
    }
    /**
     * {@inheritdoc}
     * @since 2.0.36
     */
    public function init(): void
    {
        parent::init();
        $this->request = Instance::ensure($this->request, Request::class_name());
        $this->response = Instance::ensure($this->response, Response::class_name());
    }
    /**
     * Declares external actions for the controller.
     *
     * This method is meant to be overwritten to declare external actions for the controller.
     * It should return an array, with array keys being action IDs, and array values the corresponding
     * action class names or action configuration arrays. For example,
     *
     * ```
     * return [
     *     'action1' => 'app\components\Action1',
     *     'action2' => [
     *         'class' => 'app\components\Action2',
     *         'property1' => 'value1',
     *         'property2' => 'value2',
     *     ],
     * ];
     * ```
     *
     * [[\Yii::createObject()]] will be used later to create the requested action
     * using the configuration provided here.
     * @return array<array-key, class-string|array{class: class-string, ...}>
     */
    public function actions(): array
    {
        return [];
    }
    /**
     * Runs an action within this controller with the specified action ID and parameters.
     * If the action ID is empty, the method will use [[defaultAction]].
     * @param string $id the ID of the action to be executed.
     * @param array<array-key, mixed> $params the parameters (name-value pairs) to be passed to the action.
     * @return mixed the result of the action.
     * @throws InvalidRouteException if the requested action ID cannot be resolved into an action successfully.
     * @see createAction()
     */
    public function run_action(string $id, $params = [])
    {
        $action = $this->create_action($id);
        if ($action === null) {
            throw new Invalid_Route_Exception('Unable to resolve the request: ' . $this->get_unique_id() . '/' . $id);
        }
        Yii::debug('Route to run: ' . $action->get_unique_id(), __METHOD__);
        if (Yii::$app->requested_action === null) {
            Yii::$app->requested_action = $action;
        }
        $old_action = $this->action;
        $this->action = $action;
        $modules = [];
        $run_action = true;
        // call beforeAction on modules
        foreach ($this->get_modules() as $module) {
            if ($module->before_action($action)) {
                array_unshift($modules, $module);
            } else {
                $run_action = false;
                break;
            }
        }
        $result = null;
        if ($run_action && $this->before_action($action)) {
            // run the action
            $result = $action->run_with_params($params);
            $result = $this->after_action($action, $result);
            // call afterAction on modules
            foreach ($modules as $module) {
                /** @var Module $module */
                $result = $module->after_action($action, $result);
            }
        }
        if ($old_action !== null) {
            $this->action = $old_action;
        }
        return $result;
    }
    /**
     * Runs a request specified in terms of a route.
     * The route can be either an ID of an action within this controller or a complete route consisting
     * of module IDs, controller ID and action ID. If the route starts with a slash '/', the parsing of
     * the route will start from the application; otherwise, it will start from the parent module of this controller.
     * @param string $route the route to be handled, e.g., 'view', 'comment/view', '/admin/comment/view'.
     * @param array<array-key, mixed> $params the parameters to be passed to the action.
     * @return mixed the result of the action.
     * @see runAction()
     */
    public function run($route, $params = [])
    {
        $pos = strpos($route, '/');
        if ($pos === false) {
            return $this->run_action($route, $params);
        }
        if ($pos > 0) {
            return $this->module->run_action($route, $params);
        }
        return Yii::$app->run_action(ltrim($route, '/'), $params);
    }
    /**
     * Binds the parameters to the action.
     * This method is invoked by [[Action]] when it begins to run with the given parameters.
     * @param Action<static> $action the action to be bound with parameters.
     * @param array<array-key, mixed> $params the parameters to be bound to the action.
     * @return mixed[] the valid parameters that the action can run with.
     *
     * @phpstan-param Action<static> $action
     * @psalm-param Action<self> $action
     */
    public function bind_action_params($action, $params): array
    {
        return [];
    }
    /**
     * Creates an action based on the given action ID.
     * The method first checks if the action ID has been declared in [[actions()]]. If so,
     * it will use the configuration declared there to create the action object.
     * If not, it will look for a controller method whose name is in the format of `actionXyz`
     * where `xyz` is the action ID. If found, an [[InlineAction]] representing that
     * method will be created and returned.
     * @param string $id the action ID.
     * @return Action<covariant static>|null the newly created action instance. Null if the ID doesn't resolve into any action.
     *
     * @phpstan-return Action<covariant static>|null
     * @psalm-return Action<self>|null
     */
    public function create_action($id)
    {
        if ($id === '') {
            $id = $this->default_action;
        }
        $action_map = $this->actions();
        if (isset($action_map[$id])) {
            return Yii::create_object($action_map[$id], [$id, $this]);
        }
        if (preg_match('/^(?:[a-z0-9_]+-)*[a-z0-9_]+$/', $id)) {
            $method_name = 'action_' . str_replace('-', '_', $id);
            if (method_exists($this, $method_name)) {
                $method = new \ReflectionMethod($this, $method_name);
                if ($method->is_public() && $method->get_name() === $method_name) {
                    return new Inline_Action($id, $this, $method_name);
                }
            }
        }
        return null;
    }
    /**
     * This method is invoked right before an action is executed.
     *
     * The method will trigger the [[EVENT_BEFORE_ACTION]] event. The return value of the method
     * will determine whether the action should continue to run.
     *
     * In case the action should not run, the request should be handled inside of the `beforeAction` code
     * by either providing the necessary output or redirecting the request. Otherwise the response will be empty.
     *
     * If you override this method, your code should look like the following:
     *
     * ```
     * public function beforeAction($action)
     * {
     *     // your custom code here, if you want the code to run before action filters,
     *     // which are triggered on the [[EVENT_BEFORE_ACTION]] event, e.g. PageCache or AccessControl
     *
     *     if (!parent::beforeAction($action)) {
     *         return false;
     *     }
     *
     *     // other custom code here
     *
     *     return true; // or false to not run the action
     * }
     * ```
     *
     * @param Action<static> $action the action to be executed.
     * @return bool whether the action should continue to run.
     *
     * @phpstan-param Action<static> $action
     * @psalm-param Action<self> $action
     */
    public function before_action($action)
    {
        $event = new Action_Event($action);
        $this->trigger(self::EVENT_BEFORE_ACTION, $event);
        return $event->is_valid;
    }
    /**
     * This method is invoked right after an action is executed.
     *
     * The method will trigger the [[EVENT_AFTER_ACTION]] event. The return value of the method
     * will be used as the action return value.
     *
     * If you override this method, your code should look like the following:
     *
     * ```
     * public function afterAction($action, $result)
     * {
     *     $result = parent::afterAction($action, $result);
     *     // your custom code here
     *     return $result;
     * }
     * ```
     *
     * @param Action<static> $action the action just executed.
     * @param mixed $result the action return result.
     * @return mixed the processed action result.
     *
     * @phpstan-param Action<static> $action
     * @psalm-param Action<self> $action
     */
    public function after_action($action, $result)
    {
        $event = new Action_Event($action);
        $event->result = $result;
        $this->trigger(self::EVENT_AFTER_ACTION, $event);
        return $event->result;
    }
    /**
     * Returns all ancestor modules of this controller.
     * The first module in the array is the outermost one (i.e., the application instance),
     * while the last is the innermost one.
     * @return Module[] all ancestor modules that this controller is located within.
     */
    public function get_modules(): array
    {
        $modules = [$this->module];
        $module = $this->module;
        while ($module->module !== null) {
            array_unshift($modules, $module->module);
            $module = $module->module;
        }
        return $modules;
    }
    /**
     * Returns the unique ID of the controller.
     * @return string the controller ID that is prefixed with the module ID (if any).
     */
    public function get_unique_id()
    {
        return $this->module instanceof Application ? $this->id : $this->module->get_unique_id() . '/' . $this->id;
    }
    /**
     * Returns the route of the current request.
     * @return string the route (module ID, controller ID and action ID) of the current request.
     */
    public function get_route()
    {
        return $this->action !== null ? $this->action->get_unique_id() : $this->get_unique_id();
    }
    /**
     * Renders a view and applies layout if available.
     *
     * The view to be rendered can be specified in one of the following formats:
     *
     * - [path alias](guide:concept-aliases) (e.g. "@app/views/site/index");
     * - absolute path within application (e.g. "//site/index"): the view name starts with double slashes.
     *   The actual view file will be looked for under the [[Application::viewPath|view path]] of the application.
     * - absolute path within module (e.g. "/site/index"): the view name starts with a single slash.
     *   The actual view file will be looked for under the [[Module::viewPath|view path]] of [[module]].
     * - relative path (e.g. "index"): the actual view file will be looked for under [[viewPath]].
     *
     * To determine which layout should be applied, the following two steps are conducted:
     *
     * 1. In the first step, it determines the layout name and the context module:
     *
     * - If [[layout]] is specified as a string, use it as the layout name and [[module]] as the context module;
     * - If [[layout]] is null, search through all ancestor modules of this controller and find the first
     *   module whose [[Module::layout|layout]] is not null. The layout and the corresponding module
     *   are used as the layout name and the context module, respectively. If such a module is not found
     *   or the corresponding layout is not a string, it will return false, meaning no applicable layout.
     *
     * 2. In the second step, it determines the actual layout file according to the previously found layout name
     *    and context module. The layout name can be:
     *
     * - a [path alias](guide:concept-aliases) (e.g. "@app/views/layouts/main");
     * - an absolute path (e.g. "/main"): the layout name starts with a slash. The actual layout file will be
     *   looked for under the [[Application::layoutPath|layout path]] of the application;
     * - a relative path (e.g. "main"): the actual layout file will be looked for under the
     *   [[Module::layoutPath|layout path]] of the context module.
     *
     * If the layout name does not contain a file extension, it will use the default one `.php`.
     *
     * @param string $view the view name.
     * @param array<string, mixed> $params the parameters (name-value pairs) that should be made available in the view.
     * These parameters will not be available in the layout.
     * @return string the rendering result.
     * @throws InvalidArgumentException if the view file or the layout file does not exist.
     */
    public function render($view, $params = [])
    {
        $content = $this->get_view()->render($view, $params, $this);
        return $this->render_content($content);
    }
    /**
     * Renders a static string by applying a layout.
     * @param string $content the static string being rendered
     * @return string the rendering result of the layout with the given static string as the `$content` variable.
     * If the layout is disabled, the string will be returned back.
     * @since 2.0.1
     */
    public function render_content($content)
    {
        $layout_file = $this->find_layout_file($this->get_view());
        if ($layout_file !== false) {
            return $this->get_view()->render_file($layout_file, ['content' => $content], $this);
        }
        return $content;
    }
    /**
     * Renders a view without applying layout.
     * This method differs from [[render()]] in that it does not apply any layout.
     * @param string $view the view name. Please refer to [[render()]] on how to specify a view name.
     * @param array<string, mixed> $params the parameters (name-value pairs) that should be made available in the view.
     * @return string the rendering result.
     * @throws InvalidArgumentException if the view file does not exist.
     */
    public function render_partial($view, $params = [])
    {
        return $this->get_view()->render($view, $params, $this);
    }
    /**
     * Renders a view file.
     * @param string $file the view file to be rendered. This can be either a file path or a [path alias](guide:concept-aliases).
     * @param array<string, mixed> $params the parameters (name-value pairs) that should be made available in the view.
     * @return string the rendering result.
     * @throws InvalidArgumentException if the view file does not exist.
     */
    public function render_file($file, $params = [])
    {
        return $this->get_view()->render_file($file, $params, $this);
    }
    /**
     * Returns the view object that can be used to render views or view files.
     * The [[render()]], [[renderPartial()]] and [[renderFile()]] methods will use
     * this view object to implement the actual view rendering.
     * If not set, it will default to the "view" application component.
     * @return View|\yii\web\View the view object that can be used to render views or view files.
     */
    public function get_view()
    {
        if ($this->_view === null) {
            $this->_view = Yii::$app->get_view();
        }
        return $this->_view;
    }
    /**
     * Sets the view object to be used by this controller.
     * @param View|\yii\web\View $view the view object that can be used to render views or view files.
     */
    public function set_view($view): void
    {
        $this->_view = $view;
    }
    /**
     * Returns the directory containing view files for this controller.
     * The default implementation returns the directory named as controller [[id]] under the [[module]]'s
     * [[viewPath]] directory.
     * @return string the directory containing the view files for this controller.
     */
    public function get_view_path()
    {
        if ($this->_view_path === null) {
            $this->_view_path = $this->module->get_view_path() . DIRECTORY_SEPARATOR . $this->id;
        }
        return $this->_view_path;
    }
    /**
     * Sets the directory that contains the view files.
     * @param string $path the root directory of view files.
     * @throws InvalidArgumentException if the directory is invalid
     * @since 2.0.7
     */
    public function set_view_path($path): void
    {
        $this->_view_path = Yii::get_alias($path);
    }
    /**
     * Finds the applicable layout file.
     * @param View $view the view object to render the layout file.
     * @return string|bool the layout file path, or false if layout is not needed.
     * Please refer to [[render()]] on how to specify this parameter.
     * @throws InvalidArgumentException if an invalid path alias is used to specify the layout.
     */
    public function find_layout_file($view)
    {
        $module = $this->module;
        $layout = null;
        if (is_string($this->layout)) {
            $layout = $this->layout;
        } elseif ($this->layout === null) {
            while ($module !== null && $module->layout === null) {
                $module = $module->module;
            }
            if ($module !== null && is_string($module->layout)) {
                $layout = $module->layout;
            }
        }
        if ($layout === null) {
            return false;
        }
        if (strncmp($layout, '@', 1) === 0) {
            $file = Yii::get_alias($layout);
        } elseif (strncmp($layout, '/', 1) === 0) {
            $file = Yii::$app->get_layout_path() . DIRECTORY_SEPARATOR . substr($layout, 1);
        } else {
            $file = $module->get_layout_path() . DIRECTORY_SEPARATOR . $layout;
        }
        if (pathinfo($file, PATHINFO_EXTENSION) !== '') {
            return $file;
        }
        $path = $file . '.' . $view->default_extension;
        if ($view->default_extension !== 'php' && !is_file($path)) {
            return $file . '.php';
        }
        return $path;
    }
    /**
     * Fills parameters based on types and names in action method signature.
     * @param \ReflectionNamedType $type The reflected type of the action parameter.
     * @param string $name The name of the parameter.
     * @param array &$args The array of arguments for the action, this function may append items to it.
     * @param array &$requestedParams The array with requested params, this function may write specific keys to it.
     * @throws ErrorException when we cannot load a required service.
     * @throws InvalidConfigException Thrown when there is an error in the DI configuration.
     * @throws NotInstantiableException Thrown when a definition cannot be resolved to a concrete class
     * (for example an interface type hint) without a proper definition in the container.
     * @since 2.0.36
     */
    final protected function bind_injected_params(\ReflectionNamedType $type, string $name, &$args, array &$requested_params)
    {
        // Since it is not a builtin type it must be DI injection.
        $type_name = $type->get_name();
        if (($component = $this->module->get($name, false)) instanceof $type_name) {
            $args[] = $component;
            $requested_params[$name] = 'Component: ' . get_class($component) . " \${$name}";
        } elseif ($this->module->has($type_name) && ($service = $this->module->get($type_name)) instanceof $type_name) {
            $args[] = $service;
            $requested_params[$name] = 'Module ' . get_class($this->module) . " DI: {$type_name} \${$name}";
        } elseif (\Yii::$container->has($type_name) && ($service = \Yii::$container->get($type_name)) instanceof $type_name) {
            $args[] = $service;
            $requested_params[$name] = "Container DI: {$type_name} \${$name}";
        } elseif ($type->allows_null()) {
            $args[] = null;
            $requested_params[$name] = "Unavailable service: {$name}";
        } else {
            throw new Exception('Could not load required service: ' . $name);
        }
    }
}