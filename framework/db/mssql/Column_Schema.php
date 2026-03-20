<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\db\mssql;

/**
 * Class ColumnSchema for MSSQL database
 *
 * @since 2.0.23
 */
class Column_Schema extends \yii\db\Column_Schema
{
    /**
     * @var bool whether this column is a computed column
     * @since 2.0.39
     */
    public $is_computed;
    /**
     * Prepares default value and converts it according to [[phpType]]
     * @param mixed $value default value
     * @return mixed converted value
     * @since 2.0.24
     */
    public function default_php_typecast($value)
    {
        if ($value !== null) {
            // convert from MSSQL column_default format, e.g. ('1') -> 1, ('string') -> string
            $value = substr(substr($value, 2), 0, -2);
        }
        return parent::php_typecast($value);
    }
}