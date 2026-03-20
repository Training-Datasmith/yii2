<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\test;

use yii\base\Invalid_Config_Exception;
use yii\db\Active_Record;
use yii\db\Table_Schema;
/**
 * ActiveFixture represents a fixture backed up by a [[modelClass|ActiveRecord class]] or a [[tableName|database table]].
 *
 * Either [[modelClass]] or [[tableName]] must be set. You should also provide fixture data in the file
 * specified by [[dataFile]] or overriding [[getData()]] if you want to use code to generate the fixture data.
 *
 * When the fixture is being loaded, it will first call [[resetTable()]] to remove any existing data in the table.
 * It will then populate the table with the data returned by [[getData()]].
 *
 * After the fixture is loaded, you can access the loaded data via the [[data]] property. If you set [[modelClass]],
 * you will also be able to retrieve an instance of [[modelClass]] with the populated data via [[getModel()]].
 *
 * For more details and usage information on ActiveFixture, see the [guide article on fixtures](guide:test-fixtures).
 *
 * @property-read TableSchema $tableSchema The schema information of the database table associated with this
 * fixture.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Active_Fixture extends Base_Active_Fixture
{
    /**
     * @var string|null the name of the database table that this fixture is about. If this property is not set,
     * the table name will be determined via [[modelClass]].
     * @see modelClass
     */
    public $table_name;
    /**
     * @var string|bool|null the file path or [path alias](guide:concept-aliases) of the data file that contains the fixture data
     * to be returned by [[getData()]]. If this is not set, it will default to `FixturePath/data/TableName.php`,
     * where `FixturePath` stands for the directory containing this fixture class, and `TableName` stands for the
     * name of the table associated with this fixture. You can set this property to be false to prevent loading any data.
     */
    public $data_file;
    /**
     * @var TableSchema the table schema for the table associated with this fixture
     */
    private $_table;
    /**
     * {@inheritdoc}
     */
    public function init()
    {
        parent::init();
        if ($this->table_name === null) {
            if ($this->model_class === null) {
                throw new Invalid_Config_Exception('Either "modelClass" or "tableName" must be set.');
            }
            /** @var ActiveRecord $modelClass */
            $model_class = $this->model_class;
            $this->db = $model_class::get_db();
        }
    }
    /**
     * Loads the fixture.
     *
     * It populate the table with the data returned by [[getData()]].
     *
     * If you override this method, you should consider calling the parent implementation
     * so that the data returned by [[getData()]] can be populated into the table.
     */
    public function load()
    {
        $this->data = [];
        $table = $this->get_table_schema();
        foreach ($this->get_data() as $alias => $row) {
            $primary_keys = $this->db->schema->insert($table->full_name, $row);
            $this->data[$alias] = array_merge($row, $primary_keys);
        }
        if ($table->sequence_name !== null) {
            $this->db->create_command()->execute_reset_sequence($table->full_name);
        }
    }
    /**
     * Returns the fixture data.
     *
     * The default implementation will try to return the fixture data by including the external file specified by [[dataFile]].
     * The file should return an array of data rows (column name => column value), each corresponding to a row in the table.
     *
     * If the data file does not exist, an empty array will be returned.
     *
     * @return array the data rows to be inserted into the database table.
     */
    protected function get_data()
    {
        if ($this->data_file === null) {
            if ($this->data_directory !== null) {
                $data_file = $this->get_table_schema()->full_name . '.php';
            } else {
                $class = new \ReflectionClass($this);
                $data_file = dirname($class->get_file_name()) . '/data/' . $this->get_table_schema()->full_name . '.php';
            }
            return $this->load_data($data_file, false);
        }
        return parent::get_data();
    }
    /**
     * {@inheritdoc}
     */
    public function unload()
    {
        $this->reset_table();
        parent::unload();
    }
    /**
     * Removes all existing data from the specified table and resets sequence number to 1 (if any).
     * This method is called before populating fixture data into the table associated with this fixture.
     */
    protected function reset_table()
    {
        $table = $this->get_table_schema();
        $this->db->create_command()->delete($table->full_name)->execute();
        if ($table->sequence_name !== null) {
            $this->db->create_command()->execute_reset_sequence($table->full_name, 1);
        }
    }
    /**
     * @return TableSchema the schema information of the database table associated with this fixture.
     * @throws \yii\base\InvalidConfigException if the table does not exist
     */
    public function get_table_schema()
    {
        if ($this->_table !== null) {
            return $this->_table;
        }
        $db = $this->db;
        $table_name = $this->table_name;
        if ($table_name === null) {
            /** @var \yii\db\ActiveRecord $modelClass */
            $model_class = $this->model_class;
            $table_name = $model_class::table_name();
        }
        $this->_table = $db->get_schema()->get_table_schema($table_name);
        if ($this->_table === null) {
            throw new Invalid_Config_Exception("Table does not exist: {$table_name}");
        }
        return $this->_table;
    }
}