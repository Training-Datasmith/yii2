<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\data;

use yii\helpers\Array_Helper;
/**
 * ArrayDataProvider implements a data provider based on a data array.
 *
 * The [[allModels]] property contains all data models that may be sorted and/or paginated.
 * ArrayDataProvider will provide the data after sorting and/or pagination.
 * You may configure the [[sort]] and [[pagination]] properties to
 * customize the sorting and pagination behaviors.
 *
 * Elements in the [[allModels]] array may be either objects (e.g. model objects)
 * or associative arrays (e.g. query results of DAO).
 * Make sure to set the [[key]] property to the name of the field that uniquely
 * identifies a data record or false if you do not have such a field.
 *
 * Compared to [[ActiveDataProvider]], ArrayDataProvider could be less efficient
 * because it needs to have [[allModels]] ready.
 *
 * ArrayDataProvider may be used in the following way:
 *
 * ```
 * $query = new Query;
 * $provider = new ArrayDataProvider([
 *     'allModels' => $query->from('post')->all(),
 *     'sort' => [
 *         'attributes' => ['id', 'username', 'email'],
 *     ],
 *     'pagination' => [
 *         'pageSize' => 10,
 *     ],
 * ]);
 * // get the posts in the current page
 * $posts = $provider->getModels();
 * ```
 *
 * Note: if you want to use the sorting feature, you must configure the [[sort]] property
 * so that the provider knows which columns can be sorted.
 *
 * For more details and usage information on ArrayDataProvider, see the [guide article on data providers](guide:output-data-providers).
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Array_Data_Provider extends Base_Data_Provider
{
    /**
     * @var string|array|callable|null the column that is used as the key of the data models.
     * This can be either a column name, a dot-separated path, an array of keys, or a callable
     * that returns the key value of a given data model.
     * If this is not set, the index of the [[models]] array will be used.
     * @see getKeys()
     */
    public $key;
    /**
     * @var array the data that is not paginated or sorted. When pagination is enabled,
     * this property usually contains more elements than [[models]].
     * The array elements must use zero-based integer keys.
     */
    public $all_models;
    /**
     * @var string the name of the [[\yii\base\Model|Model]] class that will be represented.
     * This property is used to get columns' names.
     * @since 2.0.9
     */
    public $model_class;
    /**
     * {@inheritdoc}
     */
    protected function prepare_models()
    {
        if (($models = $this->all_models) === null) {
            return [];
        }
        if (($sort = $this->get_sort()) !== false) {
            $models = $this->sort_models($models, $sort);
        }
        if (($pagination = $this->get_pagination()) !== false) {
            $pagination->total_count = $this->get_total_count();
            if ($pagination->get_page_size() > 0) {
                $models = array_slice($models, $pagination->get_offset(), $pagination->get_limit(), true);
            }
        }
        return $models;
    }
    /**
     * {@inheritdoc}
     */
    protected function prepare_keys($models)
    {
        if ($this->key !== null) {
            return Array_Helper::get_column($models, $this->key, false);
        }
        return array_keys($models);
    }
    /**
     * {@inheritdoc}
     */
    protected function prepare_total_count(): int
    {
        return is_array($this->all_models) ? count($this->all_models) : 0;
    }
    /**
     * Sorts the data models according to the given sort definition.
     * @param array $models the models to be sorted
     * @param Sort $sort the sort definition
     * @return array the sorted data models
     */
    protected function sort_models($models, $sort)
    {
        $orders = $sort->get_orders();
        if (!empty($orders)) {
            Array_Helper::multisort($models, array_keys($orders), array_values($orders), $sort->sort_flags);
        }
        return $models;
    }
}