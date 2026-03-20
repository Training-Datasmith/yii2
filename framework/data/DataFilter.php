<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\data;

use Yii;
use yii\base\Invalid_Config_Exception;
use yii\base\Model;
use yii\helpers\Array_Helper;
use yii\validators\Boolean_Validator;
use yii\validators\Date_Validator;
use yii\validators\Each_Validator;
use yii\validators\Number_Validator;
use yii\validators\String_Validator;
use yii\validators\Validator;
/**
 * DataFilter is a special [[Model]] for processing query filtering specification.
 * It allows validating and building a filter condition passed via request.
 *
 * Filter example:
 *
 * ```
 * {
 *     "or": [
 *         {
 *             "and": [
 *                 {
 *                     "name": "some name",
 *                 },
 *                 {
 *                     "price": "25",
 *                 }
 *             ]
 *         },
 *         {
 *             "id": {"in": [2, 5, 9]},
 *             "price": {
 *                 "gt": 10,
 *                 "lt": 50
 *             }
 *         }
 *     ]
 * }
 * ```
 *
 * In the request the filter should be specified using a key name equal to [[filterAttributeName]]. Thus actual HTTP request body
 * will look like following:
 *
 * ```
 * {
 *     "filter": {"or": {...}},
 *     "page": 2,
 *     ...
 * }
 * ```
 *
 * Raw filter value should be assigned to [[filter]] property of the model.
 * You may populate it from request data via [[load()]] method:
 *
 * ```
 * use yii\data\DataFilter;
 *
 * $dataFilter = new DataFilter();
 * $dataFilter->load(Yii::$app->request->getBodyParams());
 * ```
 *
 * In order to function this class requires a search model specified via [[searchModel]]. This search model should declare
 * all available search attributes and their validation rules. For example:
 *
 * ```
 * class SearchModel extends \yii\base\Model
 * {
 *     public $id;
 *     public $name;
 *
 *     public function rules()
 *     {
 *         return [
 *             [['id', 'name'], 'trim'],
 *             ['id', 'integer'],
 *             ['name', 'string'],
 *         ];
 *     }
 * }
 * ```
 *
 * In order to reduce amount of classes, you may use [[\yii\base\DynamicModel]] instance as a [[searchModel]].
 * In this case you should specify [[searchModel]] using a PHP callable:
 *
 * ```
 * function () {
 *     return (new \yii\base\DynamicModel(['id' => null, 'name' => null]))
 *         ->addRule(['id', 'name'], 'trim')
 *         ->addRule('id', 'integer')
 *         ->addRule('name', 'string');
 * }
 * ```
 *
 * You can use [[validate()]] method to check if filter value is valid. If validation fails you can use
 * [[getErrors()]] to get actual error messages.
 *
 * In order to acquire filter condition suitable for fetching data use [[build()]] method.
 *
 * > Note: This is a base class. Its implementation of [[build()]] simply returns normalized [[filter]] value.
 * In order to convert filter to particular format you should use descendant of this class that implements
 * [[buildInternal()]] method accordingly.
 *
 * @see ActiveDataFilter
 *
 * @property array $errorMessages Error messages in format `[errorKey => message]`. Note that the type of this
 * property differs in getter and setter. See [[getErrorMessages()]] and [[setErrorMessages()]] for details.
 * @property mixed $filter Raw filter value.
 * @property array $searchAttributeTypes Search attribute type map. Note that the type of this property
 * differs in getter and setter. See [[getSearchAttributeTypes()]] and [[setSearchAttributeTypes()]] for details.
 * @property Model $searchModel Model instance. Note that the type of this property differs in getter and
 * setter. See [[getSearchModel()]] and [[setSearchModel()]] for details.
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 2.0.13
 */
class Data_Filter extends Model
{
    public const TYPE_INTEGER = 'integer';
    public const TYPE_FLOAT = 'float';
    public const TYPE_BOOLEAN = 'boolean';
    public const TYPE_STRING = 'string';
    public const TYPE_ARRAY = 'array';
    public const TYPE_DATETIME = 'datetime';
    public const TYPE_DATE = 'date';
    public const TYPE_TIME = 'time';
    /**
     * @var string name of the attribute that handles filter value.
     * The name is used to load data via [[load()]] method.
     */
    public $filter_attribute_name = 'filter';
    /**
     * @var string label for the filter attribute specified via [[filterAttributeName]].
     * It will be used during error messages composition.
     */
    public $filter_attribute_label;
    /**
     * @var array keywords or expressions that could be used in a filter.
     * Array keys are the expressions used in raw filter value obtained from user request.
     * Array values are internal build keys used in this class methods.
     *
     * Any unspecified keyword will not be recognized as a filter control and will be treated as
     * an attribute name. Thus you should avoid conflicts between control keywords and attribute names.
     * For example: in case you have control keyword 'like' and an attribute named 'like', specifying condition
     * for such attribute will be impossible.
     *
     * You may specify several keywords for the same filter build key, creating multiple aliases. For example:
     *
     * ```
     * [
     *     'eq' => '=',
     *     '=' => '=',
     *     '==' => '=',
     *     '===' => '=',
     *     // ...
     * ]
     * ```
     *
     * > Note: while specifying filter controls take actual data exchange format, which your API uses, in mind.
     * > Make sure each specified control keyword is valid for the format. For example, in XML tag name can start
     * > only with a letter character, thus controls like `>`, '=' or `$gt` will break the XML schema.
     */
    public $filter_controls = ['and' => 'AND', 'or' => 'OR', 'not' => 'NOT', 'lt' => '<', 'gt' => '>', 'lte' => '<=', 'gte' => '>=', 'eq' => '=', 'neq' => '!=', 'in' => 'IN', 'nin' => 'NOT IN', 'like' => 'LIKE'];
    /**
     * @var array maps filter condition keywords to validation methods.
     * These methods are used by [[validateCondition()]] to validate raw filter conditions.
     */
    public $condition_validators = ['AND' => 'validateConjunctionCondition', 'OR' => 'validateConjunctionCondition', 'NOT' => 'validateBlockCondition', '<' => 'validateOperatorCondition', '>' => 'validateOperatorCondition', '<=' => 'validateOperatorCondition', '>=' => 'validateOperatorCondition', '=' => 'validateOperatorCondition', '!=' => 'validateOperatorCondition', 'IN' => 'validateOperatorCondition', 'NOT IN' => 'validateOperatorCondition', 'LIKE' => 'validateOperatorCondition'];
    /**
     * @var array specifies the list of supported search attribute types per each operator.
     * This field should be in format: 'operatorKeyword' => ['type1', 'type2' ...].
     * Supported types list can be specified as `*`, which indicates that operator supports all types available.
     * Any unspecified keyword will not be considered as a valid operator.
     */
    public $operator_types = ['<' => [self::TYPE_INTEGER, self::TYPE_FLOAT, self::TYPE_DATETIME, self::TYPE_DATE, self::TYPE_TIME], '>' => [self::TYPE_INTEGER, self::TYPE_FLOAT, self::TYPE_DATETIME, self::TYPE_DATE, self::TYPE_TIME], '<=' => [self::TYPE_INTEGER, self::TYPE_FLOAT, self::TYPE_DATETIME, self::TYPE_DATE, self::TYPE_TIME], '>=' => [self::TYPE_INTEGER, self::TYPE_FLOAT, self::TYPE_DATETIME, self::TYPE_DATE, self::TYPE_TIME], '=' => '*', '!=' => '*', 'IN' => '*', 'NOT IN' => '*', 'LIKE' => [self::TYPE_STRING]];
    /**
     * @var array list of operators keywords, which should accept multiple values.
     */
    public $multi_value_operators = ['IN', 'NOT IN'];
    /**
     * @var array actual attribute names to be used in searched condition, in format: [filterAttribute => actualAttribute].
     * For example, in case of using table joins in the search query, attribute map may look like the following:
     *
     * ```
     * [
     *     'authorName' => '{{author}}.[[name]]'
     * ]
     * ```
     *
     * Attribute map will be applied to filter condition in [[normalize()]] method.
     */
    public $attribute_map = [];
    /**
     * @var string representation of `null` instead of literal `null` in case the latter cannot be used.
     * @since 2.0.40
     */
    public $null_value = 'NULL';
    /**
     * @var array|\Closure list of error messages responding to invalid filter structure, in format: `[errorKey => message]`.
     */
    private $_error_messages;
    /**
     * @var mixed raw filter specification.
     */
    private $_filter;
    /**
     * @var Model|array|string|callable model to be used for filter attributes validation.
     */
    private $_search_model;
    /**
     * @var array list of search attribute types in format: attributeName => type
     */
    private $_search_attribute_types;
    /**
     * @return mixed raw filter value.
     */
    public function get_filter()
    {
        return $this->_filter;
    }
    /**
     * @param mixed $filter raw filter value.
     */
    public function set_filter($filter): void
    {
        $this->_filter = $filter;
    }
    /**
     * @return Model model instance.
     * @throws InvalidConfigException on invalid configuration.
     */
    public function get_search_model()
    {
        if (!is_object($this->_search_model) || $this->_search_model instanceof \Closure) {
            $model = Yii::create_object($this->_search_model);
            if (!$model instanceof Model) {
                throw new Invalid_Config_Exception('`' . get_class($this) . '::$searchModel` should be an instance of `' . Model::class_name() . '` or its DI compatible configuration.');
            }
            $this->_search_model = $model;
        }
        return $this->_search_model;
    }
    /**
     * @param Model|array|string|callable $model model instance or its DI compatible configuration.
     * @throws InvalidConfigException on invalid configuration.
     */
    public function set_search_model($model): void
    {
        if (is_object($model) && !$model instanceof Model && !$model instanceof \Closure) {
            throw new Invalid_Config_Exception('`' . get_class($this) . '::$searchModel` should be an instance of `' . Model::class_name() . '` or its DI compatible configuration.');
        }
        $this->_search_model = $model;
    }
    /**
     * @return array search attribute type map.
     */
    public function get_search_attribute_types()
    {
        if ($this->_search_attribute_types === null) {
            $this->_search_attribute_types = $this->detect_search_attribute_types();
        }
        return $this->_search_attribute_types;
    }
    /**
     * @param array|null $searchAttributeTypes search attribute type map.
     */
    public function set_search_attribute_types($search_attribute_types): void
    {
        $this->_search_attribute_types = $search_attribute_types;
    }
    /**
     * Composes default value for [[searchAttributeTypes]] from the [[searchModel]] validation rules.
     * @return array attribute type map.
     */
    protected function detect_search_attribute_types(): array
    {
        $model = $this->get_search_model();
        $attribute_types = [];
        foreach ($model->active_attributes() as $attribute) {
            $attribute_types[$attribute] = self::TYPE_STRING;
        }
        foreach ($model->get_validators() as $validator) {
            $type = $this->detect_search_attribute_type($validator);
            if ($type !== null) {
                foreach ((array) $validator->attributes as $attribute) {
                    $attribute_types[$attribute] = $type;
                }
            }
        }
        return $attribute_types;
    }
    /**
     * Detect attribute type from given validator.
     *
     * @param Validator $validator validator from which to detect attribute type.
     * @return string|null detected attribute type.
     * @since 2.0.14
     */
    protected function detect_search_attribute_type(Validator $validator): ?string
    {
        if ($validator instanceof Boolean_Validator) {
            return self::TYPE_BOOLEAN;
        }
        if ($validator instanceof Number_Validator) {
            return $validator->integer_only ? self::TYPE_INTEGER : self::TYPE_FLOAT;
        }
        if ($validator instanceof String_Validator) {
            return self::TYPE_STRING;
        }
        if ($validator instanceof Each_Validator) {
            return self::TYPE_ARRAY;
        }
        if ($validator instanceof Date_Validator) {
            if ($validator->type == Date_Validator::TYPE_DATETIME) {
                return self::TYPE_DATETIME;
            }
            if ($validator->type == Date_Validator::TYPE_TIME) {
                return self::TYPE_TIME;
            }
            return self::TYPE_DATE;
        }
        return null;
    }
    /**
     * @return array error messages in format `[errorKey => message]`.
     */
    public function get_error_messages()
    {
        if (!is_array($this->_error_messages)) {
            if ($this->_error_messages === null) {
                $this->_error_messages = $this->default_error_messages();
            } else {
                $this->_error_messages = array_merge($this->default_error_messages(), call_user_func($this->_error_messages));
            }
        }
        return $this->_error_messages;
    }
    /**
     * Sets the list of error messages responding to invalid filter structure, in format: `[errorKey => message]`.
     * Message may contain placeholders that will be populated depending on the message context.
     * For each message a `{filter}` placeholder is available referring to the label for [[filterAttributeName]] attribute.
     * @param array|\Closure $errorMessages error messages in `[errorKey => message]` format, or a PHP callback returning them.
     */
    public function set_error_messages($error_messages): void
    {
        if (is_array($error_messages)) {
            $error_messages = array_merge($this->default_error_messages(), $error_messages);
        }
        $this->_error_messages = $error_messages;
    }
    /**
     * Returns default values for [[errorMessages]].
     * @return array default error messages in `[errorKey => message]` format.
     */
    protected function default_error_messages(): array
    {
        return ['invalidFilter' => Yii::t('yii', 'The format of {filter} is invalid.'), 'operatorRequireMultipleOperands' => Yii::t('yii', 'Operator "{operator}" requires multiple operands.'), 'unknownAttribute' => Yii::t('yii', 'Unknown filter attribute "{attribute}"'), 'invalidAttributeValueFormat' => Yii::t('yii', 'Condition for "{attribute}" should be either a value or valid operator specification.'), 'operatorRequireAttribute' => Yii::t('yii', 'Operator "{operator}" must be used with a search attribute.'), 'unsupportedOperatorType' => Yii::t('yii', '"{attribute}" does not support operator "{operator}".')];
    }
    /**
     * Parses content of the message from [[errorMessages]], specified by message key.
     * @param string $messageKey message key.
     * @param array $params params to be parsed into the message.
     * @return string composed message string.
     */
    protected function parse_error_message($message_key, $params = [])
    {
        $messages = $this->get_error_messages();
        if (isset($messages[$message_key])) {
            $message = $messages[$message_key];
        } else {
            $message = Yii::t('yii', 'The format of {filter} is invalid.');
        }
        $params = array_merge(['filter' => $this->get_attribute_label($this->filter_attribute_name)], $params);
        return Yii::$app->get_i18n()->format($message, $params, Yii::$app->language);
    }
    // Model specific:
    /**
     * {@inheritdoc}
     */
    public function attributes(): array
    {
        return [$this->filter_attribute_name];
    }
    /**
     * {@inheritdoc}
     */
    public function form_name(): string
    {
        return '';
    }
    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        return [[$this->filter_attribute_name, 'validateFilter', 'skipOnEmpty' => false]];
    }
    /**
     * {@inheritdoc}
     */
    public function attribute_labels(): array
    {
        return [$this->filter_attribute_name => $this->filter_attribute_label];
    }
    // Validation:
    /**
     * Validates filter attribute value to match filer condition specification.
     */
    public function validate_filter(): void
    {
        $value = $this->get_filter();
        if ($value !== null) {
            $this->validate_condition($value);
        }
    }
    /**
     * Validates filter condition.
     * @param mixed $condition raw filter condition.
     */
    protected function validate_condition($condition)
    {
        if (!is_array($condition)) {
            $this->add_error($this->filter_attribute_name, $this->parse_error_message('invalidFilter'));
            return;
        }
        foreach ($condition as $key => $value) {
            $method = 'validateAttributeCondition';
            if (isset($this->filter_controls[$key])) {
                $control_key = $this->filter_controls[$key];
                if (isset($this->condition_validators[$control_key])) {
                    $method = $this->condition_validators[$control_key];
                }
            }
            $this->{$method}($key, $value);
        }
    }
    /**
     * Validates conjunction condition that consists of multiple independent ones.
     * This covers such operators as `and` and `or`.
     * @param string $operator raw operator control keyword.
     * @param mixed $condition raw condition.
     */
    protected function validate_conjunction_condition($operator, $condition)
    {
        if (!is_array($condition) || !Array_Helper::is_indexed($condition)) {
            $this->add_error($this->filter_attribute_name, $this->parse_error_message('operatorRequireMultipleOperands', ['operator' => $operator]));
            return;
        }
        foreach ($condition as $part) {
            $this->validate_condition($part);
        }
    }
    /**
     * Validates block condition that consists of a single condition.
     * This covers such operators as `not`.
     * @param string $operator raw operator control keyword.
     * @param mixed $condition raw condition.
     */
    protected function validate_block_condition($operator, $condition)
    {
        $this->validate_condition($condition);
    }
    /**
     * Validates search condition for a particular attribute.
     * @param string $attribute search attribute name.
     * @param mixed $condition search condition.
     */
    protected function validate_attribute_condition($attribute, $condition)
    {
        $attribute_types = $this->get_search_attribute_types();
        if (!isset($attribute_types[$attribute])) {
            $this->add_error($this->filter_attribute_name, $this->parse_error_message('unknownAttribute', ['attribute' => $attribute]));
            return;
        }
        if (is_array($condition)) {
            $operator_count = 0;
            foreach ($condition as $raw_operator => $value) {
                if (isset($this->filter_controls[$raw_operator])) {
                    $operator = $this->filter_controls[$raw_operator];
                    if (isset($this->operator_types[$operator])) {
                        $operator_count++;
                        $this->validate_operator_condition($raw_operator, $value, $attribute);
                    }
                }
            }
            if ($operator_count > 0) {
                if ($operator_count < count($condition)) {
                    $this->add_error($this->filter_attribute_name, $this->parse_error_message('invalidAttributeValueFormat', ['attribute' => $attribute]));
                }
            } else {
                // attribute may allow array value:
                $this->validate_attribute_value($attribute, $condition);
            }
        } else {
            $this->validate_attribute_value($attribute, $condition);
        }
    }
    /**
     * Validates operator condition.
     * @param string $operator raw operator control keyword.
     * @param mixed $condition attribute condition.
     * @param string|null $attribute attribute name.
     */
    protected function validate_operator_condition($operator, $condition, $attribute = null)
    {
        if ($attribute === null) {
            // absence of an attribute indicates that operator has been placed in a wrong position
            $this->add_error($this->filter_attribute_name, $this->parse_error_message('operatorRequireAttribute', ['operator' => $operator]));
            return;
        }
        $internal_operator = $this->filter_controls[$operator];
        // check operator type :
        $operator_types = $this->operator_types[$internal_operator];
        if ($operator_types !== '*') {
            $attribute_types = $this->get_search_attribute_types();
            $attribute_type = $attribute_types[$attribute];
            if (!in_array($attribute_type, $operator_types, true)) {
                $this->add_error($this->filter_attribute_name, $this->parse_error_message('unsupportedOperatorType', ['attribute' => $attribute, 'operator' => $operator]));
                return;
            }
        }
        if (in_array($internal_operator, $this->multi_value_operators, true)) {
            // multi-value operator:
            if (!is_array($condition)) {
                $this->add_error($this->filter_attribute_name, $this->parse_error_message('operatorRequireMultipleOperands', ['operator' => $operator]));
            } else {
                foreach ($condition as $v) {
                    $this->validate_attribute_value($attribute, $v);
                }
            }
        } else {
            // single-value operator :
            $this->validate_attribute_value($attribute, $condition);
        }
    }
    /**
     * Validates attribute value in the scope of [[model]].
     * @param string $attribute attribute name.
     * @param mixed $value attribute value.
     */
    protected function validate_attribute_value($attribute, $value)
    {
        $model = $this->get_search_model();
        if (!$model->is_attribute_safe($attribute)) {
            $this->add_error($this->filter_attribute_name, $this->parse_error_message('unknownAttribute', ['attribute' => $attribute]));
            return;
        }
        $model->{$attribute} = $value === $this->null_value ? null : $value;
        if (!$model->validate([$attribute])) {
            $this->add_error($this->filter_attribute_name, $model->get_first_error($attribute));
            return;
        }
    }
    /**
     * Validates attribute value in the scope of [[searchModel]], applying attribute value filters if any.
     * @param string $attribute attribute name.
     * @param mixed $value attribute value.
     * @return mixed filtered attribute value.
     */
    protected function filter_attribute_value($attribute, $value)
    {
        $model = $this->get_search_model();
        if (!$model->is_attribute_safe($attribute)) {
            $this->add_error($this->filter_attribute_name, $this->parse_error_message('unknownAttribute', ['attribute' => $attribute]));
            return $value;
        }
        $model->{$attribute} = $value;
        if (!$model->validate([$attribute])) {
            $this->add_error($this->filter_attribute_name, $model->get_first_error($attribute));
            return $value;
        }
        return $model->{$attribute};
    }
    // Build:
    /**
     * Builds actual filter specification form [[filter]] value.
     * @param bool $runValidation whether to perform validation (calling [[validate()]])
     * before building the filter. Defaults to `true`. If the validation fails, no filter will
     * be built and this method will return `false`.
     * @return mixed|false built actual filter value, or `false` if validation fails.
     */
    public function build($run_validation = true)
    {
        if ($run_validation && !$this->validate()) {
            return false;
        }
        return $this->build_internal();
    }
    /**
     * Performs actual filter build.
     * By default this method returns result of [[normalize()]].
     * The child class may override this method providing more specific implementation.
     * @return mixed built actual filter value.
     */
    protected function build_internal()
    {
        return $this->normalize(false);
    }
    /**
     * Normalizes filter value, replacing raw keys according to [[filterControls]] and [[attributeMap]].
     * @param bool $runValidation whether to perform validation (calling [[validate()]])
     * before normalizing the filter. Defaults to `true`. If the validation fails, no filter will
     * be processed and this method will return `false`.
     * @return array|bool normalized filter value, or `false` if validation fails.
     */
    public function normalize($run_validation = true)
    {
        if ($run_validation && !$this->validate()) {
            return false;
        }
        $filter = $this->get_filter();
        if (!is_array($filter) || empty($filter)) {
            return [];
        }
        return $this->normalize_complex_filter($filter);
    }
    /**
     * Normalizes complex filter recursively.
     * @param array $filter raw filter.
     * @return array normalized filter.
     */
    private function normalize_complex_filter(array $filter): array
    {
        $result = [];
        foreach ($filter as $key => $value) {
            if (isset($this->filter_controls[$key])) {
                $key = $this->filter_controls[$key];
            } elseif (isset($this->attribute_map[$key])) {
                $key = $this->attribute_map[$key];
            }
            if (is_array($value)) {
                $result[$key] = $this->normalize_complex_filter($value);
            } elseif ($value === $this->null_value) {
                $result[$key] = null;
            } else {
                $result[$key] = $value;
            }
        }
        return $result;
    }
    // Property access:
    /**
     * {@inheritdoc}
     */
    public function can_get_property($name, $check_vars = true, $check_behaviors = true)
    {
        if ($name === $this->filter_attribute_name) {
            return true;
        }
        return parent::can_get_property($name, $check_vars, $check_behaviors);
    }
    /**
     * {@inheritdoc}
     */
    public function can_set_property($name, $check_vars = true, $check_behaviors = true)
    {
        if ($name === $this->filter_attribute_name) {
            return true;
        }
        return parent::can_set_property($name, $check_vars, $check_behaviors);
    }
    /**
     * {@inheritdoc}
     */
    public function __get($name)
    {
        if ($name === $this->filter_attribute_name) {
            return $this->get_filter();
        }
        return parent::__get($name);
    }
    /**
     * {@inheritdoc}
     */
    public function __set($name, $value)
    {
        if ($name === $this->filter_attribute_name) {
            $this->set_filter($value);
        } else {
            parent::__set($name, $value);
        }
    }
    /**
     * {@inheritdoc}
     */
    public function __isset($name)
    {
        if ($name === $this->filter_attribute_name) {
            return $this->get_filter() !== null;
        }
        return parent::__isset($name);
    }
    /**
     * {@inheritdoc}
     */
    public function __unset($name)
    {
        if ($name === $this->filter_attribute_name) {
            $this->set_filter(null);
        } else {
            parent::__unset($name);
        }
    }
}