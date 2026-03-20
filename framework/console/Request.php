<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\console;

/**
 * The console Request represents the environment information for a console application.
 *
 * It is a wrapper for the PHP `$_SERVER` variable which holds information about the
 * currently running PHP script and the command line arguments given to it.
 *
 * @property array $params The command line arguments. It does not include the entry script name.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Request extends \yii\base\Request
{
    private $_params;
    /**
     * Returns the command line arguments.
     * @return array the command line arguments. It does not include the entry script name.
     */
    public function get_params()
    {
        if ($this->_params === null) {
            if (isset($_SERVER['argv'])) {
                $this->_params = $_SERVER['argv'];
                array_shift($this->_params);
            } else {
                $this->_params = [];
            }
        }
        return $this->_params;
    }
    /**
     * Sets the command line arguments.
     * @param array $params the command line arguments
     */
    public function set_params($params): void
    {
        $this->_params = $params;
    }
    /**
     * Resolves the current request into a route and the associated parameters.
     * @return array the first element is the route, and the second is the associated parameters.
     * @throws Exception when parameter is wrong and can not be resolved
     */
    public function resolve(): array
    {
        $raw_params = $this->get_params();
        $end_of_options_found = false;
        if (isset($raw_params[0])) {
            $route = array_shift($raw_params);
            if ($route === '--') {
                $end_of_options_found = true;
                $route = array_shift($raw_params);
            }
        } else {
            $route = '';
        }
        $params = [];
        $prev_option = null;
        foreach ($raw_params as $param) {
            if ($end_of_options_found) {
                $params[] = $param;
            } elseif ($param === '--') {
                $end_of_options_found = true;
            } elseif (preg_match('/^--([\w-]+)(?:=(.*))?$/', $param, $matches)) {
                $name = $matches[1];
                if (is_numeric(substr($name, 0, 1))) {
                    throw new Exception('Parameter "' . $name . '" is not valid');
                }
                if ($name !== Application::OPTION_APPCONFIG) {
                    $params[$name] = $matches[2] ?? true;
                    $prev_option =& $params[$name];
                }
            } elseif (preg_match('/^-([\w-]+)(?:=(.*))?$/', $param, $matches)) {
                $name = $matches[1];
                if (is_numeric($name)) {
                    $params[] = $param;
                } else {
                    $params['_aliases'][$name] = $matches[2] ?? true;
                    $prev_option =& $params['_aliases'][$name];
                }
            } elseif ($prev_option === true) {
                // `--option value` syntax
                $prev_option = $param;
            } else {
                $params[] = $param;
            }
        }
        return [$route, $params];
    }
}