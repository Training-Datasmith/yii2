<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\web;

use Yii;
use yii\base\Component;
use yii\base\InvalidArgumentException;
use yii\base\Invalid_Config_Exception;
/**
 * Session provides session data management and the related configurations.
 *
 * Session is a Web application component that can be accessed via `Yii::$app->session`.
 *
 * To start the session, call [[open()]]; To complete and send out session data, call [[close()]];
 * To destroy the session, call [[destroy()]].
 *
 * Session can be used like an array to set and get session data. For example,
 *
 * ```
 * $session = new Session;
 * $session->open();
 * $value1 = $session['name1'];  // get session variable 'name1'
 * $value2 = $session['name2'];  // get session variable 'name2'
 * foreach ($session as $name => $value) // traverse all session variables
 * $session['name3'] = $value3;  // set session variable 'name3'
 * ```
 *
 * Session can be extended to support customized session storage.
 * To do so, override [[useCustomStorage]] so that it returns true, and
 * override these methods with the actual logic about using custom storage:
 * [[openSession()]], [[closeSession()]], [[readSession()]], [[writeSession()]],
 * [[destroySession()]] and [[gcSession()]].
 *
 * Session also supports a special type of session data, called *flash messages*.
 * A flash message is available only in the current request and the next request.
 * After that, it will be deleted automatically. Flash messages are particularly
 * useful for displaying confirmation messages. To use flash messages, simply
 * call methods such as [[setFlash()]], [[getFlash()]].
 *
 * For more details and usage information on Session, see the [guide article on sessions](guide:runtime-sessions-cookies).
 *
 * @property-read array $allFlashes Flash messages (key => message or key => [message1, message2]).
 * @property string $cacheLimiter Current cache limiter.
 * @property array $cookieParams The session cookie parameters.
 * @property-read int $count The number of session variables.
 * @property-write string $flash The key identifying the flash message. Note that flash messages and normal
 * session variables share the same name space. If you have a normal session variable using the same name, its
 * value will be overwritten by this method.
 * @property float $gCProbability The probability (percentage) that the GC (garbage collection) process is
 * started on every session initialization.
 * @property bool $hasSessionId Whether the current request has sent the session ID.
 * @property string $id The current session ID.
 * @property-read bool $isActive Whether the session has started.
 * @property-read SessionIterator $iterator An iterator for traversing the session variables.
 * @property string $name The current session name.
 * @property string $savePath The current session save path, defaults to '/tmp'.
 * @property int $timeout The number of seconds after which data will be seen as 'garbage' and cleaned up. The
 * default value is 1440 seconds (or the value of "session.gc_maxlifetime" set in php.ini).
 * @property bool|null $useCookies The value indicating whether cookies should be used to store session IDs.
 * @property-read bool $useCustomStorage Whether to use custom storage.
 * @property bool $useStrictMode Whether strict mode is enabled or not.
 * @property bool $useTransparentSessionID Whether transparent sid support is enabled or not, defaults to
 * false.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 *
 * @implements \IteratorAggregate<array-key, mixed>
 * @implements \ArrayAccess<array-key, mixed>
 */
class Session extends Component implements \IteratorAggregate, \ArrayAccess, \Countable
{
    /**
     * @var string|null Holds the original session module (before a custom handler is registered) so that it can be
     * restored when a Session component without custom handler is used after one that has.
     */
    protected static $_original_session_module;
    /**
     * Polyfill for ini directive session.use-strict-mode for PHP < 5.5.2.
     */
    private static $_use_strict_mode_polyfill = false;
    /**
     * @var string the name of the session variable that stores the flash message data.
     */
    public $flash_param = '__flash';
    /**
     * @var \SessionHandlerInterface|array an object implementing the SessionHandlerInterface or a configuration array. If set, will be used to provide persistency instead of build-in methods.
     */
    public $handler;
    /**
     * @var string|null Holds the session id in case useStrictMode is enabled and the session id needs to be regenerated
     */
    protected $_force_regenerate_id;
    /**
     * @var array parameter-value pairs to override default session cookie parameters that are used for session_set_cookie_params() function
     * Array may have the following possible keys: 'lifetime', 'path', 'domain', 'secure', 'httponly'
     * @see https://www.php.net/manual/en/function.session-set-cookie-params.php
     */
    private array $_cookie_params = ['httponly' => true];
    /**
     * @var array|null is used for saving session between recreations due to session parameters update.
     */
    private ?array $_frozen_session_data = null;
    /**
     * Initializes the application component.
     * This method is required by IApplicationComponent and is invoked by application.
     */
    public function init(): void
    {
        parent::init();
        register_shutdown_function([$this, 'close']);
        if ($this->get_is_active()) {
            Yii::warning('Session is already started', __METHOD__);
            $this->update_flash_counters();
        }
    }
    /**
     * Returns a value indicating whether to use custom session storage.
     * This method should be overridden to return true by child classes that implement custom session storage.
     * To implement custom session storage, override these methods: [[openSession()]], [[closeSession()]],
     * [[readSession()]], [[writeSession()]], [[destroySession()]] and [[gcSession()]].
     * @return bool whether to use custom storage.
     */
    public function get_use_custom_storage(): bool
    {
        return false;
    }
    /**
     * Starts the session.
     */
    public function open(): void
    {
        if ($this->get_is_active()) {
            return;
        }
        $this->register_session_handler();
        if ($this->get_use_cookies() !== false) {
            $this->set_cookie_params_internal();
        }
        YII_DEBUG ? session_start() : @session_start();
        if ($this->get_use_strict_mode() && $this->_force_regenerate_id) {
            $this->regenerate_id();
            $this->_force_regenerate_id = null;
        }
        if ($this->get_is_active()) {
            Yii::info('Session started', __METHOD__);
            $this->update_flash_counters();
        } else {
            $error = error_get_last();
            $message = $error['message'] ?? 'Failed to start session.';
            Yii::error($message, __METHOD__);
        }
    }
    /**
     * Registers session handler.
     * @throws \yii\base\InvalidConfigException
     */
    protected function register_session_handler()
    {
        $session_module_name = session_module_name();
        if (static::$_original_session_module === null) {
            static::$_original_session_module = $session_module_name;
        }
        if ($this->handler === null && $this->get_use_custom_storage()) {
            $this->handler = Yii::create_object(['__class' => Session_Handler::class, '__construct()' => [$this]]);
        }
        if ($this->handler !== null) {
            if (is_array($this->handler)) {
                $this->handler = Yii::create_object($this->handler);
            }
            if (!$this->handler instanceof \Session_Handler_Interface) {
                throw new Invalid_Config_Exception('"' . get_class($this) . '::handler" must implement the SessionHandlerInterface.');
            }
            YII_DEBUG ? session_set_save_handler($this->handler, false) : @session_set_save_handler($this->handler, false);
        } elseif ($session_module_name !== static::$_original_session_module && static::$_original_session_module !== null && static::$_original_session_module !== 'user') {
            session_module_name(static::$_original_session_module);
        }
    }
    /**
     * Ends the current session and store session data.
     */
    public function close(): void
    {
        if ($this->get_is_active()) {
            YII_DEBUG ? session_write_close() : @session_write_close();
        }
        $this->_force_regenerate_id = null;
    }
    /**
     * Frees all session variables and destroys all data registered to a session.
     *
     * This method has no effect when session is not [[getIsActive()|active]].
     * Make sure to call [[open()]] before calling it.
     * @see open()
     * @see isActive
     */
    public function destroy(): void
    {
        if ($this->get_is_active()) {
            $session_id = session_id();
            $this->close();
            $this->set_id($session_id);
            $this->open();
            session_unset();
            session_destroy();
            $this->set_id($session_id);
        }
    }
    /**
     * @return bool whether the session has started
     */
    public function get_is_active(): bool
    {
        return session_status() === PHP_SESSION_ACTIVE;
    }
    private $_has_session_id;
    /**
     * Returns a value indicating whether the current request has sent the session ID.
     * The default implementation will check cookie and $_GET using the session name.
     * If you send session ID via other ways, you may need to override this method
     * or call [[setHasSessionId()]] to explicitly set whether the session ID is sent.
     * @return bool whether the current request has sent the session ID.
     */
    public function get_has_session_id()
    {
        if ($this->_has_session_id === null) {
            $name = $this->get_name();
            $request = Yii::$app->get_request();
            if (!empty($_COOKIE[$name]) && ini_get('session.use_cookies')) {
                $this->_has_session_id = true;
            } elseif (PHP_VERSION_ID < 80400 && !ini_get('session.use_only_cookies') && ini_get('session.use_trans_sid')) {
                $this->_has_session_id = $request->get($name) != '';
            } else {
                $this->_has_session_id = false;
            }
        }
        return $this->_has_session_id;
    }
    /**
     * Sets the value indicating whether the current request has sent the session ID.
     * This method is provided so that you can override the default way of determining
     * whether the session ID is sent.
     * @param bool $value whether the current request has sent the session ID.
     */
    public function set_has_session_id($value): void
    {
        $this->_has_session_id = $value;
    }
    /**
     * Gets the session ID.
     * This is a wrapper for [PHP session_id()](https://www.php.net/manual/en/function.session-id.php).
     * @return string the current session ID
     */
    public function get_id()
    {
        return session_id();
    }
    /**
     * Sets the session ID.
     * This is a wrapper for [PHP session_id()](https://www.php.net/manual/en/function.session-id.php).
     * @param string $value the session ID for the current session
     */
    public function set_id($value): void
    {
        session_id($value);
    }
    /**
     * Updates the current session ID with a newly generated one.
     *
     * Please refer to <https://www.php.net/session_regenerate_id> for more details.
     *
     * This method has no effect when session is not [[getIsActive()|active]].
     * Make sure to call [[open()]] before calling it.
     *
     * @param bool $deleteOldSession Whether to delete the old associated session file or not.
     * @see open()
     * @see isActive
     */
    public function regenerate_id($delete_old_session = false): void
    {
        if ($this->get_is_active()) {
            // add @ to inhibit possible warning due to race condition
            // https://github.com/yiisoft/yii2/pull/1812
            if (!headers_sent()) {
                session_regenerate_id($delete_old_session);
            } else {
                @session_regenerate_id($delete_old_session);
            }
        }
    }
    /**
     * Gets the name of the current session.
     * This is a wrapper for [PHP session_name()](https://www.php.net/manual/en/function.session-name.php).
     * @return string the current session name
     */
    public function get_name()
    {
        return session_name();
    }
    /**
     * Sets the name for the current session.
     * This is a wrapper for [PHP session_name()](https://www.php.net/manual/en/function.session-name.php).
     * @param string $value the session name for the current session, must be an alphanumeric string.
     * It defaults to "PHPSESSID".
     */
    public function set_name($value): void
    {
        $this->freeze();
        session_name($value);
        $this->unfreeze();
    }
    /**
     * Gets the current session save path.
     * This is a wrapper for [PHP session_save_path()](https://www.php.net/manual/en/function.session-save-path.php).
     * @return string the current session save path, defaults to '/tmp'.
     */
    public function get_save_path()
    {
        return session_save_path();
    }
    /**
     * Sets the current session save path.
     * This is a wrapper for [PHP session_save_path()](https://www.php.net/manual/en/function.session-save-path.php).
     * @param string $value the current session save path. This can be either a directory name or a [path alias](guide:concept-aliases).
     * @throws InvalidArgumentException if the path is not a valid directory
     */
    public function set_save_path(string $value): void
    {
        $path = Yii::get_alias($value);
        if (is_dir($path)) {
            session_save_path($path);
        } else {
            throw new InvalidArgumentException("Session save path is not a valid directory: {$value}");
        }
    }
    /**
     * @return array the session cookie parameters.
     * @see https://www.php.net/manual/en/function.session-get-cookie-params.php
     */
    public function get_cookie_params(): array
    {
        return array_merge(session_get_cookie_params(), array_change_key_case($this->_cookie_params));
    }
    /**
     * Sets the session cookie parameters.
     * The cookie parameters passed to this method will be merged with the result
     * of `session_get_cookie_params()`.
     * @param array $value cookie parameters, valid keys include: `lifetime`, `path`, `domain`, `secure` and `httponly`.
     * Starting with Yii 2.0.21 `sameSite` is also supported. It requires PHP version 7.3.0 or higher.
     * For security, an exception will be thrown if `sameSite` is set while using an unsupported version of PHP.
     * To use this feature across different PHP versions check the version first. E.g.
     * ```
     * [
     *     'sameSite' => PHP_VERSION_ID >= 70300 ? yii\web\Cookie::SAME_SITE_LAX : null,
     * ]
     * ```
     * See https://owasp.org/www-community/SameSite for more information about `sameSite`.
     *
     * @throws InvalidArgumentException if the parameters are incomplete.
     * @see https://www.php.net/manual/en/function.session-set-cookie-params.php
     */
    public function set_cookie_params(array $value): void
    {
        $this->_cookie_params = $value;
    }
    /**
     * Sets the session cookie parameters.
     * This method is called by [[open()]] when it is about to open the session.
     * @throws InvalidArgumentException if the parameters are incomplete.
     * @see https://www.php.net/manual/en/function.session-set-cookie-params.php
     */
    private function set_cookie_params_internal(): void
    {
        $data = $this->get_cookie_params();
        if (isset($data['lifetime'], $data['path'], $data['domain'], $data['secure'], $data['httponly'])) {
            session_set_cookie_params($data);
        } else {
            throw new InvalidArgumentException('Please make sure cookieParams contains these elements: lifetime, path, domain, secure and httponly.');
        }
    }
    /**
     * Returns the value indicating whether cookies should be used to store session IDs.
     * @return bool|null the value indicating whether cookies should be used to store session IDs.
     * @see setUseCookies()
     */
    public function get_use_cookies(): ?bool
    {
        if (ini_get('session.use_cookies') === '0') {
            return false;
        }
        if (PHP_VERSION_ID >= 80400 || ini_get('session.use_only_cookies') === '1') {
            return true;
        }
        return null;
    }
    /**
     * Sets the value indicating whether cookies should be used to store session IDs.
     *
     * Three states are possible:
     *
     * - true: cookies and only cookies will be used to store session IDs.
     * - false: cookies will not be used to store session IDs.
     * - null: if possible, cookies will be used to store session IDs; if not, other mechanisms will be used (e.g. GET parameter)
     *
     * @param bool|null $value the value indicating whether cookies should be used to store session IDs.
     */
    public function set_use_cookies($value): void
    {
        $this->freeze();
        if ($value === false) {
            ini_set('session.use_cookies', '0');
            if (PHP_VERSION_ID < 80400) {
                ini_set('session.use_only_cookies', '0');
            }
        } elseif ($value === true) {
            ini_set('session.use_cookies', '1');
            if (PHP_VERSION_ID < 80400) {
                ini_set('session.use_only_cookies', '1');
            }
        } else {
            ini_set('session.use_cookies', '1');
            if (PHP_VERSION_ID < 80400) {
                ini_set('session.use_only_cookies', '0');
            }
        }
        $this->unfreeze();
    }
    /**
     * @return float the probability (percentage) that the GC (garbage collection) process is started on every session initialization.
     */
    public function get_gc_probability(): float
    {
        /** @var numeric-string|false $gcProbability */
        $gc_probability = ini_get('session.gc_probability');
        /** @var numeric-string|false $gcDivisor */
        $gc_divisor = ini_get('session.gc_divisor');
        return (float) ($gc_probability / $gc_divisor * 100);
    }
    /**
     * @param float $value the probability (percentage) that the GC (garbage collection) process is started on every session initialization.
     * @throws InvalidArgumentException if the value is not between 0 and 100.
     */
    public function set_gc_probability($value): void
    {
        $this->freeze();
        if ($value >= 0 && $value <= 100) {
            // percent * 21474837 / 2147483647 ≈ percent * 0.01
            ini_set('session.gc_probability', floor($value * 21474836.47));
            ini_set('session.gc_divisor', 2147483647);
        } else {
            throw new InvalidArgumentException('GCProbability must be a value between 0 and 100.');
        }
        $this->unfreeze();
    }
    /**
     * @return bool whether transparent sid support is enabled or not, defaults to false.
     */
    public function get_use_transparent_session_id()
    {
        if (PHP_VERSION_ID < 80400) {
            return ini_get('session.use_trans_sid') == 1;
        }
        return false;
    }
    /**
     * @param bool $value whether transparent sid support is enabled or not.
     */
    public function set_use_transparent_session_id($value): void
    {
        $this->freeze();
        if (PHP_VERSION_ID < 80400) {
            ini_set('session.use_trans_sid', $value ? '1' : '0');
        }
        $this->unfreeze();
    }
    /**
     * @return int the number of seconds after which data will be seen as 'garbage' and cleaned up.
     * The default value is 1440 seconds (or the value of "session.gc_maxlifetime" set in php.ini).
     */
    public function get_timeout(): int
    {
        return (int) ini_get('session.gc_maxlifetime');
    }
    /**
     * @param int $value the number of seconds after which data will be seen as 'garbage' and cleaned up
     */
    public function set_timeout($value): void
    {
        $this->freeze();
        ini_set('session.gc_maxlifetime', $value);
        $this->unfreeze();
    }
    /**
     * @param bool $value Whether strict mode is enabled or not.
     * When `true` this setting prevents the session component to use an uninitialized session ID.
     * Note: Enabling `useStrictMode` on PHP < 5.5.2 is only supported with custom storage classes.
     * Warning! Although enabling strict mode is mandatory for secure sessions, the default value of 'session.use-strict-mode' is `0`.
     * @see https://www.php.net/manual/en/session.configuration.php#ini.session.use-strict-mode
     * @since 2.0.38
     */
    public function set_use_strict_mode($value): void
    {
        $this->freeze();
        ini_set('session.use_strict_mode', $value ? '1' : '0');
        $this->unfreeze();
    }
    /**
     * @return bool Whether strict mode is enabled or not.
     * @see setUseStrictMode()
     * @since 2.0.38
     */
    public function get_use_strict_mode(): bool
    {
        return (bool) ini_get('session.use_strict_mode');
    }
    /**
     * Session open handler.
     * This method should be overridden if [[useCustomStorage]] returns true.
     * @internal Do not call this method directly.
     * @param string $savePath session save path
     * @param string $sessionName session name
     * @return bool whether session is opened successfully
     */
    public function open_session($save_path, $session_name): bool
    {
        return true;
    }
    /**
     * Session close handler.
     * This method should be overridden if [[useCustomStorage]] returns true.
     * @internal Do not call this method directly.
     * @return bool whether session is closed successfully
     */
    public function close_session(): bool
    {
        return true;
    }
    /**
     * Session read handler.
     * This method should be overridden if [[useCustomStorage]] returns true.
     * @internal Do not call this method directly.
     * @param string $id session ID
     * @return string|false the session data, or false on failure
     */
    public function read_session($id): string
    {
        return '';
    }
    /**
     * Session write handler.
     * This method should be overridden if [[useCustomStorage]] returns true.
     * @internal Do not call this method directly.
     * @param string $id session ID
     * @param string $data session data
     * @return bool whether session write is successful
     */
    public function write_session($id, $data): bool
    {
        return true;
    }
    /**
     * Session destroy handler.
     * This method should be overridden if [[useCustomStorage]] returns true.
     * @internal Do not call this method directly.
     * @param string $id session ID
     * @return bool whether session is destroyed successfully
     */
    public function destroy_session($id): bool
    {
        return true;
    }
    /**
     * Session GC (garbage collection) handler.
     * This method should be overridden if [[useCustomStorage]] returns true.
     * @internal Do not call this method directly.
     * @param int $maxLifetime the number of seconds after which data will be seen as 'garbage' and cleaned up.
     * @return int|false the number of deleted sessions on success, or false on failure
     */
    public function gc_session($max_lifetime): int
    {
        return 0;
    }
    /**
     * Returns an iterator for traversing the session variables.
     * This method is required by the interface [[\IteratorAggregate]].
     * @return SessionIterator an iterator for traversing the session variables.
     */
    #[\Return_Type_Will_Change]
    public function getIterator()
    {
        $this->open();
        return new Session_Iterator();
    }
    /**
     * Returns the number of items in the session.
     * @return int the number of session variables
     */
    public function get_count(): int
    {
        $this->open();
        return count($_SESSION);
    }
    /**
     * Returns the number of items in the session.
     * This method is required by [[\Countable]] interface.
     * @return int number of items in the session.
     */
    #[\Return_Type_Will_Change]
    public function count()
    {
        return $this->get_count();
    }
    /**
     * Returns the session variable value with the session variable name.
     * If the session variable does not exist, the `$defaultValue` will be returned.
     * @param string $key the session variable name
     * @param mixed $defaultValue the default value to be returned when the session variable does not exist.
     * @return mixed the session variable value, or $defaultValue if the session variable does not exist.
     */
    public function get($key, $default_value = null)
    {
        $this->open();
        return $_SESSION[$key] ?? $default_value;
    }
    /**
     * Adds a session variable.
     * If the specified name already exists, the old value will be overwritten.
     * @param string $key session variable name
     * @param mixed $value session variable value
     */
    public function set($key, $value): void
    {
        $this->open();
        $_SESSION[$key] = $value;
    }
    /**
     * Removes a session variable.
     * @param string $key the name of the session variable to be removed
     * @return mixed the removed value, null if no such session variable.
     */
    public function remove($key)
    {
        $this->open();
        if (isset($_SESSION[$key])) {
            $value = $_SESSION[$key];
            unset($_SESSION[$key]);
            return $value;
        }
        return null;
    }
    /**
     * Removes all session variables.
     */
    public function remove_all(): void
    {
        $this->open();
        foreach (array_keys($_SESSION) as $key) {
            unset($_SESSION[$key]);
        }
    }
    /**
     * @param mixed $key session variable name
     * @return bool whether there is the named session variable
     */
    public function has($key): bool
    {
        $this->open();
        return isset($_SESSION[$key]);
    }
    /**
     * Updates the counters for flash messages and removes outdated flash messages.
     * This method should only be called once in [[init()]].
     */
    protected function update_flash_counters()
    {
        $counters = $this->get($this->flash_param, []);
        if (is_array($counters)) {
            foreach ($counters as $key => $count) {
                if ($count > 0) {
                    unset($counters[$key], $_SESSION[$key]);
                } elseif ($count == 0) {
                    $counters[$key]++;
                }
            }
            $_SESSION[$this->flash_param] = $counters;
        } else {
            // fix the unexpected problem that flashParam doesn't return an array
            unset($_SESSION[$this->flash_param]);
        }
    }
    /**
     * Returns a flash message.
     * @param string $key the key identifying the flash message
     * @param mixed $defaultValue value to be returned if the flash message does not exist.
     * @param bool $delete whether to delete this flash message right after this method is called.
     * If false, the flash message will be automatically deleted in the next request.
     * @return mixed the flash message or an array of messages if addFlash was used
     * @see setFlash()
     * @see addFlash()
     * @see hasFlash()
     * @see getAllFlashes()
     * @see removeFlash()
     */
    public function get_flash($key, $default_value = null, $delete = false)
    {
        $counters = $this->get($this->flash_param, []);
        if (isset($counters[$key])) {
            $value = $this->get($key, $default_value);
            if ($delete) {
                $this->remove_flash($key);
            } elseif ($counters[$key] < 0) {
                // mark for deletion in the next request
                $counters[$key] = 1;
                $_SESSION[$this->flash_param] = $counters;
            }
            return $value;
        }
        return $default_value;
    }
    /**
     * Returns all flash messages.
     *
     * You may use this method to display all the flash messages in a view file:
     *
     * ```
     * <?php
     * foreach (Yii::$app->session->getAllFlashes() as $key => $message) {
     *     echo '<div class="alert alert-' . $key . '">' . $message . '</div>';
     * } ?>
     * ```
     *
     * With the above code you can use the [bootstrap alert][] classes such as `success`, `info`, `danger`
     * as the flash message key to influence the color of the div.
     *
     * Note that if you use [[addFlash()]], `$message` will be an array, and you will have to adjust the above code.
     *
     * [bootstrap alert]: https://getbootstrap.com/docs/3.4/components/#alerts
     *
     * @param bool $delete whether to delete the flash messages right after this method is called.
     * If false, the flash messages will be automatically deleted in the next request.
     * @return array flash messages (key => message or key => [message1, message2]).
     * @see setFlash()
     * @see addFlash()
     * @see getFlash()
     * @see hasFlash()
     * @see removeFlash()
     */
    public function get_all_flashes($delete = false): array
    {
        $counters = $this->get($this->flash_param, []);
        $flashes = [];
        foreach (array_keys($counters) as $key) {
            if (array_key_exists($key, $_SESSION)) {
                $flashes[$key] = $_SESSION[$key];
                if ($delete) {
                    unset($counters[$key], $_SESSION[$key]);
                } elseif ($counters[$key] < 0) {
                    // mark for deletion in the next request
                    $counters[$key] = 1;
                }
            } else {
                unset($counters[$key]);
            }
        }
        $_SESSION[$this->flash_param] = $counters;
        return $flashes;
    }
    /**
     * Sets a flash message.
     * A flash message will be automatically deleted after it is accessed in a request and the deletion will happen
     * in the next request.
     * If there is already an existing flash message with the same key, it will be overwritten by the new one.
     * @param string $key the key identifying the flash message. Note that flash messages
     * and normal session variables share the same name space. If you have a normal
     * session variable using the same name, its value will be overwritten by this method.
     * @param mixed $value flash message
     * @param bool $removeAfterAccess whether the flash message should be automatically removed only if
     * it is accessed. If false, the flash message will be automatically removed after the next request,
     * regardless if it is accessed or not. If true (default value), the flash message will remain until after
     * it is accessed.
     * @see getFlash()
     * @see addFlash()
     * @see removeFlash()
     */
    public function set_flash($key, $value = true, $remove_after_access = true): void
    {
        $counters = $this->get($this->flash_param, []);
        $counters[$key] = $remove_after_access ? -1 : 0;
        $_SESSION[$key] = $value;
        $_SESSION[$this->flash_param] = $counters;
    }
    /**
     * Adds a flash message.
     * If there are existing flash messages with the same key, the new one will be appended to the existing message array.
     * @param string $key the key identifying the flash message.
     * @param mixed $value flash message
     * @param bool $removeAfterAccess whether the flash message should be automatically removed only if
     * it is accessed. If false, the flash message will be automatically removed after the next request,
     * regardless if it is accessed or not. If true (default value), the flash message will remain until after
     * it is accessed.
     * @see getFlash()
     * @see setFlash()
     * @see removeFlash()
     */
    public function add_flash($key, $value = true, $remove_after_access = true): void
    {
        $counters = $this->get($this->flash_param, []);
        $counters[$key] = $remove_after_access ? -1 : 0;
        $_SESSION[$this->flash_param] = $counters;
        if (empty($_SESSION[$key])) {
            $_SESSION[$key] = [$value];
        } elseif (is_array($_SESSION[$key])) {
            $_SESSION[$key][] = $value;
        } else {
            $_SESSION[$key] = [$_SESSION[$key], $value];
        }
    }
    /**
     * Removes a flash message.
     * @param string $key the key identifying the flash message. Note that flash messages
     * and normal session variables share the same name space.  If you have a normal
     * session variable using the same name, it will be removed by this method.
     * @return mixed the removed flash message. Null if the flash message does not exist.
     * @see getFlash()
     * @see setFlash()
     * @see addFlash()
     * @see removeAllFlashes()
     */
    public function remove_flash($key)
    {
        $counters = $this->get($this->flash_param, []);
        $value = isset($_SESSION[$key], $counters[$key]) ? $_SESSION[$key] : null;
        unset($counters[$key], $_SESSION[$key]);
        $_SESSION[$this->flash_param] = $counters;
        return $value;
    }
    /**
     * Removes all flash messages.
     * Note that flash messages and normal session variables share the same name space.
     * If you have a normal session variable using the same name, it will be removed
     * by this method.
     * @see getFlash()
     * @see setFlash()
     * @see addFlash()
     * @see removeFlash()
     */
    public function remove_all_flashes(): void
    {
        $counters = $this->get($this->flash_param, []);
        foreach (array_keys($counters) as $key) {
            unset($_SESSION[$key]);
        }
        unset($_SESSION[$this->flash_param]);
    }
    /**
     * Returns a value indicating whether there are flash messages associated with the specified key.
     * @param string $key key identifying the flash message type
     * @return bool whether any flash messages exist under specified key
     */
    public function has_flash($key): bool
    {
        return $this->get_flash($key) !== null;
    }
    /**
     * This method is required by the interface [[\ArrayAccess]].
     * @param int|string $offset the offset to check on
     * @return bool
     */
    #[\Return_Type_Will_Change]
    public function offsetExists($offset)
    {
        $this->open();
        return isset($_SESSION[$offset]);
    }
    /**
     * This method is required by the interface [[\ArrayAccess]].
     * @param int|string $offset the offset to retrieve element.
     * @return mixed the element at the offset, null if no element is found at the offset
     */
    #[\Return_Type_Will_Change]
    public function offsetGet($offset)
    {
        $this->open();
        return $_SESSION[$offset] ?? null;
    }
    /**
     * This method is required by the interface [[\ArrayAccess]].
     * @param int|string $offset the offset to set element
     * @param mixed $item the element value
     */
    #[\Return_Type_Will_Change]
    public function offsetSet($offset, $item): void
    {
        $this->open();
        $_SESSION[$offset] = $item;
    }
    /**
     * This method is required by the interface [[\ArrayAccess]].
     * @param int|string $offset the offset to unset element
     */
    #[\Return_Type_Will_Change]
    public function offsetUnset($offset): void
    {
        $this->open();
        unset($_SESSION[$offset]);
    }
    /**
     * If session is started it's not possible to edit session ini settings. In PHP7.2+ it throws exception.
     * This function saves session data to temporary variable and stop session.
     * @since 2.0.14
     */
    protected function freeze()
    {
        if ($this->get_is_active()) {
            if (isset($_SESSION)) {
                $this->_frozen_session_data = $_SESSION;
            }
            $this->close();
            Yii::info('Session frozen', __METHOD__);
        }
    }
    /**
     * Starts session and restores data from temporary variable
     * @since 2.0.14
     */
    protected function unfreeze()
    {
        if (null !== $this->_frozen_session_data) {
            YII_DEBUG ? session_start() : @session_start();
            if ($this->get_is_active()) {
                Yii::info('Session unfrozen', __METHOD__);
            } else {
                $error = error_get_last();
                $message = $error['message'] ?? 'Failed to unfreeze session.';
                Yii::error($message, __METHOD__);
            }
            $_SESSION = $this->_frozen_session_data;
            $this->_frozen_session_data = null;
        }
    }
    /**
     * Set cache limiter
     *
     * @param string $cacheLimiter
     * @since 2.0.14
     */
    public function set_cache_limiter($cache_limiter): void
    {
        $this->freeze();
        session_cache_limiter($cache_limiter);
        $this->unfreeze();
    }
    /**
     * Returns current cache limiter
     *
     * @return string current cache limiter
     * @since 2.0.14
     */
    public function get_cache_limiter()
    {
        return session_cache_limiter();
    }
}