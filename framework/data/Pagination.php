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
use yii\web\Link;
use yii\web\Linkable;
use yii\web\Request;
/**
 * Pagination represents information relevant to pagination of data items.
 *
 * When data needs to be rendered in multiple pages, Pagination can be used to
 * represent information such as [[totalCount|total item count]], [[pageSize|page size]],
 * [[page|current page]], etc. These information can be passed to [[\yii\widgets\LinkPager|pagers]]
 * to render pagination buttons or links.
 *
 * The following example shows how to create a pagination object and feed it
 * to a pager.
 *
 * Controller action:
 *
 * ```
 * public function actionIndex()
 * {
 *     $query = Article::find()->where(['status' => 1]);
 *     $countQuery = clone $query;
 *     $pages = new Pagination(['totalCount' => $countQuery->count()]);
 *     $models = $query->offset($pages->offset)
 *         ->limit($pages->limit)
 *         ->all();
 *
 *     return $this->render('index', [
 *          'models' => $models,
 *          'pages' => $pages,
 *     ]);
 * }
 * ```
 *
 * View:
 *
 * ```
 * foreach ($models as $model) {
 *     // display $model here
 * }
 *
 * // display pagination
 * echo LinkPager::widget([
 *     'pagination' => $pages,
 * ]);
 * ```
 *
 * For more details and usage information on Pagination, see the [guide article on pagination](guide:output-pagination).
 *
 * @property-read int $limit The limit of the data. This may be used to set the LIMIT value for a SQL
 * statement for fetching the current page of data. Note that if the page size is infinite, a value -1 will be
 * returned.
 * @property-read array $links The links for navigational purpose. The array keys specify the purpose of the
 * links (e.g. [[LINK_FIRST]]), and the array values are the corresponding URLs.
 * @property-read int $offset The offset of the data. This may be used to set the OFFSET value for a SQL
 * statement for fetching the current page of data.
 * @property int $page The zero-based current page number.
 * @property-read int $pageCount Number of pages.
 * @property int $pageSize The number of items per page. If it is less than 1, it means the page size is
 * infinite, and thus a single page contains all items.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Pagination extends Base_Object implements Linkable
{
    public const LINK_NEXT = 'next';
    public const LINK_PREV = 'prev';
    public const LINK_FIRST = 'first';
    public const LINK_LAST = 'last';
    /**
     * @var string name of the parameter storing the current page index.
     * @see params
     */
    public $page_param = 'page';
    /**
     * @var string name of the parameter storing the page size.
     * @see params
     */
    public $page_size_param = 'per-page';
    /**
     * @var bool whether to always have the page parameter in the URL created by [[createUrl()]].
     * If false and [[page]] is 0, the page parameter will not be put in the URL.
     */
    public $force_page_param = true;
    /**
     * @var string|null the route of the controller action for displaying the paged contents.
     * If not set, it means using the currently requested route.
     */
    public $route;
    /**
     * @var array|null parameters (name => value) that should be used to obtain the current page number
     * and to create new pagination URLs. If not set, all parameters from $_GET will be used instead.
     *
     * In order to add hash to all links use `array_merge($_GET, ['#' => 'my-hash'])`.
     *
     * The array element indexed by [[pageParam]] is considered to be the current page number (defaults to 0);
     * while the element indexed by [[pageSizeParam]] is treated as the page size (defaults to [[defaultPageSize]]).
     */
    public $params;
    /**
     * @var \yii\web\UrlManager|null the URL manager used for creating pagination URLs. If not set,
     * the "urlManager" application component will be used.
     */
    public $url_manager;
    /**
     * @var bool whether to check if [[page]] is within valid range.
     * When this property is true, the value of [[page]] will always be between 0 and ([[pageCount]]-1).
     * Because [[pageCount]] relies on the correct value of [[totalCount]] which may not be available
     * in some cases (e.g. MongoDB), you may want to set this property to be false to disable the page
     * number validation. By doing so, [[page]] will return the value indexed by [[pageParam]] in [[params]].
     */
    public $validate_page = true;
    /**
     * @var int total number of items.
     */
    public $total_count = 0;
    /**
     * @var int the default page size. This property will be returned by [[pageSize]] when page size
     * cannot be determined by [[pageSizeParam]] from [[params]].
     */
    public $default_page_size = 20;
    /**
     * @var array|false the page size limits. The first array element defines the minimum page size, and the second
     * the maximum page size. If this is false, it means [[pageSize]] should always return the value of [[defaultPageSize]].
     */
    public $page_size_limit = [1, 50];
    /**
     * @var int|null number of items on each page.
     * If it is less than 1, it means the page size is infinite, and thus a single page contains all items.
     */
    private $_page_size;
    /**
     * @return int number of pages
     */
    public function get_page_count(): int
    {
        $page_size = $this->get_page_size();
        if ($page_size < 1) {
            return $this->total_count > 0 ? 1 : 0;
        }
        $total_count = $this->total_count < 0 ? 0 : (int) $this->total_count;
        return (int) (($total_count + $page_size - 1) / $page_size);
    }
    private $_page;
    /**
     * Returns the zero-based current page number.
     * @param bool $recalculate whether to recalculate the current page based on the page size and item count.
     * @return int the zero-based current page number.
     */
    public function get_page($recalculate = false)
    {
        if ($this->_page === null || $recalculate) {
            $page = (int) $this->get_query_param($this->page_param, 1) - 1;
            $this->set_page($page, true);
        }
        return $this->_page;
    }
    /**
     * Sets the current page number.
     * @param int $value the zero-based index of the current page.
     * @param bool $validatePage whether to validate the page number. Note that in order
     * to validate the page number, both [[validatePage]] and this parameter must be true.
     */
    public function set_page($value, $validate_page = false): void
    {
        if ($value === null) {
            $this->_page = null;
        } else {
            $value = (int) $value;
            if ($validate_page && $this->validate_page) {
                $page_count = $this->get_page_count();
                if ($value >= $page_count) {
                    $value = $page_count - 1;
                }
            }
            if ($value < 0) {
                $value = 0;
            }
            $this->_page = $value;
        }
    }
    /**
     * Returns the number of items per page.
     * By default, this method will try to determine the page size by [[pageSizeParam]] in [[params]].
     * If the page size cannot be determined this way, [[defaultPageSize]] will be returned.
     * @return int the number of items per page. If it is less than 1, it means the page size is infinite,
     * and thus a single page contains all items.
     * @see pageSizeLimit
     */
    public function get_page_size()
    {
        if ($this->_page_size === null) {
            if (empty($this->page_size_limit) || !isset($this->page_size_limit[0], $this->page_size_limit[1])) {
                $page_size = $this->default_page_size;
                $this->set_page_size($page_size);
            } else {
                $page_size = (int) $this->get_query_param($this->page_size_param, $this->default_page_size);
                $this->set_page_size($page_size, true);
            }
        }
        return $this->_page_size;
    }
    /**
     * @param int $value the number of items per page.
     * @param bool $validatePageSize whether to validate page size.
     */
    public function set_page_size($value, $validate_page_size = false): void
    {
        if ($value === null) {
            $this->_page_size = null;
        } else {
            $value = (int) $value;
            if ($validate_page_size && isset($this->page_size_limit[0], $this->page_size_limit[1])) {
                if ($value < $this->page_size_limit[0]) {
                    $value = $this->page_size_limit[0];
                } elseif ($value > $this->page_size_limit[1]) {
                    $value = $this->page_size_limit[1];
                }
            }
            $this->_page_size = $value;
        }
    }
    /**
     * Creates the URL suitable for pagination with the specified page number.
     * This method is mainly called by pagers when creating URLs used to perform pagination.
     * @param int $page the zero-based page number that the URL should point to.
     * @param int|null $pageSize the number of items on each page. If not set, the value of [[pageSize]] will be used.
     * @param bool $absolute whether to create an absolute URL. Defaults to `false`.
     * @return string the created URL
     * @see params
     * @see forcePageParam
     */
    public function create_url($page, $page_size = null, $absolute = false)
    {
        $page = (int) $page;
        $page_size = (int) $page_size;
        if (($params = $this->params) === null) {
            $request = Yii::$app->get_request();
            $params = $request instanceof Request ? $request->get_query_params() : [];
        }
        if ($page > 0 || $page == 0 && $this->force_page_param) {
            $params[$this->page_param] = $page + 1;
        } else {
            unset($params[$this->page_param]);
        }
        if ($page_size <= 0) {
            $page_size = $this->get_page_size();
        }
        if ($page_size != $this->default_page_size) {
            $params[$this->page_size_param] = $page_size;
        } else {
            unset($params[$this->page_size_param]);
        }
        $params[0] = $this->route ?? Yii::$app->controller->get_route();
        $url_manager = $this->url_manager ?? Yii::$app->get_url_manager();
        if ($absolute) {
            return $url_manager->create_absolute_url($params);
        }
        return $url_manager->create_url($params);
    }
    /**
     * @return int the offset of the data. This may be used to set the
     * OFFSET value for a SQL statement for fetching the current page of data.
     */
    public function get_offset()
    {
        $page_size = $this->get_page_size();
        return $page_size < 1 ? 0 : $this->get_page() * $page_size;
    }
    /**
     * @return int the limit of the data. This may be used to set the
     * LIMIT value for a SQL statement for fetching the current page of data.
     * Note that if the page size is infinite, a value -1 will be returned.
     */
    public function get_limit()
    {
        $page_size = $this->get_page_size();
        return $page_size < 1 ? -1 : $page_size;
    }
    /**
     * Returns a whole set of links for navigating to the first, last, next and previous pages.
     * @param bool $absolute whether the generated URLs should be absolute.
     * @return array the links for navigational purpose. The array keys specify the purpose of the links (e.g. [[LINK_FIRST]]),
     * and the array values are the corresponding URLs.
     */
    public function get_links($absolute = false): array
    {
        $current_page = $this->get_page();
        $page_count = $this->get_page_count();
        $links = [Link::REL_SELF => $this->create_url($current_page, null, $absolute)];
        if ($page_count > 0) {
            $links[self::LINK_FIRST] = $this->create_url(0, null, $absolute);
            $links[self::LINK_LAST] = $this->create_url($page_count - 1, null, $absolute);
            if ($current_page > 0) {
                $links[self::LINK_PREV] = $this->create_url($current_page - 1, null, $absolute);
            }
            if ($current_page < $page_count - 1) {
                $links[self::LINK_NEXT] = $this->create_url($current_page + 1, null, $absolute);
            }
        }
        return $links;
    }
    /**
     * Returns the value of the specified query parameter.
     * This method returns the named parameter value from [[params]]. Null is returned if the value does not exist.
     * @param string $name the parameter name
     * @param string|null $defaultValue the value to be returned when the specified parameter does not exist in [[params]].
     * @return string|null the parameter value
     */
    protected function get_query_param($name, $default_value = null)
    {
        if (($params = $this->params) === null) {
            $request = Yii::$app->get_request();
            $params = $request instanceof Request ? $request->get_query_params() : [];
        }
        return isset($params[$name]) && is_scalar($params[$name]) ? $params[$name] : $default_value;
    }
}