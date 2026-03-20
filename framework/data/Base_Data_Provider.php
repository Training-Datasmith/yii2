<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\data;

use Yii;
use yii\base\Component;
use yii\base\InvalidArgumentException;
/**
 * BaseDataProvider provides a base class that implements the [[DataProviderInterface]].
 *
 * For more details and usage information on BaseDataProvider, see the [guide article on data providers](guide:output-data-providers).
 *
 * @property-read int $count The number of data models in the current page.
 * @property array $keys The list of key values corresponding to [[models]]. Each data model in [[models]] is
 * uniquely identified by the corresponding key value in this array.
 * @property array $models The list of data models in the current page.
 * @property Pagination|false $pagination The pagination object. If this is false, it means the pagination is
 * disabled. Note that the type of this property differs in getter and setter. See [[getPagination()]] and
 * [[setPagination()]] for details.
 * @property Sort|bool $sort The sorting object. If this is false, it means the sorting is disabled. Note that
 * the type of this property differs in getter and setter. See [[getSort()]] and [[setSort()]] for details.
 * @property int $totalCount Total number of possible data models.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 * @phpcs:disable Squiz.NamingConventions.ValidVariableName.PrivateNoUnderscore
 */
abstract class Base_Data_Provider extends Component implements Data_Provider_Interface
{
    /**
     * @var int Number of data providers on the current page. Used to generate unique IDs.
     */
    private static int $counter = 0;
    /**
     * @var string|null an ID that uniquely identifies the data provider among all data providers.
     * Generated automatically the following way in case it is not set:
     *
     * - First data provider ID is empty.
     * - Second and all subsequent data provider IDs are: "dp-1", "dp-2", etc.
     */
    public $id;
    private $_sort;
    private $_pagination;
    private $_keys;
    private $_models;
    private $_total_count;
    /**
     * {@inheritdoc}
     */
    public function init(): void
    {
        parent::init();
        if ($this->id === null) {
            if (self::$counter > 0) {
                $this->id = 'dp-' . self::$counter;
            }
            self::$counter++;
        }
    }
    /**
     * Prepares the data models that will be made available in the current page.
     * @return array the available data models
     */
    abstract protected function prepare_models();
    /**
     * Prepares the keys associated with the currently available data models.
     * @param array $models the available data models
     * @return array the keys
     */
    abstract protected function prepare_keys($models);
    /**
     * Returns a value indicating the total number of data models in this data provider.
     * @return int total number of data models in this data provider.
     */
    abstract protected function prepare_total_count();
    /**
     * Prepares the data models and keys.
     *
     * This method will prepare the data models and keys that can be retrieved via
     * [[getModels()]] and [[getKeys()]].
     *
     * This method will be implicitly called by [[getModels()]] and [[getKeys()]] if it has not been called before.
     *
     * @param bool $forcePrepare whether to force data preparation even if it has been done before.
     */
    public function prepare($force_prepare = false): void
    {
        if ($force_prepare || $this->_models === null) {
            $this->_models = $this->prepare_models();
        }
        if ($force_prepare || $this->_keys === null) {
            $this->_keys = $this->prepare_keys($this->_models);
        }
    }
    /**
     * Returns the data models in the current page.
     * @return array the list of data models in the current page.
     */
    public function get_models()
    {
        $this->prepare();
        return $this->_models;
    }
    /**
     * Sets the data models in the current page.
     * @param array $models the models in the current page
     */
    public function set_models($models): void
    {
        $this->_models = $models;
    }
    /**
     * Returns the key values associated with the data models.
     * @return array the list of key values corresponding to [[models]]. Each data model in [[models]]
     * is uniquely identified by the corresponding key value in this array.
     */
    public function get_keys()
    {
        $this->prepare();
        return $this->_keys;
    }
    /**
     * Sets the key values associated with the data models.
     * @param array $keys the list of key values corresponding to [[models]].
     */
    public function set_keys($keys): void
    {
        $this->_keys = $keys;
    }
    /**
     * Returns the number of data models in the current page.
     * @return int the number of data models in the current page.
     */
    public function get_count()
    {
        return count($this->get_models());
    }
    /**
     * Returns the total number of data models.
     * When [[pagination]] is false, this returns the same value as [[count]].
     * Otherwise, it will call [[prepareTotalCount()]] to get the count.
     * @return int total number of possible data models.
     */
    public function get_total_count()
    {
        if ($this->get_pagination() === false) {
            return $this->get_count();
        }
        if ($this->_total_count === null) {
            $this->_total_count = $this->prepare_total_count();
        }
        return $this->_total_count;
    }
    /**
     * Sets the total number of data models.
     * @param int $value the total number of data models.
     */
    public function set_total_count($value): void
    {
        $this->_total_count = $value;
    }
    /**
     * Returns the pagination object used by this data provider.
     * Note that you should call [[prepare()]] or [[getModels()]] first to get correct values
     * of [[Pagination::totalCount]] and [[Pagination::pageCount]].
     * @return Pagination|false the pagination object. If this is false, it means the pagination is disabled.
     */
    public function get_pagination()
    {
        if ($this->_pagination === null) {
            $this->set_pagination([]);
        }
        return $this->_pagination;
    }
    /**
     * Sets the pagination for this data provider.
     * @param array|Pagination|bool $value the pagination to be used by this data provider.
     * This can be one of the following:
     *
     * - a configuration array for creating the pagination object. The "class" element defaults
     *   to 'yii\data\Pagination'
     * - an instance of [[Pagination]] or its subclass
     * - false, if pagination needs to be disabled.
     *
     * @throws InvalidArgumentException
     */
    public function set_pagination($value): void
    {
        if (is_array($value)) {
            $config = ['class' => Pagination::class_name()];
            if ($this->id !== null) {
                $config['pageParam'] = $this->id . '-page';
                $config['pageSizeParam'] = $this->id . '-per-page';
            }
            $this->_pagination = Yii::create_object(array_merge($config, $value));
        } elseif ($value instanceof Pagination || $value === false) {
            $this->_pagination = $value;
        } else {
            throw new InvalidArgumentException('Only Pagination instance, configuration array or false is allowed.');
        }
    }
    /**
     * Returns the sorting object used by this data provider.
     * @return Sort|bool the sorting object. If this is false, it means the sorting is disabled.
     */
    public function get_sort()
    {
        if ($this->_sort === null) {
            $this->set_sort([]);
        }
        return $this->_sort;
    }
    /**
     * Sets the sort definition for this data provider.
     * @param array|Sort|bool $value the sort definition to be used by this data provider.
     * This can be one of the following:
     *
     * - a configuration array for creating the sort definition object. The "class" element defaults
     *   to 'yii\data\Sort'
     * - an instance of [[Sort]] or its subclass
     * - false, if sorting needs to be disabled.
     *
     * @throws InvalidArgumentException
     */
    public function set_sort($value): void
    {
        if (is_array($value)) {
            $config = ['class' => Sort::class_name()];
            if ($this->id !== null) {
                $config['sortParam'] = $this->id . '-sort';
            }
            $this->_sort = Yii::create_object(array_merge($config, $value));
        } elseif ($value instanceof Sort || $value === false) {
            $this->_sort = $value;
        } else {
            throw new InvalidArgumentException('Only Sort instance, configuration array or false is allowed.');
        }
    }
    /**
     * Refreshes the data provider.
     * After calling this method, if [[getModels()]], [[getKeys()]] or [[getTotalCount()]] is called again,
     * they will re-execute the query and return the latest data available.
     */
    public function refresh(): void
    {
        $this->_total_count = null;
        $this->_models = null;
        $this->_keys = null;
    }
}