<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\base;

use Yii;
/**
 * StaticInstanceTrait provides methods to satisfy [[StaticInstanceInterface]] interface.
 *
 * @see StaticInstanceInterface
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 2.0.13
 */
trait Static_Instance_Trait
{
    /**
     * @var static[] static instances in format: `[className => object]`
     */
    private static $_instances = [];
    /**
     * Returns static class instance, which can be used to obtain meta information.
     * @param bool $refresh whether to re-create static instance even, if it is already cached.
     * @return static class instance.
     */
    public static function instance($refresh = false)
    {
        $class_name = static::class;
        if ($refresh || !isset(self::$_instances[$class_name])) {
            self::$_instances[$class_name] = Yii::create_object($class_name);
        }
        return self::$_instances[$class_name];
    }
}