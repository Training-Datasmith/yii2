<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\rest;

use Yii;
use yii\base\Arrayable;
use yii\base\Component;
use yii\base\Model;
use yii\data\Data_Provider_Interface;
use yii\data\Pagination;
use yii\helpers\Array_Helper;
use yii\web\Link;
use yii\web\Request;
use yii\web\Response;
/**
 * Serializer converts resource objects and collections into array representation.
 *
 * Serializer is mainly used by REST controllers to convert different objects into array representation
 * so that they can be further turned into different formats, such as JSON, XML, by response formatters.
 *
 * The default implementation handles resources as [[Model]] objects and collections as objects
 * implementing [[DataProviderInterface]]. You may override [[serialize()]] to handle more types.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Serializer extends Component
{
    /**
     * @var string the name of the query parameter containing the information about which fields should be returned
     * for a [[Model]] object. If the parameter is not provided or empty, the default set of fields as defined
     * by [[Model::fields()]] will be returned.
     */
    public $fields_param = 'fields';
    /**
     * @var string the name of the query parameter containing the information about which fields should be returned
     * in addition to those listed in [[fieldsParam]] for a resource object.
     */
    public $expand_param = 'expand';
    /**
     * @var string the name of the HTTP header containing the information about total number of data items.
     * This is used when serving a resource collection with pagination.
     */
    public $total_count_header = 'X-Pagination-Total-Count';
    /**
     * @var string the name of the HTTP header containing the information about total number of pages of data.
     * This is used when serving a resource collection with pagination.
     */
    public $page_count_header = 'X-Pagination-Page-Count';
    /**
     * @var string the name of the HTTP header containing the information about the current page number (1-based).
     * This is used when serving a resource collection with pagination.
     */
    public $current_page_header = 'X-Pagination-Current-Page';
    /**
     * @var string the name of the HTTP header containing the information about the number of data items in each page.
     * This is used when serving a resource collection with pagination.
     */
    public $per_page_header = 'X-Pagination-Per-Page';
    /**
     * @var string|null the name of the envelope (e.g. `items`) for returning the resource objects in a collection.
     * This is used when serving a resource collection. When this is set and pagination is enabled, the serializer
     * will return a collection in the following format:
     *
     * ```
     * [
     *     'items' => [...],  // assuming collectionEnvelope is "items"
     *     '_links' => {  // pagination links as returned by Pagination::getLinks()
     *         'self' => '...',
     *         'next' => '...',
     *         'last' => '...',
     *     },
     *     '_meta' => {  // meta information as returned by Pagination::toArray()
     *         'totalCount' => 100,
     *         'pageCount' => 5,
     *         'currentPage' => 1,
     *         'perPage' => 20,
     *     },
     * ]
     * ```
     *
     * If this property is not set, the resource arrays will be directly returned without using envelope.
     * The pagination information as shown in `_links` and `_meta` can be accessed from the response HTTP headers.
     */
    public $collection_envelope;
    /**
     * @var string the name of the envelope (e.g. `_links`) for returning the links objects.
     * It takes effect only, if `collectionEnvelope` is set.
     * @since 2.0.4
     */
    public $links_envelope = '_links';
    /**
     * @var string the name of the envelope (e.g. `_meta`) for returning the pagination object.
     * It takes effect only, if `collectionEnvelope` is set.
     * @since 2.0.4
     */
    public $meta_envelope = '_meta';
    /**
     * @var Request|null the current request. If not set, the `request` application component will be used.
     */
    public $request;
    /**
     * @var Response|null the response to be sent. If not set, the `response` application component will be used.
     */
    public $response;
    /**
     * @var bool whether to preserve array keys when serializing collection data.
     * Set this to `true` to allow serialization of a collection as a JSON object where array keys are
     * used to index the model objects. The default is to serialize all collections as array, regardless
     * of how the array is indexed.
     * @see serializeDataProvider()
     * @since 2.0.10
     */
    public $preserve_keys = false;
    /**
     * {@inheritdoc}
     */
    public function init(): void
    {
        if ($this->request === null) {
            $this->request = Yii::$app->get_request();
        }
        if ($this->response === null) {
            $this->response = Yii::$app->get_response();
        }
    }
    /**
     * Serializes the given data into a format that can be easily turned into other formats.
     * This method mainly converts the objects of recognized types into array representation.
     * It will not do conversion for unknown object types or non-object data.
     * The default implementation will handle [[Model]], [[DataProviderInterface]] and [\JsonSerializable](https://www.php.net/manual/en/class.jsonserializable.php).
     * You may override this method to support more object types.
     * @param mixed $data the data to be serialized.
     * @return mixed the converted data.
     */
    public function serialize($data)
    {
        if ($data instanceof Model && $data->has_errors()) {
            return $this->serialize_model_errors($data);
        }
        if ($data instanceof Arrayable) {
            return $this->serialize_model($data);
        }
        if ($data instanceof \JsonSerializable) {
            return $data->jsonSerialize();
        }
        if ($data instanceof Data_Provider_Interface) {
            return $this->serialize_data_provider($data);
        }
        if (is_array($data)) {
            $serialized_array = [];
            foreach ($data as $key => $value) {
                $serialized_array[$key] = $this->serialize($value);
            }
            return $serialized_array;
        }
        return $data;
    }
    /**
     * @return array the names of the requested fields. The first element is an array
     * representing the list of default fields requested, while the second element is
     * an array of the extra fields requested in addition to the default fields.
     * @see Model::fields()
     * @see Model::extraFields()
     */
    protected function get_requested_fields(): array
    {
        $fields = $this->request->get($this->fields_param);
        $expand = $this->request->get($this->expand_param);
        return [is_string($fields) ? preg_split('/\s*,\s*/', $fields, -1, PREG_SPLIT_NO_EMPTY) : [], is_string($expand) ? preg_split('/\s*,\s*/', $expand, -1, PREG_SPLIT_NO_EMPTY) : []];
    }
    /**
     * Serializes a data provider.
     * @param DataProviderInterface $dataProvider
     * @return array|null the array representation of the data provider.
     */
    protected function serialize_data_provider($data_provider)
    {
        if ($this->preserve_keys) {
            $models = $data_provider->get_models();
        } else {
            $models = array_values($data_provider->get_models());
        }
        $models = $this->serialize_models($models);
        if (($pagination = $data_provider->get_pagination()) !== false) {
            $this->add_pagination_headers($pagination);
        }
        if ($this->request->get_is_head()) {
            return null;
        }
        if ($this->collection_envelope === null) {
            return $models;
        }
        $result = [$this->collection_envelope => $models];
        if ($pagination !== false) {
            return array_merge($result, $this->serialize_pagination($pagination));
        }
        return $result;
    }
    /**
     * Serializes a pagination into an array.
     * @param Pagination $pagination
     * @return array the array representation of the pagination
     * @see addPaginationHeaders()
     */
    protected function serialize_pagination($pagination): array
    {
        return [$this->links_envelope => Link::serialize($pagination->get_links(true)), $this->meta_envelope => ['totalCount' => $pagination->total_count, 'pageCount' => $pagination->get_page_count(), 'currentPage' => $pagination->get_page() + 1, 'perPage' => $pagination->get_page_size()]];
    }
    /**
     * Adds HTTP headers about the pagination to the response.
     * @param Pagination $pagination
     */
    protected function add_pagination_headers($pagination)
    {
        $links = [];
        foreach ($pagination->get_links(true) as $rel => $url) {
            $links[] = "<{$url}>; rel={$rel}";
        }
        $this->response->get_headers()->set($this->total_count_header, $pagination->total_count)->set($this->page_count_header, $pagination->get_page_count())->set($this->current_page_header, $pagination->get_page() + 1)->set($this->per_page_header, $pagination->page_size)->set('Link', implode(', ', $links));
    }
    /**
     * Serializes a model object.
     * @param Arrayable $model
     * @return array|null the array representation of the model
     */
    protected function serialize_model($model)
    {
        if ($this->request->get_is_head()) {
            return null;
        }
        [$fields, $expand] = $this->get_requested_fields();
        return $model->to_array($fields, $expand);
    }
    /**
     * Serializes the validation errors in a model.
     * @param Model $model
     * @return array the array representation of the errors
     */
    protected function serialize_model_errors($model): array
    {
        $this->response->set_status_code(422, 'Data Validation Failed.');
        $result = [];
        foreach ($model->get_first_errors() as $name => $message) {
            $result[] = ['field' => $name, 'message' => $message];
        }
        return $result;
    }
    /**
     * Serializes a set of models.
     * @return array the array representation of the models
     */
    protected function serialize_models(array $models): array
    {
        foreach ($models as $i => $model) {
            if ($model instanceof Arrayable) {
                $models[$i] = $this->serialize_model($model);
            } elseif (is_array($model)) {
                $models[$i] = Array_Helper::to_array($model);
            }
        }
        return $models;
    }
}