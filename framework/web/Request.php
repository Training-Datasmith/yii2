<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\web;

use Yii;
use yii\base\Invalid_Config_Exception;
use yii\validators\Ip_Validator;
/**
 * The web Request class represents an HTTP request.
 *
 * It encapsulates the $_SERVER variable and resolves its inconsistency among different Web servers.
 * Also it provides an interface to retrieve request parameters from $_POST, $_GET, $_COOKIES and REST
 * parameters sent via other HTTP methods like PUT or DELETE.
 *
 * Request is configured as an application component in [[\yii\web\Application]] by default.
 * You can access that instance via `Yii::$app->request`.
 *
 * For more details and usage information on Request, see the [guide article on requests](guide:runtime-requests).
 *
 * @property string|null $hostInfo Schema and hostname part (with port number if needed) of the request URL
 * (e.g. `https://www.yiiframework.com`), null if can't be obtained from `$_SERVER` and wasn't set. See
 * [[getHostInfo()]] for security related notes on this property.
 * @property-read string $absoluteUrl The currently requested absolute URL.
 * @property array $acceptableContentTypes The content types ordered by the quality score. Types with the
 * highest scores will be returned first. The array keys are the content types, while the array values are the
 * corresponding quality score and other parameters as given in the header.
 * @property array $acceptableLanguages The languages ordered by the preference level. The first element
 * represents the most preferred language.
 * @property-read array $authCredentials That contains exactly two elements: - 0: the username sent via HTTP
 * authentication, `null` if the username is not given - 1: the password sent via HTTP authentication, `null` if
 * the password is not given.
 * @property-read string|null $authPassword The password sent via HTTP authentication, `null` if the password
 * is not given.
 * @property-read string|null $authUser The username sent via HTTP authentication, `null` if the username is
 * not given.
 * @property string $baseUrl The relative URL for the application.
 * @property array|object $bodyParams The request parameters given in the request body.
 * @property-read string $contentType Request content-type. Empty string is returned if this information is
 * not available.
 * @property-read CookieCollection $cookies The cookie collection.
 * @property-read null|string $csrfToken The token used to perform CSRF validation. Null is returned if the
 * [[validateCsrfHeaderOnly]] is true.
 * @property-read string|null $csrfTokenFromHeader The CSRF token sent via [[csrfHeader]] by browser. Null is
 * returned if no such header is sent.
 * @property-read array $eTags The entity tags.
 * @property-read HeaderCollection $headers The header collection.
 * @property-read string|null $hostName Hostname part of the request URL (e.g. `www.yiiframework.com`).
 * @property-read bool $isAjax Whether this is an AJAX (XMLHttpRequest) request.
 * @property-read bool $isDelete Whether this is a DELETE request.
 * @property-read bool $isFlash Whether this is an Adobe Flash or Adobe Flex request.
 * @property-read bool $isGet Whether this is a GET request.
 * @property-read bool $isHead Whether this is a HEAD request.
 * @property-read bool $isOptions Whether this is a OPTIONS request.
 * @property-read bool $isPatch Whether this is a PATCH request.
 * @property-read bool $isPjax Whether this is a PJAX request.
 * @property-read bool $isPost Whether this is a POST request.
 * @property-read bool $isPut Whether this is a PUT request.
 * @property-read bool $isSecureConnection If the request is sent via secure channel (https).
 * @property-read string $method Request method, such as GET, POST, HEAD, PUT, PATCH, DELETE. The value
 * returned is turned into upper case.
 * @property-read string|null $origin URL origin of a CORS request, `null` if not available.
 * @property string $pathInfo Part of the request URL that is after the entry script and before the question
 * mark. Note, the returned path info is already URL-decoded.
 * @property int $port Port number for insecure requests.
 * @property array $queryParams The request GET parameter values.
 * @property-read string $queryString Part of the request URL that is after the question mark.
 * @property string $rawBody The request body.
 * @property-read string|null $referrer URL referrer, null if not available.
 * @property-read string|null $remoteHost Remote host name, `null` if not available.
 * @property-read string|null $remoteIP Remote IP address, `null` if not available.
 * @property string $scriptFile The entry script file path.
 * @property string $scriptUrl The relative URL of the entry script.
 * @property int $securePort Port number for secure requests.
 * @property-read string|null $serverName Server name, null if not available.
 * @property-read int|null $serverPort Server port number, null if not available.
 * @property string $url The currently requested relative URL. Note that the URI returned may be URL-encoded
 * depending on the client.
 * @property-read string|null $userAgent User agent, null if not available.
 * @property-read string|null $userHost User host name, null if not available.
 * @property-read string|null $userIP User IP address, null if not available.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Request extends \yii\base\Request
{
    /**
     * Default name of the HTTP header for sending CSRF token.
     */
    public const CSRF_HEADER = 'X-CSRF-Token';
    /**
     * The length of the CSRF token mask.
     * @deprecated since 2.0.12. The mask length is now equal to the token length.
     */
    public const CSRF_MASK_LENGTH = 8;
    /**
     * @var bool whether to enable CSRF (Cross-Site Request Forgery) validation. Defaults to true.
     * When CSRF validation is enabled, forms submitted to an Yii Web application must be originated
     * from the same application. If not, a 400 HTTP exception will be raised.
     *
     * Note, this feature requires that the user client accepts cookie. Also, to use this feature,
     * forms submitted via POST method must contain a hidden input whose name is specified by [[csrfParam]].
     * You may use [[\yii\helpers\Html::beginForm()]] to generate his hidden input.
     *
     * In JavaScript, you may get the values of [[csrfParam]] and [[csrfToken]] via `yii.getCsrfParam()` and
     * `yii.getCsrfToken()`, respectively. The [[\yii\web\YiiAsset]] asset must be registered.
     * You also need to include CSRF meta tags in your pages by using [[\yii\helpers\Html::csrfMetaTags()]].
     *
     * For SPA, you can use CSRF validation by custom header with a random or an empty value.
     * Include a header with the name specified by [[csrfHeader]] to requests that must be validated.
     * Warning! CSRF validation by custom header can be used only for same-origin requests or
     * with CORS configured to allow requests from the list of specific origins only.
     *
     * @see Controller::enableCsrfValidation
     * @see https://en.wikipedia.org/wiki/Cross-site_request_forgery
     */
    public $enable_csrf_validation = true;
    /**
     * @var string the name of the HTTP header for sending CSRF token. Defaults to [[CSRF_HEADER]].
     * This property may be changed for Yii API applications only.
     * Don't change this property for Yii Web application.
     */
    public $csrf_header = self::CSRF_HEADER;
    /**
     * @var array the name of the HTTP header for sending CSRF token.
     * by default validate CSRF token on non-"safe" methods only
     * This property is used only when [[enableCsrfValidation]] is true.
     * @see https://datatracker.ietf.org/doc/html/rfc9110#name-safe-methods
     */
    public $csrf_token_safe_methods = ['GET', 'HEAD', 'OPTIONS'];
    /**
     * @var array "unsafe" methods not triggered a CORS-preflight request
     * This property is used only when both [[enableCsrfValidation]] and [[validateCsrfHeaderOnly]] are true.
     * @see https://fetch.spec.whatwg.org/#http-cors-protocol
     */
    public $csrf_header_unsafe_methods = ['GET', 'HEAD', 'POST'];
    /**
     * @var bool whether to use custom header only to CSRF validation of SPA. Defaults to false.
     * If false and [[enableCsrfValidation]] is true, CSRF validation by token will used.
     * Warning! CSRF validation by custom header can be used for Yii API applications only.
     * @see https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html#employing-custom-request-headers-for-ajaxapi
     */
    public $validate_csrf_header_only = false;
    /**
     * @var string the name of the token used to prevent CSRF. Defaults to '_csrf'.
     * This property is used only when [[enableCsrfValidation]] is true.
     */
    public $csrf_param = '_csrf';
    /**
     * @var array the configuration for creating the CSRF [[Cookie|cookie]]. This property is used only when
     * both [[enableCsrfValidation]] and [[enableCsrfCookie]] are true.
     */
    public $csrf_cookie = ['httpOnly' => true];
    /**
     * @var bool whether to use cookie to persist CSRF token. If false, CSRF token will be stored
     * in session under the name of [[csrfParam]]. Note that while storing CSRF tokens in session increases
     * security, it requires starting a session for every page, which will degrade your site performance.
     */
    public $enable_csrf_cookie = true;
    /**
     * @var bool whether cookies should be validated to ensure they are not tampered. Defaults to true.
     */
    public $enable_cookie_validation = true;
    /**
     * @var string a secret key used for cookie validation. This property must be set if [[enableCookieValidation]] is true.
     */
    public $cookie_validation_key;
    /**
     * @var string the name of the POST parameter that is used to indicate if a request is a PUT, PATCH or DELETE
     * request tunneled through POST. Defaults to '_method'.
     * @see getMethod()
     * @see getBodyParams()
     */
    public $method_param = '_method';
    /**
     * @var array the parsers for converting the raw HTTP request body into [[bodyParams]].
     * The array keys are the request `Content-Types`, and the array values are the
     * corresponding configurations for [[Yii::createObject|creating the parser objects]].
     * A parser must implement the [[RequestParserInterface]].
     *
     * To enable parsing for JSON requests you can use the [[JsonParser]] class like in the following example:
     *
     * ```
     * [
     *     'application/json' => 'yii\web\JsonParser',
     * ]
     * ```
     *
     * To register a parser for parsing all request types you can use `'*'` as the array key.
     * This one will be used as a fallback in case no other types match.
     *
     * @see getBodyParams()
     */
    public $parsers = [];
    /**
     * @var array the configuration for trusted security related headers.
     *
     * An array key is an IPv4 or IPv6 IP address in CIDR notation for matching a client.
     *
     * An array value is a list of headers to trust. These will be matched against
     * [[secureHeaders]] to determine which headers are allowed to be sent by a specified host.
     * The case of the header names must be the same as specified in [[secureHeaders]].
     *
     * For example, to trust all headers listed in [[secureHeaders]] for IP addresses
     * in range `192.168.0.0-192.168.0.254` write the following:
     *
     * ```
     * [
     *     '192.168.0.0/24',
     * ]
     * ```
     *
     * To trust just the `X-Forwarded-For` header from `10.0.0.1`, use:
     *
     * ```
     * [
     *     '10.0.0.1' => ['X-Forwarded-For']
     * ]
     * ```
     *
     * Default is to trust all headers except those listed in [[secureHeaders]] from all hosts.
     * Matches are tried in order and searching is stopped when IP matches.
     *
     * > Info: Matching is performed using [[IpValidator]].
     * See [[IpValidator::::setRanges()|IpValidator::setRanges()]]
     * and [[IpValidator::networks]] for advanced matching.
     *
     * @see secureHeaders
     * @since 2.0.13
     */
    public $trusted_hosts = [];
    /**
     * @var array lists of headers that are, by default, subject to the trusted host configuration.
     * These headers will be filtered unless explicitly allowed in [[trustedHosts]].
     * If the list contains the `Forwarded` header, processing will be done according to RFC 7239.
     * The match of header names is case-insensitive.
     * @see https://en.wikipedia.org/wiki/List_of_HTTP_header_fields
     * @see https://datatracker.ietf.org/doc/html/rfc7239
     * @see trustedHosts
     * @since 2.0.13
     */
    public $secure_headers = [
        // Common:
        'X-Forwarded-For',
        'X-Forwarded-Host',
        'X-Forwarded-Proto',
        'X-Forwarded-Port',
        // Microsoft:
        'Front-End-Https',
        'X-Rewrite-Url',
        // ngrok:
        'X-Original-Host',
    ];
    /**
     * @var string[] List of headers where proxies store the real client IP.
     * It's not advisable to put insecure headers here.
     * To use the `Forwarded` header according to RFC 7239, the header must be added to [[secureHeaders]] list.
     * The match of header names is case-insensitive.
     * @see trustedHosts
     * @see secureHeaders
     * @since 2.0.13
     */
    public $ip_headers = ['X-Forwarded-For'];
    /**
     * @var string[] List of headers where proxies store the real request port.
     * It's not advisable to put insecure headers here.
     * To use the `Forwarded Port`, the header must be added to [[secureHeaders]] list.
     * The match of header names is case-insensitive.
     * @see trustedHosts
     * @see secureHeaders
     * @since 2.0.46
     */
    public $port_headers = ['X-Forwarded-Port'];
    /**
     * @var array list of headers to check for determining whether the connection is made via HTTPS.
     * The array keys are header names and the array value is a list of header values that indicate a secure connection.
     * The match of header names and values is case-insensitive.
     * It's not advisable to put insecure headers here.
     * @see trustedHosts
     * @see secureHeaders
     * @since 2.0.13
     */
    public $secure_protocol_headers = [
        'X-Forwarded-Proto' => ['https'],
        // Common
        'Front-End-Https' => ['on'],
    ];
    /**
     * @var CookieCollection Collection of request cookies.
     */
    private ?\yii\web\Cookie_Collection $_cookies = null;
    /**
     * @var HeaderCollection Collection of request headers.
     */
    private ?\yii\web\Header_Collection $_headers = null;
    /**
     * Resolves the current request into a route and the associated parameters.
     * @return array the first element is the route, and the second is the associated parameters.
     * @throws NotFoundHttpException if the request cannot be resolved.
     */
    public function resolve()
    {
        $result = Yii::$app->get_url_manager()->parse_request($this);
        if ($result !== false) {
            [$route, $params] = $result;
            if ($this->_query_params === null) {
                $_GET = $params + $_GET;
                // preserve numeric keys
            } else {
                $this->_query_params = $params + $this->_query_params;
            }
            return [$route, $this->get_query_params()];
        }
        throw new Not_Found_Http_Exception(Yii::t('yii', 'Page not found.'));
    }
    /**
     * Filters headers according to the [[trustedHosts]].
     * @since 2.0.13
     */
    protected function filter_headers(Header_Collection $header_collection)
    {
        $trusted_headers = $this->get_trusted_headers();
        // remove all secure headers unless they are trusted
        foreach ($this->secure_headers as $secure_header) {
            if (!in_array($secure_header, $trusted_headers)) {
                $header_collection->remove($secure_header);
            }
        }
    }
    /**
     * Trusted headers according to the [[trustedHosts]].
     * @return array
     * @since 2.0.28
     */
    protected function get_trusted_headers()
    {
        // do not trust any of the [[secureHeaders]] by default
        $trusted_headers = [];
        // check if the client is a trusted host
        if (!empty($this->trusted_hosts)) {
            $validator = $this->get_ip_validator();
            $ip = $this->get_remote_ip();
            foreach ($this->trusted_hosts as $cidr => $headers) {
                if (!is_array($headers)) {
                    $cidr = $headers;
                    $headers = $this->secure_headers;
                }
                $validator->set_ranges($cidr);
                if ($validator->validate($ip)) {
                    $trusted_headers = $headers;
                    break;
                }
            }
        }
        return $trusted_headers;
    }
    /**
     * Creates instance of [[IpValidator]].
     * You can override this method to adjust validator or implement different matching strategy.
     *
     * @since 2.0.13
     */
    protected function get_ip_validator(): \yii\validators\Ip_Validator
    {
        return new Ip_Validator();
    }
    /**
     * Returns the header collection.
     * The header collection contains incoming HTTP headers.
     * @return HeaderCollection the header collection
     */
    public function get_headers()
    {
        if ($this->_headers === null) {
            $this->_headers = new Header_Collection();
            if (function_exists('getallheaders')) {
                $headers = getallheaders();
                foreach ($headers as $name => $value) {
                    $this->_headers->add($name, $value);
                }
            } elseif (function_exists('http_get_request_headers')) {
                $headers = http_get_request_headers();
                foreach ($headers as $name => $value) {
                    $this->_headers->add($name, $value);
                }
            } else {
                // ['prefix' => length]
                $header_prefixes = ['HTTP_' => 5, 'REDIRECT_HTTP_' => 14];
                foreach ($_SERVER as $name => $value) {
                    foreach ($header_prefixes as $prefix => $length) {
                        if (strncmp($name, $prefix, $length) === 0) {
                            $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, $length)))));
                            $this->_headers->add($name, $value);
                            continue 2;
                        }
                    }
                }
            }
            $this->filter_headers($this->_headers);
        }
        return $this->_headers;
    }
    /**
     * Returns the method of the current request (e.g. GET, POST, HEAD, PUT, PATCH, DELETE).
     * @return string request method, such as GET, POST, HEAD, PUT, PATCH, DELETE.
     * The value returned is turned into upper case.
     */
    public function get_method(): string
    {
        if (isset($_POST[$this->method_param]) && !in_array(strtoupper($_POST[$this->method_param]), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return strtoupper($_POST[$this->method_param]);
        }
        if ($this->headers->has('X-Http-Method-Override')) {
            return strtoupper($this->headers->get('X-Http-Method-Override'));
        }
        if (isset($_SERVER['REQUEST_METHOD'])) {
            return strtoupper($_SERVER['REQUEST_METHOD']);
        }
        return 'GET';
    }
    /**
     * Returns whether this is a GET request.
     * @return bool whether this is a GET request.
     */
    public function get_is_get(): bool
    {
        return $this->get_method() === 'GET';
    }
    /**
     * Returns whether this is an OPTIONS request.
     * @return bool whether this is a OPTIONS request.
     */
    public function get_is_options(): bool
    {
        return $this->get_method() === 'OPTIONS';
    }
    /**
     * Returns whether this is a HEAD request.
     * @return bool whether this is a HEAD request.
     */
    public function get_is_head(): bool
    {
        return $this->get_method() === 'HEAD';
    }
    /**
     * Returns whether this is a POST request.
     * @return bool whether this is a POST request.
     */
    public function get_is_post(): bool
    {
        return $this->get_method() === 'POST';
    }
    /**
     * Returns whether this is a DELETE request.
     * @return bool whether this is a DELETE request.
     */
    public function get_is_delete(): bool
    {
        return $this->get_method() === 'DELETE';
    }
    /**
     * Returns whether this is a PUT request.
     * @return bool whether this is a PUT request.
     */
    public function get_is_put(): bool
    {
        return $this->get_method() === 'PUT';
    }
    /**
     * Returns whether this is a PATCH request.
     * @return bool whether this is a PATCH request.
     */
    public function get_is_patch(): bool
    {
        return $this->get_method() === 'PATCH';
    }
    /**
     * Returns whether this is an AJAX (XMLHttpRequest) request.
     *
     * Note that in case of cross domain requests, browser doesn't set the X-Requested-With header by default:
     * https://stackoverflow.com/questions/8163703/cross-domain-ajax-doesnt-send-x-requested-with-header
     *
     * In case you are using `fetch()`, pass header manually:
     *
     * ```
     * fetch(url, {
     *    method: 'GET',
     *    headers: {'X-Requested-With': 'XMLHttpRequest'}
     * })
     * ```
     *
     * @return bool whether this is an AJAX (XMLHttpRequest) request.
     */
    public function get_is_ajax(): bool
    {
        return $this->headers->get('X-Requested-With') === 'XMLHttpRequest';
    }
    /**
     * Returns whether this is a PJAX request.
     * @return bool whether this is a PJAX request
     */
    public function get_is_pjax(): bool
    {
        return $this->get_is_ajax() && $this->headers->has('X-Pjax');
    }
    /**
     * Returns whether this is an Adobe Flash or Flex request.
     * @return bool whether this is an Adobe Flash or Adobe Flex request.
     */
    public function get_is_flash(): bool
    {
        $user_agent = $this->headers->get('User-Agent', '');
        return stripos($user_agent, 'Shockwave') !== false || stripos($user_agent, 'Flash') !== false;
    }
    private $_raw_body;
    /**
     * Returns the raw HTTP request body.
     * @return string the request body
     */
    public function get_raw_body()
    {
        if ($this->_raw_body === null) {
            $this->_raw_body = file_get_contents('php://input');
        }
        return $this->_raw_body;
    }
    /**
     * Sets the raw HTTP request body, this method is mainly used by test scripts to simulate raw HTTP requests.
     * @param string $rawBody the request body
     */
    public function set_raw_body($raw_body): void
    {
        $this->_raw_body = $raw_body;
    }
    /** @var array|object */
    private $_body_params;
    /**
     * Returns the request parameters given in the request body.
     *
     * Request parameters are determined using the parsers configured in [[parsers]] property.
     * If no parsers are configured for the current [[contentType]] it uses the PHP function `mb_parse_str()`
     * to parse the [[rawBody|request body]].
     * @return array|object the request parameters given in the request body.
     * @throws \yii\base\InvalidConfigException if a registered parser does not implement the [[RequestParserInterface]].
     * @see getMethod()
     * @see getBodyParam()
     * @see setBodyParams()
     */
    public function get_body_params()
    {
        if ($this->_body_params === null) {
            if (isset($_POST[$this->method_param])) {
                $this->_body_params = $_POST;
                unset($this->_body_params[$this->method_param]);
                return $this->_body_params;
            }
            $raw_content_type = $this->get_content_type();
            if (($pos = strpos((string) $raw_content_type, ';')) !== false) {
                // e.g. text/html; charset=UTF-8
                $content_type = substr($raw_content_type, 0, $pos);
            } else {
                $content_type = $raw_content_type;
            }
            if (isset($this->parsers[$content_type])) {
                $parser = Yii::create_object($this->parsers[$content_type]);
                if (!$parser instanceof Request_Parser_Interface) {
                    throw new Invalid_Config_Exception("The '{$content_type}' request parser is invalid. It must implement the yii\\web\\RequestParserInterface.");
                }
                $this->_body_params = $parser->parse($this->get_raw_body(), $raw_content_type);
            } elseif (isset($this->parsers['*'])) {
                $parser = Yii::create_object($this->parsers['*']);
                if (!$parser instanceof Request_Parser_Interface) {
                    throw new Invalid_Config_Exception('The fallback request parser is invalid. It must implement the yii\web\RequestParserInterface.');
                }
                $this->_body_params = $parser->parse($this->get_raw_body(), $raw_content_type);
            } elseif ($this->get_method() === 'POST') {
                // PHP has already parsed the body so we have all params in $_POST
                $this->_body_params = $_POST;
            } else {
                $this->_body_params = [];
                mb_parse_str($this->get_raw_body(), $this->_body_params);
            }
        }
        return $this->_body_params;
    }
    /**
     * Sets the request body parameters.
     *
     * @param array|object $values the request body parameters (name-value pairs)
     * @see getBodyParams()
     */
    public function set_body_params($values): void
    {
        $this->_body_params = $values;
    }
    /**
     * Returns the named request body parameter value.
     *
     * If the parameter does not exist, the second parameter passed to this method will be returned.
     *
     * @param string $name the parameter name
     * @param mixed $defaultValue the default parameter value if the parameter does not exist.
     * @return mixed the parameter value
     * @see getBodyParams()
     * @see setBodyParams()
     */
    public function get_body_param($name, $default_value = null)
    {
        $params = $this->get_body_params();
        if (is_object($params)) {
            // unable to use `ArrayHelper::getValue()` due to different dots in key logic and lack of exception handling
            try {
                return $params->{$name} ?? $default_value;
            } catch (\Exception $e) {
                return $default_value;
            }
        }
        return $params[$name] ?? $default_value;
    }
    /**
     * Returns POST parameter with a given name. If name isn't specified, returns an array of all POST parameters.
     *
     * @param string|null $name the parameter name
     * @param mixed $defaultValue the default parameter value if the parameter does not exist.
     * @return ($name is null ? array|object : mixed)
     */
    public function post($name = null, $default_value = null)
    {
        if ($name === null) {
            return $this->get_body_params();
        }
        return $this->get_body_param($name, $default_value);
    }
    /** @var array */
    private $_query_params;
    /**
     * Returns the request parameters given in the [[queryString]].
     *
     * This method will return the contents of `$_GET` if params where not explicitly set.
     * @return array the request GET parameter values.
     * @see setQueryParams()
     */
    public function get_query_params()
    {
        if ($this->_query_params === null) {
            return $_GET;
        }
        return $this->_query_params;
    }
    /**
     * Sets the request [[queryString]] parameters.
     * @param array $values the request query parameters (name-value pairs)
     * @see getQueryParam()
     * @see getQueryParams()
     */
    public function set_query_params($values): void
    {
        $this->_query_params = $values;
    }
    /**
     * Returns GET parameter with a given name. If name isn't specified, returns an array of all GET parameters.
     *
     * @param string|null $name the parameter name
     * @param mixed $defaultValue the default parameter value if the parameter does not exist.
     * @return ($name is null ? array : mixed)
     */
    public function get($name = null, $default_value = null)
    {
        if ($name === null) {
            return $this->get_query_params();
        }
        return $this->get_query_param($name, $default_value);
    }
    /**
     * Returns the named GET parameter value.
     * If the GET parameter does not exist, the second parameter passed to this method will be returned.
     * @param string $name the GET parameter name.
     * @param mixed $defaultValue the default parameter value if the GET parameter does not exist.
     * @return mixed the GET parameter value
     * @see getBodyParam()
     */
    public function get_query_param($name, $default_value = null)
    {
        $params = $this->get_query_params();
        return $params[$name] ?? $default_value;
    }
    private ?string $_host_info = null;
    private $_host_name;
    /**
     * Returns the schema and host part of the current request URL.
     *
     * The returned URL does not have an ending slash.
     *
     * By default this value is based on the user request information. This method will
     * return the value of `$_SERVER['HTTP_HOST']` if it is available or `$_SERVER['SERVER_NAME']` if not.
     * You may want to check out the [PHP documentation](https://www.php.net/manual/en/reserved.variables.server.php)
     * for more information on these variables.
     *
     * You may explicitly specify it by setting the [[setHostInfo()|hostInfo]] property.
     *
     * > Warning: Dependent on the server configuration this information may not be
     * > reliable and [may be faked by the user sending the HTTP request](https://www.acunetix.com/vulnerabilities/web/host-header-attack).
     * > If the webserver is configured to serve the same site independent of the value of
     * > the `Host` header, this value is not reliable. In such situations you should either
     * > fix your webserver configuration or explicitly set the value by setting the [[setHostInfo()|hostInfo]] property.
     * > If you don't have access to the server configuration, you can setup [[\yii\filters\HostControl]] filter at
     * > application level in order to protect against such kind of attack.
     *
     * @return string|null schema and hostname part (with port number if needed) of the request URL
     * (e.g. `https://www.yiiframework.com`), null if can't be obtained from `$_SERVER` and wasn't set.
     * @see setHostInfo()
     */
    public function get_host_info()
    {
        if ($this->_host_info === null) {
            $secure = $this->get_is_secure_connection();
            $http = $secure ? 'https' : 'http';
            if ($this->get_secure_forwarded_header_trusted_part('host') !== null) {
                $this->_host_info = $http . '://' . $this->get_secure_forwarded_header_trusted_part('host');
            } elseif ($this->headers->has('X-Forwarded-Host')) {
                $this->_host_info = $http . '://' . trim(explode(',', $this->headers->get('X-Forwarded-Host'))[0]);
            } elseif ($this->headers->has('X-Original-Host')) {
                $this->_host_info = $http . '://' . trim(explode(',', $this->headers->get('X-Original-Host'))[0]);
            } elseif ($this->headers->has('Host')) {
                $this->_host_info = $http . '://' . $this->headers->get('Host');
            } elseif (isset($_SERVER['SERVER_NAME'])) {
                $this->_host_info = $http . '://' . $_SERVER['SERVER_NAME'];
                $port = $secure ? $this->get_secure_port() : $this->get_port();
                if ($port !== 80 && !$secure || $port !== 443 && $secure) {
                    $this->_host_info .= ':' . $port;
                }
            }
        }
        return $this->_host_info;
    }
    /**
     * Sets the schema and host part of the application URL.
     * This setter is provided in case the schema and hostname cannot be determined
     * on certain Web servers.
     * @param string|null $value the schema and host part of the application URL. The trailing slashes will be removed.
     * @see getHostInfo() for security related notes on this property.
     */
    public function set_host_info($value): void
    {
        $this->_host_name = null;
        $this->_host_info = $value === null ? null : rtrim($value, '/');
    }
    /**
     * Returns the host part of the current request URL.
     * Value is calculated from current [[getHostInfo()|hostInfo]] property.
     *
     * > Warning: The content of this value may not be reliable, dependent on the server
     * > configuration. Please refer to [[getHostInfo()]] for more information.
     *
     * @return string|null hostname part of the request URL (e.g. `www.yiiframework.com`)
     * @see getHostInfo()
     * @since 2.0.10
     */
    public function get_host_name()
    {
        if ($this->_host_name === null) {
            $this->_host_name = parse_url((string) $this->get_host_info(), PHP_URL_HOST);
        }
        return $this->_host_name;
    }
    private $_base_url;
    /**
     * Returns the relative URL for the application.
     * This is similar to [[scriptUrl]] except that it does not include the script file name,
     * and the ending slashes are removed.
     * @return string the relative URL for the application
     * @see setScriptUrl()
     */
    public function get_base_url()
    {
        if ($this->_base_url === null) {
            $this->_base_url = rtrim(dirname($this->get_script_url()), '\/');
        }
        return $this->_base_url;
    }
    /**
     * Sets the relative URL for the application.
     * By default the URL is determined based on the entry script URL.
     * This setter is provided in case you want to change this behavior.
     * @param string $value the relative URL for the application
     */
    public function set_base_url($value): void
    {
        $this->_base_url = $value;
    }
    private $_script_url;
    /**
     * Returns the relative URL of the entry script.
     * The implementation of this method referenced Zend_Controller_Request_Http in Zend Framework.
     * @return string the relative URL of the entry script.
     * @throws InvalidConfigException if unable to determine the entry script URL
     */
    public function get_script_url()
    {
        if ($this->_script_url === null) {
            $script_file = $this->get_script_file();
            $script_name = basename($script_file);
            if (isset($_SERVER['SCRIPT_NAME']) && basename($_SERVER['SCRIPT_NAME']) === $script_name) {
                $this->_script_url = $_SERVER['SCRIPT_NAME'];
            } elseif (isset($_SERVER['PHP_SELF']) && basename($_SERVER['PHP_SELF']) === $script_name) {
                $this->_script_url = $_SERVER['PHP_SELF'];
            } elseif (isset($_SERVER['ORIG_SCRIPT_NAME']) && basename($_SERVER['ORIG_SCRIPT_NAME']) === $script_name) {
                $this->_script_url = $_SERVER['ORIG_SCRIPT_NAME'];
            } elseif (isset($_SERVER['PHP_SELF']) && ($pos = strpos($_SERVER['PHP_SELF'], '/' . $script_name)) !== false) {
                $this->_script_url = substr($_SERVER['SCRIPT_NAME'], 0, $pos) . '/' . $script_name;
            } elseif (!empty($_SERVER['DOCUMENT_ROOT']) && strpos($script_file, (string) $_SERVER['DOCUMENT_ROOT']) === 0) {
                $this->_script_url = str_replace([$_SERVER['DOCUMENT_ROOT'], '\\'], ['', '/'], $script_file);
            } else {
                throw new Invalid_Config_Exception('Unable to determine the entry script URL.');
            }
        }
        return $this->_script_url;
    }
    /**
     * Sets the relative URL for the application entry script.
     * This setter is provided in case the entry script URL cannot be determined
     * on certain Web servers.
     * @param string $value the relative URL for the application entry script.
     */
    public function set_script_url($value): void
    {
        $this->_script_url = $value === null ? null : '/' . trim($value, '/');
    }
    private $_script_file;
    /**
     * Returns the entry script file path.
     * The default implementation will simply return `$_SERVER['SCRIPT_FILENAME']`.
     * @return string the entry script file path
     * @throws InvalidConfigException
     */
    public function get_script_file()
    {
        if (isset($this->_script_file)) {
            return $this->_script_file;
        }
        if (isset($_SERVER['SCRIPT_FILENAME'])) {
            return $_SERVER['SCRIPT_FILENAME'];
        }
        throw new Invalid_Config_Exception('Unable to determine the entry script file path.');
    }
    /**
     * Sets the entry script file path.
     * The entry script file path normally can be obtained from `$_SERVER['SCRIPT_FILENAME']`.
     * If your server configuration does not return the correct value, you may configure
     * this property to make it right.
     * @param string $value the entry script file path.
     */
    public function set_script_file($value): void
    {
        $this->_script_file = $value;
    }
    private $_path_info;
    /**
     * Returns the path info of the currently requested URL.
     * A path info refers to the part that is after the entry script and before the question mark (query string).
     * The starting and ending slashes are both removed.
     * @return string part of the request URL that is after the entry script and before the question mark.
     * Note, the returned path info is already URL-decoded.
     * @throws InvalidConfigException if the path info cannot be determined due to unexpected server configuration
     */
    public function get_path_info()
    {
        if ($this->_path_info === null) {
            $this->_path_info = $this->resolve_path_info();
        }
        return $this->_path_info;
    }
    /**
     * Sets the path info of the current request.
     * This method is mainly provided for testing purpose.
     * @param string $value the path info of the current request
     */
    public function set_path_info($value): void
    {
        $this->_path_info = $value === null ? null : ltrim($value, '/');
    }
    /**
     * Resolves the path info part of the currently requested URL.
     * A path info refers to the part that is after the entry script and before the question mark (query string).
     * The starting slashes are both removed (ending slashes will be kept).
     * @return string part of the request URL that is after the entry script and before the question mark.
     * Note, the returned path info is decoded.
     * @throws InvalidConfigException if the path info cannot be determined due to unexpected server configuration
     */
    protected function resolve_path_info(): string
    {
        $path_info = $this->get_url();
        if (($pos = strpos($path_info, '?')) !== false) {
            $path_info = substr($path_info, 0, $pos);
        }
        $path_info = urldecode($path_info);
        // try to encode in UTF8 if not so
        // https://www.w3.org/International/questions/qa-forms-utf-8.en.html
        if (!preg_match('%^(?:
            [\x09\x0A\x0D\x20-\x7E]              # ASCII
            | [\xC2-\xDF][\x80-\xBF]             # non-overlong 2-byte
            | \xE0[\xA0-\xBF][\x80-\xBF]         # excluding overlongs
            | [\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}  # straight 3-byte
            | \xED[\x80-\x9F][\x80-\xBF]         # excluding surrogates
            | \xF0[\x90-\xBF][\x80-\xBF]{2}      # planes 1-3
            | [\xF1-\xF3][\x80-\xBF]{3}          # planes 4-15
            | \xF4[\x80-\x8F][\x80-\xBF]{2}      # plane 16
            )*$%xs', $path_info)) {
            $path_info = $this->utf8Encode($path_info);
        }
        $script_url = $this->get_script_url();
        $base_url = $this->get_base_url();
        if (strpos($path_info, $script_url) === 0) {
            $path_info = substr($path_info, strlen($script_url));
        } elseif ($base_url === '' || strpos($path_info, $base_url) === 0) {
            $path_info = substr($path_info, strlen($base_url));
        } elseif (isset($_SERVER['PHP_SELF']) && strpos($_SERVER['PHP_SELF'], $script_url) === 0) {
            $path_info = substr($_SERVER['PHP_SELF'], strlen($script_url));
        } else {
            throw new Invalid_Config_Exception('Unable to determine the path info of the current request.');
        }
        if (strncmp($path_info, '/', 1) === 0) {
            return substr($path_info, 1);
        }
        return $path_info;
    }
    /**
     * Encodes an ISO-8859-1 string to UTF-8
     * @return string the UTF-8 translation of `s`.
     * @see https://github.com/symfony/polyfill-php72/blob/master/Php72.php#L24
     * @phpcs:disable Generic.Formatting.DisallowMultipleStatements.SameLine
     * @phpcs:disable Squiz.WhiteSpace.ScopeClosingBrace.ContentBefore
     */
    private function utf8Encode(string $s): string
    {
        $s .= $s;
        $len = \strlen($s);
        for ($i = $len >> 1, $j = 0; $i < $len; ++$i, ++$j) {
            switch (true) {
                case $s[$i] < "\x80":
                    $s[$j] = $s[$i];
                    break;
                case $s[$i] < "\xc0":
                    $s[$j] = "\xc2";
                    $s[++$j] = $s[$i];
                    break;
                default:
                    $s[$j] = "\xc3";
                    $s[++$j] = \chr(\ord($s[$i]) - 64);
                    break;
            }
        }
        return substr($s, 0, $j);
    }
    /**
     * Returns the currently requested absolute URL.
     * This is a shortcut to the concatenation of [[hostInfo]] and [[url]].
     * @return string the currently requested absolute URL.
     */
    public function get_absolute_url(): string
    {
        return $this->get_host_info() . $this->get_url();
    }
    private $_url;
    /**
     * Returns the currently requested relative URL.
     * This refers to the portion of the URL that is after the [[hostInfo]] part.
     * It includes the [[queryString]] part if any.
     * @return string the currently requested relative URL. Note that the URI returned may be URL-encoded depending on the client.
     * @throws InvalidConfigException if the URL cannot be determined due to unusual server configuration
     */
    public function get_url()
    {
        if ($this->_url === null) {
            $this->_url = $this->resolve_request_uri();
        }
        return $this->_url;
    }
    /**
     * Sets the currently requested relative URL.
     * The URI must refer to the portion that is after [[hostInfo]].
     * Note that the URI should be URL-encoded.
     * @param string $value the request URI to be set
     */
    public function set_url($value): void
    {
        $this->_url = $value;
    }
    /**
     * Resolves the request URI portion for the currently requested URL.
     * This refers to the portion that is after the [[hostInfo]] part. It includes the [[queryString]] part if any.
     * The implementation of this method referenced Zend_Controller_Request_Http in Zend Framework.
     * @return string|bool the request URI portion for the currently requested URL.
     * Note that the URI returned may be URL-encoded depending on the client.
     * @throws InvalidConfigException if the request URI cannot be determined due to unusual server configuration
     */
    protected function resolve_request_uri()
    {
        if ($this->headers->has('X-Rewrite-Url')) {
            // IIS
            $request_uri = $this->headers->get('X-Rewrite-Url');
        } elseif (isset($_SERVER['REQUEST_URI'])) {
            $request_uri = $_SERVER['REQUEST_URI'];
            if ($request_uri !== '' && $request_uri[0] !== '/') {
                $request_uri = preg_replace('/^(http|https):\/\/[^\/]+/i', '', $request_uri);
            }
        } elseif (isset($_SERVER['ORIG_PATH_INFO'])) {
            // IIS 5.0 CGI
            $request_uri = $_SERVER['ORIG_PATH_INFO'];
            if (!empty($_SERVER['QUERY_STRING'])) {
                $request_uri .= '?' . $_SERVER['QUERY_STRING'];
            }
        } else {
            throw new Invalid_Config_Exception('Unable to determine the request URI.');
        }
        return $request_uri;
    }
    /**
     * Returns part of the request URL that is after the question mark.
     * @return string part of the request URL that is after the question mark
     */
    public function get_query_string()
    {
        return $_SERVER['QUERY_STRING'] ?? '';
    }
    /**
     * Return if the request is sent via secure channel (https).
     * @return bool if the request is sent via secure channel (https)
     */
    public function get_is_secure_connection()
    {
        if (isset($_SERVER['HTTPS']) && (strcasecmp($_SERVER['HTTPS'], 'on') === 0 || $_SERVER['HTTPS'] == 1)) {
            return true;
        }
        if (($proto = $this->get_secure_forwarded_header_trusted_part('proto')) !== null) {
            return strcasecmp($proto, 'https') === 0;
        }
        foreach ($this->secure_protocol_headers as $header => $values) {
            if (($header_value = $this->headers->get($header)) !== null) {
                foreach ($values as $value) {
                    if (strcasecmp($header_value, $value) === 0) {
                        return true;
                    }
                }
            }
        }
        return false;
    }
    /**
     * Returns the server name.
     * @return string|null server name, null if not available
     */
    public function get_server_name()
    {
        return $_SERVER['SERVER_NAME'] ?? null;
    }
    /**
     * Returns the server port number. If a port is specified via a forwarding header (e.g. 'X-Forwarded-Port')
     * and the remote host is a "trusted host" the that port will be used (see [[portHeaders]]),
     * otherwise the default server port will be returned.
     * @return int|null server port number, null if not available
     * @see portHeaders
     */
    public function get_server_port(): ?int
    {
        foreach ($this->port_headers as $port_header) {
            if ($this->headers->has($port_header)) {
                $port = $this->headers->get($port_header);
                if ($port !== null) {
                    return (int) $port;
                }
            }
        }
        return isset($_SERVER['SERVER_PORT']) ? (int) $_SERVER['SERVER_PORT'] : null;
    }
    /**
     * Returns the URL referrer.
     * @return string|null URL referrer, null if not available
     */
    public function get_referrer()
    {
        return $this->headers->get('Referer');
    }
    /**
     * Returns the URL origin of a CORS request.
     *
     * The return value is taken from the `Origin` [[getHeaders()|header]] sent by the browser.
     *
     * Note that the origin request header indicates where a fetch originates from.
     * It doesn't include any path information, but only the server name.
     * It is sent with a CORS requests, as well as with POST requests.
     * It is similar to the referer header, but, unlike this header, it doesn't disclose the whole path.
     * Please refer to <https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Origin> for more information.
     *
     * @return string|null URL origin of a CORS request, `null` if not available.
     * @see getHeaders()
     * @since 2.0.13
     */
    public function get_origin()
    {
        return $this->get_headers()->get('origin');
    }
    /**
     * Returns the user agent.
     * @return string|null user agent, null if not available
     */
    public function get_user_agent()
    {
        return $this->headers->get('User-Agent');
    }
    /**
     * Returns the user IP address from [[ipHeaders]].
     * @return string|null user IP address, null if not available
     * @see ipHeaders
     * @since 2.0.28
     */
    protected function get_user_ip_from_ip_headers()
    {
        $ip = $this->get_secure_forwarded_header_trusted_part('for');
        if ($ip !== null && preg_match('/^\[?(?P<ip>(?:(?:(?:[0-9a-f]{1,4}:){1,6}(?:[0-9a-f]{1,4})?(?:(?::[0-9a-f]{1,4}){1,6}))|(?:\d{1,3}\.){3}\d{1,3}))\]?(?::(?P<port>\d+))?$/', $ip, $matches)) {
            $ip = $this->get_user_ip_from_ip_header($matches['ip']);
            if ($ip !== null) {
                return $ip;
            }
        }
        foreach ($this->ip_headers as $ip_header) {
            if ($this->headers->has($ip_header)) {
                $ip = $this->get_user_ip_from_ip_header($this->headers->get($ip_header));
                if ($ip !== null) {
                    return $ip;
                }
            }
        }
        return null;
    }
    private $_ip;
    /**
     * Returns the user IP address.
     * The IP is determined using headers and / or `$_SERVER` variables.
     * @return string|null user IP address, null if not available
     */
    public function get_user_ip()
    {
        if ($this->_ip === null) {
            $this->_ip = $this->get_user_ip_from_ip_headers();
            if ($this->_ip === null) {
                $this->_ip = $this->get_remote_ip();
            }
        }
        return $this->_ip;
    }
    /**
     * Return user IP's from IP header.
     *
     * @param string $ips comma separated IP list
     * @return string|null IP as string. Null is returned if IP can not be determined from header.
     * @see getUserHost()
     * @see ipHeaders
     * @see getTrustedHeaders()
     * @since 2.0.28
     */
    protected function get_user_ip_from_ip_header($ips)
    {
        $ips = trim($ips);
        if ($ips === '') {
            return null;
        }
        $ips = preg_split('/\s*,\s*/', $ips, -1, PREG_SPLIT_NO_EMPTY);
        krsort($ips);
        $validator = $this->get_ip_validator();
        $result_ip = null;
        foreach ($ips as $ip) {
            $validator->set_ranges('any');
            if (!$validator->validate($ip)) {
                break;
            }
            $result_ip = $ip;
            $is_trusted = false;
            foreach ($this->trusted_hosts as $trusted_cidr => $trusted_cidr_or_headers) {
                if (!is_array($trusted_cidr_or_headers)) {
                    $trusted_cidr = $trusted_cidr_or_headers;
                }
                $validator->set_ranges($trusted_cidr);
                if ($validator->validate($ip)) {
                    $is_trusted = true;
                    break;
                }
            }
            if (!$is_trusted) {
                break;
            }
        }
        return $result_ip;
    }
    /**
     * Returns the user host name.
     * The HOST is determined using headers and / or `$_SERVER` variables.
     * @return string|null user host name, null if not available
     */
    public function get_user_host()
    {
        $user_ip = $this->get_user_ip_from_ip_headers();
        if ($user_ip === null) {
            return $this->get_remote_host();
        }
        return gethostbyaddr($user_ip);
    }
    /**
     * Returns the IP on the other end of this connection.
     * This is always the next hop, any headers are ignored.
     * @return string|null remote IP address, `null` if not available.
     * @since 2.0.13
     */
    public function get_remote_ip()
    {
        return $_SERVER['REMOTE_ADDR'] ?? null;
    }
    /**
     * Returns the host name of the other end of this connection.
     * This is always the next hop, any headers are ignored.
     * @return string|null remote host name, `null` if not available
     * @see getUserHost()
     * @see getRemoteIP()
     * @since 2.0.13
     */
    public function get_remote_host()
    {
        return $_SERVER['REMOTE_HOST'] ?? null;
    }
    /**
     * @return string|null the username sent via HTTP authentication, `null` if the username is not given
     * @see getAuthCredentials() to get both username and password in one call
     */
    public function get_auth_user()
    {
        return $this->get_auth_credentials()[0];
    }
    /**
     * @return string|null the password sent via HTTP authentication, `null` if the password is not given
     * @see getAuthCredentials() to get both username and password in one call
     */
    public function get_auth_password()
    {
        return $this->get_auth_credentials()[1];
    }
    /**
     * @return array that contains exactly two elements:
     * - 0: the username sent via HTTP authentication, `null` if the username is not given
     * - 1: the password sent via HTTP authentication, `null` if the password is not given
     * @see getAuthUser() to get only username
     * @see getAuthPassword() to get only password
     * @since 2.0.13
     */
    public function get_auth_credentials(): array
    {
        $username = $_SERVER['PHP_AUTH_USER'] ?? null;
        $password = $_SERVER['PHP_AUTH_PW'] ?? null;
        if ($username !== null || $password !== null) {
            return [$username, $password];
        }
        /**
         * Apache with php-cgi does not pass HTTP Basic authentication to PHP by default.
         * To make it work, add one of the following lines to to your .htaccess file:
         *
         * SetEnvIf Authorization .+ HTTP_AUTHORIZATION=$0
         * --OR--
         * RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
         */
        $auth_token = $this->get_headers()->get('Authorization');
        if ($auth_token !== null && strncasecmp($auth_token, 'basic', 5) === 0) {
            $parts = array_map(fn($value) => strlen($value) === 0 ? null : $value, explode(':', base64_decode(mb_substr($auth_token, 6)), 2));
            if (count($parts) < 2) {
                return [$parts[0], null];
            }
            return $parts;
        }
        return [null, null];
    }
    private $_port;
    /**
     * Returns the port to use for insecure requests.
     * Defaults to 80, or the port specified by the server if the current
     * request is insecure.
     * @return int port number for insecure requests.
     * @see setPort()
     */
    public function get_port()
    {
        if ($this->_port === null) {
            $server_port = $this->get_server_port();
            $this->_port = !$this->get_is_secure_connection() && $server_port !== null ? $server_port : 80;
        }
        return $this->_port;
    }
    /**
     * Sets the port to use for insecure requests.
     * This setter is provided in case a custom port is necessary for certain
     * server configurations.
     * @param int $value port number.
     */
    public function set_port($value): void
    {
        if ($value != $this->_port) {
            $this->_port = (int) $value;
            $this->_host_info = null;
        }
    }
    private $_secure_port;
    /**
     * Returns the port to use for secure requests.
     * Defaults to 443, or the port specified by the server if the current
     * request is secure.
     * @return int port number for secure requests.
     * @see setSecurePort()
     */
    public function get_secure_port()
    {
        if ($this->_secure_port === null) {
            $server_port = $this->get_server_port();
            $this->_secure_port = $this->get_is_secure_connection() && $server_port !== null ? $server_port : 443;
        }
        return $this->_secure_port;
    }
    /**
     * Sets the port to use for secure requests.
     * This setter is provided in case a custom port is necessary for certain
     * server configurations.
     * @param int $value port number.
     */
    public function set_secure_port($value): void
    {
        if ($value != $this->_secure_port) {
            $this->_secure_port = (int) $value;
            $this->_host_info = null;
        }
    }
    private $_content_types;
    /**
     * Returns the content types acceptable by the end user.
     *
     * This is determined by the `Accept` HTTP header. For example,
     *
     * ```
     * $_SERVER['HTTP_ACCEPT'] = 'text/plain; q=0.5, application/json; version=1.0, application/xml; version=2.0;';
     * $types = $request->getAcceptableContentTypes();
     * print_r($types);
     * // displays:
     * // [
     * //     'application/json' => ['q' => 1, 'version' => '1.0'],
     * //      'application/xml' => ['q' => 1, 'version' => '2.0'],
     * //           'text/plain' => ['q' => 0.5],
     * // ]
     * ```
     *
     * @return array the content types ordered by the quality score. Types with the highest scores
     * will be returned first. The array keys are the content types, while the array values
     * are the corresponding quality score and other parameters as given in the header.
     */
    public function get_acceptable_content_types()
    {
        if ($this->_content_types === null) {
            if ($this->headers->get('Accept') !== null) {
                $this->_content_types = $this->parse_accept_header($this->headers->get('Accept'));
            } else {
                $this->_content_types = [];
            }
        }
        return $this->_content_types;
    }
    /**
     * Sets the acceptable content types.
     * Please refer to [[getAcceptableContentTypes()]] on the format of the parameter.
     * @param array $value the content types that are acceptable by the end user. They should
     * be ordered by the preference level.
     * @see getAcceptableContentTypes()
     * @see parseAcceptHeader()
     */
    public function set_acceptable_content_types($value): void
    {
        $this->_content_types = $value;
    }
    /**
     * Returns request content-type
     * The Content-Type header field indicates the MIME type of the data
     * contained in [[getRawBody()]] or, in the case of the HEAD method, the
     * media type that would have been sent had the request been a GET.
     * For the MIME-types the user expects in response, see [[acceptableContentTypes]].
     * @return string request content-type. Empty string is returned if this information is not available.
     * @link https://tools.ietf.org/html/rfc2616#section-14.17
     * HTTP 1.1 header field definitions
     */
    public function get_content_type()
    {
        //fix bug https://bugs.php.net/bug.php?id=66606
        return $_SERVER['CONTENT_TYPE'] ?? ($this->headers->get('Content-Type') ?: '');
    }
    private $_languages;
    /**
     * Returns the languages acceptable by the end user.
     * This is determined by the `Accept-Language` HTTP header.
     * @return array the languages ordered by the preference level. The first element
     * represents the most preferred language.
     */
    public function get_acceptable_languages()
    {
        if ($this->_languages === null) {
            if ($this->headers->has('Accept-Language')) {
                $this->_languages = array_keys($this->parse_accept_header($this->headers->get('Accept-Language')));
            } else {
                $this->_languages = [];
            }
        }
        return $this->_languages;
    }
    /**
     * @param array $value the languages that are acceptable by the end user. They should
     * be ordered by the preference level.
     */
    public function set_acceptable_languages($value): void
    {
        $this->_languages = $value;
    }
    /**
     * Parses the given `Accept` (or `Accept-Language`) header.
     *
     * This method will return the acceptable values with their quality scores and the corresponding parameters
     * as specified in the given `Accept` header. The array keys of the return value are the acceptable values,
     * while the array values consisting of the corresponding quality scores and parameters. The acceptable
     * values with the highest quality scores will be returned first. For example,
     *
     * ```
     * $header = 'text/plain; q=0.5, application/json; version=1.0, application/xml; version=2.0;';
     * $accepts = $request->parseAcceptHeader($header);
     * print_r($accepts);
     * // displays:
     * // [
     * //     'application/json' => ['q' => 1, 'version' => '1.0'],
     * //      'application/xml' => ['q' => 1, 'version' => '2.0'],
     * //           'text/plain' => ['q' => 0.5],
     * // ]
     * ```
     *
     * @param string $header the header to be parsed
     * @return array the acceptable values ordered by their quality score. The values with the highest scores
     * will be returned first.
     */
    public function parse_accept_header($header): array
    {
        $accepts = [];
        foreach (explode(',', $header) as $i => $part) {
            $params = preg_split('/\s*;\s*/', trim($part), -1, PREG_SPLIT_NO_EMPTY);
            if (empty($params)) {
                continue;
            }
            $values = ['q' => [$i, array_shift($params), 1]];
            foreach ($params as $param) {
                if (strpos($param, '=') !== false) {
                    [$key, $value] = explode('=', $param, 2);
                    if ($key === 'q') {
                        $values['q'][2] = (float) $value;
                    } else {
                        $values[$key] = $value;
                    }
                } else {
                    $values[] = $param;
                }
            }
            $accepts[] = $values;
        }
        usort($accepts, function (array $a, array $b): int {
            $a = $a['q'];
            // index, name, q
            $b = $b['q'];
            if ($a[2] > $b[2]) {
                return -1;
            }
            if ($a[2] < $b[2]) {
                return 1;
            }
            if ($a[1] === $b[1]) {
                return $a[0] > $b[0] ? 1 : -1;
            }
            if ($a[1] === '*/*') {
                return 1;
            }
            if ($b[1] === '*/*') {
                return -1;
            }
            $wa = $a[1][strlen($a[1]) - 1] === '*';
            $wb = $b[1][strlen($b[1]) - 1] === '*';
            if ($wa xor $wb) {
                return $wa ? 1 : -1;
            }
            return $a[0] > $b[0] ? 1 : -1;
        });
        $result = [];
        foreach ($accepts as $accept) {
            $name = $accept['q'][1];
            $accept['q'] = $accept['q'][2];
            $result[$name] = $accept;
        }
        return $result;
    }
    /**
     * Returns the user-preferred language that should be used by this application.
     * The language resolution is based on the user preferred languages and the languages
     * supported by the application. The method will try to find the best match.
     * @param array $languages a list of the languages supported by the application. If this is empty, the current
     * application language will be returned without further processing.
     * @return string the language that the application should use.
     */
    public function get_preferred_language(array $languages = [])
    {
        if (empty($languages)) {
            return Yii::$app->language;
        }
        foreach ($this->get_acceptable_languages() as $acceptable_language) {
            $acceptable_language = str_replace('_', '-', strtolower($acceptable_language));
            foreach ($languages as $language) {
                $normalized_language = str_replace('_', '-', strtolower($language));
                if ($normalized_language === $acceptable_language || strpos($acceptable_language, $normalized_language . '-') === 0 || strpos($normalized_language, $acceptable_language . '-') === 0) {
                    return $language;
                }
            }
        }
        return reset($languages);
    }
    /**
     * Gets the Etags.
     *
     * @return array The entity tags
     */
    public function get_e_tags()
    {
        if ($this->headers->has('If-None-Match')) {
            return preg_split('/[\s,]+/', str_replace('-gzip', '', $this->headers->get('If-None-Match')), -1, PREG_SPLIT_NO_EMPTY);
        }
        return [];
    }
    /**
     * Returns the cookie collection.
     *
     * Through the returned cookie collection, you may access a cookie using the following syntax:
     *
     * ```
     * $cookie = $request->cookies['name']
     * if ($cookie !== null) {
     *     $value = $cookie->value;
     * }
     *
     * // alternatively
     * $value = $request->cookies->getValue('name');
     * ```
     *
     * @return CookieCollection the cookie collection.
     */
    public function get_cookies()
    {
        if ($this->_cookies === null) {
            $this->_cookies = new Cookie_Collection($this->load_cookies(), ['readOnly' => true]);
        }
        return $this->_cookies;
    }
    /**
     * Converts `$_COOKIE` into an array of [[Cookie]].
     * @return array the cookies obtained from request
     * @throws InvalidConfigException if [[cookieValidationKey]] is not set when [[enableCookieValidation]] is true
     */
    protected function load_cookies(): array
    {
        $cookies = [];
        if ($this->enable_cookie_validation) {
            if ($this->cookie_validation_key == '') {
                throw new Invalid_Config_Exception(get_class($this) . '::cookieValidationKey must be configured with a secret key.');
            }
            foreach ($_COOKIE as $name => $value) {
                if (!is_string($value)) {
                    continue;
                }
                $data = Yii::$app->get_security()->validate_data($value, $this->cookie_validation_key);
                if ($data === false) {
                    continue;
                }
                if (defined('PHP_VERSION_ID') && PHP_VERSION_ID >= 70000) {
                    $data = @unserialize($data, ['allowed_classes' => false]);
                } else {
                    $data = @unserialize($data);
                }
                if (is_array($data) && isset($data[0], $data[1]) && $data[0] === $name) {
                    $cookies[$name] = Yii::create_object(['class' => 'yii\web\Cookie', 'name' => $name, 'value' => $data[1], 'expire' => null]);
                }
            }
        } else {
            foreach ($_COOKIE as $name => $value) {
                $cookies[$name] = Yii::create_object(['class' => 'yii\web\Cookie', 'name' => $name, 'value' => $value, 'expire' => null]);
            }
        }
        return $cookies;
    }
    private $_csrf_token;
    /**
     * Returns the token used to perform CSRF validation.
     *
     * This token is generated in a way to prevent [BREACH attacks](https://en.wikipedia.org/wiki/BREACH). It may be passed
     * along via a hidden field of an HTML form or an HTTP header value to support CSRF validation.
     * @param bool $regenerate whether to regenerate CSRF token. When this parameter is true, each time
     * this method is called, a new CSRF token will be generated and persisted (in session or cookie).
     * @return null|string the token used to perform CSRF validation. Null is returned if the [[validateCsrfHeaderOnly]] is true.
     */
    public function get_csrf_token($regenerate = false)
    {
        if ($this->validate_csrf_header_only) {
            return null;
        }
        if ($this->_csrf_token === null || $regenerate) {
            $token = $this->load_csrf_token();
            if ($regenerate || empty($token)) {
                $token = $this->generate_csrf_token();
            }
            $this->_csrf_token = Yii::$app->security->mask_token($token);
        }
        return $this->_csrf_token;
    }
    /**
     * Loads the CSRF token from cookie or session.
     * @return string|null the CSRF token loaded from cookie or session. Null is returned if the cookie or session
     * does not have CSRF token.
     */
    protected function load_csrf_token()
    {
        if ($this->enable_csrf_cookie) {
            return $this->get_cookies()->get_value($this->csrf_param);
        }
        return Yii::$app->get_session()->get($this->csrf_param);
    }
    /**
     * Generates an unmasked random token used to perform CSRF validation.
     * @return string the random token for CSRF validation.
     */
    protected function generate_csrf_token()
    {
        $token = Yii::$app->get_security()->generate_random_string();
        if ($this->enable_csrf_cookie) {
            $cookie = $this->create_csrf_cookie($token);
            Yii::$app->get_response()->get_cookies()->add($cookie);
        } else {
            Yii::$app->get_session()->set($this->csrf_param, $token);
        }
        return $token;
    }
    /**
     * @return string|null the CSRF token sent via [[csrfHeader]] by browser. Null is returned if no such header is sent.
     */
    public function get_csrf_token_from_header()
    {
        return $this->headers->get($this->csrf_header);
    }
    /**
     * Creates a cookie with a randomly generated CSRF token.
     * Initial values specified in [[csrfCookie]] will be applied to the generated cookie.
     * @param string $token the CSRF token
     * @return Cookie the generated cookie
     * @see enableCsrfValidation
     */
    protected function create_csrf_cookie($token)
    {
        $options = $this->csrf_cookie;
        return Yii::create_object(array_merge($options, ['class' => 'yii\web\Cookie', 'name' => $this->csrf_param, 'value' => $token]));
    }
    /**
     * Performs the CSRF validation.
     *
     * This method will validate the user-provided CSRF token by comparing it with the one stored in cookie or session.
     * This method is mainly called in [[Controller::beforeAction()]].
     *
     * Note that the method will NOT perform CSRF validation if [[enableCsrfValidation]] is false or the HTTP method
     * is among GET, HEAD or OPTIONS.
     *
     * @param string|null $clientSuppliedToken the user-provided CSRF token to be validated. If null, the token will be retrieved from
     * the [[csrfParam]] POST field or HTTP header.
     * This parameter is available since version 2.0.4.
     * @return bool whether CSRF token is valid. If [[enableCsrfValidation]] is false, this method will return true.
     */
    public function validate_csrf_token($client_supplied_token = null)
    {
        $method = $this->get_method();
        if ($this->validate_csrf_header_only) {
            return in_array($method, $this->csrf_header_unsafe_methods, true) ? $this->headers->has($this->csrf_header) : true;
        }
        if (!$this->enable_csrf_validation || in_array($method, $this->csrf_token_safe_methods, true)) {
            return true;
        }
        $true_token = $this->get_csrf_token();
        if ($client_supplied_token !== null) {
            return $this->validate_csrf_token_internal($client_supplied_token, $true_token);
        }
        if ($this->validate_csrf_token_internal($this->get_body_param($this->csrf_param), $true_token)) {
            return true;
        }
        return $this->validate_csrf_token_internal($this->get_csrf_token_from_header(), $true_token);
    }
    /**
     * Validates CSRF token.
     *
     * @param string $clientSuppliedToken The masked client-supplied token.
     * @param string $trueToken The masked true token.
     * @return bool
     */
    private function validate_csrf_token_internal($client_supplied_token, $true_token)
    {
        if (!is_string($client_supplied_token)) {
            return false;
        }
        $security = Yii::$app->security;
        return $security->compare_string($security->unmask_token($client_supplied_token), $security->unmask_token($true_token));
    }
    /**
     * Gets first `Forwarded` header value for token
     *
     * @param string $token Header token
     *
     * @return string|null
     *
     * @since 2.0.31
     */
    protected function get_secure_forwarded_header_trusted_part($token)
    {
        $token = strtolower($token);
        if ($parts = $this->get_secure_forwarded_header_trusted_parts()) {
            $last_element = array_pop($parts);
            if ($last_element && isset($last_element[$token])) {
                return $last_element[$token];
            }
        }
        return null;
    }
    private ?array $_secure_forwarded_header_trusted_parts = null;
    /**
     * Gets only trusted `Forwarded` header parts
     *
     * @return array
     *
     * @since 2.0.31
     */
    protected function get_secure_forwarded_header_trusted_parts()
    {
        if ($this->_secure_forwarded_header_trusted_parts !== null) {
            return $this->_secure_forwarded_header_trusted_parts;
        }
        $validator = $this->get_ip_validator();
        $trusted_hosts = [];
        foreach ($this->trusted_hosts as $trusted_cidr => $trusted_cidr_or_headers) {
            if (!is_array($trusted_cidr_or_headers)) {
                $trusted_cidr = $trusted_cidr_or_headers;
            }
            $trusted_hosts[] = $trusted_cidr;
        }
        $validator->set_ranges($trusted_hosts);
        $this->_secure_forwarded_header_trusted_parts = array_filter($this->get_secure_forwarded_header_parts(), fn(array $header_part) => isset($header_part['for']) ? !$validator->validate($header_part['for']) : true);
        return $this->_secure_forwarded_header_trusted_parts;
    }
    private ?array $_secure_forwarded_header_parts = null;
    /**
     * Returns decoded forwarded header
     *
     * @return array
     *
     * @since 2.0.31
     */
    protected function get_secure_forwarded_header_parts()
    {
        if ($this->_secure_forwarded_header_parts !== null) {
            return $this->_secure_forwarded_header_parts;
        }
        if (count(preg_grep('/^forwarded$/i', $this->secure_headers)) === 0) {
            return $this->_secure_forwarded_header_parts = [];
        }
        /*
         * First header is always correct, because proxy CAN add headers
         * after last one is found.
         * Keep in mind that it is NOT enforced, therefore we cannot be
         * sure, that this is really a first one.
         *
         * FPM keeps last header sent which is a bug. You need to merge
         * headers together on your web server before letting FPM handle it
         * @see https://bugs.php.net/bug.php?id=78844
         */
        $forwarded = $this->headers->get('Forwarded', '');
        if ($forwarded === '') {
            return $this->_secure_forwarded_header_parts = [];
        }
        preg_match_all('/(?:[^",]++|"[^"]++")+/', $forwarded, $forwarded_elements);
        foreach ($forwarded_elements[0] as $forwarded_pairs) {
            preg_match_all('/(?P<key>\w+)\s*=\s*(?:(?P<value>[^",;]*[^",;\s])|"(?P<value2>[^"]+)")/', $forwarded_pairs, $matches, PREG_SET_ORDER);
            $this->_secure_forwarded_header_parts[] = array_reduce($matches, function (array $carry, array $item): array {
                $value = $item['value'];
                if (isset($item['value2']) && $item['value2'] !== '') {
                    $value = $item['value2'];
                }
                $carry[strtolower($item['key'])] = $value;
                return $carry;
            }, []);
        }
        return $this->_secure_forwarded_header_parts;
    }
}