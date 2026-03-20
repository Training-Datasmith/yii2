<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\console;

use Yii;
use yii\base\Action;
use yii\base\Controller as BaseController;
use yii\base\Inline_Action;
use yii\base\Invalid_Route_Exception;
use yii\base\Module;
use yii\helpers\Console;
use yii\helpers\Inflector;
/**
 * Controller is the base class of console command classes.
 *
 * A console controller consists of one or several actions known as sub-commands.
 * Users call a console command by specifying the corresponding route which identifies a controller action.
 * The `yii` program is used when calling a console command, like the following:
 *
 * ```
 * yii <route> [--param1=value1 --param2 ...]
 * ```
 *
 * where `<route>` is a route to a controller action and the params will be populated as properties of a command.
 * See [[options()]] for details.
 *
 * @property Request $request The request object.
 * @property Response $response The response object.
 * @property-read string $help The help information for this controller.
 * @property-write bool $help Whether to display help information about current command.
 * @property-read string $helpSummary The one-line short summary describing this controller.
 * @property-read array $passedOptionValues The properties corresponding to the passed options.
 * @property-read array $passedOptions The names of the options passed during execution.
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
     * @deprecated since 2.0.13. Use [[ExitCode::OK]] instead.
     */
    public const EXIT_CODE_NORMAL = 0;
    /**
     * @deprecated since 2.0.13. Use [[ExitCode::UNSPECIFIED_ERROR]] instead.
     */
    public const EXIT_CODE_ERROR = 1;
    /**
     * @var bool whether to run the command interactively.
     */
    public $interactive = true;
    /**
     * @var bool|null whether to enable ANSI color in the output.
     * If not set, ANSI color will only be enabled for terminals that support it.
     */
    public $color;
    /**
     * @var bool whether to display help information about current command.
     * @since 2.0.10
     */
    public $help = false;
    /**
     * @var bool|null if true - script finish with `ExitCode::OK` in case of exception.
     * false - `ExitCode::UNSPECIFIED_ERROR`.
     * Default: `YII_ENV_TEST`
     * @since 2.0.36
     */
    public $silent_exit_on_exception;
    /**
     * @var array the options passed during execution.
     */
    private $_passed_options = [];
    /**
     * {@inheritdoc}
     */
    public function before_action($action)
    {
        $silent_exit = $this->silent_exit_on_exception ?? YII_ENV_TEST;
        Yii::$app->error_handler->silent_exit_on_exception = $silent_exit;
        return parent::before_action($action);
    }
    /**
     * Returns a value indicating whether ANSI color is enabled.
     *
     * ANSI color is enabled only if [[color]] is set true or is not set
     * and the terminal supports ANSI color.
     *
     * @param resource $stream the stream to check.
     * @return bool Whether to enable ANSI style in output.
     */
    public function is_color_enabled($stream = \STDOUT)
    {
        return $this->color ?? Console::stream_supports_ansi_colors($stream);
    }
    /**
     * Runs an action with the specified action ID and parameters.
     * If the action ID is empty, the method will use [[defaultAction]].
     * @param string $id the ID of the action to be executed.
     * @param array $params the parameters (name-value pairs) to be passed to the action.
     * @return mixed the result of the action.
     * @throws InvalidRouteException if the requested action ID cannot be resolved into an action successfully.
     * @throws Exception if there are unknown options or missing arguments
     * @see createAction
     */
    public function run_action(string $id, $params = [])
    {
        if (!empty($params)) {
            // populate options here so that they are available in beforeAction().
            $options = $this->options($id === '' ? $this->default_action : $id);
            if (isset($params['_aliases'])) {
                $option_aliases = $this->option_aliases();
                foreach ($params['_aliases'] as $name => $value) {
                    if (array_key_exists($name, $option_aliases)) {
                        $params[$option_aliases[$name]] = $value;
                    } else {
                        $message = Yii::t('yii', 'Unknown alias: -{name}', ['name' => $name]);
                        if (!empty($option_aliases)) {
                            $aliases_available = [];
                            foreach ($option_aliases as $alias => $option) {
                                $aliases_available[] = '-' . $alias . ' (--' . $option . ')';
                            }
                            $message .= '. ' . Yii::t('yii', 'Aliases available: {aliases}', ['aliases' => implode(', ', $aliases_available)]);
                        }
                        throw new Exception($message);
                    }
                }
                unset($params['_aliases']);
            }
            foreach ($params as $name => $value) {
                // Allow camelCase options to be entered in kebab-case
                if (!in_array($name, $options, true) && strpos($name, '-') !== false) {
                    $kebab_name = $name;
                    $alt_name = lcfirst(Inflector::id2camel($kebab_name));
                    if (in_array($alt_name, $options, true)) {
                        $name = $alt_name;
                    }
                }
                if (in_array($name, $options, true)) {
                    $default = $this->{$name};
                    if (is_array($default) && is_string($value)) {
                        $this->{$name} = preg_split('/\s*,\s*(?![^()]*\))/', $value);
                    } elseif ($default !== null) {
                        settype($value, gettype($default));
                        $this->{$name} = $value;
                    } else {
                        $this->{$name} = $value;
                    }
                    $this->_passed_options[] = $name;
                    unset($params[$name]);
                    if (isset($kebab_name)) {
                        unset($params[$kebab_name]);
                    }
                } elseif (!is_int($name)) {
                    $message = Yii::t('yii', 'Unknown option: --{name}', ['name' => $name]);
                    if (!empty($options)) {
                        $message .= '. ' . Yii::t('yii', 'Options available: {options}', ['options' => '--' . implode(', --', $options)]);
                    }
                    throw new Exception($message);
                }
            }
        }
        if ($this->help) {
            $route = $this->get_unique_id() . '/' . $id;
            return Yii::$app->run_action('help', [$route]);
        }
        return parent::run_action($id, $params);
    }
    /**
     * Binds the parameters to the action.
     * This method is invoked by [[Action]] when it begins to run with the given parameters.
     * This method will first bind the parameters with the [[options()|options]]
     * available to the action. It then validates the given arguments.
     * @param Action<static> $action the action to be bound with parameters
     * @param array<array-key, mixed> $params the parameters to be bound to the action
     * @return mixed[] the valid parameters that the action can run with.
     * @throws Exception if there are unknown options or missing arguments
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
        $param_keys = array_keys($params);
        $args = [];
        $missing = [];
        $action_params = [];
        $requested_params = [];
        foreach ($method->get_parameters() as $i => $param) {
            $name = $param->get_name();
            $key = null;
            if (array_key_exists($i, $params)) {
                $key = $i;
            } elseif (array_key_exists($name, $params)) {
                $key = $name;
            }
            if ($key !== null) {
                if ($param->is_variadic()) {
                    for ($j = array_search($key, $param_keys); $j < count($param_keys); $j++) {
                        $j_key = $param_keys[$j];
                        if ($j_key !== $key && !is_int($j_key)) {
                            break;
                        }
                        $args[] = $action_params[$key][] = $params[$j_key];
                        unset($params[$j_key]);
                    }
                } else {
                    if (PHP_VERSION_ID >= 80000) {
                        $is_array = ($type = $param->get_type()) instanceof \ReflectionNamedType && $type->get_name() === 'array';
                    } else {
                        $is_array = $param->is_array();
                    }
                    if ($is_array) {
                        $params[$key] = $params[$key] === '' ? [] : preg_split('/\s*,\s*/', $params[$key]);
                    }
                    $args[] = $action_params[$key] = $params[$key];
                    unset($params[$key]);
                }
            } elseif (PHP_VERSION_ID >= 70100 && ($type = $param->get_type()) !== null && $type instanceof \ReflectionNamedType && !$type->is_builtin()) {
                try {
                    $this->bind_injected_params($type, $name, $args, $requested_params);
                } catch (\yii\base\Exception $e) {
                    throw new Exception($e->get_message());
                }
            } elseif ($param->is_default_value_available()) {
                $args[] = $action_params[$i] = $param->get_default_value();
            } else {
                $missing[] = $name;
            }
        }
        if (!empty($missing)) {
            throw new Exception(Yii::t('yii', 'Missing required arguments: {params}', ['params' => implode(', ', $missing)]));
        }
        // We use a different array here, specifically one that doesn't contain service instances but descriptions instead.
        if (\Yii::$app->requested_params === null) {
            \Yii::$app->requested_params = array_merge($action_params, $requested_params);
        }
        return array_merge($args, $params);
    }
    /**
     * Formats a string with ANSI codes.
     *
     * You may pass additional parameters using the constants defined in [[\yii\helpers\Console]].
     *
     * Example:
     *
     * ```
     * echo $this->ansiFormat('This will be red and underlined.', Console::FG_RED, Console::UNDERLINE);
     * ```
     *
     * @param string $string the string to be formatted
     * @return string
     */
    public function ansi_format($string)
    {
        if ($this->is_color_enabled()) {
            $args = func_get_args();
            array_shift($args);
            $string = Console::ansi_format($string, $args);
        }
        return $string;
    }
    /**
     * Prints a string to STDOUT.
     *
     * You may optionally format the string with ANSI codes by
     * passing additional parameters using the constants defined in [[\yii\helpers\Console]].
     *
     * Example:
     *
     * ```
     * $this->stdout('This will be red and underlined.', Console::FG_RED, Console::UNDERLINE);
     * ```
     *
     * @param string $string the string to print
     * @param int ...$args additional parameters to decorate the output
     * @return int|bool Number of bytes printed or false on error
     */
    public function stdout($string)
    {
        if ($this->is_color_enabled()) {
            $args = func_get_args();
            array_shift($args);
            $string = Console::ansi_format($string, $args);
        }
        return Console::stdout($string);
    }
    /**
     * Prints a string to STDERR.
     *
     * You may optionally format the string with ANSI codes by
     * passing additional parameters using the constants defined in [[\yii\helpers\Console]].
     *
     * Example:
     *
     * ```
     * $this->stderr('This will be red and underlined.', Console::FG_RED, Console::UNDERLINE);
     * ```
     *
     * @param string $string the string to print
     * @param int ...$args additional parameters to decorate the output
     * @return int|bool Number of bytes printed or false on error
     */
    public function stderr($string)
    {
        if ($this->is_color_enabled(\STDERR)) {
            $args = func_get_args();
            array_shift($args);
            $string = Console::ansi_format($string, $args);
        }
        return fwrite(\STDERR, $string);
    }
    /**
     * Prompts the user for input and validates it.
     *
     * @param string $text prompt string
     * @param array $options the options to validate the input:
     *
     *  - required: whether it is required or not
     *  - default: default value if no input is inserted by the user
     *  - pattern: regular expression pattern to validate user input
     *  - validator: a callable function to validate input. The function must accept two parameters:
     *      - $input: the user input to validate
     *      - $error: the error value passed by reference if validation failed.
     *
     * An example of how to use the prompt method with a validator function.
     *
     * ```
     * $code = $this->prompt('Enter 4-Chars-Pin', ['required' => true, 'validator' => function($input, &$error) {
     *     if (strlen($input) !== 4) {
     *         $error = 'The Pin must be exactly 4 chars!';
     *         return false;
     *     }
     *     return true;
     * }]);
     * ```
     *
     * @return string the user input
     */
    public function prompt($text, array $options = [])
    {
        if ($this->interactive) {
            return Console::prompt($text, $options);
        }
        return $options['default'] ?? '';
    }
    /**
     * Asks user to confirm by typing y or n.
     *
     * A typical usage looks like the following:
     *
     * ```
     * if ($this->confirm("Are you sure?")) {
     *     echo "user typed yes\n";
     * } else {
     *     echo "user typed no\n";
     * }
     * ```
     *
     * @param string $message to echo out before waiting for user input
     * @param bool $default this value is returned if no selection is made.
     * @return bool whether user confirmed.
     * Will return true if [[interactive]] is false.
     */
    public function confirm($message, $default = false)
    {
        if ($this->interactive) {
            return Console::confirm($message, $default);
        }
        return true;
    }
    /**
     * Gives the user an option to choose from. Giving '?' as an input will show
     * a list of options to choose from and their explanations.
     *
     * @param string $prompt the prompt message
     * @param array $options Key-value array of options to choose from
     * @param string|null $default value to use when the user doesn't provide an option.
     * If the default is `null`, the user is required to select an option.
     *
     * @return string An option character the user chose
     * @since 2.0.49 Added the $default argument
     */
    public function select($prompt, $options = [], $default = null)
    {
        if ($this->interactive) {
            return Console::select($prompt, $options, $default);
        }
        return $default;
    }
    /**
     * Returns the names of valid options for the action (id)
     * An option requires the existence of a public member variable whose
     * name is the option name.
     * Child classes may override this method to specify possible options.
     *
     * Note that the values setting via options are not available
     * until [[beforeAction()]] is being called.
     *
     * @param string $actionID the action id of the current request
     * @return string[] the names of the options valid for the action
     */
    public function options($action_id): array
    {
        // $actionId might be used in subclasses to provide options specific to action id
        return ['color', 'interactive', 'help', 'silentExitOnException'];
    }
    /**
     * Returns option alias names.
     * Child classes may override this method to specify alias options.
     *
     * @return array the options alias names valid for the action
     * where the keys is alias name for option and value is option name.
     *
     * @since 2.0.8
     * @see options()
     */
    public function option_aliases(): array
    {
        return ['h' => 'help'];
    }
    /**
     * Returns properties corresponding to the options for the action id
     * Child classes may override this method to specify possible properties.
     *
     * @param string $actionID the action id of the current request
     * @return array properties corresponding to the options for the action
     */
    public function get_option_values($action_id): array
    {
        // $actionId might be used in subclasses to provide properties specific to action id
        $properties = [];
        foreach ($this->options($this->action->id) as $property) {
            $properties[$property] = $this->{$property};
        }
        return $properties;
    }
    /**
     * Returns the names of valid options passed during execution.
     *
     * @return array the names of the options passed during execution
     */
    public function get_passed_options()
    {
        return $this->_passed_options;
    }
    /**
     * Returns the properties corresponding to the passed options.
     *
     * @return array the properties corresponding to the passed options
     */
    public function get_passed_option_values(): array
    {
        $properties = [];
        foreach ($this->_passed_options as $property) {
            $properties[$property] = $this->{$property};
        }
        return $properties;
    }
    /**
     * Returns one-line short summary describing this controller.
     *
     * You may override this method to return customized summary.
     * The default implementation returns first line from the PHPDoc comment.
     *
     * @return string the one-line short summary describing this controller.
     */
    public function get_help_summary()
    {
        return $this->parse_doc_comment_summary(new \ReflectionClass($this));
    }
    /**
     * Returns help information for this controller.
     *
     * You may override this method to return customized help.
     * The default implementation returns help information retrieved from the PHPDoc comment.
     * @return string the help information for this controller.
     */
    public function get_help()
    {
        return $this->parse_doc_comment_detail(new \ReflectionClass($this));
    }
    /**
     * Returns a one-line short summary describing the specified action.
     * @param Action<static> $action action to get summary for
     * @return string a one-line short summary describing the specified action.
     *
     * @phpstan-param Action<static> $action
     * @psalm-param Action<self> $action
     */
    public function get_action_help_summary($action)
    {
        if ($action === null) {
            return $this->ansi_format(Yii::t('yii', 'Action not found.'), Console::FG_RED);
        }
        return $this->parse_doc_comment_summary($this->get_action_method_reflection($action));
    }
    /**
     * Returns the detailed help information for the specified action.
     * @param Action<static> $action action to get help for
     * @return string the detailed help information for the specified action.
     *
     * @phpstan-param Action<static> $action
     * @psalm-param Action<self> $action
     */
    public function get_action_help($action)
    {
        return $this->parse_doc_comment_detail($this->get_action_method_reflection($action));
    }
    /**
     * Returns the help information for the anonymous arguments for the action.
     *
     * The returned value should be an array. The keys are the argument names, and the values are
     * the corresponding help information. Each value must be an array of the following structure:
     *
     * - required: bool, whether this argument is required
     * - type: string|null, the PHP type(s) of this argument
     * - default: mixed, the default value of this argument
     * - comment: string, the description of this argument
     *
     * The default implementation will return the help information extracted from the Reflection or
     * DocBlock of the parameters corresponding to the action method.
     *
     * @param Action<static> $action the action instance
     * @return array the help information of the action arguments
     *
     * @phpstan-param Action<static> $action
     * @psalm-param Action<self> $action
     */
    public function get_action_args_help($action): array
    {
        $method = $this->get_action_method_reflection($action);
        $tags = $this->parse_doc_comment_tags($method);
        $tags['param'] = isset($tags['param']) ? (array) $tags['param'] : [];
        $php_doc_params = [];
        foreach ($tags['param'] as $i => $tag) {
            if (preg_match('/^(?<type>\S+)(\s+\$(?<name>\w+))?(?<comment>.*)/us', $tag, $matches) === 1) {
                $key = empty($matches['name']) ? $i : $matches['name'];
                $php_doc_params[$key] = ['type' => $matches['type'], 'comment' => $matches['comment']];
            }
        }
        unset($tags);
        $args = [];
        /** @var \ReflectionParameter $parameter */
        foreach ($method->get_parameters() as $i => $parameter) {
            $type = null;
            $comment = '';
            if (PHP_MAJOR_VERSION > 5 && $parameter->has_type()) {
                $reflection_type = $parameter->get_type();
                $types = method_exists($reflection_type, 'getTypes') ? $reflection_type->get_types() : [$reflection_type];
                foreach ($types as $key => $reflection_type) {
                    $types[$key] = $reflection_type->get_name();
                }
                $type = implode('|', $types);
            }
            // find PhpDoc tag by property name or position
            $key = isset($php_doc_params[$parameter->name]) ? $parameter->name : (isset($php_doc_params[$i]) ? $i : null);
            if ($key !== null) {
                $comment = $php_doc_params[$key]['comment'];
                if ($type === null && !empty($php_doc_params[$key]['type'])) {
                    $type = $php_doc_params[$key]['type'];
                }
            }
            // if type still not detected, then using type of default value
            if ($type === null && $parameter->is_default_value_available() && $parameter->get_default_value() !== null) {
                $type = gettype($parameter->get_default_value());
            }
            $args[$parameter->name] = ['required' => !$parameter->is_optional(), 'type' => $type, 'default' => $parameter->is_default_value_available() ? $parameter->get_default_value() : null, 'comment' => $comment];
        }
        return $args;
    }
    /**
     * Returns the help information for the options for the action.
     *
     * The returned value should be an array. The keys are the option names, and the values are
     * the corresponding help information. Each value must be an array of the following structure:
     *
     * - type: string, the PHP type of this argument.
     * - default: string, the default value of this argument
     * - comment: string, the comment of this argument
     *
     * The default implementation will return the help information extracted from the doc-comment of
     * the properties corresponding to the action options.
     *
     * @param Action<static> $action
     * @return array the help information of the action options
     *
     * @phpstan-param Action<static> $action
     * @psalm-param Action<self> $action
     */
    public function get_action_options_help($action): array
    {
        $option_names = $this->options($action->id);
        if (empty($option_names)) {
            return [];
        }
        $class = new \ReflectionClass($this);
        $options = [];
        foreach ($class->get_properties() as $property) {
            $name = $property->get_name();
            if (!in_array($name, $option_names, true)) {
                continue;
            }
            $default_value = $property->get_value($this);
            $tags = $this->parse_doc_comment_tags($property);
            // Display camelCase options in kebab-case
            $name = Inflector::camel2id($name, '-', true);
            if (isset($tags['var']) || isset($tags['property'])) {
                $doc = $tags['var'] ?? $tags['property'];
                if (is_array($doc)) {
                    $doc = reset($doc);
                }
                if (preg_match('/^(\S+)(.*)/s', $doc, $matches)) {
                    $type = $matches[1];
                    $comment = $matches[2];
                } else {
                    $type = null;
                    $comment = $doc;
                }
                $options[$name] = ['type' => $type, 'default' => $default_value, 'comment' => $comment];
            } else {
                $options[$name] = ['type' => null, 'default' => $default_value, 'comment' => ''];
            }
        }
        return $options;
    }
    private $_reflections = [];
    /**
     * @param Action<static> $action
     * @return \ReflectionFunctionAbstract
     *
     * @phpstan-param Action<static> $action
     * @psalm-param Action<self> $action
     */
    protected function get_action_method_reflection($action)
    {
        if (!isset($this->_reflections[$action->id])) {
            if ($action instanceof Inline_Action) {
                $this->_reflections[$action->id] = new \ReflectionMethod($this, $action->action_method);
            } else {
                $this->_reflections[$action->id] = new \ReflectionMethod($action, 'run');
            }
        }
        return $this->_reflections[$action->id];
    }
    /**
     * Parses the comment block into tags.
     * @param \ReflectionClass<object>|\ReflectionProperty|\ReflectionFunctionAbstract $reflection the comment block
     * @return array the parsed tags
     */
    protected function parse_doc_comment_tags($reflection): array
    {
        $comment = $reflection->get_doc_comment();
        $comment = "@description \n" . strtr(trim(preg_replace('/^\s*\**([ \t])?/m', '', trim($comment, '/'))), "\r", '');
        $parts = preg_split('/^\s*@/m', $comment, -1, PREG_SPLIT_NO_EMPTY);
        $tags = [];
        foreach ($parts as $part) {
            if (preg_match('/^(\w+)(.*)/ms', trim($part), $matches)) {
                $name = $matches[1];
                if (!isset($tags[$name])) {
                    $tags[$name] = trim($matches[2]);
                } elseif (is_array($tags[$name])) {
                    $tags[$name][] = trim($matches[2]);
                } else {
                    $tags[$name] = [$tags[$name], trim($matches[2])];
                }
            }
        }
        return $tags;
    }
    /**
     * Returns the first line of docblock.
     *
     * @param \ReflectionClass<static>|\ReflectionProperty|\ReflectionFunctionAbstract $reflection
     *
     * @phpstan-param \ReflectionClass<static>|\ReflectionProperty|\ReflectionFunctionAbstract $reflection
     * @psalm-param \ReflectionClass<self>|\ReflectionProperty|\ReflectionFunctionAbstract $reflection
     */
    protected function parse_doc_comment_summary($reflection): string
    {
        $doc_lines = preg_split('~\R~u', $reflection->get_doc_comment());
        if (isset($doc_lines[1])) {
            return trim($doc_lines[1], "\t *");
        }
        return '';
    }
    /**
     * Returns full description from the docblock.
     *
     * @param \ReflectionClass<static>|\ReflectionProperty|\ReflectionFunctionAbstract $reflection
     *
     * @phpstan-param \ReflectionClass<static>|\ReflectionProperty|\ReflectionFunctionAbstract $reflection
     * @psalm-param \ReflectionClass<self>|\ReflectionProperty|\ReflectionFunctionAbstract $reflection
     */
    protected function parse_doc_comment_detail($reflection): string
    {
        $comment = strtr(trim(preg_replace('/^\s*\**([ \t])?/m', '', trim($reflection->get_doc_comment(), '/'))), "\r", '');
        if (preg_match('/^\s*@\w+/m', $comment, $matches, PREG_OFFSET_CAPTURE)) {
            $comment = trim(substr($comment, 0, $matches[0][1]));
        }
        if ($comment !== '') {
            return rtrim(Console::render_colored_string(Console::markdown_to_ansi($comment)));
        }
        return '';
    }
}