<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\base;

use yii\validators\Validator;
/**
 * DynamicModel is a model class that supports defining attributes at run-time (the so-called
 * "dynamic attributes") using its constructor or [[defineAttribute()]]. DynamicModel can be used
 * to support ad hoc data validation.
 *
 * The typical usage of DynamicModel is as follows,
 *
 * ```
 * public function actionSearch($name, $email)
 * {
 *     $model = DynamicModel::validateData(compact('name', 'email'), [
 *         [['name', 'email'], 'string', 'max' => 128],
 *         ['email', 'email'],
 *     ]);
 *     if ($model->hasErrors()) {
 *         // validation fails
 *     } else {
 *         // validation succeeds
 *     }
 * }
 * ```
 *
 * The above example shows how to validate `$name` and `$email` with the help of DynamicModel.
 * The [[validateData()]] method creates an instance of DynamicModel, defines the attributes
 * using the given data (`name` and `email` in this example), and then calls [[Model::validate()]].
 *
 * You can check the validation result using [[hasErrors()]], like you do with a normal model.
 * You may also access the dynamic attributes defined through the model instance, e.g.,
 * `$model->name` and `$model->email`.
 *
 * Alternatively, you may use the following more "classic" syntax to perform ad-hoc data validation:
 *
 * ```
 * $model = new DynamicModel(compact('name', 'email'));
 * $model->addRule(['name', 'email'], 'string', ['max' => 128])
 *     ->addRule('email', 'email')
 *     ->validate();
 * ```
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Dynamic_Model extends Model
{
    /**
     * @var mixed[] dynamic attribute values (name => value).
     */
    private $_attributes = [];
    /**
     * @var string[] dynamic attribute labels (name => label).
     * Used as form field labels and in validation error messages.
     * @since 2.0.35
     */
    private $_attribute_labels = [];
    /**
     * Constructor.
     * @param array<string, mixed>|string[] $attributes the attributes (name-value pairs, or names) being defined.
     * @param array<string, mixed> $config the configuration array to be applied to this object.
     */
    public function __construct(array $attributes = [], $config = [])
    {
        foreach ($attributes as $name => $value) {
            if (is_int($name)) {
                $this->_attributes[$value] = null;
            } else {
                $this->_attributes[$name] = $value;
            }
        }
        parent::__construct($config);
    }
    /**
     * {@inheritdoc}
     */
    public function __get($name)
    {
        if ($this->has_attribute($name)) {
            return $this->_attributes[$name];
        }
        return parent::__get($name);
    }
    /**
     * {@inheritdoc}
     */
    public function __set($name, $value)
    {
        if ($this->has_attribute($name)) {
            $this->_attributes[$name] = $value;
        } else {
            parent::__set($name, $value);
        }
    }
    /**
     * {@inheritdoc}
     */
    public function __isset($name)
    {
        if ($this->has_attribute($name)) {
            return isset($this->_attributes[$name]);
        }
        return parent::__isset($name);
    }
    /**
     * {@inheritdoc}
     */
    public function __unset($name)
    {
        if ($this->has_attribute($name)) {
            unset($this->_attributes[$name]);
        } else {
            parent::__unset($name);
        }
    }
    /**
     * {@inheritdoc}
     */
    public function can_get_property($name, $check_vars = true, $check_behaviors = true): bool
    {
        if (parent::can_get_property($name, $check_vars, $check_behaviors)) {
            return true;
        }
        return $this->has_attribute($name);
    }
    /**
     * {@inheritdoc}
     */
    public function can_set_property($name, $check_vars = true, $check_behaviors = true): bool
    {
        if (parent::can_set_property($name, $check_vars, $check_behaviors)) {
            return true;
        }
        return $this->has_attribute($name);
    }
    /**
     * Returns a value indicating whether the model has an attribute with the specified name.
     * @param string $name the name of the attribute.
     * @return bool whether the model has an attribute with the specified name.
     * @since 2.0.16
     */
    public function has_attribute($name): bool
    {
        return array_key_exists($name, $this->_attributes);
    }
    /**
     * Defines an attribute.
     * @param string $name the attribute name.
     * @param mixed $value the attribute value.
     */
    public function define_attribute($name, $value = null): void
    {
        $this->_attributes[$name] = $value;
    }
    /**
     * Undefines an attribute.
     * @param string $name the attribute name.
     */
    public function undefine_attribute($name): void
    {
        unset($this->_attributes[$name]);
    }
    /**
     * Adds a validation rule to this model.
     * You can also directly manipulate [[validators]] to add or remove validation rules.
     * This method provides a shortcut.
     * @param string|array $attributes the attribute(s) to be validated by the rule.
     * @param string|Validator|\Closure $validator the validator. This can be either:
     *  * a built-in validator name listed in [[builtInValidators]];
     *  * a method name of the model class;
     *  * an anonymous function;
     *  * a validator class name.
     *  * a Validator.
     * @param array $options the options (name-value pairs) to be applied to the validator.
     * @return $this
     */
    public function add_rule($attributes, $validator, $options = []): self
    {
        $validators = $this->get_validators();
        if ($validator instanceof Validator) {
            $validator->attributes = (array) $attributes;
        } else {
            $validator = Validator::create_validator($validator, $this, (array) $attributes, $options);
        }
        $validators->append($validator);
        $this->define_attributes_by_validator($validator);
        return $this;
    }
    /**
     * Validates the given data with the specified validation rules.
     * This method will create a DynamicModel instance, populate it with the data to be validated,
     * create the specified validation rules, and then validate the data using these rules.
     * @param array $data the data (name-value pairs) to be validated.
     * @param array $rules the validation rules. Please refer to [[Model::rules()]] on the format of this parameter.
     * @return static the model instance that contains the data being validated.
     * @throws InvalidConfigException if a validation rule is not specified correctly.
     */
    public static function validate_data(array $data, $rules = [])
    {
        $model = new static($data);
        if (!empty($rules)) {
            $validators = $model->get_validators();
            foreach ($rules as $rule) {
                if ($rule instanceof Validator) {
                    $validators->append($rule);
                    $model->define_attributes_by_validator($rule);
                } elseif (is_array($rule) && isset($rule[0], $rule[1])) {
                    // attributes, validator type
                    $validator = Validator::create_validator($rule[1], $model, (array) $rule[0], array_slice($rule, 2));
                    $validators->append($validator);
                    $model->define_attributes_by_validator($validator);
                } else {
                    throw new Invalid_Config_Exception('Invalid validation rule: a rule must specify both attribute names and validator type.');
                }
            }
        }
        $model->validate();
        return $model;
    }
    /**
     * Define the attributes that applies to the specified Validator.
     * @param Validator $validator the validator whose attributes are to be defined.
     */
    private function define_attributes_by_validator($validator): void
    {
        foreach ($validator->get_attribute_names() as $attribute) {
            if (!$this->has_attribute($attribute)) {
                $this->define_attribute($attribute);
            }
        }
    }
    /**
     * {@inheritdoc}
     */
    public function attributes(): array
    {
        return array_keys($this->_attributes);
    }
    /**
     * Sets the labels for all attributes.
     * @param string[] $labels attribute labels.
     * @return $this
     * @since 2.0.35
     */
    public function set_attribute_labels(array $labels = []): self
    {
        $this->_attribute_labels = $labels;
        return $this;
    }
    /**
     * Sets a label for a single attribute.
     * @param string $attribute attribute name.
     * @param string $label attribute label value.
     * @return $this
     * @since 2.0.35
     */
    public function set_attribute_label($attribute, $label): self
    {
        $this->_attribute_labels[$attribute] = $label;
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function attribute_labels()
    {
        return $this->_attribute_labels;
    }
}