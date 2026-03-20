<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\base;

use Yii;
use yii\di\Service_Locator;
/**
 * Module is the base class for module and application classes.
 *
 * A module represents a sub-application which contains MVC elements by itself, such as
 * models, views, controllers, etc.
 *
 * A module may consist of [[modules|sub-modules]].
 *
 * [[components|Components]] may be registered with the module so that they are globally
 * accessible within the module.
 *
 * For more details and usage information on Module, see the [guide article on modules](guide:structure-modules).
 *
 * @property-write array $aliases List of path aliases to be defined. The array keys are alias names (must
 * start with `@`) and the array values are the corresponding paths or aliases. See [[setAliases()]] for an
 * example.
 * @property string $basePath The root directory of the module.
 * @property string $controllerPath The directory that contains the controller classes.
 * @property string $layoutPath The root directory of layout files. Defaults to "[[viewPath]]/layouts".
 * @property array $modules The modules (indexed by their IDs).
 * @property-read string $uniqueId The unique ID of the module.
 * @property string $version The version of this module. Note that the type of this property differs in getter
 * and setter. See [[getVersion()]] and [[setVersion()]] for details.
 * @property string $viewPath The root directory of view files. Defaults to "[[basePath]]/views".
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Module extends Service_Locator
{
    /**
     * @event ActionEvent an event raised before executing a controller action.
     * You may set [[ActionEvent::isValid]] to be `false` to cancel the action execution.
     */
    public const EVENT_BEFORE_ACTION = 'beforeAction';
    /**
     * @event ActionEvent an event raised after executing a controller action.
     */
    public const EVENT_AFTER_ACTION = 'afterAction';
    /**
     * @var array custom module parameters (name => value).
     */
    public $params = [];
    /**
     * @var string an ID that uniquely identifies this module among other modules which have the same [[module|parent]].
     */
    public $id;
    /**
     * @var Module|null the parent module of this module. `null` if this module does not have a parent.
     */
    public $module;
    /**
     * @var string|bool|null the layout that should be applied for views within this module. This refers to a view name
     * relative to [[layoutPath]]. If this is not set, it means the layout value of the [[module|parent module]]
     * will be taken. If this is `false`, layout will be disabled within this module.
     */
    public $layout;
    /**
     * @var array mapping from controller ID to controller configurations.
     * Each name-value pair specifies the configuration of a single controller.
     * A controller configuration can be either a string or an array.
     * If the former, the string should be the fully qualified class name of the controller.
     * If the latter, the array must contain a `class` element which specifies
     * the controller's fully qualified class name, and the rest of the name-value pairs
     * in the array are used to initialize the corresponding controller properties. For example,
     *
     * ```
     * [
     *   'account' => 'app\controllers\UserController',
     *   'article' => [
     *      'class' => 'app\controllers\PostController',
     *      'pageTitle' => 'something new',
     *   ],
     * ]
     * ```
     */
    public $controller_map = [];
    /**
     * @var string|null the namespace that controller classes are in.
     * This namespace will be used to load controller classes by prepending it to the controller
     * class name.
     *
     * If not set, it will use the `controllers` sub-namespace under the namespace of this module.
     * For example, if the namespace of this module is `foo\bar`, then the default
     * controller namespace would be `foo\bar\controllers`.
     *
     * See also the [guide section on autoloading](guide:concept-autoloading) to learn more about
     * defining namespaces and how classes are loaded.
     */
    public $controller_namespace;
    /**
     * @var string the default route of this module. Defaults to `default`.
     * The route may consist of child module ID, controller ID, and/or action ID.
     * For example, `help`, `post/create`, `admin/post/create`.
     * If action ID is not given, it will take the default value as specified in
     * [[Controller::defaultAction]].
     */
    public $default_route = 'default';
    /**
     * @var string the root directory of the module.
     */
    private ?string $_base_path = null;
    /**
     * @var string The root directory that contains the controller classes for this module.
     */
    private $_controller_path;
    /**
     * @var string the root directory that contains view files for this module
     */
    private $_view_path;
    /**
     * @var string the root directory that contains layout view files for this module.
     */
    private $_layout_path;
    /**
     * @var array child modules of this module
     */
    private $_modules = [];
    /**
     * @var string|callable|null the version of this module.
     * Version can be specified as a PHP callback, which can accept module instance as an argument and should
     * return the actual version. For example:
     *
     * ```
     * function (Module $module) {
     *     //return string|int
     * }
     * ```
     *
     * If not set, [[defaultVersion()]] will be used to determine actual value.
     *
     * @since 2.0.11
     */
    private $_version;
    /**
     * Constructor.
     * @param string $id the ID of this module.
     * @param Module|null $parent the parent module (if any).
     * @param array<string, mixed> $config name-value pairs that will be used to initialize the object properties.
     */
    public function __construct($id, $parent = null, $config = [])
    {
        $this->id = $id;
        $this->module = $parent;
        parent::__construct($config);
    }
    /**
     * Returns the currently requested instance of this module class.
     * If the module class is not currently requested, `null` will be returned.
     * This method is provided so that you access the module instance from anywhere within the module.
     * @return static|null the currently requested instance of this module class, or `null` if the module class is not requested.
     */
    public static function get_instance()
    {
        $class = static::class;
        return Yii::$app->loaded_modules[$class] ?? null;
    }
    /**
     * Sets the currently requested instance of this module class.
     * @param Module|null $instance the currently requested instance of this module class.
     * If it is `null`, the instance of the calling class will be removed, if any.
     */
    public static function set_instance($instance): void
    {
        if ($instance === null) {
            unset(Yii::$app->loaded_modules[static::class]);
        } else {
            Yii::$app->loaded_modules[get_class($instance)] = $instance;
        }
    }
    /**
     * Initializes the module.
     *
     * This method is called after the module is created and initialized with property values
     * given in configuration. The default implementation will initialize [[controllerNamespace]]
     * if it is not set.
     *
     * If you override this method, please make sure you call the parent implementation.
     */
    public function init(): void
    {
        if ($this->controller_namespace === null) {
            $class = get_class($this);
            if (($pos = strrpos($class, '\\')) !== false) {
                $this->controller_namespace = substr($class, 0, $pos) . '\controllers';
            }
        }
    }
    /**
     * Returns an ID that uniquely identifies this module among all modules within the current application.
     * Note that if the module is an application, an empty string will be returned.
     * @return string the unique ID of the module.
     */
    public function get_unique_id()
    {
        return $this->module ? ltrim($this->module->get_unique_id() . '/' . $this->id, '/') : $this->id;
    }
    /**
     * Returns the root directory of the module.
     * It defaults to the directory containing the module class file.
     * @return string the root directory of the module.
     */
    public function get_base_path()
    {
        if ($this->_base_path === null) {
            $class = new \ReflectionClass($this);
            $this->_base_path = dirname($class->get_file_name());
        }
        return $this->_base_path;
    }
    /**
     * Sets the root directory of the module.
     * This method can only be invoked at the beginning of the constructor.
     * @param string $path the root directory of the module. This can be either a directory name or a [path alias](guide:concept-aliases).
     * @throws InvalidArgumentException if the directory does not exist.
     */
    public function set_base_path($path): void
    {
        $path = Yii::get_alias($path);
        $p = strncmp($path, 'phar://', 7) === 0 ? $path : realpath($path);
        if (is_string($p) && is_dir($p)) {
            $this->_base_path = $p;
        } else {
            throw new InvalidArgumentException("The directory does not exist: {$path}");
        }
    }
    /**
     * Returns the directory that contains the controller classes according to [[controllerNamespace]].
     * Note that in order for this method to return a value, you must define
     * an alias for the root namespace of [[controllerNamespace]].
     * @return string the directory that contains the controller classes.
     * @throws InvalidArgumentException if there is no alias defined for the root namespace of [[controllerNamespace]].
     */
    public function get_controller_path()
    {
        if ($this->_controller_path === null) {
            $this->_controller_path = Yii::get_alias('@' . str_replace('\\', '/', $this->controller_namespace));
        }
        return $this->_controller_path;
    }
    /**
     * Sets the directory that contains the controller classes.
     * @param string $path the root directory that contains the controller classes.
     * @throws InvalidArgumentException if the directory is invalid.
     * @since 2.0.44
     */
    public function set_controller_path($path): void
    {
        $this->_controller_path = Yii::get_alias($path);
    }
    /**
     * Returns the directory that contains the view files for this module.
     * @return string the root directory of view files. Defaults to "[[basePath]]/views".
     */
    public function get_view_path()
    {
        if ($this->_view_path === null) {
            $this->_view_path = $this->get_base_path() . DIRECTORY_SEPARATOR . 'views';
        }
        return $this->_view_path;
    }
    /**
     * Sets the directory that contains the view files.
     * @param string $path the root directory of view files.
     * @throws InvalidArgumentException if the directory is invalid.
     */
    public function set_view_path($path): void
    {
        $this->_view_path = Yii::get_alias($path);
    }
    /**
     * Returns the directory that contains layout view files for this module.
     * @return string the root directory of layout files. Defaults to "[[viewPath]]/layouts".
     */
    public function get_layout_path()
    {
        if ($this->_layout_path === null) {
            $this->_layout_path = $this->get_view_path() . DIRECTORY_SEPARATOR . 'layouts';
        }
        return $this->_layout_path;
    }
    /**
     * Sets the directory that contains the layout files.
     * @param string $path the root directory or [path alias](guide:concept-aliases) of layout files.
     * @throws InvalidArgumentException if the directory is invalid
     */
    public function set_layout_path($path): void
    {
        $this->_layout_path = Yii::get_alias($path);
    }
    /**
     * Returns current module version.
     * If version is not explicitly set, [[defaultVersion()]] method will be used to determine its value.
     * @return string the version of this module.
     * @since 2.0.11
     */
    public function get_version()
    {
        if ($this->_version === null) {
            $this->_version = $this->default_version();
        } else if (!is_scalar($this->_version)) {
            $this->_version = call_user_func($this->_version, $this);
        }
        return $this->_version;
    }
    /**
     * Sets current module version.
     * @param string|callable|null $version the version of this module.
     * Version can be specified as a PHP callback, which can accept module instance as an argument and should
     * return the actual version. For example:
     *
     * ```
     * function (Module $module) {
     *     //return string
     * }
     * ```
     *
     * @since 2.0.11
     */
    public function set_version($version): void
    {
        $this->_version = $version;
    }
    /**
     * Returns default module version.
     * Child class may override this method to provide more specific version detection.
     * @return string the version of this module.
     * @since 2.0.11
     */
    protected function default_version()
    {
        if ($this->module === null) {
            return '1.0';
        }
        return $this->module->get_version();
    }
    /**
     * Defines path aliases.
     * This method calls [[Yii::setAlias()]] to register the path aliases.
     * This method is provided so that you can define path aliases when configuring a module.
     * @param array $aliases list of path aliases to be defined. The array keys are alias names
     * (must start with `@`) and the array values are the corresponding paths or aliases.
     * For example,
     *
     * ```
     * [
     *     '@models' => '@app/models', // an existing alias
     *     '@backend' => __DIR__ . '/../backend',  // a directory
     * ]
     * ```
     */
    public function set_aliases($aliases): void
    {
        foreach ($aliases as $name => $alias) {
            Yii::set_alias($name, $alias);
        }
    }
    /**
     * Checks whether the child module of the specified ID exists.
     * This method supports checking the existence of both child and grand child modules.
     * @param string $id module ID. For grand child modules, use ID path relative to this module (e.g. `admin/content`).
     * @return bool whether the named module exists. Both loaded and unloaded modules
     * are considered.
     */
    public function has_module($id)
    {
        if (($pos = strpos($id, '/')) !== false) {
            // sub-module
            $module = $this->get_module(substr($id, 0, $pos));
            return $module === null ? false : $module->has_module(substr($id, $pos + 1));
        }
        return isset($this->_modules[$id]);
    }
    /**
     * Retrieves the child module of the specified ID.
     * This method supports retrieving both child modules and grand child modules.
     * @param string $id module ID (case-sensitive). To retrieve grand child modules,
     * use ID path relative to this module (e.g. `admin/content`).
     * @param bool $load whether to load the module if it is not yet loaded.
     * @return Module|null the module instance, `null` if the module does not exist.
     * @see hasModule()
     */
    public function get_module($id, $load = true)
    {
        if (($pos = strpos($id, '/')) !== false) {
            // sub-module
            $module = $this->get_module(substr($id, 0, $pos));
            return $module === null ? null : $module->get_module(substr($id, $pos + 1), $load);
        }
        if (isset($this->_modules[$id])) {
            if ($this->_modules[$id] instanceof self) {
                return $this->_modules[$id];
            }
            if ($load) {
                Yii::debug("Loading module: {$id}", __METHOD__);
                /** @var self $module */
                $module = Yii::create_object($this->_modules[$id], [$id, $this]);
                $module::set_instance($module);
                return $this->_modules[$id] = $module;
            }
        }
        return null;
    }
    /**
     * Adds a sub-module to this module.
     * @param string $id module ID.
     * @param Module|array|null $module the sub-module to be added to this module. This can
     * be one of the following:
     *
     * - a [[Module]] object
     * - a configuration array: when [[getModule()]] is called initially, the array
     *   will be used to instantiate the sub-module
     * - `null`: the named sub-module will be removed from this module
     */
    public function set_module($id, $module): void
    {
        if ($module === null) {
            unset($this->_modules[$id]);
        } else {
            $this->_modules[$id] = $module;
            if ($module instanceof self) {
                $module->module = $this;
            }
        }
    }
    /**
     * Returns the sub-modules in this module.
     * @param bool $loadedOnly whether to return the loaded sub-modules only. If this is set `false`,
     * then all sub-modules registered in this module will be returned, whether they are loaded or not.
     * Loaded modules will be returned as objects, while unloaded modules as configuration arrays.
     * @return array the modules (indexed by their IDs).
     */
    public function get_modules($loaded_only = false)
    {
        if ($loaded_only) {
            $modules = [];
            foreach ($this->_modules as $module) {
                if ($module instanceof self) {
                    $modules[] = $module;
                }
            }
            return $modules;
        }
        return $this->_modules;
    }
    /**
     * Registers sub-modules in the current module.
     *
     * Each sub-module should be specified as a name-value pair, where
     * name refers to the ID of the module and value the module or a configuration
     * array that can be used to create the module. In the latter case, [[Yii::createObject()]]
     * will be used to create the module.
     *
     * If a new sub-module has the same ID as an existing one, the existing one will be overwritten silently.
     *
     * The following is an example for registering two sub-modules:
     *
     * ```
     * [
     *     'comment' => [
     *         'class' => 'app\modules\comment\CommentModule',
     *         'db' => 'db',
     *     ],
     *     'booking' => ['class' => 'app\modules\booking\BookingModule'],
     * ]
     * ```
     *
     * @param array $modules modules (id => module configuration or instances).
     */
    public function set_modules($modules): void
    {
        foreach ($modules as $id => $module) {
            $this->_modules[$id] = $module;
            if ($module instanceof self) {
                $module->module = $this;
            }
        }
    }
    /**
     * Runs a controller action specified by a route.
     * This method parses the specified route and creates the corresponding child module(s), controller and action
     * instances. It then calls [[Controller::runAction()]] to run the action with the given parameters.
     * If the route is empty, the method will use [[defaultRoute]].
     * @param string $route the route that specifies the action.
     * @param array $params the parameters to be passed to the action
     * @return mixed the result of the action.
     * @throws InvalidRouteException if the requested route cannot be resolved into an action successfully.
     */
    public function run_action(string $route, $params = [])
    {
        $parts = $this->create_controller($route);
        if (is_array($parts)) {
            [$controller, $action_id] = $parts;
            $old_controller = Yii::$app->controller;
            Yii::$app->controller = $controller;
            $result = $controller->run_action($action_id, $params);
            if ($old_controller !== null) {
                Yii::$app->controller = $old_controller;
            }
            return $result;
        }
        $id = $this->get_unique_id();
        throw new Invalid_Route_Exception('Unable to resolve the request "' . ($id === '' ? $route : $id . '/' . $route) . '".');
    }
    /**
     * Creates a controller instance based on the given route.
     *
     * The route should be relative to this module. The method implements the following algorithm
     * to resolve the given route:
     *
     * 1. If the route is empty, use [[defaultRoute]];
     * 2. If the first segment of the route is found in [[controllerMap]], create a controller
     *    based on the corresponding configuration found in [[controllerMap]];
     * 3. If the first segment of the route is a valid module ID as declared in [[modules]],
     *    call the module's `createController()` with the rest part of the route;
     * 4. The given route is in the format of `abc/def/xyz`. Try either `abc\DefController`
     *    or `abc\def\XyzController` class within the [[controllerNamespace|controller namespace]].
     *
     * If any of the above steps resolves into a controller, it is returned together with the rest
     * part of the route which will be treated as the action ID. Otherwise, `false` will be returned.
     *
     * @param string $route the route consisting of module, controller and action IDs.
     * @return array{Controller<static>, string}|false If the controller is created successfully, it will be returned together
     * with the requested action ID. Otherwise `false` will be returned.
     * @throws InvalidConfigException if the controller class and its file do not match.
     *
     * @phpstan-return array{Controller<static>, string}|false
     * @psalm-return array{Controller<self>, string}|false
     */
    public function create_controller($route)
    {
        if ($route === '') {
            $route = $this->default_route;
        }
        // double slashes or leading/ending slashes may cause substr problem
        $route = trim($route, '/');
        if (strpos($route, '//') !== false) {
            return false;
        }
        if (strpos($route, '/') !== false) {
            [$id, $route] = explode('/', $route, 2);
        } else {
            $id = $route;
            $route = '';
        }
        // module and controller map take precedence
        if (isset($this->controller_map[$id])) {
            $controller = Yii::create_object($this->controller_map[$id], [$id, $this]);
            return [$controller, $route];
        }
        $module = $this->get_module($id);
        if ($module !== null) {
            return $module->create_controller($route);
        }
        if (($pos = strrpos($route, '/')) !== false) {
            $id .= '/' . substr($route, 0, $pos);
            $route = substr($route, $pos + 1);
        }
        $controller = $this->create_controller_by_id($id);
        if ($controller === null && $route !== '') {
            $controller = $this->create_controller_by_id($id . '/' . $route);
            $route = '';
        }
        return $controller === null ? false : [$controller, $route];
    }
    /**
     * Creates a controller based on the given controller ID.
     *
     * The controller ID is relative to this module. The controller class
     * should be namespaced under [[controllerNamespace]].
     *
     * Note that this method does not check [[modules]] or [[controllerMap]].
     *
     * @param string $id the controller ID.
     * @return Controller<static>|null the newly created controller instance, or `null` if the controller ID is invalid.
     * @throws InvalidConfigException if the controller class and its file name do not match.
     * This exception is only thrown when in debug mode.
     *
     * @phpstan-return Controller<static>|null
     * @psalm-return Controller<self>|null
     */
    public function create_controller_by_id($id): ?\yii\base\Controller
    {
        $pos = strrpos($id, '/');
        if ($pos === false) {
            $prefix = '';
            $class_name = $id;
        } else {
            $prefix = substr($id, 0, $pos + 1);
            $class_name = substr($id, $pos + 1);
        }
        if ($this->is_incorrect_class_name_or_prefix($class_name, $prefix)) {
            return null;
        }
        $class_name = preg_replace_callback('%-([a-z0-9_])%i', fn($matches) => ucfirst($matches[1]), ucfirst($class_name)) . 'Controller';
        $class_name = ltrim($this->controller_namespace . '\\' . str_replace('/', '\\', $prefix) . $class_name, '\\');
        if (strpos($class_name, '-') !== false || !class_exists($class_name)) {
            return null;
        }
        if (is_subclass_of($class_name, 'yii\base\Controller')) {
            $controller = Yii::create_object($class_name, [$id, $this]);
            return get_class($controller) === $class_name ? $controller : null;
        }
        throw new Invalid_Config_Exception('Controller class must extend from \yii\base\Controller.');
    }
    /**
     * Checks if class name or prefix is incorrect
     *
     * @param string $className
     */
    private function is_incorrect_class_name_or_prefix($class_name, string $prefix): bool
    {
        if (!preg_match('%^[a-z][a-z0-9\-_]*$%', $class_name)) {
            return true;
        }
        if ($prefix !== '' && !preg_match('%^[a-z0-9_/]+$%i', $prefix)) {
            return true;
        }
        return false;
    }
    /**
     * This method is invoked right before an action within this module is executed.
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
     *     if (!parent::beforeAction($action)) {
     *         return false;
     *     }
     *
     *     // your custom code here
     *
     *     return true; // or false to not run the action
     * }
     * ```
     *
     * @param Action<Controller<static>> $action the action to be executed.
     * @return bool whether the action should continue to be executed.
     *
     * @phpstan-param Action<Controller<static>> $action
     * @psalm-param Action<Controller<self>> $action
     */
    public function before_action($action)
    {
        $event = new Action_Event($action);
        $this->trigger(self::EVENT_BEFORE_ACTION, $event);
        return $event->is_valid;
    }
    /**
     * This method is invoked right after an action within this module is executed.
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
     * @param Action<Controller<static>> $action the action just executed.
     * @param mixed $result the action return result.
     * @return mixed the processed action result.
     *
     * @phpstan-param Action<Controller<static>> $action
     * @psalm-param Action<Controller<self>> $action
     */
    public function after_action($action, $result)
    {
        $event = new Action_Event($action);
        $event->result = $result;
        $this->trigger(self::EVENT_AFTER_ACTION, $event);
        return $event->result;
    }
    /**
     * {@inheritdoc}
     *
     * Since version 2.0.13, if a component isn't defined in the module, it will be looked up in the parent module.
     * The parent module may be the application.
     */
    public function get($id, $throw_exception = true)
    {
        if (!isset($this->module)) {
            return parent::get($id, $throw_exception);
        }
        $component = parent::get($id, false);
        if ($component === null) {
            return $this->module->get($id, $throw_exception);
        }
        return $component;
    }
    /**
     * {@inheritdoc}
     *
     * Since version 2.0.13, if a component isn't defined in the module, it will be looked up in the parent module.
     * The parent module may be the application.
     */
    public function has($id, $check_instance = false): bool
    {
        if (parent::has($id, $check_instance)) {
            return true;
        }
        return isset($this->module) && $this->module->has($id, $check_instance);
    }
}