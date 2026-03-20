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
use yii\helpers\Url;
use yii\web\Server_Error_Http_Exception;
/**
 * CreateAction implements the API endpoint for creating a new model from the given data.
 *
 * For more details and usage information on CreateAction, see the [guide article on rest controllers](guide:rest-controllers).
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 *
 * @template T of Controller = Controller
 * @extends Action<T>
 */
class Create_Action extends Action
{
    /**
     * @var string the scenario to be assigned to the new model before it is validated and saved.
     */
    public $scenario = Model::SCENARIO_DEFAULT;
    /**
     * @var string the name of the view action. This property is needed to create the URL when the model is successfully created.
     */
    public $view_action = 'view';
    /**
     * Creates a new model.
     * @return \yii\db\ActiveRecordInterface the model newly created
     * @throws ServerErrorHttpException if there is any error when creating the model
     */
    public function run()
    {
        if ($this->check_access) {
            call_user_func($this->check_access, $this->id);
        }
        /** @var \yii\db\ActiveRecord $model */
        $model = new $this->model_class(['scenario' => $this->scenario]);
        $model->load(Yii::$app->get_request()->get_body_params(), '');
        if ($model->save()) {
            $response = Yii::$app->get_response();
            $response->set_status_code(201);
            $id = implode(',', $model->get_primary_key(true));
            $response->get_headers()->set('Location', Url::to_route([$this->view_action, 'id' => $id], true));
        } elseif (!$model->has_errors()) {
            throw new Server_Error_Http_Exception('Failed to create the object for unknown reason.');
        }
        return $model;
    }
}