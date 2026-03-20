<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\console\controllers;

use Yii;
use yii\base\Application;
use yii\base\Module;
use yii\console\Application as ConsoleApplication;
use yii\console\Controller;
use yii\console\Exception;
use yii\console\Exit_Code;
use yii\helpers\Console;
use yii\helpers\Inflector;
/**
 * Provides help information about console commands.
 *
 * This command displays the available command list in
 * the application or the detailed instructions about using
 * a specific command.
 *
 * This command can be used as follows on command line:
 *
 * ```
 * yii help [command name]
 * ```
 *
 * In the above, if the command name is not provided, all
 * available commands will be displayed.
 *
 * @property-read array $commands All available command names.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 *
 * @template T of ConsoleApplication = ConsoleApplication
 * @extends Controller<T>
 */
class Help_Controller extends Controller
{
    /**
     * Displays available commands or the detailed information
     * about a particular command.
     *
     * @param string|null $command The name of the command to show help about.
     * If not provided, all available commands will be displayed.
     * @return int the exit status
     * @throws Exception if the command for help is unknown
     */
    public function action_index($command = null): int
    {
        if ($command !== null) {
            $result = Yii::$app->create_controller($command);
            if ($result === false) {
                $name = $this->ansi_format($command, Console::FG_YELLOW);
                throw new Exception("No help for unknown command \"{$name}\".");
            }
            [$controller, $action_id] = $result;
            $actions = $this->get_actions($controller);
            if ($action_id !== '' || count($actions) === 1 && $actions[0] === $controller->default_action) {
                $this->get_sub_command_help($controller, $action_id);
            } else {
                $this->get_command_help($controller);
            }
        } else {
            $this->get_default_help();
        }
        return Exit_Code::OK;
    }
    /**
     * List all available controllers and actions in machine readable format.
     * This is used for shell completion.
     * @since 2.0.11
     */
    public function action_list(): void
    {
        foreach ($this->get_command_descriptions() as $command => $description) {
            $result = Yii::$app->create_controller($command);
            /** @var Controller<Application> $controller */
            [$controller, $action_id] = $result;
            $actions = $this->get_actions($controller);
            $prefix = $controller->get_unique_id();
            if ($controller->create_action($controller->default_action) !== null) {
                $this->stdout("{$prefix}\n");
            }
            foreach ($actions as $action) {
                $this->stdout("{$prefix}/{$action}\n");
            }
        }
    }
    /**
     * List all available options for the $action in machine readable format.
     * This is used for shell completion.
     *
     * @param string $action route to action
     * @since 2.0.11
     */
    public function action_list_action_options($action): void
    {
        $result = Yii::$app->create_controller($action);
        if ($result === false || !$result[0] instanceof Controller) {
            return;
        }
        /** @var Controller<Application> $controller */
        [$controller, $action_id] = $result;
        $action = $controller->create_action($action_id);
        if ($action === null) {
            return;
        }
        foreach ($controller->get_action_args_help($action) as $argument => $help) {
            $description = preg_replace('~\R~', '', addcslashes($help['comment'], ':')) ?: $argument;
            $this->stdout($argument . ':' . $description . "\n");
        }
        $this->stdout("\n");
        foreach ($controller->get_action_options_help($action) as $argument => $help) {
            $description = preg_replace('~\R~', '', addcslashes($help['comment'], ':'));
            $this->stdout('--' . $argument . ($description ? ':' . $description : '') . "\n");
        }
    }
    /**
     * Displays usage information for $action.
     *
     * @param string $action route to action
     * @since 2.0.11
     */
    public function action_usage($action): void
    {
        $result = Yii::$app->create_controller($action);
        if ($result === false || !$result[0] instanceof Controller) {
            return;
        }
        /** @var Controller<Application> $controller */
        [$controller, $action_id] = $result;
        $action = $controller->create_action($action_id);
        if ($action === null) {
            return;
        }
        $script_name = $this->get_script_name();
        if ($action->id === $controller->default_action) {
            $this->stdout($script_name . ' ' . $this->ansi_format($controller->get_unique_id(), Console::FG_YELLOW));
        } else {
            $this->stdout($script_name . ' ' . $this->ansi_format($action->get_unique_id(), Console::FG_YELLOW));
        }
        foreach ($controller->get_action_args_help($action) as $name => $arg) {
            if ($arg['required']) {
                $this->stdout(' <' . $name . '>', Console::FG_CYAN);
            } else {
                $this->stdout(' [' . $name . ']', Console::FG_CYAN);
            }
        }
        $this->stdout("\n");
    }
    /**
     * Returns all available command names.
     * @return array all available command names
     */
    public function get_commands(): array
    {
        $commands = $this->get_module_commands(Yii::$app);
        sort($commands);
        return array_filter(array_unique($commands), function ($command): bool {
            $result = Yii::$app->create_controller($command);
            if ($result === false || !$result[0] instanceof Controller) {
                return false;
            }
            /** @var Controller<Application> $controller */
            [$controller, $action_id] = $result;
            $actions = $this->get_actions($controller);
            return $actions !== [];
        });
    }
    /**
     * Returns an array of commands an their descriptions.
     * @return array all available commands as keys and their description as values.
     */
    protected function get_command_descriptions(): array
    {
        $descriptions = [];
        foreach ($this->get_commands() as $command) {
            $result = Yii::$app->create_controller($command);
            /** @var Controller<Application> $controller */
            [$controller, $action_id] = $result;
            $descriptions[$command] = $controller->get_help_summary();
        }
        return $descriptions;
    }
    /**
     * Returns all available actions of the specified controller.
     * @param Controller $controller the controller instance
     * @return array all available action IDs.
     */
    public function get_actions($controller): array
    {
        $actions = array_keys($controller->actions());
        $class = new \ReflectionClass($controller);
        foreach ($class->get_methods() as $method) {
            $name = $method->get_name();
            if ($name !== 'actions' && $method->is_public() && !$method->is_static() && strncmp($name, 'action', 6) === 0) {
                $actions[] = $this->camel2id(substr($name, 6));
            }
        }
        sort($actions);
        return array_unique($actions);
    }
    /**
     * Returns available commands of a specified module.
     * @param Module $module the module instance
     * @return array the available command names
     */
    protected function get_module_commands($module): array
    {
        $prefix = $module instanceof Application ? '' : $module->get_unique_id() . '/';
        $commands = [];
        foreach (array_keys($module->controller_map) as $id) {
            $commands[] = $prefix . $id;
        }
        foreach ($module->get_modules() as $id => $child) {
            if (($child = $module->get_module($id)) === null) {
                continue;
            }
            foreach ($this->get_module_commands($child) as $command) {
                $commands[] = $command;
            }
        }
        $controller_path = $module->get_controller_path();
        if (is_dir($controller_path)) {
            $iterator = new \Recursive_Iterator_Iterator(new \Recursive_Directory_Iterator($controller_path, \Recursive_Directory_Iterator::KEY_AS_PATHNAME));
            $iterator = new \Regex_Iterator($iterator, '/.*Controller\.php$/', \Recursive_Regex_Iterator::GET_MATCH);
            foreach ($iterator as $matches) {
                $file = $matches[0];
                $relative_path = str_replace($controller_path, '', $file);
                $class = strtr($relative_path, ['/' => '\\', '.php' => '']);
                $controller_class = $module->controller_namespace . $class;
                if ($this->validate_controller_class($controller_class)) {
                    $dir = ltrim(pathinfo($relative_path, PATHINFO_DIRNAME), '\/');
                    $command = Inflector::camel2id(substr(basename($file), 0, -14), '-', true);
                    if (!empty($dir)) {
                        $command = $dir . '/' . $command;
                    }
                    $commands[] = $prefix . $command;
                }
            }
        }
        return $commands;
    }
    /**
     * Validates if the given class is a valid console controller class.
     * @param string $controllerClass
     * @return bool
     */
    protected function validate_controller_class($controller_class)
    {
        if (class_exists($controller_class)) {
            $class = new \ReflectionClass($controller_class);
            return !$class->is_abstract() && $class->is_subclass_of('yii\console\Controller');
        }
        return false;
    }
    /**
     * Displays all available commands.
     */
    protected function get_default_help()
    {
        $commands = $this->get_command_descriptions();
        $this->stdout($this->get_default_help_header());
        if (empty($commands)) {
            $this->stdout("\nNo commands are found.\n\n", Console::BOLD);
            return;
        }
        $this->stdout("\nThe following commands are available:\n\n", Console::BOLD);
        $max_length = 0;
        foreach ($commands as $command => $description) {
            $result = Yii::$app->create_controller($command);
            /** @var Controller<Application> $controller */
            [$controller, $action_id] = $result;
            $actions = $this->get_actions($controller);
            $prefix = $controller->get_unique_id();
            foreach ($actions as $action) {
                $string = $prefix . '/' . $action;
                if ($action === $controller->default_action) {
                    $string .= ' (default)';
                }
                $max_length = max($max_length, strlen($string));
            }
        }
        foreach ($commands as $command => $description) {
            $result = Yii::$app->create_controller($command);
            /** @var Controller<Application> $controller */
            [$controller, $action_id] = $result;
            $actions = $this->get_actions($controller);
            $this->stdout('- ' . $this->ansi_format($command, Console::FG_YELLOW));
            $this->stdout(str_repeat(' ', $max_length + 4 - strlen($command)));
            $this->stdout(Console::wrap_text($description, $max_length + 4 + 2), Console::BOLD);
            $this->stdout("\n");
            $prefix = $controller->get_unique_id();
            foreach ($actions as $action) {
                $string = '  ' . $prefix . '/' . $action;
                $this->stdout('  ' . $this->ansi_format($string, Console::FG_GREEN));
                if ($action === $controller->default_action) {
                    $string .= ' (default)';
                    $this->stdout(' (default)', Console::FG_YELLOW);
                }
                $summary = $controller->get_action_help_summary($controller->create_action($action));
                if ($summary !== '') {
                    $this->stdout(str_repeat(' ', $max_length + 4 - strlen($string)));
                    $this->stdout(Console::wrap_text($summary, $max_length + 4 + 2));
                }
                $this->stdout("\n");
            }
            $this->stdout("\n");
        }
        $script_name = $this->get_script_name();
        $this->stdout("\nTo see the help of each command, enter:\n", Console::BOLD);
        $this->stdout("\n  {$script_name} " . $this->ansi_format('help', Console::FG_YELLOW) . ' ' . $this->ansi_format('<command-name>', Console::FG_CYAN) . "\n\n");
    }
    /**
     * Displays the overall information of the command.
     * @param Controller $controller the controller instance
     */
    protected function get_command_help($controller)
    {
        $controller->color = $this->color;
        $this->stdout("\nDESCRIPTION\n", Console::BOLD);
        $comment = $controller->get_help();
        if ($comment !== '') {
            $this->stdout("\n{$comment}\n\n");
        }
        $actions = $this->get_actions($controller);
        if (!empty($actions)) {
            $this->stdout("\nSUB-COMMANDS\n\n", Console::BOLD);
            $prefix = $controller->get_unique_id();
            $maxlen = 5;
            foreach ($actions as $action) {
                $len = strlen($prefix . '/' . $action) + 2 + ($action === $controller->default_action ? 10 : 0);
                $maxlen = max($maxlen, $len);
            }
            foreach ($actions as $action) {
                $this->stdout('- ' . $this->ansi_format($prefix . '/' . $action, Console::FG_YELLOW));
                $len = strlen($prefix . '/' . $action) + 2;
                if ($action === $controller->default_action) {
                    $this->stdout(' (default)', Console::FG_GREEN);
                    $len += 10;
                }
                $summary = $controller->get_action_help_summary($controller->create_action($action));
                if ($summary !== '') {
                    $this->stdout(str_repeat(' ', $maxlen - $len + 2) . Console::wrap_text($summary, $maxlen + 2));
                }
                $this->stdout("\n");
            }
            $script_name = $this->get_script_name();
            $this->stdout("\nTo see the detailed information about individual sub-commands, enter:\n");
            $this->stdout("\n  {$script_name} " . $this->ansi_format('help', Console::FG_YELLOW) . ' ' . $this->ansi_format('<sub-command>', Console::FG_CYAN) . "\n\n");
        }
    }
    /**
     * Displays the detailed information of a command action.
     * @param Controller $controller the controller instance
     * @param string $actionID action ID
     * @throws Exception if the action does not exist
     */
    protected function get_sub_command_help($controller, string $action_id)
    {
        $action = $controller->create_action($action_id);
        if ($action === null) {
            $name = $this->ansi_format(rtrim($controller->get_unique_id() . '/' . $action_id, '/'), Console::FG_YELLOW);
            throw new Exception("No help for unknown sub-command \"{$name}\".");
        }
        $description = $controller->get_action_help($action);
        if ($description !== '') {
            $this->stdout("\nDESCRIPTION\n", Console::BOLD);
            $this->stdout("\n{$description}\n\n");
        }
        $this->stdout("\nUSAGE\n\n", Console::BOLD);
        $script_name = $this->get_script_name();
        if ($action->id === $controller->default_action) {
            $this->stdout($script_name . ' ' . $this->ansi_format($controller->get_unique_id(), Console::FG_YELLOW));
        } else {
            $this->stdout($script_name . ' ' . $this->ansi_format($action->get_unique_id(), Console::FG_YELLOW));
        }
        $args = $controller->get_action_args_help($action);
        foreach ($args as $name => $arg) {
            if ($arg['required']) {
                $this->stdout(' <' . $name . '>', Console::FG_CYAN);
            } else {
                $this->stdout(' [' . $name . ']', Console::FG_CYAN);
            }
        }
        $options = $controller->get_action_options_help($action);
        $options[\yii\console\Application::OPTION_APPCONFIG] = ['type' => 'string', 'default' => null, 'comment' => "custom application configuration file path.\nIf not set, default application configuration is used."];
        ksort($options);
        $this->stdout(' [...options...]', Console::FG_RED);
        $this->stdout("\n\n");
        if (!empty($args)) {
            foreach ($args as $name => $arg) {
                $this->stdout($this->format_option_help('- ' . $this->ansi_format($name, Console::FG_CYAN), $arg['required'], $arg['type'], $arg['default'], $arg['comment']) . "\n\n");
            }
        }
        $this->stdout("\nOPTIONS\n\n", Console::BOLD);
        foreach ($options as $name => $option) {
            $this->stdout($this->format_option_help($this->ansi_format('--' . $name . $this->format_option_aliases($controller, $name), Console::FG_RED, empty($option['required']) ? Console::FG_RED : Console::BOLD), !empty($option['required']), $option['type'], $option['default'], $option['comment']) . "\n\n");
        }
    }
    /**
     * Generates a well-formed string for an argument or option.
     * @param string $name the name of the argument or option
     * @param bool $required whether the argument is required
     * @param string $type the type of the option or argument
     * @param mixed $defaultValue the default value of the option or argument
     * @param string $comment comment about the option or argument
     * @return string the formatted string for the argument or option
     */
    protected function format_option_help($name, $required, $type, $default_value, $comment)
    {
        $comment = trim((string) $comment);
        $type = trim((string) $type);
        if (strncmp($type, 'bool', 4) === 0) {
            $type = 'boolean, 0 or 1';
        }
        if ($default_value !== null && !is_array($default_value)) {
            if ($type === null) {
                $type = gettype($default_value);
            }
            if (is_bool($default_value)) {
                // show as integer to avoid confusion
                $default_value = (int) $default_value;
            }
            if (is_string($default_value)) {
                $default_value = "'" . $default_value . "'";
            } else {
                $default_value = var_export($default_value, true);
            }
            $doc = "{$type} (defaults to {$default_value})";
        } else {
            $doc = $type;
        }
        if ($doc === '') {
            $doc = $comment;
        } elseif ($comment !== '') {
            $doc .= "\n" . preg_replace('/^/m', '  ', $comment);
        }
        $name = $required ? "{$name} (required)" : $name;
        return $doc === '' ? $name : "{$name}: {$doc}";
    }
    /**
     * @param Controller $controller the controller instance
     * @param string $option the option name
     * @return string the formatted string for the alias argument or option
     * @since 2.0.8
     */
    protected function format_option_aliases($controller, $option): string
    {
        foreach ($controller->option_aliases() as $name => $value) {
            if (Inflector::camel2id($value, '-', true) === $option) {
                return ', -' . $name;
            }
        }
        return '';
    }
    /**
     * @return string the name of the cli script currently running.
     */
    protected function get_script_name(): string
    {
        return basename(Yii::$app->request->script_file);
    }
    /**
     * Return a default help header.
     * @return string default help header.
     * @since 2.0.11
     */
    protected function get_default_help_header(): string
    {
        return "\nThis is Yii version " . \Yii::get_version() . ".\n";
    }
    /**
     * Converts a CamelCase action name into an ID in lowercase.
     * Words in the ID are concatenated using the specified character '-'.
     * For example, 'CreateUser' will be converted to 'create-user'.
     * @param string $name the string to be converted
     * @return string the resulting ID
     */
    private function camel2id($name): string
    {
        return mb_strtolower(trim(preg_replace('/\p{Lu}/u', '-\0', $name), '-'), 'UTF-8');
    }
}