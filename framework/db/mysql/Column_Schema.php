<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\db\mysql;

use yii\db\Expression_Interface;
use yii\db\Json_Expression;
/**
 * Class ColumnSchema for MySQL database
 *
 * @author Dmytro Naumenko <d.naumenko.a@gmail.com>
 * @since 2.0.14.1
 */
class Column_Schema extends \yii\db\Column_Schema
{
    /**
     * @var bool whether the column schema should OMIT using JSON support feature.
     * You can use this property to make upgrade to Yii 2.0.14 easier.
     * Default to `false`, meaning JSON support is enabled.
     *
     * @since 2.0.14.1
     * @deprecated Since 2.0.14.1 and will be removed in 2.1.
     */
    public $disable_json_support = false;
    /**
     * {@inheritdoc}
     */
    public function db_typecast($value)
    {
        if ($value === null) {
            return $value;
        }
        if ($value instanceof Expression_Interface) {
            return $value;
        }
        if (!$this->disable_json_support && $this->db_type === Schema::TYPE_JSON) {
            return new Json_Expression($value, $this->type);
        }
        return $this->typecast($value);
    }
    /**
     * {@inheritdoc}
     */
    public function php_typecast($value)
    {
        if ($value === null) {
            return null;
        }
        if (!$this->disable_json_support && $this->type === Schema::TYPE_JSON) {
            return json_decode($value, true);
        }
        return parent::php_typecast($value);
    }
}