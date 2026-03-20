<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\rest;

use Yii;
use yii\web\Server_Error_Http_Exception;
/**
 * DeleteAction implements the API endpoint for deleting a model.
 *
 * For more details and usage information on DeleteAction, see the [guide article on rest controllers](guide:rest-controllers).
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 *
 * @template T of Controller = Controller
 * @extends Action<T>
 */
class Delete_Action extends Action
{
    /**
     * Deletes a model.
     * @param mixed $id id of the model to be deleted.
     * @throws ServerErrorHttpException on failure.
     */
    public function run($id): void
    {
        $model = $this->find_model($id);
        if ($this->check_access) {
            call_user_func($this->check_access, $this->id, $model);
        }
        if ($model->delete() === false) {
            throw new Server_Error_Http_Exception('Failed to delete the object for unknown reason.');
        }
        Yii::$app->get_response()->set_status_code(204);
    }
}