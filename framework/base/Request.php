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
 * Request represents a request that is handled by an [[Application]].
 *
 * For more details and usage information on Request, see the [guide article on requests](guide:runtime-requests).
 *
 * @property bool $isConsoleRequest The value indicating whether the current request is made via console.
 * @property string $scriptFile Entry script file path (processed w/ realpath()).
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
abstract class Request extends Component
{
    private ?string $_script_file = null;
    private $_is_console_request;
    /**
     * Resolves the current request into a route and the associated parameters.
     * @return array the first element is the route, and the second is the associated parameters.
     */
    abstract public function resolve();
    /**
     * Returns a value indicating whether the current request is made via command line.
     * @return bool the value indicating whether the current request is made via console
     */
    public function get_is_console_request()
    {
        return $this->_is_console_request ?? PHP_SAPI === 'cli';
    }
    /**
     * Sets the value indicating whether the current request is made via command line.
     * @param bool $value the value indicating whether the current request is made via command line
     */
    public function set_is_console_request($value): void
    {
        $this->_is_console_request = $value;
    }
    /**
     * Returns entry script file path.
     * @return string entry script file path (processed w/ realpath())
     * @throws InvalidConfigException if the entry script file path cannot be determined automatically.
     */
    public function get_script_file()
    {
        if ($this->_script_file === null) {
            if (isset($_SERVER['SCRIPT_FILENAME'])) {
                $this->set_script_file($_SERVER['SCRIPT_FILENAME']);
            } else {
                throw new Invalid_Config_Exception('Unable to determine the entry script file path.');
            }
        }
        return $this->_script_file;
    }
    /**
     * Sets the entry script file path.
     * The entry script file path can normally be determined based on the `SCRIPT_FILENAME` SERVER variable.
     * However, for some server configurations, this may not be correct or feasible.
     * This setter is provided so that the entry script file path can be manually specified.
     * @param string $value the entry script file path. This can be either a file path or a [path alias](guide:concept-aliases).
     * @throws InvalidConfigException if the provided entry script file path is invalid.
     */
    public function set_script_file($value): void
    {
        $script_file = realpath(Yii::get_alias($value));
        if ($script_file !== false && is_file($script_file)) {
            $this->_script_file = $script_file;
        } else {
            throw new Invalid_Config_Exception('Unable to determine the entry script file path.');
        }
    }
}