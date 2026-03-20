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
 * ArrayFixture represents arbitrary fixture that can be loaded from PHP files.
 *
 * For more details and usage information on ArrayFixture, see the [guide article on fixtures](guide:test-fixtures).
 *
 * @author Mark Jebri <mark.github@yandex.ru>
 * @since 2.0
 *
 * @implements \IteratorAggregate<string, array<string, mixed>>
 * @implements \ArrayAccess<string, array<string, mixed>|null>
 */
class Array_Fixture extends Fixture implements \IteratorAggregate, \ArrayAccess, \Countable
{
    use Array_Access_Trait;
    use File_Fixture_Trait;
    /**
     * @var array<string, array<string, mixed>> the data rows. Each array element represents one row of data (column name => column value).
     */
    public $data = [];
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
     * The default implementation will try to return the fixture data by including the external file specified by [[dataFile]].
     * The file should return the data array that will be stored in [[data]] after inserting into the database.
     *
     * @return array<string, array<string, mixed>> the data to be put into the database
     * @throws InvalidConfigException if the specified data file does not exist.
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
    }
}