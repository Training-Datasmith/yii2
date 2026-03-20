<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\di;

use ReflectionClass;
use Reflection_Exception;
use ReflectionNamedType;
use ReflectionParameter;
use Yii;
use yii\base\Component;
use yii\base\Invalid_Config_Exception;
use yii\helpers\Array_Helper;
/**
 * Container implements a [dependency injection](https://en.wikipedia.org/wiki/Dependency_injection) container.
 *
 * A dependency injection (DI) container is an object that knows how to instantiate and configure objects and
 * all their dependent objects. For more information about DI, please refer to
 * [Martin Fowler's article](https://martinfowler.com/articles/injection.html).
 *
 * Container supports constructor injection as well as property injection.
 *
 * To use Container, you first need to set up the class dependencies by calling [[set()]].
 * You then call [[get()]] to create a new class object. The Container will automatically instantiate
 * dependent objects, inject them into the object being created, configure, and finally return the newly created object.
 *
 * By default, [[\Yii::$container]] refers to a Container instance which is used by [[\Yii::createObject()]]
 * to create new object instances. You may use this method to replace the `new` operator
 * when creating a new object, which gives you the benefit of automatic dependency resolution and default
 * property configuration.
 *
 * Below is an example of using Container:
 *
 * ```
 * namespace app\models;
 *
 * use yii\base\BaseObject;
 * use yii\db\Connection;
 * use yii\di\Container;
 *
 * interface UserFinderInterface
 * {
 *     function findUser();
 * }
 *
 * class UserFinder extends BaseObject implements UserFinderInterface
 * {
 *     public $db;
 *
 *     public function __construct(Connection $db, $config = [])
 *     {
 *         $this->db = $db;
 *         parent::__construct($config);
 *     }
 *
 *     public function findUser()
 *     {
 *     }
 * }
 *
 * class UserLister extends BaseObject
 * {
 *     public $finder;
 *
 *     public function __construct(UserFinderInterface $finder, $config = [])
 *     {
 *         $this->finder = $finder;
 *         parent::__construct($config);
 *     }
 * }
 *
 * $container = new Container;
 * $container->set('yii\db\Connection', [
 *     'dsn' => '...',
 * ]);
 * $container->set('app\models\UserFinderInterface', [
 *     'class' => 'app\models\UserFinder',
 * ]);
 * $container->set('userLister', 'app\models\UserLister');
 *
 * $lister = $container->get('userLister');
 *
 * // which is equivalent to:
 *
 * $db = new \yii\db\Connection(['dsn' => '...']);
 * $finder = new UserFinder($db);
 * $lister = new UserLister($finder);
 * ```
 *
 * For more details and usage information on Container, see the [guide article on di-containers](guide:concept-di-container).
 *
 * @property array $definitions The list of the object definitions or the loaded shared objects (type or ID =>
 * definition or instance).
 * @property-write bool $resolveArrays Whether to attempt to resolve elements in array dependencies.
 * @property-write array $singletons Array of singleton definitions. See [[setDefinitions()]] for allowed
 * formats of array.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Container extends Component
{
    /**
     * @var array singleton objects indexed by their types
     */
    private $_singletons = [];
    /**
     * @var array object definitions indexed by their types
     */
    private array $_definitions = [];
    /**
     * @var array constructor parameters indexed by object types
     */
    private array $_params = [];
    /**
     * @var array cached ReflectionClass objects indexed by class/interface names
     */
    private array $_reflections = [];
    /**
     * @var array<class-string, array<string, mixed>> cached dependencies indexed by class/interface names. Each class name
     * is associated with a list of constructor parameter types or default values.
     */
    private array $_dependencies = [];
    /**
     * @var bool whether to attempt to resolve elements in array dependencies
     */
    private bool $_resolve_arrays = false;
    /**
     * Returns an instance of the requested class.
     *
     * You may provide constructor parameters (`$params`) and object configurations (`$config`)
     * that will be used during the creation of the instance.
     *
     * If the class implements [[\yii\base\Configurable]], the `$config` parameter will be passed as the last
     * parameter to the class constructor; Otherwise, the configuration will be applied *after* the object is
     * instantiated.
     *
     * Note that if the class is declared to be singleton by calling [[setSingleton()]],
     * the same instance of the class will be returned each time this method is called.
     * In this case, the constructor parameters and object configurations will be used
     * only if the class is instantiated the first time.
     *
     * @template T of object
     *
     * @param string|class-string<T>|Instance $class the class Instance, name, or an alias name (e.g. `foo`) that was previously
     * registered via [[set()]] or [[setSingleton()]].
     * @param array $params a list of constructor parameter values. Use one of two definitions:
     *  - Parameters as name-value pairs, for example: `['posts' => PostRepository::class]`.
     *  - Parameters in the order they appear in the constructor declaration. If you want to skip some parameters,
     *    you should index the remaining ones with the integers that represent their positions in the constructor
     *    parameter list.
     *    Dependencies indexed by name and by position in the same array are not allowed.
     * @param array $config a list of name-value pairs that will be used to initialize the object properties.
     * @return ($class is class-string<T> ? T : object) an instance of the requested class.
     * @throws InvalidConfigException if the class cannot be recognized or correspond to an invalid definition
     * @throws NotInstantiableException If resolved to an abstract class or an interface (since 2.0.9)
     */
    public function get($class, $params = [], $config = [])
    {
        if ($class instanceof Instance) {
            $class = $class->id;
        }
        if (isset($this->_singletons[$class])) {
            // singleton
            return $this->_singletons[$class];
        }
        if (!isset($this->_definitions[$class])) {
            return $this->build($class, $params, $config);
        }
        $definition = $this->_definitions[$class];
        if (is_callable($definition, true)) {
            $params = $this->resolve_dependencies($this->merge_params($class, $params));
            $object = call_user_func($definition, $this, $params, $config);
        } elseif (is_array($definition)) {
            $concrete = $definition['class'];
            unset($definition['class']);
            $config = array_merge($definition, $config);
            $params = $this->merge_params($class, $params);
            if ($concrete === $class) {
                $object = $this->build($class, $params, $config);
            } else {
                $object = $this->get($concrete, $params, $config);
            }
        } elseif (is_object($definition)) {
            return $this->_singletons[$class] = $definition;
        } else {
            throw new Invalid_Config_Exception('Unexpected object definition type: ' . gettype($definition));
        }
        if (array_key_exists($class, $this->_singletons)) {
            // singleton
            $this->_singletons[$class] = $object;
        }
        return $object;
    }
    /**
     * Registers a class definition with this container.
     *
     * For example,
     *
     * ```
     * // register a class name as is. This can be skipped.
     * $container->set('yii\db\Connection');
     *
     * // register an interface
     * // When a class depends on the interface, the corresponding class
     * // will be instantiated as the dependent object
     * $container->set('yii\mail\MailInterface', 'yii\swiftmailer\Mailer');
     *
     * // register an alias name. You can use $container->get('foo')
     * // to create an instance of Connection
     * $container->set('foo', 'yii\db\Connection');
     *
     * // register a class with configuration. The configuration
     * // will be applied when the class is instantiated by get()
     * $container->set('yii\db\Connection', [
     *     'dsn' => 'mysql:host=127.0.0.1;dbname=demo',
     *     'username' => 'root',
     *     'password' => '',
     *     'charset' => 'utf8',
     * ]);
     *
     * // register an alias name with class configuration
     * // In this case, a "class" element is required to specify the class
     * $container->set('db', [
     *     'class' => 'yii\db\Connection',
     *     'dsn' => 'mysql:host=127.0.0.1;dbname=demo',
     *     'username' => 'root',
     *     'password' => '',
     *     'charset' => 'utf8',
     * ]);
     *
     * // register a PHP callable
     * // The callable will be executed when $container->get('db') is called
     * $container->set('db', function ($container, $params, $config) {
     *     return new \yii\db\Connection($config);
     * });
     * ```
     *
     * If a class definition with the same name already exists, it will be overwritten with the new one.
     * You may use [[has()]] to check if a class definition already exists.
     *
     * @param string $class class name, interface name or alias name
     * @param mixed $definition the definition associated with `$class`. It can be one of the following:
     *
     * - a PHP callable: The callable will be executed when [[get()]] is invoked. The signature of the callable
     *   should be `function ($container, $params, $config)`, where `$params` stands for the list of constructor
     *   parameters, `$config` the object configuration, and `$container` the container object. The return value
     *   of the callable will be returned by [[get()]] as the object instance requested.
     * - a configuration array: the array contains name-value pairs that will be used to initialize the property
     *   values of the newly created object when [[get()]] is called. The `class` element stands for
     *   the class of the object to be created. If `class` is not specified, `$class` will be used as the class name.
     * - a string: a class name, an interface name or an alias name.
     * @param array $params the list of constructor parameters. The parameters will be passed to the class
     * constructor when [[get()]] is called.
     * @return $this the container itself
     */
    public function set($class, $definition = [], array $params = []): self
    {
        $this->_definitions[$class] = $this->normalize_definition($class, $definition);
        $this->_params[$class] = $params;
        unset($this->_singletons[$class]);
        return $this;
    }
    /**
     * Registers a class definition with this container and marks the class as a singleton class.
     *
     * This method is similar to [[set()]] except that classes registered via this method will only have one
     * instance. Each time [[get()]] is called, the same instance of the specified class will be returned.
     *
     * @param string $class class name, interface name or alias name
     * @param mixed $definition the definition associated with `$class`. See [[set()]] for more details.
     * @param array $params the list of constructor parameters. The parameters will be passed to the class
     * constructor when [[get()]] is called.
     * @return $this the container itself
     * @see set()
     */
    public function set_singleton($class, $definition = [], array $params = []): self
    {
        $this->_definitions[$class] = $this->normalize_definition($class, $definition);
        $this->_params[$class] = $params;
        $this->_singletons[$class] = null;
        return $this;
    }
    /**
     * Returns a value indicating whether the container has the definition of the specified name.
     * @param string $class class name, interface name or alias name
     * @return bool Whether the container has the definition of the specified name.
     * @see set()
     */
    public function has($class): bool
    {
        return isset($this->_definitions[$class]);
    }
    /**
     * Returns a value indicating whether the given name corresponds to a registered singleton.
     * @param string $class class name, interface name or alias name
     * @param bool $checkInstance whether to check if the singleton has been instantiated.
     * @return bool whether the given name corresponds to a registered singleton. If `$checkInstance` is true,
     * the method should return a value indicating whether the singleton has been instantiated.
     */
    public function has_singleton($class, $check_instance = false): bool
    {
        return $check_instance ? isset($this->_singletons[$class]) : array_key_exists($class, $this->_singletons);
    }
    /**
     * Removes the definition for the specified name.
     * @param string $class class name, interface name or alias name
     */
    public function clear($class): void
    {
        unset($this->_definitions[$class], $this->_singletons[$class]);
    }
    /**
     * Normalizes the class definition.
     * @param string $class class name
     * @param string|array|callable $definition the class definition
     * @return array the normalized class definition
     * @throws InvalidConfigException if the definition is invalid.
     */
    protected function normalize_definition($class, $definition)
    {
        if (empty($definition)) {
            return ['class' => $class];
        }
        if (is_string($definition)) {
            return ['class' => $definition];
        }
        if ($definition instanceof Instance) {
            return ['class' => $definition->id];
        }
        if (is_callable($definition, true) || is_object($definition)) {
            return $definition;
        }
        if (is_array($definition)) {
            if (!isset($definition['class']) && isset($definition['__class'])) {
                $definition['class'] = $definition['__class'];
                unset($definition['__class']);
            }
            if (!isset($definition['class'])) {
                if (strpos($class, '\\') !== false) {
                    $definition['class'] = $class;
                } else {
                    throw new Invalid_Config_Exception('A class definition requires a "class" member.');
                }
            }
            return $definition;
        }
        throw new Invalid_Config_Exception("Unsupported definition type for \"{$class}\": " . gettype($definition));
    }
    /**
     * Returns the list of the object definitions or the loaded shared objects.
     * @return array the list of the object definitions or the loaded shared objects (type or ID => definition or instance).
     */
    public function get_definitions()
    {
        return $this->_definitions;
    }
    /**
     * Creates an instance of the specified class.
     * This method will resolve dependencies of the specified class, instantiate them, and inject
     * them into the new instance of the specified class.
     *
     * @template T of object
     *
     * @param class-string<T> $class the class name
     * @param array $params constructor parameters
     * @param array $config configurations to be applied to the new instance
     * @return T the newly created instance of the specified class
     * @throws NotInstantiableException If resolved to an abstract class or an interface (since 2.0.9)
     */
    protected function build($class, $params, $config)
    {
        /** @var ReflectionClass<T> $reflection */
        [$reflection, $dependencies] = $this->get_dependencies($class);
        $add_dependencies = [];
        if (isset($config['__construct()'])) {
            $add_dependencies = $config['__construct()'];
            unset($config['__construct()']);
        }
        foreach ($params as $index => $param) {
            $add_dependencies[$index] = $param;
        }
        $this->validate_dependencies($add_dependencies);
        if ($add_dependencies && is_int(key($add_dependencies))) {
            $dependencies = array_values($dependencies);
            $dependencies = $this->merge_dependencies($dependencies, $add_dependencies);
        } else {
            $dependencies = $this->merge_dependencies($dependencies, $add_dependencies);
            $dependencies = array_values($dependencies);
        }
        $dependencies = $this->resolve_dependencies($dependencies, $reflection);
        if (!$reflection->is_instantiable()) {
            throw new Not_Instantiable_Exception($reflection->name);
        }
        if (empty($config)) {
            return $reflection->new_instance_args($dependencies);
        }
        $config = $this->resolve_dependencies($config);
        if (!empty($dependencies) && $reflection->implements_interface('yii\base\Configurable')) {
            // set $config as the last parameter (existing one will be overwritten)
            $dependencies[count($dependencies) - 1] = $config;
            return $reflection->new_instance_args($dependencies);
        }
        $object = $reflection->new_instance_args($dependencies);
        foreach ($config as $name => $value) {
            $object->{$name} = $value;
        }
        return $object;
    }
    /**
     * @param array $b
     */
    private function merge_dependencies(array $a, $b): array
    {
        foreach ($b as $index => $dependency) {
            $a[$index] = $dependency;
        }
        return $a;
    }
    /**
     * @param array $parameters
     * @throws InvalidConfigException
     */
    private function validate_dependencies($parameters): void
    {
        $has_string_parameter = false;
        $has_int_parameter = false;
        foreach ($parameters as $index => $parameter) {
            if (is_string($index)) {
                $has_string_parameter = true;
                if ($has_int_parameter) {
                    break;
                }
            } else {
                $has_int_parameter = true;
                if ($has_string_parameter) {
                    break;
                }
            }
        }
        if ($has_int_parameter && $has_string_parameter) {
            throw new Invalid_Config_Exception('Dependencies indexed by name and by position in the same array are not allowed.');
        }
    }
    /**
     * Merges the user-specified constructor parameters with the ones registered via [[set()]].
     * @param string $class class name, interface name or alias name
     * @param array $params the constructor parameters
     * @return array the merged parameters
     */
    protected function merge_params($class, $params)
    {
        if (empty($this->_params[$class])) {
            return $params;
        }
        if (empty($params)) {
            return $this->_params[$class];
        }
        $ps = $this->_params[$class];
        foreach ($params as $index => $value) {
            $ps[$index] = $value;
        }
        return $ps;
    }
    /**
     * Returns the dependencies of the specified class.
     *
     * @template T of object
     *
     * @param class-string<T> $class class name, interface name or alias name
     * @return array{ReflectionClass<T>, array<string, mixed>} the dependencies of the specified class.
     * @throws NotInstantiableException if a dependency cannot be resolved or if a dependency cannot be fulfilled.
     */
    protected function get_dependencies(string $class): array
    {
        if (isset($this->_reflections[$class])) {
            return [$this->_reflections[$class], $this->_dependencies[$class]];
        }
        $dependencies = [];
        try {
            $reflection = new ReflectionClass($class);
        } catch (\Reflection_Exception $e) {
            throw new Not_Instantiable_Exception($class, 'Failed to instantiate component or class "' . $class . '".', 0, $e);
        }
        $constructor = $reflection->get_constructor();
        if ($constructor !== null) {
            foreach ($constructor->get_parameters() as $param) {
                if (PHP_VERSION_ID >= 50600 && $param->is_variadic()) {
                    break;
                }
                if (PHP_VERSION_ID >= 80000) {
                    $c = $param->get_type();
                    $is_class = false;
                    if ($c instanceof ReflectionNamedType) {
                        $is_class = !$c->is_builtin();
                    }
                } else {
                    try {
                        $c = $param->get_class();
                    } catch (Reflection_Exception $e) {
                        if (!$this->is_nulled_param($param)) {
                            $not_instantiable_class = null;
                            $type = $param->get_type();
                            if ($type instanceof ReflectionNamedType) {
                                $not_instantiable_class = $type->get_name();
                            }
                            throw new Not_Instantiable_Exception($not_instantiable_class, $not_instantiable_class === null ? 'Can not instantiate unknown class.' : null);
                        }
                        $c = null;
                    }
                    $is_class = $c !== null;
                }
                $class_name = $is_class ? $c->get_name() : null;
                if ($class_name !== null) {
                    $dependencies[$param->get_name()] = Instance::of($class_name, $this->is_nulled_param($param));
                } else {
                    $dependencies[$param->get_name()] = $param->is_default_value_available() ? $param->get_default_value() : null;
                }
            }
        }
        $this->_reflections[$class] = $reflection;
        $this->_dependencies[$class] = $dependencies;
        return [$reflection, $dependencies];
    }
    /**
     * @param ReflectionParameter $param
     */
    private function is_nulled_param($param): bool
    {
        if ($param->is_optional()) {
            return true;
        }
        return PHP_VERSION_ID >= 70100 && $param->get_type()->allows_null();
    }
    /**
     * Resolves dependencies by replacing them with the actual object instances.
     * @param array $dependencies the dependencies
     * @param ReflectionClass<object>|null $reflection the class reflection associated with the dependencies
     * @return array the resolved dependencies
     * @throws InvalidConfigException if a dependency cannot be resolved or if a dependency cannot be fulfilled.
     */
    protected function resolve_dependencies(array $dependencies, $reflection = null): array
    {
        foreach ($dependencies as $index => $dependency) {
            if ($dependency instanceof Instance) {
                if ($dependency->id !== null) {
                    $dependencies[$index] = $dependency->get($this);
                } elseif ($reflection !== null) {
                    $name = $reflection->get_constructor()->get_parameters()[$index]->get_name();
                    $class = $reflection->get_name();
                    throw new Invalid_Config_Exception("Missing required parameter \"{$name}\" when instantiating \"{$class}\".");
                }
            } elseif ($this->_resolve_arrays && is_array($dependency)) {
                $dependencies[$index] = $this->resolve_dependencies($dependency, $reflection);
            }
        }
        return $dependencies;
    }
    /**
     * Invoke a callback with resolving dependencies in parameters.
     *
     * This method allows invoking a callback and let type hinted parameter names to be
     * resolved as objects of the Container. It additionally allows calling function using named parameters.
     *
     * For example, the following callback may be invoked using the Container to resolve the formatter dependency:
     *
     * ```
     * $formatString = function($string, \yii\i18n\Formatter $formatter) {
     *    // ...
     * }
     * Yii::$container->invoke($formatString, ['string' => 'Hello World!']);
     * ```
     *
     * This will pass the string `'Hello World!'` as the first param, and a formatter instance created
     * by the DI container as the second param to the callable.
     *
     * @param callable $callback callable to be invoked.
     * @param array $params The array of parameters for the function.
     * This can be either a list of parameters, or an associative array representing named function parameters.
     * @return mixed the callback return value.
     * @throws InvalidConfigException if a dependency cannot be resolved or if a dependency cannot be fulfilled.
     * @throws NotInstantiableException If resolved to an abstract class or an interface (since 2.0.9)
     * @since 2.0.7
     */
    public function invoke(callable $callback, $params = [])
    {
        return call_user_func_array($callback, $this->resolve_callable_dependencies($callback, $params));
    }
    /**
     * Resolve dependencies for a function.
     *
     * This method can be used to implement similar functionality as provided by [[invoke()]] in other
     * components.
     *
     * @param callable $callback callable to be invoked.
     * @param array $params The array of parameters for the function, can be either numeric or associative.
     * @return array The resolved dependencies.
     * @throws InvalidConfigException if a dependency cannot be resolved or if a dependency cannot be fulfilled.
     * @throws NotInstantiableException If resolved to an abstract class or an interface (since 2.0.9)
     * @since 2.0.7
     */
    public function resolve_callable_dependencies(callable $callback, array $params = []): array
    {
        if (is_array($callback)) {
            $reflection = new \ReflectionMethod($callback[0], $callback[1]);
        } elseif (is_object($callback) && !$callback instanceof \Closure) {
            $reflection = new \ReflectionMethod($callback, '__invoke');
        } else {
            $reflection = new \ReflectionFunction($callback);
        }
        $args = [];
        $associative = Array_Helper::is_associative($params);
        foreach ($reflection->get_parameters() as $param) {
            $name = $param->get_name();
            if (PHP_VERSION_ID >= 80000) {
                $class = $param->get_type();
                if ($class instanceof \ReflectionUnionType || PHP_VERSION_ID >= 80100 && $class instanceof \ReflectionIntersectionType) {
                    $is_class = false;
                    /** @var ReflectionNamedType $type */
                    foreach ($class->get_types() as $type) {
                        if (!$type->is_builtin()) {
                            $class = $type;
                            $is_class = true;
                            break;
                        }
                    }
                } else {
                    /** @var ReflectionNamedType|null $class */
                    $is_class = $class !== null && !$class->is_builtin();
                }
            } else {
                $class = $param->get_class();
                $is_class = $class !== null;
            }
            if ($is_class) {
                $class_name = $class->get_name();
                if (PHP_VERSION_ID >= 50600 && $param->is_variadic()) {
                    $args = array_merge($args, array_values($params));
                    break;
                }
                if ($associative && isset($params[$name]) && $params[$name] instanceof $class_name) {
                    $args[] = $params[$name];
                    unset($params[$name]);
                } elseif (!$associative && isset($params[0]) && $params[0] instanceof $class_name) {
                    $args[] = array_shift($params);
                } elseif (isset(Yii::$app) && Yii::$app->has($name) && ($obj = Yii::$app->get($name)) instanceof $class_name) {
                    $args[] = $obj;
                } else {
                    // If the argument is optional we catch not instantiable exceptions
                    try {
                        $args[] = $this->get($class_name);
                    } catch (Not_Instantiable_Exception $e) {
                        if ($param->is_default_value_available()) {
                            $args[] = $param->get_default_value();
                        } else {
                            throw $e;
                        }
                    }
                }
            } elseif ($associative && isset($params[$name])) {
                $args[] = $params[$name];
                unset($params[$name]);
            } elseif (!$associative && count($params)) {
                $args[] = array_shift($params);
            } elseif ($param->is_default_value_available()) {
                $args[] = $param->get_default_value();
            } elseif (!$param->is_optional()) {
                $func_name = $reflection->get_name();
                throw new Invalid_Config_Exception("Missing required parameter \"{$name}\" when calling \"{$func_name}\".");
            }
        }
        foreach ($params as $value) {
            $args[] = $value;
        }
        return $args;
    }
    /**
     * Registers class definitions within this container.
     *
     * @param array $definitions array of definitions. There are two allowed formats of array.
     * The first format:
     *  - key: class name, interface name or alias name. The key will be passed to the [[set()]] method
     *    as a first argument `$class`.
     *  - value: the definition associated with `$class`. Possible values are described in
     *    [[set()]] documentation for the `$definition` parameter. Will be passed to the [[set()]] method
     *    as the second argument `$definition`.
     *
     * Example:
     * ```
     * $container->setDefinitions([
     *     'yii\web\Request' => 'app\components\Request',
     *     'yii\web\Response' => [
     *         'class' => 'app\components\Response',
     *         'format' => 'json'
     *     ],
     *     'foo\Bar' => function () {
     *         $qux = new Qux;
     *         $foo = new Foo($qux);
     *         return new Bar($foo);
     *     }
     * ]);
     * ```
     *
     * The second format:
     *  - key: class name, interface name or alias name. The key will be passed to the [[set()]] method
     *    as a first argument `$class`.
     *  - value: array of two elements. The first element will be passed the [[set()]] method as the
     *    second argument `$definition`, the second one — as `$params`.
     *
     * Example:
     * ```
     * $container->setDefinitions([
     *     'foo\Bar' => [
     *          ['class' => 'app\Bar'],
     *          [Instance::of('baz')]
     *      ]
     * ]);
     * ```
     *
     * @see set() to know more about possible values of definitions
     * @since 2.0.11
     */
    public function set_definitions(array $definitions): void
    {
        foreach ($definitions as $class => $definition) {
            if (is_array($definition) && count($definition) === 2 && array_values($definition) === $definition && is_array($definition[1])) {
                $this->set($class, $definition[0], $definition[1]);
                continue;
            }
            $this->set($class, $definition);
        }
    }
    /**
     * Registers class definitions as singletons within this container by calling [[setSingleton()]].
     *
     * @param array $singletons array of singleton definitions. See [[setDefinitions()]]
     * for allowed formats of array.
     *
     * @see setDefinitions() for allowed formats of $singletons parameter
     * @see setSingleton() to know more about possible values of definitions
     * @since 2.0.11
     */
    public function set_singletons(array $singletons): void
    {
        foreach ($singletons as $class => $definition) {
            if (is_array($definition) && count($definition) === 2 && array_values($definition) === $definition) {
                $this->set_singleton($class, $definition[0], $definition[1]);
                continue;
            }
            $this->set_singleton($class, $definition);
        }
    }
    /**
     * @param bool $value whether to attempt to resolve elements in array dependencies
     * @since 2.0.37
     */
    public function set_resolve_arrays($value): void
    {
        $this->_resolve_arrays = (bool) $value;
    }
}