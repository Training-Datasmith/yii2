<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\behaviors;

use Yii;
use yii\base\Invalid_Call_Exception;
use yii\db\Base_Active_Record;
use yii\helpers\Array_Helper;
use yii\validators\Number_Validator;
/**
 * OptimisticLockBehavior automatically upgrades a model's lock version using the column name
 * returned by [[\yii\db\BaseActiveRecord::optimisticLock()|optimisticLock()]].
 *
 * Optimistic locking allows multiple users to access the same record for edits and avoids
 * potential conflicts. In case when a user attempts to save the record upon some staled data
 * (because another user has modified the data), a [[StaleObjectException]] exception will be thrown,
 * and the update or deletion is skipped.
 *
 * To use this behavior, first enable optimistic lock by following the steps listed in
 * [[\yii\db\BaseActiveRecord::optimisticLock()|optimisticLock()]], remove the column name
 * holding the lock version from the [[\yii\base\Model::rules()|rules()]] method of your
 * ActiveRecord class, then add the following code to it:
 *
 * ```
 * use yii\behaviors\OptimisticLockBehavior;
 *
 * public function behaviors()
 * {
 *     return [
 *         OptimisticLockBehavior::class,
 *     ];
 * }
 * ```
 *
 * By default, OptimisticLockBehavior will use [[\yii\web\Request::getBodyParam()|getBodyParam()]] to parse
 * the submitted value or set it to 0 on any fail. That means a request not holding the version attribute
 * may achieve a first successful update to entity, but starting from there any further try should fail
 * unless the request is holding the expected version number.
 *
 * Once attached, internal use of the model class should also fail to save the record if the version number
 * isn't held by [[\yii\web\Request::getBodyParam()|getBodyParam()]]. It may be useful to extend your model class,
 * enable optimistic lock in parent class by overriding [[\yii\db\BaseActiveRecord::optimisticLock()|optimisticLock()]],
 * then attach the behavior to the child class so you can tie the parent model to internal use while linking the child model
 * holding this behavior to the controllers responsible of receiving end user inputs.
 * Alternatively, you can also configure the [[value]] property with a PHP callable to implement a different logic.
 *
 * OptimisticLockBehavior also provides a method named [[upgrade()]] that increases a model's
 * version by one, that may be useful when you need to mark an entity as stale among connected clients
 * and avoid any change to it until they load it again:
 *
 * ```
 * $model->upgrade();
 * ```
 *
 * @author Salem Ouerdani <tunecino@gmail.com>
 * @since 2.0.16
 * @see \yii\db\BaseActiveRecord::optimisticLock() for details on how to enable optimistic lock.
 *
 * @template T of BaseActiveRecord = BaseActiveRecord
 * @extends AttributeBehavior<T>
 */
class Optimistic_Lock_Behavior extends Attribute_Behavior
{
    /**
     * {@inheritdoc}
     *
     * In case of `null` value it will be directly parsed from [[\yii\web\Request::getBodyParam()|getBodyParam()]] or set to 0.
     */
    public $value;
    /**
     * {@inheritdoc}
     */
    public $skip_update_on_clean = false;
    /**
     * @var string the attribute name holding the version value.
     */
    private $_lock_attribute;
    /**
     * {@inheritdoc}
     */
    public function attach($owner): void
    {
        parent::attach($owner);
        if (empty($this->attributes)) {
            $lock = $this->get_lock_attribute();
            $this->attributes = array_fill_keys(array_keys($this->events()), $lock);
        }
    }
    /**
     * {@inheritdoc}
     */
    public function events()
    {
        return Yii::$app->request instanceof \yii\web\Request ? [Base_Active_Record::EVENT_BEFORE_INSERT => 'evaluateAttributes', Base_Active_Record::EVENT_BEFORE_UPDATE => 'evaluateAttributes', Base_Active_Record::EVENT_BEFORE_DELETE => 'evaluateAttributes'] : [];
    }
    /**
     * Returns the column name to hold the version value as defined in [[\yii\db\BaseActiveRecord::optimisticLock()|optimisticLock()]].
     * @return string the property name.
     * @throws InvalidCallException if [[\yii\db\BaseActiveRecord::optimisticLock()|optimisticLock()]] is not properly configured.
     * @since 2.0.16
     */
    protected function get_lock_attribute()
    {
        if ($this->_lock_attribute) {
            return $this->_lock_attribute;
        }
        /** @var BaseActiveRecord $owner */
        $owner = $this->owner;
        $lock = $owner->optimistic_lock();
        if ($lock === null || $owner->has_attribute($lock) === false) {
            throw new Invalid_Call_Exception("Unable to get the optimistic lock attribute. Probably 'optimisticLock()' method is misconfigured.");
        }
        $this->_lock_attribute = $lock;
        return $lock;
    }
    /**
     * {@inheritdoc}
     *
     * In case of `null`, value will be parsed from [[\yii\web\Request::getBodyParam()|getBodyParam()]] or set to 0.
     */
    protected function get_value($event)
    {
        if ($this->value === null) {
            $request = Yii::$app->get_request();
            $lock = $this->get_lock_attribute();
            $form_name = $this->owner->form_name();
            $form_value = $form_name ? Array_Helper::get_value($request->get_body_params(), $form_name . '.' . $lock) : null;
            $input = $form_value ?: $request->get_body_param($lock);
            $is_valid = $input && (new Number_Validator())->validate($input);
            return $is_valid ? $input : 0;
        }
        return parent::get_value($event);
    }
    /**
     * Upgrades the version value by one and stores it to database.
     *
     * ```
     * $model->upgrade();
     * ```
     * @throws InvalidCallException if owner is a new record.
     * @since 2.0.16
     */
    public function upgrade(): void
    {
        /** @var BaseActiveRecord $owner */
        $owner = $this->owner;
        if ($owner->get_is_new_record()) {
            throw new Invalid_Call_Exception('Upgrading the model version is not possible on a new record.');
        }
        $lock = $this->get_lock_attribute();
        $version = $owner->{$lock} ?: 0;
        $owner->update_attributes([$lock => $version + 1]);
    }
}