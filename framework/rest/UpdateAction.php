<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\rest;

use Yii;
use yii\base\Model;
use yii\db\Active_Record;
use yii\web\Server_Error_Http_Exception;
/**
 * UpdateAction implements the API endpoint for updating a model.
 *
 * For more details and usage information on UpdateAction, see the [guide article on rest controllers](guide:rest-controllers).
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 *
 * @template T of Controller = Controller
 * @extends Action<T>
 */
class Update_Action extends Action
{
    /**
     * @var string the scenario to be assigned to the model before it is validated and updated.
     */
    public $scenario = Model::SCENARIO_DEFAULT;
    /**
     * Updates an existing model.
     * @param string $id the primary key of the model.
     * @return \yii\db\ActiveRecordInterface the model being updated
     * @throws ServerErrorHttpException if there is any error when updating the model
     */
    public function run($id)
    {
        /** @var ActiveRecord $model */
        $model = $this->find_model($id);
        if ($this->check_access) {
            call_user_func($this->check_access, $this->id, $model);
        }
        $model->scenario = $this->scenario;
        $model->load(Yii::$app->get_request()->get_body_params(), '');
        if ($model->save() === false && !$model->has_errors()) {
            throw new Server_Error_Http_Exception('Failed to update the object for unknown reason.');
        }
        return $model;
    }
}