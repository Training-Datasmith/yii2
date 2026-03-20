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
 * Action is the base class for all controller action classes.
 *
 * Action provides a way to reuse action method code. An action method in an Action
 * class can be used in multiple controllers or in different projects.
 *
 * Derived classes must implement a method named `run()`. This method
 * will be invoked by the controller when the action is requested.
 * The `run()` method can have parameters which will be filled up
 * with user input values automatically according to their names.
 * For example, if the `run()` method is declared as follows:
 *
 * ```
 * public function run($id, $type = 'book') { ... }
 * ```
 *
 * And the parameters provided for the action are: `['id' => 1]`.
 * Then the `run()` method will be invoked as `run(1)` automatically.
 *
 * For more details and usage information on Action, see the [guide article on actions](guide:structure-controllers).
 *
 * @property-read string $uniqueId The unique ID of this action among the whole application.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 *
 * @template T of Controller = Controller
 */
class Action extends Component
{
    /**
     * @var string ID of the action
     */
    public $id;
    /**
     * @var T the controller that owns this action
     */
    public $controller;
    /**
     * Constructor.
     *
     * @param string $id the ID of this action
     * @param T $controller the controller that owns this action
     * @param array<string, mixed> $config name-value pairs that will be used to initialize the object properties
     */
    public function __construct($id, $controller, $config = [])
    {
        $this->id = $id;
        $this->controller = $controller;
        parent::__construct($config);
    }
    /**
     * Returns the unique ID of this action among the whole application.
     *
     * @return string the unique ID of this action among the whole application.
     */
    public function get_unique_id(): string
    {
        return $this->controller->get_unique_id() . '/' . $this->id;
    }
    /**
     * Runs this action with the specified parameters.
     * This method is mainly invoked by the controller.
     *
     * @param array $params the parameters to be bound to the action's run() method.
     * @return mixed the result of the action
     * @throws InvalidConfigException if the action class does not have a run() method
     */
    public function run_with_params($params)
    {
        if (!method_exists($this, 'run')) {
            throw new Invalid_Config_Exception(get_class($this) . ' must define a "run()" method.');
        }
        $args = $this->controller->bind_action_params($this, $params);
        Yii::debug('Running action: ' . get_class($this) . '::run(), invoked by ' . get_class($this->controller), __METHOD__);
        if (Yii::$app->requested_params === null) {
            Yii::$app->requested_params = $args;
        }
        if ($this->before_run()) {
            $result = call_user_func_array([$this, 'run'], $args);
            $this->after_run();
            return $result;
        }
        return null;
    }
    /**
     * This method is called right before `run()` is executed.
     * You may override this method to do preparation work for the action run.
     * If the method returns false, it will cancel the action.
     *
     * @return bool whether to run the action.
     */
    protected function before_run(): bool
    {
        return true;
    }
    /**
     * This method is called right after `run()` is executed.
     * You may override this method to do post-processing work for the action run.
     */
    protected function after_run()
    {
    }
}