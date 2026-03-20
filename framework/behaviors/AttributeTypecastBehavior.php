<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\behaviors;

use yii\base\Behavior;
use yii\base\InvalidArgumentException;
use yii\base\Model;
use yii\db\Base_Active_Record;
use yii\helpers\String_Helper;
use yii\validators\Boolean_Validator;
use yii\validators\Number_Validator;
use yii\validators\String_Validator;
/**
 * AttributeTypecastBehavior provides an ability of automatic model attribute typecasting.
 * This behavior is very useful in case of usage of ActiveRecord for the schema-less databases like MongoDB or Redis.
 * It may also come in handy for regular [[\yii\db\ActiveRecord]] or even [[\yii\base\Model]], allowing to maintain
 * strict attribute types after model validation.
 *
 * This behavior should be attached to [[\yii\base\Model]] or [[\yii\db\BaseActiveRecord]] descendant.
 *
 * You should specify exact attribute types via [[attributeTypes]].
 *
 * For example:
 *
 * ```
 * use yii\behaviors\AttributeTypecastBehavior;
 *
 * class Item extends \yii\db\ActiveRecord
 * {
 *     public function behaviors()
 *     {
 *         return [
 *             'typecast' => [
 *                 'class' => AttributeTypecastBehavior::class,
 *                 'attributeTypes' => [
 *                     'amount' => AttributeTypecastBehavior::TYPE_INTEGER,
 *                     'price' => AttributeTypecastBehavior::TYPE_FLOAT,
 *                     'is_active' => AttributeTypecastBehavior::TYPE_BOOLEAN,
 *                 ],
 *                 'typecastAfterValidate' => true,
 *                 'typecastBeforeSave' => false,
 *                 'typecastAfterFind' => false,
 *             ],
 *         ];
 *     }
 *
 *     // ...
 * }
 * ```
 *
 * Tip: you may left [[attributeTypes]] blank - in this case its value will be detected
 * automatically based on owner validation rules.
 * Following example will automatically create same [[attributeTypes]] value as it was configured at the above one:
 *
 * ```
 * use yii\behaviors\AttributeTypecastBehavior;
 *
 * class Item extends \yii\db\ActiveRecord
 * {
 *
 *     public function rules()
 *     {
 *         return [
 *             ['amount', 'integer'],
 *             ['price', 'number'],
 *             ['is_active', 'boolean'],
 *         ];
 *     }
 *
 *     public function behaviors()
 *     {
 *         return [
 *             'typecast' => [
 *                 'class' => AttributeTypecastBehavior::class,
 *                 // 'attributeTypes' will be composed automatically according to `rules()`
 *             ],
 *         ];
 *     }
 *
 *     // ...
 * }
 * ```
 *
 * This behavior allows automatic attribute typecasting at following cases:
 *
 * - after successful model validation
 * - before model save (insert or update)
 * - after model find (found by query or refreshed)
 *
 * You may control automatic typecasting for particular case using fields [[typecastAfterValidate]],
 * [[typecastBeforeSave]] and [[typecastAfterFind]].
 * By default typecasting will be performed only after model validation.
 *
 * Note: you can manually trigger attribute typecasting anytime invoking [[typecastAttributes()]] method:
 *
 * ```
 * $model = new Item();
 * $model->price = '38.5';
 * $model->is_active = 1;
 * $model->typecastAttributes();
 * ```
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 2.0.10
 *
 * @template T of Model|BaseActiveRecord = Model|BaseActiveRecord
 * @extends Behavior<T>
 */
class Attribute_Typecast_Behavior extends Behavior
{
    public const TYPE_INTEGER = 'integer';
    public const TYPE_FLOAT = 'float';
    public const TYPE_BOOLEAN = 'boolean';
    public const TYPE_STRING = 'string';
    /**
     * @var T|null the owner of this behavior.
     */
    public $owner;
    /**
     * @var array|null attribute typecast map in format: attributeName => type.
     * Type can be set via PHP callable, which accept raw value as an argument and should return
     * typecast result.
     * For example:
     *
     * ```
     * [
     *     'amount' => 'integer',
     *     'price' => 'float',
     *     'is_active' => 'boolean',
     *     'date' => function ($value) {
     *         return ($value instanceof \DateTime) ? $value->getTimestamp(): (int) $value;
     *     },
     * ]
     * ```
     *
     * If not set, attribute type map will be composed automatically from the owner validation rules.
     */
    public $attribute_types;
    /**
     * @var bool whether to skip typecasting of `null` values.
     * If enabled attribute value which equals to `null` will not be type-casted (e.g. `null` remains `null`),
     * otherwise it will be converted according to the type configured at [[attributeTypes]].
     */
    public $skip_on_null = true;
    /**
     * @var bool whether to perform typecasting after owner model validation.
     * Note that typecasting will be performed only if validation was successful, e.g.
     * owner model has no errors.
     * Note that changing this option value will have no effect after this behavior has been attached to the model.
     */
    public $typecast_after_validate = true;
    /**
     * @var bool whether to perform typecasting before saving owner model (insert or update).
     * This option may be disabled in order to achieve better performance.
     * For example, in case of [[\yii\db\ActiveRecord]] usage, typecasting before save
     * will grant no benefit an thus can be disabled.
     * Note that changing this option value will have no effect after this behavior has been attached to the model.
     */
    public $typecast_before_save = false;
    /**
     * @var bool whether to perform typecasting after saving owner model (insert or update).
     * This option may be disabled in order to achieve better performance.
     * For example, in case of [[\yii\db\ActiveRecord]] usage, typecasting after save
     * will grant no benefit an thus can be disabled.
     * Note that changing this option value will have no effect after this behavior has been attached to the model.
     * @since 2.0.14
     */
    public $typecast_after_save = false;
    /**
     * @var bool whether to perform typecasting after retrieving owner model data from
     * the database (after find or refresh).
     * This option may be disabled in order to achieve better performance.
     * For example, in case of [[\yii\db\ActiveRecord]] usage, typecasting after find
     * will grant no benefit in most cases an thus can be disabled.
     * Note that changing this option value will have no effect after this behavior has been attached to the model.
     */
    public $typecast_after_find = false;
    /**
     * @var array internal static cache for auto detected [[attributeTypes]] values
     * in format: ownerClassName => attributeTypes
     */
    private static array $_auto_detected_attribute_types = [];
    /**
     * Clears internal static cache of auto detected [[attributeTypes]] values
     * over all affected owner classes.
     */
    public static function clear_auto_detected_attribute_types(): void
    {
        self::$_auto_detected_attribute_types = [];
    }
    /**
     * {@inheritdoc}
     */
    public function attach($owner): void
    {
        parent::attach($owner);
        if ($this->attribute_types === null) {
            $owner_class = $this->owner !== null ? get_class($this->owner) : self::class;
            if (!isset(self::$_auto_detected_attribute_types[$owner_class])) {
                self::$_auto_detected_attribute_types[$owner_class] = $this->detect_attribute_types();
            }
            $this->attribute_types = self::$_auto_detected_attribute_types[$owner_class];
        }
    }
    /**
     * Typecast owner attributes according to [[attributeTypes]].
     * @param array|null $attributeNames list of attribute names that should be type-casted.
     * If this parameter is empty, it means any attribute listed in the [[attributeTypes]]
     * should be type-casted.
     */
    public function typecast_attributes($attribute_names = null): void
    {
        $attribute_types = [];
        if ($attribute_names === null) {
            $attribute_types = $this->attribute_types;
        } else {
            foreach ($attribute_names as $attribute) {
                if (!isset($this->attribute_types[$attribute])) {
                    throw new InvalidArgumentException("There is no type mapping for '{$attribute}'.");
                }
                $attribute_types[$attribute] = $this->attribute_types[$attribute];
            }
        }
        foreach ($attribute_types as $attribute => $type) {
            $value = $this->owner->{$attribute};
            if ($this->skip_on_null && $value === null) {
                continue;
            }
            $this->owner->{$attribute} = $this->typecast_value($value, $type);
        }
    }
    /**
     * Casts the given value to the specified type.
     * @param mixed $value value to be type-casted.
     * @param string|callable $type type name or typecast callable.
     * @return mixed typecast result.
     */
    protected function typecast_value($value, $type)
    {
        if (is_scalar($type)) {
            if (is_object($value) && method_exists($value, '__toString')) {
                $value = $value->__toString();
            }
            switch ($type) {
                case self::TYPE_INTEGER:
                    return (int) $value;
                case self::TYPE_FLOAT:
                    return (float) $value;
                case self::TYPE_BOOLEAN:
                    return (bool) $value;
                case self::TYPE_STRING:
                    if (is_float($value)) {
                        return String_Helper::float_to_string($value);
                    }
                    return (string) $value;
            }
            if (PHP_VERSION_ID >= 80100 && is_subclass_of($type, \Backed_Enum::class)) {
                if ($value instanceof $type) {
                    return $value;
                }
                return $type::from($value);
            }
            throw new InvalidArgumentException("Unsupported type '{$type}'");
        }
        return call_user_func($type, $value);
    }
    /**
     * Composes default value for [[attributeTypes]] from the owner validation rules.
     * @return array attribute type map.
     */
    protected function detect_attribute_types(): array
    {
        $attribute_types = [];
        foreach ($this->owner->get_validators() as $validator) {
            $type = null;
            if ($validator instanceof Boolean_Validator) {
                $type = self::TYPE_BOOLEAN;
            } elseif ($validator instanceof Number_Validator) {
                $type = $validator->integer_only ? self::TYPE_INTEGER : self::TYPE_FLOAT;
            } elseif ($validator instanceof String_Validator) {
                $type = self::TYPE_STRING;
            }
            if ($type !== null) {
                $attribute_types += array_fill_keys($validator->get_attribute_names(), $type);
            }
        }
        return $attribute_types;
    }
    /**
     * {@inheritdoc}
     * @return 'afterFind'[]|'afterSave'[]|'afterValidate'[]|'beforeSave'[]
     */
    public function events(): array
    {
        $events = [];
        if ($this->typecast_after_validate) {
            $events[Model::EVENT_AFTER_VALIDATE] = 'afterValidate';
        }
        if ($this->typecast_before_save) {
            $events[Base_Active_Record::EVENT_BEFORE_INSERT] = 'beforeSave';
            $events[Base_Active_Record::EVENT_BEFORE_UPDATE] = 'beforeSave';
        }
        if ($this->typecast_after_save) {
            $events[Base_Active_Record::EVENT_AFTER_INSERT] = 'afterSave';
            $events[Base_Active_Record::EVENT_AFTER_UPDATE] = 'afterSave';
        }
        if ($this->typecast_after_find) {
            $events[Base_Active_Record::EVENT_AFTER_FIND] = 'afterFind';
        }
        return $events;
    }
    /**
     * Handles owner 'afterValidate' event, ensuring attribute typecasting.
     * @param \yii\base\Event $event event instance.
     */
    public function after_validate($event): void
    {
        if (!$this->owner->has_errors()) {
            $this->typecast_attributes();
        }
    }
    /**
     * Handles owner 'beforeInsert' and 'beforeUpdate' events, ensuring attribute typecasting.
     * @param \yii\base\Event $event event instance.
     */
    public function before_save($event): void
    {
        $this->typecast_attributes();
    }
    /**
     * Handles owner 'afterInsert' and 'afterUpdate' events, ensuring attribute typecasting.
     * @param \yii\base\Event $event event instance.
     * @since 2.0.14
     */
    public function after_save($event): void
    {
        $this->typecast_attributes();
    }
    /**
     * Handles owner 'afterFind' event, ensuring attribute typecasting.
     * @param \yii\base\Event $event event instance.
     */
    public function after_find($event): void
    {
        $this->typecast_attributes();
        $this->reset_old_attributes();
    }
    /**
     * Resets the old values of the named attributes.
     */
    protected function reset_old_attributes()
    {
        if ($this->attribute_types === null) {
            return;
        }
        $attributes = array_keys($this->attribute_types);
        foreach ($attributes as $attribute) {
            if ($this->owner->can_set_old_attribute($attribute)) {
                $this->owner->set_old_attribute($attribute, $this->owner->{$attribute});
            }
        }
    }
}