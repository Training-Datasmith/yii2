<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\rest;

use yii\base\Action as BaseAction;
use yii\base\Invalid_Config_Exception;
use yii\db\Active_Record_Interface;
use yii\web\Not_Found_Http_Exception;
/**
 * Action is the base class for action classes that implement RESTful API.
 *
 * For more details and usage information on Action, see the [guide article on rest controllers](guide:rest-controllers).
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 *
 * @template T of Controller = Controller
 * @extends BaseAction<T>
 */
class Action extends Base_Action
{
    /**
     * @var string class name of the model which will be handled by this action.
     * The model class must implement [[ActiveRecordInterface]].
     * This property must be set.
     */
    public $model_class;
    /**
     * @var callable|null a PHP callable that will be called to return the model corresponding
     * to the specified primary key value. If not set, [[findModel()]] will be used instead.
     * The signature of the callable should be:
     *
     * ```
     * function ($id, $action) {
     *     // $id is the primary key value. If composite primary key, the key values
     *     // will be separated by comma.
     *     // $action is the action object currently running
     * }
     * ```
     *
     * The callable should return the model found, or throw an exception if not found.
     */
    public $find_model;
    /**
     * @var callable|null a PHP callable that will be called when running an action to determine
     * if the current user has the permission to execute the action. If not set, the access
     * check will not be performed. The signature of the callable should be as follows,
     *
     * ```
     * function ($action, $model = null) {
     *     // $model is the requested model instance.
     *     // If null, it means no specific model (e.g. IndexAction)
     * }
     * ```
     */
    public $check_access;
    /**
     * {@inheritdoc}
     */
    public function init(): void
    {
        if ($this->model_class === null) {
            throw new Invalid_Config_Exception(get_class($this) . '::$modelClass must be set.');
        }
    }
    /**
     * Returns the data model based on the primary key given.
     * If the data model is not found, a 404 HTTP exception will be raised.
     * @param string $id the ID of the model to be loaded. If the model has a composite primary key,
     * the ID must be a string of the primary key values separated by commas.
     * The order of the primary key values should follow that returned by the `primaryKey()` method
     * of the model.
     * @return ActiveRecordInterface the model found
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function find_model($id)
    {
        if ($this->find_model !== null) {
            return call_user_func($this->find_model, $id, $this);
        }
        /** @var ActiveRecordInterface $modelClass */
        $model_class = $this->model_class;
        $keys = $model_class::primary_key();
        if (count($keys) > 1) {
            $values = explode(',', $id);
            if (count($keys) === count($values)) {
                $model = $model_class::find_one(array_combine($keys, $values));
            }
        } elseif ($id !== null) {
            $model = $model_class::find_one($id);
        }
        if (isset($model)) {
            return $model;
        }
        throw new Not_Found_Http_Exception("Object not found: {$id}");
    }
}