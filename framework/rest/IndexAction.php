<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\rest;

use Yii;
use yii\data\Active_Data_Provider;
use yii\data\Data_Filter;
use yii\data\Pagination;
use yii\data\Sort;
use yii\helpers\Array_Helper;
/**
 * IndexAction implements the API endpoint for listing multiple models.
 *
 * For more details and usage information on IndexAction, see the [guide article on rest controllers](guide:rest-controllers).
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 *
 * @template T of Controller = Controller
 * @extends Action<T>
 */
class Index_Action extends Action
{
    /**
     * @var callable|null a PHP callable that will be called to prepare a data provider that
     * should return a collection of the models. If not set, [[prepareDataProvider()]] will be used instead.
     * The signature of the callable should be:
     *
     * ```
     * function (IndexAction $action) {
     *     // $action is the action object currently running
     * }
     * ```
     *
     * The callable should return an instance of [[ActiveDataProvider]].
     *
     * If [[dataFilter]] is set the result of [[DataFilter::build()]] will be passed to the callable as a second parameter.
     * In this case the signature of the callable should be the following:
     *
     * ```
     * function (IndexAction $action, mixed $filter) {
     *     // $action is the action object currently running
     *     // $filter the built filter condition
     * }
     * ```
     */
    public $prepare_data_provider;
    /**
     * @var callable a PHP callable that will be called to prepare query in prepareDataProvider.
     * Should return $query.
     * For example:
     *
     * ```
     * function ($query, $requestParams) {
     *     $query->andFilterWhere(['id' => 1]);
     *     ...
     *     return $query;
     * }
     * ```
     *
     * @since 2.0.42
     */
    public $prepare_search_query;
    /**
     * @var DataFilter|null data filter to be used for the search filter composition.
     * You must set up this field explicitly in order to enable filter processing.
     * For example:
     *
     * ```
     * [
     *     'class' => 'yii\data\ActiveDataFilter',
     *     'searchModel' => function () {
     *         return (new \yii\base\DynamicModel(['id' => null, 'name' => null, 'price' => null]))
     *             ->addRule('id', 'integer')
     *             ->addRule('name', 'trim')
     *             ->addRule('name', 'string')
     *             ->addRule('price', 'number');
     *     },
     * ]
     * ```
     *
     * @see DataFilter
     *
     * @since 2.0.13
     */
    public $data_filter;
    /**
     * @var array|Pagination|false The pagination to be used by [[prepareDataProvider()]].
     * If this is `false`, it means pagination is disabled.
     * Note: if a Pagination object is passed, it's `params` will be set to the request parameters.
     * @see Pagination
     * @since 2.0.45
     */
    public $pagination = [];
    /**
     * @var array|Sort|false The sorting to be used by [[prepareDataProvider()]].
     * If this is `false`, it means sorting is disabled.
     * Note: if a Sort object is passed, it's `params` will be set to the request parameters.
     * @see Sort
     * @since 2.0.45
     */
    public $sort = [];
    /**
     * @return ActiveDataProvider
     */
    public function run()
    {
        if ($this->check_access) {
            call_user_func($this->check_access, $this->id);
        }
        return $this->prepare_data_provider();
    }
    /**
     * Prepares the data provider that should return the requested collection of the models.
     * @return ActiveDataProvider
     */
    protected function prepare_data_provider()
    {
        $request_params = Yii::$app->get_request()->get_body_params();
        if (empty($request_params)) {
            $request_params = Yii::$app->get_request()->get_query_params();
        }
        $filter = null;
        if ($this->data_filter !== null) {
            $this->data_filter = Yii::create_object($this->data_filter);
            if ($this->data_filter->load($request_params)) {
                $filter = $this->data_filter->build();
                if ($filter === false) {
                    return $this->data_filter;
                }
            }
        }
        if ($this->prepare_data_provider !== null) {
            return call_user_func($this->prepare_data_provider, $this, $filter);
        }
        /** @var \yii\db\BaseActiveRecord $modelClass */
        $model_class = $this->model_class;
        $query = $model_class::find();
        if (!empty($filter)) {
            $query->and_where($filter);
        }
        if (is_callable($this->prepare_search_query)) {
            $query = call_user_func($this->prepare_search_query, $query, $request_params);
        }
        if (is_array($this->pagination)) {
            $pagination = Array_Helper::merge(['params' => $request_params], $this->pagination);
        } else {
            $pagination = $this->pagination;
            if ($this->pagination instanceof Pagination) {
                $pagination->params = $request_params;
            }
        }
        if (is_array($this->sort)) {
            $sort = Array_Helper::merge(['params' => $request_params], $this->sort);
        } else {
            $sort = $this->sort;
            if ($this->sort instanceof Sort) {
                $sort->params = $request_params;
            }
        }
        return Yii::create_object(['class' => Active_Data_Provider::class_name(), 'query' => $query, 'pagination' => $pagination, 'sort' => $sort]);
    }
}