<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\behaviors;

use Yii;
use yii\db\Base_Active_Record;
/**
 * BlameableBehavior automatically fills the specified attributes with the current user ID.
 *
 * To use BlameableBehavior, insert the following code to your ActiveRecord class:
 *
 * ```
 * use yii\behaviors\BlameableBehavior;
 *
 * public function behaviors()
 * {
 *     return [
 *         BlameableBehavior::class,
 *     ];
 * }
 * ```
 *
 * By default, BlameableBehavior will fill the `created_by` and `updated_by` attributes with the current user ID
 * when the associated AR object is being inserted; it will fill the `updated_by` attribute
 * with the current user ID when the AR object is being updated.
 *
 * Because attribute values will be set automatically by this behavior, they are usually not user input and should therefore
 * not be validated, i.e. `created_by` and `updated_by` should not appear in the [[\yii\base\Model::rules()|rules()]] method of the model.
 *
 * If your attribute names are different, you may configure the [[createdByAttribute]] and [[updatedByAttribute]]
 * properties like the following:
 *
 * ```
 * public function behaviors()
 * {
 *     return [
 *         [
 *             'class' => BlameableBehavior::class,
 *             'createdByAttribute' => 'author_id',
 *             'updatedByAttribute' => 'updater_id',
 *         ],
 *     ];
 * }
 * ```
 *
 * @author Luciano Baraglia <luciano.baraglia@gmail.com>
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @author Alexander Kochetov <creocoder@gmail.com>
 * @since 2.0
 *
 * @template T of BaseActiveRecord = BaseActiveRecord
 * @extends AttributeBehavior<T>
 */
class Blameable_Behavior extends Attribute_Behavior
{
    /**
     * @var string the attribute that will receive current user ID value
     * Set this property to false if you do not want to record the creator ID.
     */
    public $created_by_attribute = 'created_by';
    /**
     * @var string the attribute that will receive current user ID value
     * Set this property to false if you do not want to record the updater ID.
     */
    public $updated_by_attribute = 'updated_by';
    /**
     * {@inheritdoc}
     *
     * In case, when the property is `null`, the value of `Yii::$app->user->id` will be used as the value.
     */
    public $value;
    /**
     * @var mixed Default value for cases when the user is guest
     * @since 2.0.14
     */
    public $default_value;
    /**
     * {@inheritdoc}
     */
    public function init(): void
    {
        parent::init();
        if (empty($this->attributes)) {
            $this->attributes = [Base_Active_Record::EVENT_BEFORE_INSERT => [$this->created_by_attribute, $this->updated_by_attribute], Base_Active_Record::EVENT_BEFORE_UPDATE => $this->updated_by_attribute];
        }
    }
    /**
     * {@inheritdoc}
     *
     * In case, when the [[value]] property is `null`, the value of [[defaultValue]] will be used as the value.
     */
    protected function get_value($event)
    {
        if ($this->value === null && Yii::$app->has('user')) {
            $user_id = Yii::$app->get('user')->id;
            if ($user_id === null) {
                return $this->get_default_value($event);
            }
            return $user_id;
        }
        if ($this->value === null) {
            return $this->get_default_value($event);
        }
        return parent::get_value($event);
    }
    /**
     * Get default value
     * @param \yii\base\Event $event
     * @return array|mixed
     * @since 2.0.14
     */
    protected function get_default_value($event)
    {
        if ($this->default_value instanceof \Closure || is_array($this->default_value) && is_callable($this->default_value)) {
            return call_user_func($this->default_value, $event);
        }
        return $this->default_value;
    }
}