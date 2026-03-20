<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\base;

use Yii;
/**
 * Application is the base class for all application classes.
 *
 * For more details and usage information on Application, see the [guide article on applications](guide:structure-applications).
 *
 * @property-read \yii\web\AssetManager $assetManager The asset manager application component.
 * @property-read \yii\rbac\ManagerInterface|null $authManager The auth manager application component or null
 * if it's not configured.
 * @property string $basePath The root directory of the application.
 * @property-read \yii\caching\CacheInterface|null $cache The cache application component. Null if the
 * component is not enabled.
 * @property-write array $container Values given in terms of name-value pairs.
 * @property-read \yii\db\Connection $db The database connection.
 * @property-read \yii\web\ErrorHandler|\yii\console\ErrorHandler $errorHandler The error handler application
 * component.
 * @property-read \yii\i18n\Formatter $formatter The formatter application component.
 * @property-read \yii\i18n\I18N $i18n The internationalization application component.
 * @property-read \yii\log\Dispatcher $log The log dispatcher application component.
 * @property-read \yii\mail\MailerInterface $mailer The mailer application component.
 * @property-read \yii\web\Request|\yii\console\Request $request The request component.
 * @property-read \yii\web\Response|\yii\console\Response $response The response component.
 * @property string $runtimePath The directory that stores runtime files. Defaults to the "runtime"
 * subdirectory under [[basePath]].
 * @property-read \yii\base\Security $security The security application component.
 * @property string $timeZone The time zone used by this application.
 * @property-read string $uniqueId The unique ID of the module.
 * @property-read \yii\web\UrlManager $urlManager The URL manager for this application.
 * @property string $vendorPath The directory that stores vendor files. Defaults to "vendor" directory under
 * [[basePath]].
 * @property-read View|\yii\web\View $view The view application component that is used to render various view
 * files.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
abstract class Application extends Module
{
    /**
     * @event Event an event raised before the application starts to handle a request.
     */
    public const EVENT_BEFORE_REQUEST = 'beforeRequest';
    /**
     * @event Event an event raised after the application successfully handles a request (before the response is sent out).
     */
    public const EVENT_AFTER_REQUEST = 'afterRequest';
    /**
     * Application state used by [[state]]: application just started.
     */
    public const STATE_BEGIN = 0;
    /**
     * Application state used by [[state]]: application is initializing.
     */
    public const STATE_INIT = 1;
    /**
     * Application state used by [[state]]: application is triggering [[EVENT_BEFORE_REQUEST]].
     */
    public const STATE_BEFORE_REQUEST = 2;
    /**
     * Application state used by [[state]]: application is handling the request.
     */
    public const STATE_HANDLING_REQUEST = 3;
    /**
     * Application state used by [[state]]: application is triggering [[EVENT_AFTER_REQUEST]]..
     */
    public const STATE_AFTER_REQUEST = 4;
    /**
     * Application state used by [[state]]: application is about to send response.
     */
    public const STATE_SENDING_RESPONSE = 5;
    /**
     * Application state used by [[state]]: application has ended.
     */
    public const STATE_END = 6;
    /**
     * @var string the namespace that controller classes are located in.
     * This namespace will be used to load controller classes by prepending it to the controller class name.
     * The default namespace is `app\controllers`.
     *
     * Please refer to the [guide about class autoloading](guide:concept-autoloading.md) for more details.
     */
    public $controller_namespace = 'app\controllers';
    /**
     * @var string the application name.
     */
    public $name = 'My Application';
    /**
     * @var string the charset currently used for the application.
     */
    public $charset = 'UTF-8';
    /**
     * @var string the language that is meant to be used for end users. It is recommended that you
     * use [IETF language tags](https://en.wikipedia.org/wiki/IETF_language_tag). For example, `en` stands
     * for English, while `en-US` stands for English (United States).
     * @see sourceLanguage
     */
    public $language = 'en-US';
    /**
     * @var string the language that the application is written in. This mainly refers to
     * the language that the messages and view files are written in.
     * @see language
     */
    public $source_language = 'en-US';
    /**
     * @var Controller|null the currently active controller instance
     */
    public $controller;
    /**
     * @var string|bool the layout that should be applied for views in this application. Defaults to 'main'.
     * If this is false, layout will be disabled.
     */
    public $layout = 'main';
    /**
     * @var string the requested route
     */
    public $requested_route;
    /**
     * @var Action<covariant Controller>|null the requested Action. If null, it means the request cannot be resolved into an action.
     */
    public $requested_action;
    /**
     * @var array|null the parameters supplied to the requested action.
     */
    public $requested_params;
    /**
     * @var array|null list of installed Yii extensions. Each array element represents a single extension
     * with the following structure:
     *
     * ```
     * [
     *     'name' => 'extension name',
     *     'version' => 'version number',
     *     'bootstrap' => 'BootstrapClassName',  // optional, may also be a configuration array
     *     'alias' => [
     *         '@alias1' => 'to/path1',
     *         '@alias2' => 'to/path2',
     *     ],
     * ]
     * ```
     *
     * The "bootstrap" class listed above will be instantiated during the application
     * [[bootstrap()|bootstrapping process]]. If the class implements [[BootstrapInterface]],
     * its [[BootstrapInterface::bootstrap()|bootstrap()]] method will be also be called.
     *
     * If not set explicitly in the application config, this property will be populated with the contents of
     * `@vendor/yiisoft/extensions.php`.
     */
    public $extensions;
    /**
     * @var array list of components that should be run during the application [[bootstrap()|bootstrapping process]].
     *
     * Each component may be specified in one of the following formats:
     *
     * - an application component ID as specified via [[components]].
     * - a module ID as specified via [[modules]].
     * - a class name.
     * - a configuration array.
     * - a Closure
     *
     * During the bootstrapping process, each component will be instantiated. If the component class
     * implements [[BootstrapInterface]], its [[BootstrapInterface::bootstrap()|bootstrap()]] method
     * will be also be called.
     */
    public $bootstrap = [];
    /**
     * @var int the current application state during a request handling life cycle.
     * This property is managed by the application. Do not modify this property.
     */
    public $state;
    /**
     * @var array list of loaded modules indexed by their class names.
     */
    public $loaded_modules = [];
    /**
     * Constructor.
     * @param array<array-key, mixed> $config name-value pairs that will be used to initialize the object properties.
     * Note that the configuration must contain both [[id]] and [[basePath]].
     * @throws InvalidConfigException if either [[id]] or [[basePath]] configuration is missing.
     */
    public function __construct($config = [])
    {
        Yii::$app = $this;
        static::set_instance($this);
        $this->state = self::STATE_BEGIN;
        $this->pre_init($config);
        $this->register_error_handler($config);
        Component::__construct($config);
    }
    /**
     * Pre-initializes the application.
     * This method is called at the beginning of the application constructor.
     * It initializes several important application properties.
     * If you override this method, please make sure you call the parent implementation.
     * @param array $config the application configuration
     * @throws InvalidConfigException if either [[id]] or [[basePath]] configuration is missing.
     */
    public function pre_init(array &$config): void
    {
        if (!isset($config['id'])) {
            throw new Invalid_Config_Exception('The "id" configuration for the Application is required.');
        }
        if (isset($config['basePath'])) {
            $this->set_base_path($config['basePath']);
            unset($config['basePath']);
        } else {
            throw new Invalid_Config_Exception('The "basePath" configuration for the Application is required.');
        }
        if (isset($config['vendorPath'])) {
            $this->set_vendor_path($config['vendorPath']);
            unset($config['vendorPath']);
        } else {
            // set "@vendor"
            $this->get_vendor_path();
        }
        if (isset($config['runtimePath'])) {
            $this->set_runtime_path($config['runtimePath']);
            unset($config['runtimePath']);
        } else {
            // set "@runtime"
            $this->get_runtime_path();
        }
        if (isset($config['timeZone'])) {
            $this->set_time_zone($config['timeZone']);
            unset($config['timeZone']);
        } elseif (!ini_get('date.timezone')) {
            $this->set_time_zone('UTC');
        }
        if (isset($config['container'])) {
            $this->set_container($config['container']);
            unset($config['container']);
        }
        // merge core components with custom components
        foreach ($this->core_components() as $id => $component) {
            if (!isset($config['components'][$id])) {
                $config['components'][$id] = $component;
            } elseif (is_array($config['components'][$id]) && !isset($config['components'][$id]['class'])) {
                $config['components'][$id]['class'] = $component['class'];
            }
        }
    }
    /**
     * {@inheritdoc}
     */
    public function init(): void
    {
        $this->state = self::STATE_INIT;
        $this->bootstrap();
    }
    /**
     * Initializes extensions and executes bootstrap components.
     * This method is called by [[init()]] after the application has been fully configured.
     * If you override this method, make sure you also call the parent implementation.
     */
    protected function bootstrap()
    {
        if ($this->extensions === null) {
            $file = Yii::get_alias('@vendor/yiisoft/extensions.php');
            $this->extensions = is_file($file) ? include $file : [];
        }
        foreach ($this->extensions as $extension) {
            if (!empty($extension['alias'])) {
                foreach ($extension['alias'] as $name => $path) {
                    Yii::set_alias($name, $path);
                }
            }
            if (isset($extension['bootstrap'])) {
                $component = Yii::create_object($extension['bootstrap']);
                if ($component instanceof Bootstrap_Interface) {
                    Yii::debug('Bootstrap with ' . get_class($component) . '::bootstrap()', __METHOD__);
                    $component->bootstrap($this);
                } else {
                    Yii::debug('Bootstrap with ' . get_class($component), __METHOD__);
                }
            }
        }
        foreach ($this->bootstrap as $mixed) {
            $component = null;
            if ($mixed instanceof \Closure) {
                Yii::debug('Bootstrap with Closure', __METHOD__);
                if (!$component = call_user_func($mixed, $this)) {
                    continue;
                }
            } elseif (is_string($mixed)) {
                if ($this->has($mixed)) {
                    $component = $this->get($mixed);
                } elseif ($this->has_module($mixed)) {
                    $component = $this->get_module($mixed);
                } elseif (strpos($mixed, '\\') === false) {
                    throw new Invalid_Config_Exception("Unknown bootstrapping component ID: {$mixed}");
                }
            }
            if (!isset($component)) {
                $component = Yii::create_object($mixed);
            }
            if ($component instanceof Bootstrap_Interface) {
                Yii::debug('Bootstrap with ' . get_class($component) . '::bootstrap()', __METHOD__);
                $component->bootstrap($this);
            } else {
                Yii::debug('Bootstrap with ' . get_class($component), __METHOD__);
            }
        }
    }
    /**
     * Registers the errorHandler component as a PHP error handler.
     * @param array $config application config
     */
    protected function register_error_handler(array &$config)
    {
        if (YII_ENABLE_ERROR_HANDLER) {
            if (!isset($config['components']['errorHandler']['class'])) {
                echo "Error: no errorHandler component is configured.\n";
                exit(1);
            }
            $this->set('errorHandler', $config['components']['errorHandler']);
            unset($config['components']['errorHandler']);
            $this->get_error_handler()->register();
        }
    }
    /**
     * Returns an ID that uniquely identifies this module among all modules within the current application.
     * Since this is an application instance, it will always return an empty string.
     * @return string the unique ID of the module.
     */
    public function get_unique_id()
    {
        return '';
    }
    /**
     * Sets the root directory of the application and the @app alias.
     * This method can only be invoked at the beginning of the constructor.
     * @param string $path the root directory of the application.
     * @throws InvalidArgumentException if the directory does not exist.
     */
    public function set_base_path($path): void
    {
        parent::set_base_path($path);
        Yii::set_alias('@app', $this->get_base_path());
    }
    /**
     * Runs the application.
     * This is the main entrance of an application.
     * @return int the exit status (0 means normal, non-zero values mean abnormal)
     */
    public function run()
    {
        try {
            $this->state = self::STATE_BEFORE_REQUEST;
            $this->trigger(self::EVENT_BEFORE_REQUEST);
            $this->state = self::STATE_HANDLING_REQUEST;
            $response = $this->handle_request($this->get_request());
            $this->state = self::STATE_AFTER_REQUEST;
            $this->trigger(self::EVENT_AFTER_REQUEST);
            $this->state = self::STATE_SENDING_RESPONSE;
            $response->send();
            $this->state = self::STATE_END;
            return $response->exit_status;
        } catch (Exit_Exception $e) {
            $this->end($e->status_code, $response ?? null);
            return $e->status_code;
        }
    }
    /**
     * Handles the specified request.
     *
     * This method should return an instance of [[Response]] or its child class
     * which represents the handling result of the request.
     *
     * @param Request $request the request to be handled
     * @return Response the resulting response
     */
    abstract public function handle_request($request);
    private $_runtime_path;
    /**
     * Returns the directory that stores runtime files.
     * @return string the directory that stores runtime files.
     * Defaults to the "runtime" subdirectory under [[basePath]].
     */
    public function get_runtime_path()
    {
        if ($this->_runtime_path === null) {
            $this->set_runtime_path($this->get_base_path() . DIRECTORY_SEPARATOR . 'runtime');
        }
        return $this->_runtime_path;
    }
    /**
     * Sets the directory that stores runtime files.
     * @param string $path the directory that stores runtime files.
     */
    public function set_runtime_path($path): void
    {
        $this->_runtime_path = Yii::get_alias($path);
        Yii::set_alias('@runtime', $this->_runtime_path);
    }
    private $_vendor_path;
    /**
     * Returns the directory that stores vendor files.
     * @return string the directory that stores vendor files.
     * Defaults to "vendor" directory under [[basePath]].
     */
    public function get_vendor_path()
    {
        if ($this->_vendor_path === null) {
            $this->set_vendor_path($this->get_base_path() . DIRECTORY_SEPARATOR . 'vendor');
        }
        return $this->_vendor_path;
    }
    /**
     * Sets the directory that stores vendor files.
     * @param string $path the directory that stores vendor files.
     */
    public function set_vendor_path($path): void
    {
        $this->_vendor_path = Yii::get_alias($path);
        Yii::set_alias('@vendor', $this->_vendor_path);
        Yii::set_alias('@bower', $this->_vendor_path . DIRECTORY_SEPARATOR . 'bower');
        Yii::set_alias('@npm', $this->_vendor_path . DIRECTORY_SEPARATOR . 'npm');
    }
    /**
     * Returns the time zone used by this application.
     * This is a simple wrapper of PHP function date_default_timezone_get().
     * If time zone is not configured in php.ini or application config,
     * it will be set to UTC by default.
     * @return string the time zone used by this application.
     * @see https://www.php.net/manual/en/function.date-default-timezone-get.php
     */
    public function get_time_zone()
    {
        return date_default_timezone_get();
    }
    /**
     * Sets the time zone used by this application.
     * This is a simple wrapper of PHP function date_default_timezone_set().
     * Refer to the [php manual](https://www.php.net/manual/en/timezones.php) for available timezones.
     * @param string $value the time zone used by this application.
     * @see https://www.php.net/manual/en/function.date-default-timezone-set.php
     */
    public function set_time_zone($value): void
    {
        date_default_timezone_set($value);
    }
    /**
     * Returns the database connection component.
     * @return \yii\db\Connection the database connection.
     */
    public function get_db()
    {
        return $this->get('db');
    }
    /**
     * Returns the log dispatcher component.
     * @return \yii\log\Dispatcher the log dispatcher application component.
     */
    public function get_log()
    {
        return $this->get('log');
    }
    /**
     * Returns the error handler component.
     * @return \yii\web\ErrorHandler|\yii\console\ErrorHandler the error handler application component.
     */
    public function get_error_handler()
    {
        return $this->get('errorHandler');
    }
    /**
     * Returns the cache component.
     * @return \yii\caching\CacheInterface|null the cache application component. Null if the component is not enabled.
     */
    public function get_cache()
    {
        return $this->get('cache', false);
    }
    /**
     * Returns the formatter component.
     * @return \yii\i18n\Formatter the formatter application component.
     */
    public function get_formatter()
    {
        return $this->get('formatter');
    }
    /**
     * Returns the request component.
     * @return \yii\web\Request|\yii\console\Request the request component.
     */
    public function get_request()
    {
        return $this->get('request');
    }
    /**
     * Returns the response component.
     * @return \yii\web\Response|\yii\console\Response the response component.
     */
    public function get_response()
    {
        return $this->get('response');
    }
    /**
     * Returns the view object.
     * @return View|\yii\web\View the view application component that is used to render various view files.
     */
    public function get_view()
    {
        return $this->get('view');
    }
    /**
     * Returns the URL manager for this application.
     * @return \yii\web\UrlManager the URL manager for this application.
     */
    public function get_url_manager()
    {
        return $this->get('urlManager');
    }
    /**
     * Returns the internationalization (i18n) component.
     * @return \yii\i18n\I18N the internationalization application component.
     */
    public function get_i18n()
    {
        return $this->get('i18n');
    }
    /**
     * Returns the mailer component.
     * @return \yii\mail\MailerInterface the mailer application component.
     * @throws InvalidConfigException If this component is not configured.
     */
    public function get_mailer()
    {
        return $this->get('mailer');
    }
    /**
     * Returns the auth manager for this application.
     * @return \yii\rbac\ManagerInterface|null the auth manager application component or null if it's not configured.
     */
    public function get_auth_manager()
    {
        return $this->get('authManager', false);
    }
    /**
     * Returns the asset manager.
     * @return \yii\web\AssetManager the asset manager application component.
     */
    public function get_asset_manager()
    {
        return $this->get('assetManager');
    }
    /**
     * Returns the security component.
     * @return \yii\base\Security the security application component.
     */
    public function get_security()
    {
        return $this->get('security');
    }
    /**
     * Returns the configuration of core application components.
     * @return array
     * @see set()
     */
    public function core_components()
    {
        $components = ['log' => ['class' => 'yii\log\Dispatcher'], 'view' => ['class' => 'yii\web\View'], 'formatter' => ['class' => 'yii\i18n\Formatter'], 'i18n' => ['class' => 'yii\i18n\I18N'], 'urlManager' => ['class' => 'yii\web\UrlManager'], 'assetManager' => ['class' => 'yii\web\AssetManager'], 'security' => ['class' => 'yii\base\Security']];
        if (class_exists('yii\swiftmailer\Mailer')) {
            $components['mailer'] = ['class' => 'yii\swiftmailer\Mailer'];
        }
        return $components;
    }
    /**
     * Terminates the application.
     * This method replaces the `exit()` function by ensuring the application life cycle is completed
     * before terminating the application.
     * @param int $status the exit status (value 0 means normal exit while other values mean abnormal exit).
     * @param Response|null $response the response to be sent. If not set, the default application [[response]] component will be used.
     * @throws ExitException if the application is in testing mode
     */
    public function end($status = 0, $response = null): void
    {
        if ($this->state === self::STATE_BEFORE_REQUEST || $this->state === self::STATE_HANDLING_REQUEST) {
            $this->state = self::STATE_AFTER_REQUEST;
            $this->trigger(self::EVENT_AFTER_REQUEST);
        }
        if ($this->state !== self::STATE_SENDING_RESPONSE && $this->state !== self::STATE_END) {
            $this->state = self::STATE_END;
            $response = $response ?: $this->get_response();
            $response->send();
        }
        throw new Exit_Exception($status);
    }
    /**
     * Configures [[Yii::$container]] with the $config.
     *
     * @param array $config values given in terms of name-value pairs
     * @since 2.0.11
     */
    public function set_container($config): void
    {
        Yii::configure(Yii::$container, $config);
    }
}