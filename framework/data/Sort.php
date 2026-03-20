<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\data;

use Yii;
use yii\base\Base_Object;
use yii\base\Invalid_Config_Exception;
use yii\helpers\Html;
use yii\helpers\Inflector;
use yii\web\Request;
/**
 * Sort represents information relevant to sorting.
 *
 * When data needs to be sorted according to one or several attributes,
 * we can use Sort to represent the sorting information and generate
 * appropriate hyperlinks that can lead to sort actions.
 *
 * A typical usage example is as follows,
 *
 * ```
 * public function actionIndex()
 * {
 *     $sort = new Sort([
 *         'attributes' => [
 *             'age',
 *             'name' => [
 *                 'asc' => ['first_name' => SORT_ASC, 'last_name' => SORT_ASC],
 *                 'desc' => ['first_name' => SORT_DESC, 'last_name' => SORT_DESC],
 *                 'default' => SORT_DESC,
 *                 'label' => 'Name',
 *             ],
 *         ],
 *     ]);
 *
 *     $models = Article::find()
 *         ->where(['status' => 1])
 *         ->orderBy($sort->orders)
 *         ->all();
 *
 *     return $this->render('index', [
 *          'models' => $models,
 *          'sort' => $sort,
 *     ]);
 * }
 * ```
 *
 * View:
 *
 * ```
 * // display links leading to sort actions
 * echo $sort->link('name') . ' | ' . $sort->link('age');
 *
 * foreach ($models as $model) {
 *     // display $model here
 * }
 * ```
 *
 * In the above, we declare two [[attributes]] that support sorting: `name` and `age`.
 * We pass the sort information to the Article query so that the query results are
 * sorted by the orders specified by the Sort object. In the view, we show two hyperlinks
 * that can lead to pages with the data sorted by the corresponding attributes.
 *
 * For more details and usage information on Sort, see the [guide article on sorting](guide:output-sorting).
 *
 * @property array $attributeOrders Sort directions indexed by attribute names. Sort direction can be either
 * `SORT_ASC` for ascending order or `SORT_DESC` for descending order. Note that the type of this property
 * differs in getter and setter. See [[getAttributeOrders()]] and [[setAttributeOrders()]] for details.
 * @property-read array $orders The columns (keys) and their corresponding sort directions (values). This can
 * be passed to [[\yii\db\Query::orderBy()]] to construct a DB query.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Sort extends Base_Object
{
    /**
     * @var bool whether the sorting can be applied to multiple attributes simultaneously.
     * Defaults to `false`, which means each time the data can only be sorted by one attribute.
     */
    public $enable_multi_sort = false;
    /**
     * @var array list of attributes that are allowed to be sorted. Its syntax can be
     * described using the following example:
     *
     * ```
     * [
     *     'age',
     *     'name' => [
     *         'asc' => ['first_name' => SORT_ASC, 'last_name' => SORT_ASC],
     *         'desc' => ['first_name' => SORT_DESC, 'last_name' => SORT_DESC],
     *         'default' => SORT_DESC,
     *         'label' => 'Name',
     *     ],
     * ]
     * ```
     *
     * In the above, two attributes are declared: `age` and `name`. The `age` attribute is
     * a simple attribute which is equivalent to the following:
     *
     * ```
     * 'age' => [
     *     'asc' => ['age' => SORT_ASC],
     *     'desc' => ['age' => SORT_DESC],
     *     'default' => SORT_ASC,
     *     'label' => Inflector::camel2words('age'),
     * ]
     * ```
     *
     * Since 2.0.12 particular sort direction can be also specified as direct sort expression, like following:
     *
     * ```
     * 'name' => [
     *     'asc' => '[[last_name]] ASC NULLS FIRST', // PostgreSQL specific feature
     *     'desc' => '[[last_name]] DESC NULLS LAST',
     * ]
     * ```
     *
     * The `name` attribute is a composite attribute:
     *
     * - The `name` key represents the attribute name which will appear in the URLs leading
     *   to sort actions.
     * - The `asc` and `desc` elements specify how to sort by the attribute in ascending
     *   and descending orders, respectively. Their values represent the actual columns and
     *   the directions by which the data should be sorted by.
     * - The `default` element specifies by which direction the attribute should be sorted
     *   if it is not currently sorted (the default value is ascending order).
     * - The `label` element specifies what label should be used when calling [[link()]] to create
     *   a sort link. If not set, [[Inflector::camel2words()]] will be called to get a label.
     *   Note that it will not be HTML-encoded.
     *
     * Note that if the Sort object is already created, you can only use the full format
     * to configure every attribute. Each attribute must include these elements: `asc` and `desc`.
     */
    public $attributes = [];
    /**
     * @var string the name of the parameter that specifies which attributes to be sorted
     * in which direction. Defaults to `sort`.
     * @see params
     */
    public $sort_param = 'sort';
    /**
     * @var array|null the order that should be used when the current request does not specify any order.
     * The array keys are attribute names and the array values are the corresponding sort directions. For example,
     *
     * ```
     * [
     *     'name' => SORT_ASC,
     *     'created_at' => SORT_DESC,
     * ]
     * ```
     *
     * @see attributeOrders
     */
    public $default_order;
    /**
     * @var string|null the route of the controller action for displaying the sorted contents.
     * If not set, it means using the currently requested route.
     */
    public $route;
    /**
     * @var string the character used to separate different attributes that need to be sorted by.
     */
    public $separator = ',';
    /**
     * @var array|null parameters (name => value) that should be used to obtain the current sort directions
     * and to create new sort URLs. If not set, `$_GET` will be used instead.
     *
     * In order to add hash to all links use `array_merge($_GET, ['#' => 'my-hash'])`.
     *
     * The array element indexed by [[sortParam]] is considered to be the current sort directions.
     * If the element does not exist, the [[defaultOrder|default order]] will be used.
     *
     * @see sortParam
     * @see defaultOrder
     */
    public $params;
    /**
     * @var \yii\web\UrlManager|null the URL manager used for creating sort URLs. If not set,
     * the `urlManager` application component will be used.
     */
    public $url_manager;
    /**
     * @var int Allow to control a value of the fourth parameter which will be
     * passed to [[ArrayHelper::multisort()]]
     * @since 2.0.33
     */
    public $sort_flags = SORT_REGULAR;
    /**
     * @var string|null the name of the [[\yii\base\Model]]-based class used by the [[link()]] method to retrieve
     * attributes' labels. See [[link]] method for details.
     * @since 2.0.49
     */
    public $model_class;
    /**
     * Normalizes the [[attributes]] property.
     */
    public function init(): void
    {
        $attributes = [];
        foreach ($this->attributes as $name => $attribute) {
            if (!is_array($attribute)) {
                $attributes[$attribute] = ['asc' => [$attribute => SORT_ASC], 'desc' => [$attribute => SORT_DESC]];
            } elseif (!isset($attribute['asc'], $attribute['desc'])) {
                $attributes[$name] = array_merge(['asc' => [$name => SORT_ASC], 'desc' => [$name => SORT_DESC]], $attribute);
            } else {
                $attributes[$name] = $attribute;
            }
        }
        $this->attributes = $attributes;
    }
    /**
     * Returns the columns and their corresponding sort directions.
     * @param bool $recalculate whether to recalculate the sort directions
     * @return array the columns (keys) and their corresponding sort directions (values).
     * This can be passed to [[\yii\db\Query::orderBy()]] to construct a DB query.
     */
    public function get_orders($recalculate = false): array
    {
        $attribute_orders = $this->get_attribute_orders($recalculate);
        $orders = [];
        foreach ($attribute_orders as $attribute => $direction) {
            $definition = $this->attributes[$attribute];
            $columns = $definition[$direction === SORT_ASC ? 'asc' : 'desc'];
            if (is_iterable($columns)) {
                foreach ($columns as $name => $dir) {
                    $orders[$name] = $dir;
                }
            } else {
                $orders[] = $columns;
            }
        }
        return $orders;
    }
    /**
     * @var array the currently requested sort order as computed by [[getAttributeOrders]].
     */
    private $_attribute_orders;
    /**
     * Returns the currently requested sort information.
     * @param bool $recalculate whether to recalculate the sort directions
     * @return array sort directions indexed by attribute names.
     * Sort direction can be either `SORT_ASC` for ascending order or
     * `SORT_DESC` for descending order.
     */
    public function get_attribute_orders($recalculate = false)
    {
        if ($this->_attribute_orders === null || $recalculate) {
            $this->_attribute_orders = [];
            if (($params = $this->params) === null) {
                $request = Yii::$app->get_request();
                $params = $request instanceof Request ? $request->get_query_params() : [];
            }
            if (isset($params[$this->sort_param])) {
                foreach ($this->parse_sort_param($params[$this->sort_param]) as $attribute) {
                    $descending = false;
                    if (strncmp($attribute, '-', 1) === 0) {
                        $descending = true;
                        $attribute = substr($attribute, 1);
                    }
                    if (isset($this->attributes[$attribute])) {
                        $this->_attribute_orders[$attribute] = $descending ? SORT_DESC : SORT_ASC;
                        if (!$this->enable_multi_sort) {
                            return $this->_attribute_orders;
                        }
                    }
                }
                return $this->_attribute_orders;
            }
            if (empty($this->_attribute_orders) && is_array($this->default_order)) {
                $this->_attribute_orders = $this->default_order;
            }
        }
        return $this->_attribute_orders;
    }
    /**
     * Parses the value of [[sortParam]] into an array of sort attributes.
     *
     * The format must be the attribute name only for ascending
     * or the attribute name prefixed with `-` for descending.
     *
     * For example the following return value will result in ascending sort by
     * `category` and descending sort by `created_at`:
     *
     * ```
     * [
     *     'category',
     *     '-created_at'
     * ]
     * ```
     *
     * @param mixed $param the value of the [[sortParam]].
     * @return array the valid sort attributes.
     * @since 2.0.12
     * @see separator for the attribute name separator.
     * @see sortParam
     */
    protected function parse_sort_param($param)
    {
        return is_scalar($param) ? explode($this->separator, $param) : [];
    }
    /**
     * Sets up the currently sort information.
     * @param array|null $attributeOrders sort directions indexed by attribute names.
     * Sort direction can be either `SORT_ASC` for ascending order or
     * `SORT_DESC` for descending order.
     * @param bool $validate whether to validate given attribute orders against [[attributes]] and [[enableMultiSort]].
     * If validation is enabled incorrect entries will be removed.
     * @since 2.0.10
     */
    public function set_attribute_orders($attribute_orders, $validate = true): void
    {
        if ($attribute_orders === null || !$validate) {
            $this->_attribute_orders = $attribute_orders;
        } else {
            $this->_attribute_orders = [];
            foreach ($attribute_orders as $attribute => $order) {
                if (isset($this->attributes[$attribute])) {
                    $this->_attribute_orders[$attribute] = $order;
                    if (!$this->enable_multi_sort) {
                        break;
                    }
                }
            }
        }
    }
    /**
     * Returns the sort direction of the specified attribute in the current request.
     * @param string $attribute the attribute name
     * @return int|null Sort direction of the attribute. Can be either `SORT_ASC`
     * for ascending order or `SORT_DESC` for descending order. Null is returned
     * if the attribute is invalid or does not need to be sorted.
     */
    public function get_attribute_order($attribute)
    {
        $orders = $this->get_attribute_orders();
        return $orders[$attribute] ?? null;
    }
    /**
     * Generates a hyperlink that links to the sort action to sort by the specified attribute.
     * Based on the sort direction, the CSS class of the generated hyperlink will be appended
     * with "asc" or "desc".
     * @param string $attribute the attribute name by which the data should be sorted by.
     * @param array $options additional HTML attributes for the hyperlink tag.
     * There is one special attribute `label` which will be used as the label of the hyperlink.
     * If this is not set, the label defined in [[attributes]] will be used.
     * If no label is defined, it will be retrieved from the instance of [[modelClass]] (if [[modelClass]] is not null)
     * or generated from attribute name using [[\yii\helpers\Inflector::camel2words()]].
     * Note that it will not be HTML-encoded.
     * @return string the generated hyperlink
     * @throws InvalidConfigException if the attribute is unknown
     */
    public function link($attribute, array $options = [])
    {
        if (($direction = $this->get_attribute_order($attribute)) !== null) {
            $class = $direction === SORT_DESC ? 'desc' : 'asc';
            if (isset($options['class'])) {
                $options['class'] .= ' ' . $class;
            } else {
                $options['class'] = $class;
            }
        }
        $url = $this->create_url($attribute);
        $options['data-sort'] = $this->create_sort_param($attribute);
        if (isset($options['label'])) {
            $label = $options['label'];
            unset($options['label']);
        } else if (isset($this->attributes[$attribute]['label'])) {
            $label = $this->attributes[$attribute]['label'];
        } elseif ($this->model_class !== null) {
            $model_class = $this->model_class;
            /** @var \yii\base\Model $model */
            $model = $model_class::instance();
            $label = $model->get_attribute_label($attribute);
        } else {
            $label = Inflector::camel2words($attribute);
        }
        return Html::a($label, $url, $options);
    }
    /**
     * Creates a URL for sorting the data by the specified attribute.
     * This method will consider the current sorting status given by [[attributeOrders]].
     * For example, if the current page already sorts the data by the specified attribute in ascending order,
     * then the URL created will lead to a page that sorts the data by the specified attribute in descending order.
     * @param string $attribute the attribute name
     * @param bool $absolute whether to create an absolute URL. Defaults to `false`.
     * @return string the URL for sorting. False if the attribute is invalid.
     * @throws InvalidConfigException if the attribute is unknown
     * @see attributeOrders
     * @see params
     */
    public function create_url($attribute, $absolute = false)
    {
        if (($params = $this->params) === null) {
            $request = Yii::$app->get_request();
            $params = $request instanceof Request ? $request->get_query_params() : [];
        }
        $params[$this->sort_param] = $this->create_sort_param($attribute);
        $params[0] = $this->route ?? Yii::$app->controller->get_route();
        $url_manager = $this->url_manager ?? Yii::$app->get_url_manager();
        if ($absolute) {
            return $url_manager->create_absolute_url($params);
        }
        return $url_manager->create_url($params);
    }
    /**
     * Creates the sort variable for the specified attribute.
     * The newly created sort variable can be used to create a URL that will lead to
     * sorting by the specified attribute.
     * @param string $attribute the attribute name
     * @return string the value of the sort variable
     * @throws InvalidConfigException if the specified attribute is not defined in [[attributes]]
     */
    public function create_sort_param(string $attribute): string
    {
        if (!isset($this->attributes[$attribute])) {
            throw new Invalid_Config_Exception("Unknown attribute: {$attribute}");
        }
        $definition = $this->attributes[$attribute];
        $directions = $this->get_attribute_orders();
        if (isset($directions[$attribute])) {
            if ($this->enable_multi_sort) {
                if ($directions[$attribute] === SORT_ASC) {
                    $direction = SORT_DESC;
                } else {
                    $direction = null;
                }
            } else {
                $direction = $directions[$attribute] === SORT_DESC ? SORT_ASC : SORT_DESC;
            }
            unset($directions[$attribute]);
        } else {
            $direction = $definition['default'] ?? SORT_ASC;
        }
        if ($this->enable_multi_sort) {
            if ($direction !== null) {
                $directions = array_merge([$attribute => $direction], $directions);
            }
        } else {
            $directions = [$attribute => $direction];
        }
        $sorts = [];
        foreach ($directions as $attribute => $direction) {
            $sorts[] = $direction === SORT_DESC ? '-' . $attribute : $attribute;
        }
        return implode($this->separator, $sorts);
    }
    /**
     * Returns a value indicating whether the sort definition supports sorting by the named attribute.
     * @param string $name the attribute name
     * @return bool whether the sort definition supports sorting by the named attribute.
     */
    public function has_attribute($name): bool
    {
        return isset($this->attributes[$name]);
    }
}