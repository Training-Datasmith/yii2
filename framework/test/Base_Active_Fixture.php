<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\test;

use yii\base\Array_Access_Trait;
use yii\base\Invalid_Config_Exception;
/**
 * BaseActiveFixture is the base class for fixture classes that support accessing fixture data as ActiveRecord objects.
 *
 * For more details and usage information on BaseActiveFixture, see the [guide article on fixtures](guide:test-fixtures).
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 *
 * @implements \IteratorAggregate<string, array<string, mixed>>
 * @implements \ArrayAccess<string, array<string, mixed>|null>
 */
abstract class Base_Active_Fixture extends Db_Fixture implements \IteratorAggregate, \ArrayAccess, \Countable
{
    use Array_Access_Trait;
    use File_Fixture_Trait;
    /**
     * @var string the AR model class associated with this fixture.
     */
    public $model_class;
    /**
     * @var array<string, array<string, mixed>> the data rows. Each array element represents one row of data (column name => column value).
     */
    public $data = [];
    /**
     * @var \yii\db\ActiveRecord[] the loaded AR models
     */
    private $_models = [];
    /**
     * Returns the AR model by the specified model name.
     * A model name is the key of the corresponding data row in [[data]].
     * @param string $name the model name.
     * @return \yii\db\ActiveRecord|null the AR model, or null if the model cannot be found in the database
     * @throws \yii\base\InvalidConfigException if [[modelClass]] is not set.
     */
    public function get_model($name)
    {
        if (!isset($this->data[$name])) {
            return null;
        }
        if (array_key_exists($name, $this->_models)) {
            return $this->_models[$name];
        }
        if ($this->model_class === null) {
            throw new Invalid_Config_Exception('The "modelClass" property must be set.');
        }
        $row = $this->data[$name];
        /** @var \yii\db\ActiveRecord $modelClass */
        $model_class = $this->model_class;
        $keys = [];
        foreach ($model_class::primary_key() as $key) {
            $keys[$key] = isset($row[$key]) ? $row[$key] : null;
        }
        return $this->_models[$name] = $model_class::find_one($keys);
    }
    /**
     * Loads the fixture.
     *
     * The default implementation simply stores the data returned by [[getData()]] in [[data]].
     * You should usually override this method by putting the data into the underlying database.
     */
    public function load()
    {
        $this->data = $this->get_data();
    }
    /**
     * Returns the fixture data.
     *
     * @return array<string, array<string, mixed>> the data to be put into the database
     * @throws InvalidConfigException if the specified data file does not exist.
     * @see loadData()
     */
    protected function get_data()
    {
        return $this->load_data($this->data_file);
    }
    /**
     * {@inheritdoc}
     */
    public function unload()
    {
        parent::unload();
        $this->data = [];
        $this->_models = [];
    }
}