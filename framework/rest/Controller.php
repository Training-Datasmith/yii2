<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\rest;

use Yii;
use yii\base\Module;
use yii\filters\auth\Composite_Auth;
use yii\filters\Content_Negotiator;
use yii\filters\Rate_Limiter;
use yii\filters\Verb_Filter;
use yii\web\Controller as WebController;
use yii\web\Response;
/**
 * Controller is the base class for RESTful API controller classes.
 *
 * Controller implements the following steps in a RESTful API request handling cycle:
 *
 * 1. Resolving response format (see [[ContentNegotiator]]);
 * 2. Validating request method (see [[verbs()]]).
 * 3. Authenticating user (see [[\yii\filters\auth\AuthInterface]]);
 * 4. Rate limiting (see [[RateLimiter]]);
 * 5. Formatting response data (see [[serializeData()]]).
 *
 * For more details and usage information on Controller, see the [guide article on rest controllers](guide:rest-controllers).
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 *
 * @template T of Module = Module
 * @extends WebController<T>
 */
class Controller extends Web_Controller
{
    /**
     * @var string|array the configuration for creating the serializer that formats the response data.
     */
    public $serializer = 'yii\rest\Serializer';
    /**
     * {@inheritdoc}
     */
    public $enable_csrf_validation = false;
    /**
     * {@inheritdoc}
     */
    public function behaviors(): array
    {
        return ['contentNegotiator' => ['class' => Content_Negotiator::class_name(), 'formats' => ['application/json' => Response::FORMAT_JSON, 'application/xml' => Response::FORMAT_XML]], 'verbFilter' => ['class' => Verb_Filter::class_name(), 'actions' => $this->verbs()], 'authenticator' => ['class' => Composite_Auth::class_name()], 'rateLimiter' => ['class' => Rate_Limiter::class_name()]];
    }
    /**
     * {@inheritdoc}
     */
    public function after_action($action, $result)
    {
        $result = parent::after_action($action, $result);
        return $this->serialize_data($result);
    }
    /**
     * Declares the allowed HTTP verbs.
     * Please refer to [[VerbFilter::actions]] on how to declare the allowed verbs.
     * @return array the allowed HTTP verbs.
     */
    protected function verbs(): array
    {
        return [];
    }
    /**
     * Serializes the specified data.
     * The default implementation will create a serializer based on the configuration given by [[serializer]].
     * It then uses the serializer to serialize the given data.
     * @param mixed $data the data to be serialized
     * @return mixed the serialized data.
     */
    protected function serialize_data($data)
    {
        return Yii::create_object($this->serializer)->serialize($data);
    }
}