<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\behaviors;

use Yii;
use yii\base\Invalid_Config_Exception;
use yii\db\Base_Active_Record;
use yii\helpers\Array_Helper;
use yii\helpers\Inflector;
use yii\validators\Unique_Validator;
/**
 * SluggableBehavior automatically fills the specified attribute with a value that can be used a slug in a URL.
 *
 * Note: This behavior relies on php-intl extension for transliteration. If it is not installed it
 * falls back to replacements defined in [[\yii\helpers\Inflector::$transliteration]].
 *
 * To use SluggableBehavior, insert the following code to your ActiveRecord class:
 *
 * ```
 * use yii\behaviors\SluggableBehavior;
 *
 * public function behaviors()
 * {
 *     return [
 *         [
 *             'class' => SluggableBehavior::class,
 *             'attribute' => 'title',
 *             // 'slugAttribute' => 'slug',
 *         ],
 *     ];
 * }
 * ```
 *
 * By default, SluggableBehavior will fill the `slug` attribute with a value that can be used a slug in a URL
 * when the associated AR object is being validated.
 *
 * Because attribute values will be set automatically by this behavior, they are usually not user input and should therefore
 * not be validated, i.e. the `slug` attribute should not appear in the [[\yii\base\Model::rules()|rules()]] method of the model.
 *
 * If your attribute name is different, you may configure the [[slugAttribute]] property like the following:
 *
 * ```
 * public function behaviors()
 * {
 *     return [
 *         [
 *             'class' => SluggableBehavior::class,
 *             'slugAttribute' => 'alias',
 *         ],
 *     ];
 * }
 * ```
 *
 * @author Alexander Kochetov <creocoder@gmail.com>
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 2.0
 *
 * @template T of BaseActiveRecord = BaseActiveRecord
 * @extends AttributeBehavior<T>
 */
class Sluggable_Behavior extends Attribute_Behavior
{
    /**
     * @var string the attribute that will receive the slug value
     */
    public $slug_attribute = 'slug';
    /**
     * @var string|array|null the attribute or list of attributes whose value will be converted into a slug
     * or `null` meaning that the `$value` property will be used to generate a slug.
     */
    public $attribute;
    /**
     * @var callable|string|null the value that will be used as a slug. This can be an anonymous function
     * or an arbitrary value or null. If the former, the return value of the function will be used as a slug.
     * If `null` then the `$attribute` property will be used to generate a slug.
     * The signature of the function should be as follows,
     *
     * ```
     * function ($event)
     * {
     *     // return slug
     * }
     * ```
     */
    public $value;
    /**
     * @var bool whether to generate a new slug if it has already been generated before.
     * If true, the behavior will not generate a new slug even if [[attribute]] is changed.
     * @since 2.0.2
     */
    public $immutable = false;
    /**
     * @var bool whether to ensure generated slug value to be unique among owner class records.
     * If enabled behavior will validate slug uniqueness automatically. If validation fails it will attempt
     * generating unique slug value from based one until success.
     */
    public $ensure_unique = false;
    /**
     * @var bool whether to skip slug generation if [[attribute]] is null or an empty string.
     * If true, the behaviour will not generate a new slug if [[attribute]] is null or an empty string.
     * @since 2.0.13
     */
    public $skip_on_empty = false;
    /**
     * @var array configuration for slug uniqueness validator. Parameter 'class' may be omitted - by default
     * [[UniqueValidator]] will be used.
     * @see UniqueValidator
     */
    public $unique_validator = [];
    /**
     * @var callable|null slug unique value generator. It is used in case [[ensureUnique]] enabled and generated
     * slug is not unique. This should be a PHP callable with following signature:
     *
     * ```
     * function ($baseSlug, $iteration, $model)
     * {
     *     // return uniqueSlug
     * }
     * ```
     *
     * If not set unique slug will be generated adding incrementing suffix to the base slug.
     */
    public $unique_slug_generator;
    /**
     * {@inheritdoc}
     */
    public function init(): void
    {
        parent::init();
        if (empty($this->attributes)) {
            $this->attributes = [Base_Active_Record::EVENT_BEFORE_VALIDATE => $this->slug_attribute];
        }
        if ($this->attribute === null && $this->value === null) {
            throw new Invalid_Config_Exception('Either "attribute" or "value" property must be specified.');
        }
    }
    /**
     * {@inheritdoc}
     */
    protected function get_value($event)
    {
        if (!$this->is_new_slug_needed()) {
            return $this->owner->{$this->slug_attribute};
        }
        if ($this->attribute !== null) {
            $slug_parts = [];
            foreach ((array) $this->attribute as $attribute) {
                $part = Array_Helper::get_value($this->owner, $attribute);
                if ($this->skip_on_empty && $this->is_empty($part)) {
                    return $this->owner->{$this->slug_attribute};
                }
                $slug_parts[] = $part;
            }
            $slug = $this->generate_slug($slug_parts);
        } else {
            $slug = parent::get_value($event);
        }
        return $this->ensure_unique ? $this->make_unique($slug) : $slug;
    }
    /**
     * Checks whether the new slug generation is needed
     * This method is called by [[getValue]] to check whether the new slug generation is needed.
     * You may override it to customize checking.
     * @since 2.0.7
     */
    protected function is_new_slug_needed(): bool
    {
        if (empty($this->owner->{$this->slug_attribute})) {
            return true;
        }
        if ($this->immutable) {
            return false;
        }
        if ($this->attribute === null) {
            return true;
        }
        foreach ((array) $this->attribute as $attribute) {
            if ($this->owner->is_attribute_changed($attribute)) {
                return true;
            }
        }
        return false;
    }
    /**
     * This method is called by [[getValue]] to generate the slug.
     * You may override it to customize slug generation.
     * The default implementation calls [[\yii\helpers\Inflector::slug()]] on the input strings
     * concatenated by dashes (`-`).
     * @param array $slugParts an array of strings that should be concatenated and converted to generate the slug value.
     * @return string the conversion result.
     */
    protected function generate_slug($slug_parts)
    {
        return Inflector::slug(implode('-', $slug_parts));
    }
    /**
     * This method is called by [[getValue]] when [[ensureUnique]] is true to generate the unique slug.
     * Calls [[generateUniqueSlug]] until generated slug is unique and returns it.
     * @param string $slug basic slug value
     * @return string unique slug
     * @see getValue
     * @see generateUniqueSlug
     * @since 2.0.7
     */
    protected function make_unique($slug)
    {
        $unique_slug = $slug;
        $iteration = 0;
        while (!$this->validate_slug($unique_slug)) {
            $iteration++;
            $unique_slug = $this->generate_unique_slug($slug, $iteration);
        }
        return $unique_slug;
    }
    /**
     * Checks if given slug value is unique.
     * @param string $slug slug value
     * @return bool whether slug is unique.
     */
    protected function validate_slug($slug): bool
    {
        /** @var UniqueValidator $validator */
        $validator = Yii::create_object(array_merge(['class' => Unique_Validator::class_name()], $this->unique_validator));
        /** @var BaseActiveRecord $model */
        $model = clone $this->owner;
        $model->clear_errors();
        $model->{$this->slug_attribute} = $slug;
        $validator->validate_attribute($model, $this->slug_attribute);
        return !$model->has_errors();
    }
    /**
     * Generates slug using configured callback or increment of iteration.
     * @param string $baseSlug base slug value
     * @param int $iteration iteration number
     * @return string new slug value
     * @throws \yii\base\InvalidConfigException
     */
    protected function generate_unique_slug(string $base_slug, $iteration)
    {
        if (is_callable($this->unique_slug_generator)) {
            return call_user_func($this->unique_slug_generator, $base_slug, $iteration, $this->owner);
        }
        return $base_slug . '-' . ($iteration + 1);
    }
    /**
     * Checks if $slugPart is empty string or null.
     *
     * @param string $slugPart One of attributes that is used for slug generation.
     * @return bool whether $slugPart empty or not.
     * @since 2.0.13
     */
    protected function is_empty($slug_part): bool
    {
        return $slug_part === null || $slug_part === '';
    }
}