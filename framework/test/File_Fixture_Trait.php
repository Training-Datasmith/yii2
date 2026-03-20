<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\test;

use Yii;
use yii\base\Invalid_Config_Exception;
/**
 * FileFixtureTrait provides functionalities for loading data fixture from file.
 *
 * @author Leandro Guindani Gehlen <leandrogehlen@gmail.com>
 * @since 2.0.14
 */
trait File_Fixture_Trait
{
    /**
     * @var string the directory path or [path alias](guide:concept-aliases) that contains the fixture data
     */
    public $data_directory;
    /**
     * @var string|bool|null the file path or [path alias](guide:concept-aliases) of the data file that contains the fixture data
     * to be returned by [[getData()]]. You can set this property to be false to prevent loading any data.
     */
    public $data_file;
    /**
     * Returns the fixture data.
     *
     * The default implementation will try to return the fixture data by including the external file specified by [[dataFile]].
     * The file should return the data array that will be stored in [[data]] after inserting into the database.
     *
     * @param string $file the data file path
     * @param bool $throwException whether to throw exception if fixture data file does not exist.
     * @return array the data to be put into the database
     * @throws InvalidConfigException if the specified data file does not exist.
     */
    protected function load_data($file, $throw_exception = true)
    {
        if ($file === null || $file === false) {
            return [];
        }
        if (basename($file) === $file && $this->data_directory !== null) {
            $file = $this->data_directory . '/' . $file;
        }
        $file = Yii::get_alias($file);
        if (is_file($file)) {
            return require $file;
        }
        if ($throw_exception) {
            throw new Invalid_Config_Exception("Fixture data file does not exist: {$file}");
        }
        return [];
    }
}