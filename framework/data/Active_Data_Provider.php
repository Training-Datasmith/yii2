<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\data;

use yii\base\Invalid_Config_Exception;
use yii\base\Model;
use yii\db\Active_Query_Interface;
use yii\db\Connection;
use yii\db\Query_Interface;
use yii\di\Instance;
/**
 * ActiveDataProvider implements a data provider based on [[\yii\db\Query]] and [[\yii\db\ActiveQuery]].
 *
 * ActiveDataProvider provides data by performing DB queries using [[query]].
 *
 * The following is an example of using ActiveDataProvider to provide ActiveRecord instances:
 *
 * ```
 * $provider = new ActiveDataProvider([
 *     'query' => Post::find(),
 *     'pagination' => [
 *         'pageSize' => 20,
 *     ],
 * ]);
 *
 * // get the posts in the current page
 * $posts = $provider->getModels();
 * ```
 *
 * And the following example shows how to use ActiveDataProvider without ActiveRecord:
 *
 * ```
 * $query = new Query();
 * $provider = new ActiveDataProvider([
 *     'query' => $query->from('post'),
 *     'pagination' => [
 *         'pageSize' => 20,
 *     ],
 * ]);
 *
 * // get the posts in the current page
 * $posts = $provider->getModels();
 * ```
 *
 * For more details and usage information on ActiveDataProvider, see the [guide article on data providers](guide:output-data-providers).
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Active_Data_Provider extends Base_Data_Provider
{
    /**
     * @var QueryInterface|null the query that is used to fetch data models and [[totalCount]] if it is not explicitly set.
     */
    public $query;
    /**
     * @var string|callable|null the column that is used as the key of the data models.
     * This can be either a column name, or a callable that returns the key value of a given data model.
     *
     * If this is not set, the following rules will be used to determine the keys of the data models:
     *
     * - If [[query]] is an [[\yii\db\ActiveQuery]] instance, the primary keys of [[\yii\db\ActiveQuery::modelClass]] will be used.
     * - Otherwise, the keys of the [[models]] array will be used.
     *
     * @see getKeys()
     */
    public $key;
    /**
     * @var Connection|array|string|null the DB connection object or the application component ID of the DB connection.
     * If set it overrides [[query]] default DB connection.
     * Starting from version 2.0.2, this can also be a configuration array for creating the object.
     */
    public $db;
    /**
     * Initializes the DB connection component.
     * This method will initialize the [[db]] property (when set) to make sure it refers to a valid DB connection.
     * @throws InvalidConfigException if [[db]] is invalid.
     */
    public function init(): void
    {
        parent::init();
        if ($this->db !== null) {
            $this->db = Instance::ensure($this->db);
        }
    }
    /**
     * {@inheritdoc}
     */
    protected function prepare_models()
    {
        if (!$this->query instanceof Query_Interface) {
            throw new Invalid_Config_Exception('The "query" property must be an instance of a class that implements the QueryInterface e.g. yii\db\Query or its subclasses.');
        }
        $query = clone $this->query;
        if (($pagination = $this->get_pagination()) !== false) {
            $pagination->total_count = $this->get_total_count();
            if ($pagination->total_count === 0) {
                return [];
            }
            $query->limit($pagination->get_limit())->offset($pagination->get_offset());
        }
        if (($sort = $this->get_sort()) !== false) {
            $query->add_order_by($sort->get_orders());
        }
        return $query->all($this->db);
    }
    /**
     * {@inheritdoc}
     * @return mixed[]
     */
    protected function prepare_keys($models): array
    {
        $keys = [];
        if ($this->key !== null) {
            foreach ($models as $model) {
                if (is_string($this->key)) {
                    $keys[] = $model[$this->key];
                } else {
                    $keys[] = call_user_func($this->key, $model);
                }
            }
            return $keys;
        }
        if ($this->query instanceof Active_Query_Interface) {
            /** @var \yii\db\ActiveRecordInterface $class */
            $class = $this->query->model_class;
            $pks = $class::primary_key();
            if (count($pks) === 1) {
                $pk = $pks[0];
                foreach ($models as $model) {
                    $keys[] = $model[$pk];
                }
            } else {
                foreach ($models as $model) {
                    $kk = [];
                    foreach ($pks as $pk) {
                        $kk[$pk] = $model[$pk];
                    }
                    $keys[] = $kk;
                }
            }
            return $keys;
        }
        return array_keys($models);
    }
    /**
     * {@inheritdoc}
     */
    protected function prepare_total_count(): int
    {
        if (!$this->query instanceof Query_Interface) {
            throw new Invalid_Config_Exception('The "query" property must be an instance of a class that implements the QueryInterface e.g. yii\db\Query or its subclasses.');
        }
        $query = clone $this->query;
        return (int) $query->limit(-1)->offset(-1)->order_by([])->count('*', $this->db);
    }
    /**
     * {@inheritdoc}
     */
    public function set_sort($value): void
    {
        parent::set_sort($value);
        if ($this->query instanceof Active_Query_Interface && ($sort = $this->get_sort()) !== false) {
            /** @var Model $modelClass */
            $model_class = $this->query->model_class;
            $model = $model_class::instance();
            if (empty($sort->attributes)) {
                foreach ($model->attributes() as $attribute) {
                    $sort->attributes[$attribute] = ['asc' => [$attribute => SORT_ASC], 'desc' => [$attribute => SORT_DESC]];
                }
            }
            if ($sort->model_class === null) {
                $sort->model_class = $model_class;
            }
        }
    }
    public function __clone()
    {
        if (is_object($this->query)) {
            $this->query = clone $this->query;
        }
        parent::__clone();
    }
}