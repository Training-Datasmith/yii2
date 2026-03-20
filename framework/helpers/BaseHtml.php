<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\helpers;

use Yii;
use yii\base\InvalidArgumentException;
use yii\base\Model;
use yii\db\Active_Record_Interface;
use yii\validators\String_Validator;
use yii\web\Request;
/**
 * BaseHtml provides concrete implementation for [[Html]].
 *
 * Do not use BaseHtml. Use [[Html]] instead.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Base_Html
{
    /**
     * @var string Regular expression used for attribute name validation.
     * @since 2.0.12
     */
    public static $attribute_regex = '/(^|.*\])([\w\.\+]+)(\[.*|$)/u';
    /**
     * @var array list of void elements (element name => 1)
     * @see https://html.spec.whatwg.org/multipage/syntax.html#void-element
     */
    public static $void_elements = ['area' => 1, 'base' => 1, 'br' => 1, 'col' => 1, 'command' => 1, 'embed' => 1, 'hr' => 1, 'img' => 1, 'input' => 1, 'keygen' => 1, 'link' => 1, 'meta' => 1, 'param' => 1, 'source' => 1, 'track' => 1, 'wbr' => 1];
    /**
     * @var array the preferred order of attributes in a tag. This mainly affects the order of the attributes
     * that are rendered by [[renderTagAttributes()]].
     */
    public static $attribute_order = ['type', 'id', 'class', 'name', 'value', 'href', 'src', 'srcset', 'form', 'action', 'method', 'selected', 'checked', 'readonly', 'disabled', 'multiple', 'size', 'maxlength', 'width', 'height', 'rows', 'cols', 'alt', 'title', 'rel', 'media'];
    /**
     * @var array list of tag attributes that should be specially handled when their values are of array type.
     * In particular, if the value of the `data` attribute is `['name' => 'xyz', 'age' => 13]`, two attributes
     * will be generated instead of one: `data-name="xyz" data-age="13"`.
     * @since 2.0.3
     */
    public static $data_attributes = ['aria', 'data', 'data-ng', 'ng'];
    /**
     * @var bool whether to removes duplicate class names in tag attribute `class`
     * @see mergeCssClasses()
     * @see renderTagAttributes()
     * @since 2.0.44
     */
    public static $normalize_class_attribute = false;
    /**
     * Encodes special characters into HTML entities.
     * The [[\yii\base\Application::charset|application charset]] will be used for encoding.
     * @param string $content the content to be encoded
     * @param bool $doubleEncode whether to encode HTML entities in `$content`. If false,
     * HTML entities in `$content` will not be further encoded.
     * @return string the encoded content
     * @see decode()
     * @see https://www.php.net/manual/en/function.htmlspecialchars.php
     */
    public static function encode($content, $double_encode = true): string
    {
        return htmlspecialchars((string) $content, ENT_QUOTES | ENT_SUBSTITUTE, Yii::$app ? Yii::$app->charset : 'UTF-8', $double_encode);
    }
    /**
     * Decodes special HTML entities back to the corresponding characters.
     * This is the opposite of [[encode()]].
     * @param string $content the content to be decoded
     * @return string the decoded content
     * @see encode()
     * @see https://www.php.net/manual/en/function.htmlspecialchars-decode.php
     */
    public static function decode($content): string
    {
        return htmlspecialchars_decode($content, ENT_QUOTES);
    }
    /**
     * Generates a complete HTML tag.
     * @param string|bool|null $name the tag name. If $name is `null` or `false`, the corresponding content will be rendered without any tag.
     * @param string $content the content to be enclosed between the start and end tags. It will not be HTML-encoded.
     * If this is coming from end users, you should consider [[encode()]] it to prevent XSS attacks.
     * @param array $options the HTML tag attributes (HTML options) in terms of name-value pairs.
     * These will be rendered as the attributes of the resulting tag. The values will be HTML-encoded using [[encode()]].
     * If a value is null, the corresponding attribute will not be rendered.
     *
     * For example when using `['class' => 'my-class', 'target' => '_blank', 'value' => null]` it will result in the
     * html attributes rendered like this: `class="my-class" target="_blank"`.
     *
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     *
     * @return string the generated HTML tag
     * @see beginTag()
     * @see endTag()
     */
    public static function tag($name, $content = '', $options = [])
    {
        if ($name === null || $name === false) {
            return $content;
        }
        $html = "<{$name}" . static::render_tag_attributes($options) . '>';
        return isset(static::$void_elements[strtolower($name)]) ? $html : "{$html}{$content}</{$name}>";
    }
    /**
     * Generates a start tag.
     * @param string|bool|null $name the tag name. If $name is `null` or `false`, the corresponding content will be rendered without any tag.
     * @param array $options the tag options in terms of name-value pairs. These will be rendered as
     * the attributes of the resulting tag. The values will be HTML-encoded using [[encode()]].
     * If a value is null, the corresponding attribute will not be rendered.
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     * @return string the generated start tag
     * @see endTag()
     * @see tag()
     */
    public static function begin_tag($name, $options = []): string
    {
        if ($name === null || $name === false) {
            return '';
        }
        return "<{$name}" . static::render_tag_attributes($options) . '>';
    }
    /**
     * Generates an end tag.
     * @param string|bool|null $name the tag name. If $name is `null` or `false`, the corresponding content will be rendered without any tag.
     * @return string the generated end tag
     * @see beginTag()
     * @see tag()
     */
    public static function end_tag($name): string
    {
        if ($name === null || $name === false) {
            return '';
        }
        return "</{$name}>";
    }
    /**
     * Generates a style tag.
     * @param string $content the style content
     * @param array $options the tag options in terms of name-value pairs. These will be rendered as
     * the attributes of the resulting tag. The values will be HTML-encoded using [[encode()]].
     * If a value is null, the corresponding attribute will not be rendered.
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     * @return string the generated style tag
     */
    public static function style($content, $options = [])
    {
        $view = Yii::$app->get_view();
        if ($view instanceof \yii\web\View && !empty($view->style_options)) {
            $options = array_merge($view->style_options, $options);
        }
        return static::tag('style', $content, $options);
    }
    /**
     * Generates a script tag.
     * @param string $content the script content
     * @param array $options the tag options in terms of name-value pairs. These will be rendered as
     * the attributes of the resulting tag. The values will be HTML-encoded using [[encode()]].
     * If a value is null, the corresponding attribute will not be rendered.
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     * @return string the generated script tag
     */
    public static function script($content, $options = [])
    {
        $view = Yii::$app->get_view();
        if ($view instanceof \yii\web\View && !empty($view->script_options)) {
            $options = array_merge($view->script_options, $options);
        }
        return static::tag('script', $content, $options);
    }
    /**
     * Generates a link tag that refers to an external CSS file.
     * @param array|string $url the URL of the external CSS file. This parameter will be processed by [[Url::to()]].
     * @param array $options the tag options in terms of name-value pairs. The following options are specially handled:
     *
     * - condition: specifies the conditional comments for IE, e.g., `lt IE 9`. When this is specified,
     *   the generated `link` tag will be enclosed within the conditional comments. This is mainly useful
     *   for supporting old versions of IE browsers.
     * - noscript: if set to true, `link` tag will be wrapped into `<noscript>` tags.
     *
     * The rest of the options will be rendered as the attributes of the resulting link tag. The values will
     * be HTML-encoded using [[encode()]]. If a value is null, the corresponding attribute will not be rendered.
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     * @return string the generated link tag
     * @see Url::to()
     */
    public static function css_file($url, array $options = [])
    {
        if (!isset($options['rel'])) {
            $options['rel'] = 'stylesheet';
        }
        $options['href'] = Url::to($url);
        if (isset($options['condition'])) {
            $condition = $options['condition'];
            unset($options['condition']);
            return self::wrap_into_condition(static::tag('link', '', $options), $condition);
        }
        if (isset($options['noscript']) && $options['noscript'] === true) {
            unset($options['noscript']);
            return '<noscript>' . static::tag('link', '', $options) . '</noscript>';
        }
        return static::tag('link', '', $options);
    }
    /**
     * Generates a script tag that refers to an external JavaScript file.
     * @param string $url the URL of the external JavaScript file. This parameter will be processed by [[Url::to()]].
     * @param array $options the tag options in terms of name-value pairs. The following option is specially handled:
     *
     * - condition: specifies the conditional comments for IE, e.g., `lt IE 9`. When this is specified,
     *   the generated `script` tag will be enclosed within the conditional comments. This is mainly useful
     *   for supporting old versions of IE browsers.
     *
     * The rest of the options will be rendered as the attributes of the resulting script tag. The values will
     * be HTML-encoded using [[encode()]]. If a value is null, the corresponding attribute will not be rendered.
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     * @return string the generated script tag
     * @see Url::to()
     */
    public static function js_file($url, array $options = [])
    {
        $options['src'] = Url::to($url);
        if (isset($options['condition'])) {
            $condition = $options['condition'];
            unset($options['condition']);
            return self::wrap_into_condition(static::tag('script', '', $options), $condition);
        }
        return static::tag('script', '', $options);
    }
    /**
     * Wraps given content into conditional comments for IE, e.g., `lt IE 9`.
     * @param string $content raw HTML content.
     * @param string $condition condition string.
     * @return string generated HTML.
     */
    private static function wrap_into_condition(string $content, $condition): string
    {
        if (strpos($condition, '!IE') !== false) {
            return "<!--[if {$condition}]><!-->\n" . $content . "\n<!--<![endif]-->";
        }
        return "<!--[if {$condition}]>\n" . $content . "\n<![endif]-->";
    }
    /**
     * Generates the meta tags containing CSRF token information.
     * @return string the generated meta tags
     * @see Request::enableCsrfValidation
     */
    public static function csrf_meta_tags(): string
    {
        $request = Yii::$app->get_request();
        if ($request instanceof Request && $request->enable_csrf_validation) {
            return static::tag('meta', '', ['name' => 'csrf-param', 'content' => $request->csrf_param]) . "\n" . static::tag('meta', '', ['name' => 'csrf-token', 'content' => $request->get_csrf_token()]) . "\n";
        }
        return '';
    }
    /**
     * Generates a form start tag.
     * @param array|string $action the form action URL. This parameter will be processed by [[Url::to()]].
     * @param string $method the form submission method, such as "post", "get", "put", "delete" (case-insensitive).
     * Since most browsers only support "post" and "get", if other methods are given, they will
     * be simulated using "post", and a hidden input will be added which contains the actual method type.
     * See [[\yii\web\Request::methodParam]] for more details.
     * @param array $options the tag options in terms of name-value pairs. These will be rendered as
     * the attributes of the resulting tag. The values will be HTML-encoded using [[encode()]].
     * If a value is null, the corresponding attribute will not be rendered.
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     *
     * Special options:
     *
     *  - `csrf`: whether to generate the CSRF hidden input. Defaults to true.
     *
     * @return string the generated form start tag.
     * @see endForm()
     */
    public static function begin_form($action = '', $method = 'post', array $options = [])
    {
        $action = Url::to($action);
        $hidden_inputs = [];
        $request = Yii::$app->get_request();
        if ($request instanceof Request) {
            if (strcasecmp($method, 'get') && strcasecmp($method, 'post')) {
                // simulate PUT, DELETE, etc. via POST
                $hidden_inputs[] = static::hidden_input($request->method_param, $method);
                $method = 'post';
            }
            $csrf = Array_Helper::remove($options, 'csrf', true);
            if ($csrf && $request->enable_csrf_validation && strcasecmp($method, 'post') === 0) {
                $hidden_inputs[] = static::hidden_input($request->csrf_param, $request->get_csrf_token());
            }
        }
        if (!strcasecmp($method, 'get') && ($pos = strpos($action, '?')) !== false) {
            // query parameters in the action are ignored for GET method
            // we use hidden fields to add them back
            foreach (explode('&', substr($action, $pos + 1)) as $pair) {
                if (($pos1 = strpos($pair, '=')) !== false) {
                    $hidden_inputs[] = static::hidden_input(urldecode(substr($pair, 0, $pos1)), urldecode(substr($pair, $pos1 + 1)));
                } else {
                    $hidden_inputs[] = static::hidden_input(urldecode($pair), '');
                }
            }
            $action = substr($action, 0, $pos);
        }
        $options['action'] = $action;
        $options['method'] = $method;
        $form = static::begin_tag('form', $options);
        if (!empty($hidden_inputs)) {
            $form .= "\n" . implode("\n", $hidden_inputs);
        }
        return $form;
    }
    /**
     * Generates a form end tag.
     * @return string the generated tag
     * @see beginForm()
     */
    public static function end_form(): string
    {
        return '</form>';
    }
    /**
     * Generates a hyperlink tag.
     * @param string $text link body. It will NOT be HTML-encoded. Therefore you can pass in HTML code
     * such as an image tag. If this is coming from end users, you should consider [[encode()]]
     * it to prevent XSS attacks.
     * @param array|string|null $url the URL for the hyperlink tag. This parameter will be processed by [[Url::to()]]
     * and will be used for the "href" attribute of the tag. If this parameter is null, the "href" attribute
     * will not be generated.
     *
     * If you want to use an absolute url you can call [[Url::to()]] yourself, before passing the URL to this method,
     * like this:
     *
     * ```
     * Html::a('link text', Url::to($url, true))
     * ```
     *
     * @param array $options the tag options in terms of name-value pairs. These will be rendered as
     * the attributes of the resulting tag. The values will be HTML-encoded using [[encode()]].
     * If a value is null, the corresponding attribute will not be rendered.
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     * @return string the generated hyperlink
     * @see \yii\helpers\Url::to()
     */
    public static function a($text, $url = null, array $options = [])
    {
        if ($url !== null) {
            $options['href'] = Url::to($url);
        }
        return static::tag('a', $text, $options);
    }
    /**
     * Generates a mailto hyperlink.
     * @param string $text link body. It will NOT be HTML-encoded. Therefore you can pass in HTML code
     * such as an image tag. If this is coming from end users, you should consider [[encode()]]
     * it to prevent XSS attacks.
     * @param string|null $email email address. If this is null, the first parameter (link body) will be treated
     * as the email address and used.
     * @param array $options the tag options in terms of name-value pairs. These will be rendered as
     * the attributes of the resulting tag. The values will be HTML-encoded using [[encode()]].
     * If a value is null, the corresponding attribute will not be rendered.
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     * @return string the generated mailto link
     */
    public static function mailto($text, $email = null, array $options = [])
    {
        $options['href'] = 'mailto:' . ($email ?? $text);
        return static::tag('a', $text, $options);
    }
    /**
     * Generates an image tag.
     * @param array|string $src the image URL. This parameter will be processed by [[Url::to()]].
     * @param array $options the tag options in terms of name-value pairs. These will be rendered as
     * the attributes of the resulting tag. The values will be HTML-encoded using [[encode()]].
     * If a value is null, the corresponding attribute will not be rendered.
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     *
     * Since version 2.0.12 It is possible to pass the `srcset` option as an array which keys are
     * descriptors and values are URLs. All URLs will be processed by [[Url::to()]].
     * @return string the generated image tag.
     */
    public static function img($src, array $options = [])
    {
        $options['src'] = Url::to($src);
        if (isset($options['srcset']) && is_array($options['srcset'])) {
            $srcset = [];
            foreach ($options['srcset'] as $descriptor => $url) {
                $srcset[] = Url::to($url) . ' ' . $descriptor;
            }
            $options['srcset'] = implode(',', $srcset);
        }
        if (!isset($options['alt'])) {
            $options['alt'] = '';
        }
        return static::tag('img', '', $options);
    }
    /**
     * Generates a label tag.
     * @param string $content label text. It will NOT be HTML-encoded. Therefore you can pass in HTML code
     * such as an image tag. If this is is coming from end users, you should [[encode()]]
     * it to prevent XSS attacks.
     * @param string|null $for the ID of the HTML element that this label is associated with.
     * If this is null, the "for" attribute will not be generated.
     * @param array $options the tag options in terms of name-value pairs. These will be rendered as
     * the attributes of the resulting tag. The values will be HTML-encoded using [[encode()]].
     * If a value is null, the corresponding attribute will not be rendered.
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     * @return string the generated label tag
     */
    public static function label($content, $for = null, array $options = [])
    {
        $options['for'] = $for;
        return static::tag('label', $content, $options);
    }
    /**
     * Generates a button tag.
     * @param string $content the content enclosed within the button tag. It will NOT be HTML-encoded.
     * Therefore you can pass in HTML code such as an image tag. If this is is coming from end users,
     * you should consider [[encode()]] it to prevent XSS attacks.
     * @param array $options the tag options in terms of name-value pairs. These will be rendered as
     * the attributes of the resulting tag. The values will be HTML-encoded using [[encode()]].
     * If a value is null, the corresponding attribute will not be rendered.
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     * @return string the generated button tag
     */
    public static function button($content = 'Button', array $options = [])
    {
        if (!isset($options['type'])) {
            $options['type'] = 'button';
        }
        return static::tag('button', $content, $options);
    }
    /**
     * Generates a submit button tag.
     *
     * Be careful when naming form elements such as submit buttons. According to the [jQuery documentation](https://api.jquery.com/submit/) there
     * are some reserved names that can cause conflicts, e.g. `submit`, `length`, or `method`.
     *
     * @param string $content the content enclosed within the button tag. It will NOT be HTML-encoded.
     * Therefore you can pass in HTML code such as an image tag. If this is is coming from end users,
     * you should consider [[encode()]] it to prevent XSS attacks.
     * @param array $options the tag options in terms of name-value pairs. These will be rendered as
     * the attributes of the resulting tag. The values will be HTML-encoded using [[encode()]].
     * If a value is null, the corresponding attribute will not be rendered.
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     * @return string the generated submit button tag
     */
    public static function submit_button($content = 'Submit', array $options = [])
    {
        $options['type'] = 'submit';
        return static::button($content, $options);
    }
    /**
     * Generates a reset button tag.
     * @param string $content the content enclosed within the button tag. It will NOT be HTML-encoded.
     * Therefore you can pass in HTML code such as an image tag. If this is is coming from end users,
     * you should consider [[encode()]] it to prevent XSS attacks.
     * @param array $options the tag options in terms of name-value pairs. These will be rendered as
     * the attributes of the resulting tag. The values will be HTML-encoded using [[encode()]].
     * If a value is null, the corresponding attribute will not be rendered.
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     * @return string the generated reset button tag
     */
    public static function reset_button($content = 'Reset', array $options = [])
    {
        $options['type'] = 'reset';
        return static::button($content, $options);
    }
    /**
     * Generates an input type of the given type.
     * @param string $type the type attribute.
     * @param string|null $name the name attribute. If it is null, the name attribute will not be generated.
     * @param string|null $value the value attribute. If it is null, the value attribute will not be generated.
     * @param array $options the tag options in terms of name-value pairs. These will be rendered as
     * the attributes of the resulting tag. The values will be HTML-encoded using [[encode()]].
     * If a value is null, the corresponding attribute will not be rendered.
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     * @return string the generated input tag
     */
    public static function input($type, $name = null, $value = null, array $options = [])
    {
        if (!isset($options['type'])) {
            $options['type'] = $type;
        }
        $options['name'] = $name;
        $options['value'] = $value === null ? null : (string) $value;
        return static::tag('input', '', $options);
    }
    /**
     * Generates an input button.
     * @param string|null $label the value attribute. If it is null, the value attribute will not be generated.
     * @param array $options the tag options in terms of name-value pairs. These will be rendered as
     * the attributes of the resulting tag. The values will be HTML-encoded using [[encode()]].
     * If a value is null, the corresponding attribute will not be rendered.
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     * @return string the generated button tag
     */
    public static function button_input($label = 'Button', array $options = [])
    {
        $options['type'] = 'button';
        $options['value'] = $label;
        return static::tag('input', '', $options);
    }
    /**
     * Generates a submit input button.
     *
     * Be careful when naming form elements such as submit buttons. According to the [jQuery documentation](https://api.jquery.com/submit/) there
     * are some reserved names that can cause conflicts, e.g. `submit`, `length`, or `method`.
     *
     * @param string|null $label the value attribute. If it is null, the value attribute will not be generated.
     * @param array $options the tag options in terms of name-value pairs. These will be rendered as
     * the attributes of the resulting tag. The values will be HTML-encoded using [[encode()]].
     * If a value is null, the corresponding attribute will not be rendered.
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     * @return string the generated button tag
     */
    public static function submit_input($label = 'Submit', array $options = [])
    {
        $options['type'] = 'submit';
        $options['value'] = $label;
        return static::tag('input', '', $options);
    }
    /**
     * Generates a reset input button.
     * @param string|null $label the value attribute. If it is null, the value attribute will not be generated.
     * @param array $options the attributes of the button tag. The values will be HTML-encoded using [[encode()]].
     * Attributes whose value is null will be ignored and not put in the tag returned.
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     * @return string the generated button tag
     */
    public static function reset_input($label = 'Reset', array $options = [])
    {
        $options['type'] = 'reset';
        $options['value'] = $label;
        return static::tag('input', '', $options);
    }
    /**
     * Generates a text input field.
     * @param string $name the name attribute.
     * @param string|null $value the value attribute. If it is null, the value attribute will not be generated.
     * @param array $options the tag options in terms of name-value pairs. These will be rendered as
     * the attributes of the resulting tag. The values will be HTML-encoded using [[encode()]].
     * If a value is null, the corresponding attribute will not be rendered.
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     * @return string the generated text input tag
     */
    public static function text_input($name, $value = null, $options = [])
    {
        return static::input('text', $name, $value, $options);
    }
    /**
     * Generates a hidden input field.
     * @param string $name the name attribute.
     * @param string|null $value the value attribute. If it is null, the value attribute will not be generated.
     * @param array $options the tag options in terms of name-value pairs. These will be rendered as
     * the attributes of the resulting tag. The values will be HTML-encoded using [[encode()]].
     * If a value is null, the corresponding attribute will not be rendered.
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     * @return string the generated hidden input tag
     */
    public static function hidden_input($name, $value = null, $options = [])
    {
        return static::input('hidden', $name, $value, $options);
    }
    /**
     * Generates a password input field.
     * @param string $name the name attribute.
     * @param string|null $value the value attribute. If it is null, the value attribute will not be generated.
     * @param array $options the tag options in terms of name-value pairs. These will be rendered as
     * the attributes of the resulting tag. The values will be HTML-encoded using [[encode()]].
     * If a value is null, the corresponding attribute will not be rendered.
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     * @return string the generated password input tag
     */
    public static function password_input($name, $value = null, $options = [])
    {
        return static::input('password', $name, $value, $options);
    }
    /**
     * Generates a file input field.
     * To use a file input field, you should set the enclosing form's "enctype" attribute to
     * be "multipart/form-data". After the form is submitted, the uploaded file information
     * can be obtained via $_FILES[$name] (see PHP documentation).
     * @param string $name the name attribute.
     * @param string|null $value the value attribute. If it is null, the value attribute will not be generated.
     * @param array $options the tag options in terms of name-value pairs. These will be rendered as
     * the attributes of the resulting tag. The values will be HTML-encoded using [[encode()]].
     * If a value is null, the corresponding attribute will not be rendered.
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     * @return string the generated file input tag
     */
    public static function file_input($name, $value = null, $options = [])
    {
        return static::input('file', $name, $value, $options);
    }
    /**
     * Generates a text area input.
     * @param string $name the input name
     * @param string $value the input value. Note that it will be encoded using [[encode()]].
     * @param array $options the tag options in terms of name-value pairs. These will be rendered as
     * the attributes of the resulting tag. The values will be HTML-encoded using [[encode()]].
     * If a value is null, the corresponding attribute will not be rendered.
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     * The following special options are recognized:
     *
     * - `doubleEncode`: whether to double encode HTML entities in `$value`. If `false`, HTML entities in `$value` will not
     *   be further encoded. This option is available since version 2.0.11.
     *
     * @return string the generated text area tag
     */
    public static function textarea($name, $value = '', array $options = [])
    {
        $options['name'] = $name;
        $double_encode = Array_Helper::remove($options, 'doubleEncode', true);
        return static::tag('textarea', static::encode($value, $double_encode), $options);
    }
    /**
     * Generates a radio button input.
     * @param string $name the name attribute.
     * @param bool $checked whether the radio button should be checked.
     * @param array $options the tag options in terms of name-value pairs.
     * See [[booleanInput()]] for details about accepted attributes.
     *
     * @return string the generated radio button tag
     */
    public static function radio($name, $checked = false, $options = [])
    {
        return static::boolean_input('radio', $name, $checked, $options);
    }
    /**
     * Generates a checkbox input.
     * @param string $name the name attribute.
     * @param bool $checked whether the checkbox should be checked.
     * @param array $options the tag options in terms of name-value pairs.
     * See [[booleanInput()]] for details about accepted attributes.
     *
     * @return string the generated checkbox tag
     */
    public static function checkbox($name, $checked = false, $options = [])
    {
        return static::boolean_input('checkbox', $name, $checked, $options);
    }
    /**
     * Generates a boolean input.
     * @param string $type the input type. This can be either `radio` or `checkbox`.
     * @param string $name the name attribute.
     * @param bool $checked whether the checkbox should be checked.
     * @param array $options the tag options in terms of name-value pairs. The following options are specially handled:
     *
     * - uncheck: string, the value associated with the uncheck state of the checkbox. When this attribute
     *   is present, a hidden input will be generated so that if the checkbox is not checked and is submitted,
     *   the value of this attribute will still be submitted to the server via the hidden input.
     * - label: string, a label displayed next to the checkbox.  It will NOT be HTML-encoded. Therefore you can pass
     *   in HTML code such as an image tag. If this is is coming from end users, you should [[encode()]] it to prevent XSS attacks.
     *   When this option is specified, the checkbox will be enclosed by a label tag.
     * - labelOptions: array, the HTML attributes for the label tag. Do not set this option unless you set the "label" option.
     *
     * The rest of the options will be rendered as the attributes of the resulting checkbox tag. The values will
     * be HTML-encoded using [[encode()]]. If a value is null, the corresponding attribute will not be rendered.
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     *
     * @return string the generated checkbox tag
     * @since 2.0.9
     */
    protected static function boolean_input($type, $name, $checked = false, array $options = []): string
    {
        // 'checked' option has priority over $checked argument
        if (!isset($options['checked'])) {
            $options['checked'] = (bool) $checked;
        }
        $value = array_key_exists('value', $options) ? $options['value'] : '1';
        if (isset($options['uncheck'])) {
            // add a hidden field so that if the checkbox is not selected, it still submits a value
            $hidden_options = [];
            if (isset($options['form'])) {
                $hidden_options['form'] = $options['form'];
            }
            // make sure disabled input is not sending any value
            if (!empty($options['disabled'])) {
                $hidden_options['disabled'] = $options['disabled'];
            }
            $hidden = static::hidden_input($name, $options['uncheck'], $hidden_options);
            unset($options['uncheck']);
        } else {
            $hidden = '';
        }
        if (isset($options['label'])) {
            $label = $options['label'];
            $label_options = $options['labelOptions'] ?? [];
            unset($options['label'], $options['labelOptions']);
            $content = static::label(static::input($type, $name, $value, $options) . ' ' . $label, null, $label_options);
            return $hidden . $content;
        }
        return $hidden . static::input($type, $name, $value, $options);
    }
    /**
     * Generates a drop-down list.
     * @param string $name the input name
     * @param string|bool|array|null $selection the selected value(s). String/boolean for single or array for multiple
     * selection(s).
     * @param array $items the option data items. The array keys are option values, and the array values
     * are the corresponding option labels. The array can also be nested (i.e. some array values are arrays too).
     * For each sub-array, an option group will be generated whose label is the key associated with the sub-array.
     * If you have a list of data models, you may convert them into the format described above using
     * [[\yii\helpers\ArrayHelper::map()]].
     *
     * Note, the values and labels will be automatically HTML-encoded by this method, and the blank spaces in
     * the labels will also be HTML-encoded.
     * @param array $options the tag options in terms of name-value pairs. The following options are specially handled:
     *
     * - prompt: string, a prompt text to be displayed as the first option. Since version 2.0.11 you can use an array
     *   to override the value and to set other tag attributes:
     *
     *   ```
     *   ['text' => 'Please select', 'options' => ['value' => 'none', 'class' => 'prompt', 'label' => 'Select']],
     *   ```
     *
     * - options: array, the attributes for the select option tags. The array keys must be valid option values,
     *   and the array values are the extra attributes for the corresponding option tags. For example,
     *
     *   ```
     *   [
     *       'value1' => ['disabled' => true],
     *       'value2' => ['label' => 'value 2'],
     *   ];
     *   ```
     *
     * - groups: array, the attributes for the optgroup tags. The structure of this is similar to that of 'options',
     *   except that the array keys represent the optgroup labels specified in $items.
     * - encodeSpaces: bool, whether to encode spaces in option prompt and option value with `&nbsp;` character.
     *   Defaults to false.
     * - encode: bool, whether to encode option prompt and option value characters.
     *   Defaults to `true`. This option is available since 2.0.3.
     * - strict: boolean, if `$selection` is an array and this value is true a strict comparison will be performed on `$items` keys. Defaults to false.
     *   This option is available since 2.0.37.
     *
     * The rest of the options will be rendered as the attributes of the resulting tag. The values will
     * be HTML-encoded using [[encode()]]. If a value is null, the corresponding attribute will not be rendered.
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     *
     * @return string the generated drop-down list tag
     */
    public static function drop_down_list($name, $selection = null, $items = [], array $options = [])
    {
        if (!empty($options['multiple'])) {
            return static::list_box($name, $selection, $items, $options);
        }
        $options['name'] = $name;
        unset($options['unselect']);
        $select_options = static::render_select_options($selection, $items, $options);
        return static::tag('select', "\n" . $select_options . "\n", $options);
    }
    /**
     * Generates a list box.
     * @param string $name the input name
     * @param string|bool|array|null $selection the selected value(s). String for single or array for multiple
     * selection(s).
     * @param array $items the option data items. The array keys are option values, and the array values
     * are the corresponding option labels. The array can also be nested (i.e. some array values are arrays too).
     * For each sub-array, an option group will be generated whose label is the key associated with the sub-array.
     * If you have a list of data models, you may convert them into the format described above using
     * [[\yii\helpers\ArrayHelper::map()]].
     *
     * Note, the values and labels will be automatically HTML-encoded by this method, and the blank spaces in
     * the labels will also be HTML-encoded.
     * @param array $options the tag options in terms of name-value pairs. The following options are specially handled:
     *
     * - prompt: string, a prompt text to be displayed as the first option. Since version 2.0.11 you can use an array
     *   to override the value and to set other tag attributes:
     *
     *   ```
     *   ['text' => 'Please select', 'options' => ['value' => 'none', 'class' => 'prompt', 'label' => 'Select']],
     *   ```
     *
     * - options: array, the attributes for the select option tags. The array keys must be valid option values,
     *   and the array values are the extra attributes for the corresponding option tags. For example,
     *
     *   ```
     *   [
     *       'value1' => ['disabled' => true],
     *       'value2' => ['label' => 'value 2'],
     *   ];
     *   ```
     *
     * - groups: array, the attributes for the optgroup tags. The structure of this is similar to that of 'options',
     *   except that the array keys represent the optgroup labels specified in $items.
     * - unselect: string, the value that will be submitted when no option is selected.
     *   When this attribute is set, a hidden field will be generated so that if no option is selected in multiple
     *   mode, we can still obtain the posted unselect value.
     * - encodeSpaces: bool, whether to encode spaces in option prompt and option value with `&nbsp;` character.
     *   Defaults to false.
     * - encode: bool, whether to encode option prompt and option value characters.
     *   Defaults to `true`. This option is available since 2.0.3.
     * - strict: boolean, if `$selection` is an array and this value is true a strict comparison will be performed on `$items` keys. Defaults to false.
     *   This option is available since 2.0.37.
     *
     * The rest of the options will be rendered as the attributes of the resulting tag. The values will
     * be HTML-encoded using [[encode()]]. If a value is null, the corresponding attribute will not be rendered.
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     *
     * @return string the generated list box tag
     */
    public static function list_box($name, $selection = null, $items = [], array $options = []): string
    {
        if (!array_key_exists('size', $options)) {
            $options['size'] = 4;
        }
        if (!empty($options['multiple']) && !empty($name) && substr_compare($name, '[]', -2, 2)) {
            $name .= '[]';
        }
        $options['name'] = $name;
        if (isset($options['unselect'])) {
            // add a hidden field so that if the list box has no option being selected, it still submits a value
            if (!empty($name) && substr_compare($name, '[]', -2, 2) === 0) {
                $name = substr($name, 0, -2);
            }
            $hidden_options = [];
            // make sure disabled input is not sending any value
            if (!empty($options['disabled'])) {
                $hidden_options['disabled'] = $options['disabled'];
            }
            $hidden = static::hidden_input($name, $options['unselect'], $hidden_options);
            unset($options['unselect']);
        } else {
            $hidden = '';
        }
        $select_options = static::render_select_options($selection, $items, $options);
        return $hidden . static::tag('select', "\n" . $select_options . "\n", $options);
    }
    /**
     * Generates a list of checkboxes.
     * A checkbox list allows multiple selection, like [[listBox()]].
     * As a result, the corresponding submitted value is an array.
     * @param string $name the name attribute of each checkbox.
     * @param string|array|null $selection the selected value(s). String for single or array for multiple selection(s).
     * @param array $items the data item used to generate the checkboxes.
     * The array keys are the checkbox values, while the array values are the corresponding labels.
     * @param array $options options (name => config) for the checkbox list container tag.
     * The following options are specially handled:
     *
     * - tag: string|false, the tag name of the container element. False to render checkbox without container.
     *   See also [[tag()]].
     * - unselect: string, the value that should be submitted when none of the checkboxes is selected.
     *   By setting this option, a hidden input will be generated.
     * - disabled: boolean, whether the generated by unselect option hidden input should be disabled. Defaults to false.
     *   This option is available since version 2.0.16.
     * - encode: boolean, whether to HTML-encode the checkbox labels. Defaults to true.
     *   This option is ignored if `item` option is set.
     * - strict: boolean, if `$selection` is an array and this value is true a strict comparison will be performed on `$items` keys. Defaults to false.
     *   This option is available since 2.0.37.
     * - separator: string, the HTML code that separates items.
     * - itemOptions: array, the options for generating the checkbox tag using [[checkbox()]].
     * - item: callable, a callback that can be used to customize the generation of the HTML code
     *   corresponding to a single item in $items. The signature of this callback must be:
     *
     *   ```
     *   function ($index, $label, $name, $checked, $value)
     *   ```
     *
     *   where $index is the zero-based index of the checkbox in the whole list; $label
     *   is the label for the checkbox; and $name, $value and $checked represent the name,
     *   value and the checked status of the checkbox input, respectively.
     *
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     *
     * @return string the generated checkbox list
     */
    public static function checkbox_list(string $name, $selection = null, $items = [], array $options = []): string
    {
        if (substr($name, -2) !== '[]') {
            $name .= '[]';
        }
        if (Array_Helper::is_traversable($selection)) {
            $selection = array_map('strval', Array_Helper::to_array($selection));
        }
        $formatter = Array_Helper::remove($options, 'item');
        $item_options = Array_Helper::remove($options, 'itemOptions', []);
        $encode = Array_Helper::remove($options, 'encode', true);
        $separator = Array_Helper::remove($options, 'separator', "\n");
        $tag = Array_Helper::remove($options, 'tag', 'div');
        $strict = Array_Helper::remove($options, 'strict', false);
        $lines = [];
        $index = 0;
        foreach ($items as $value => $label) {
            $checked = $selection !== null && (!Array_Helper::is_traversable($selection) && !strcmp($value, $selection) || Array_Helper::is_traversable($selection) && Array_Helper::is_in((string) $value, $selection, $strict));
            if ($formatter !== null) {
                $lines[] = call_user_func($formatter, $index, $label, $name, $checked, $value);
            } else {
                $lines[] = static::checkbox($name, $checked, array_merge(['value' => $value, 'label' => $encode ? static::encode($label) : $label], $item_options));
            }
            $index++;
        }
        if (isset($options['unselect'])) {
            // add a hidden field so that if the list box has no option being selected, it still submits a value
            $name2 = substr($name, -2) === '[]' ? substr($name, 0, -2) : $name;
            $hidden_options = [];
            // make sure disabled input is not sending any value
            if (!empty($options['disabled'])) {
                $hidden_options['disabled'] = $options['disabled'];
            }
            $hidden = static::hidden_input($name2, $options['unselect'], $hidden_options);
            unset($options['unselect'], $options['disabled']);
        } else {
            $hidden = '';
        }
        $visible_content = implode($separator, $lines);
        if ($tag === false) {
            return $hidden . $visible_content;
        }
        return $hidden . static::tag($tag, $visible_content, $options);
    }
    /**
     * Generates a list of radio buttons.
     * A radio button list is like a checkbox list, except that it only allows single selection.
     * @param string $name the name attribute of each radio button.
     * @param string|array|null $selection the selected value(s). String for single or array for multiple selection(s).
     * @param array $items the data item used to generate the radio buttons.
     * The array keys are the radio button values, while the array values are the corresponding labels.
     * @param array $options options (name => config) for the radio button list container tag.
     * The following options are specially handled:
     *
     * - tag: string|false, the tag name of the container element. False to render radio buttons without container.
     *   See also [[tag()]].
     * - unselect: string, the value that should be submitted when none of the radio buttons is selected.
     *   By setting this option, a hidden input will be generated.
     * - disabled: boolean, whether the generated by unselect option hidden input should be disabled. Defaults to false.
     *   This option is available since version 2.0.16.
     * - encode: boolean, whether to HTML-encode the checkbox labels. Defaults to true.
     *   This option is ignored if `item` option is set.
     * - strict: boolean, if `$selection` is an array and this value is true a strict comparison will be performed on `$items` keys. Defaults to false.
     *   This option is available since 2.0.37.
     * - separator: string, the HTML code that separates items.
     * - itemOptions: array, the options for generating the radio button tag using [[radio()]].
     * - item: callable, a callback that can be used to customize the generation of the HTML code
     *   corresponding to a single item in $items. The signature of this callback must be:
     *
     *   ```
     *   function ($index, $label, $name, $checked, $value)
     *   ```
     *
     *   where $index is the zero-based index of the radio button in the whole list; $label
     *   is the label for the radio button; and $name, $value and $checked represent the name,
     *   value and the checked status of the radio button input, respectively.
     *
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     *
     * @return string the generated radio button list
     */
    public static function radio_list($name, $selection = null, $items = [], array $options = []): string
    {
        if (Array_Helper::is_traversable($selection)) {
            $selection = array_map('strval', Array_Helper::to_array($selection));
        }
        $formatter = Array_Helper::remove($options, 'item');
        $item_options = Array_Helper::remove($options, 'itemOptions', []);
        $encode = Array_Helper::remove($options, 'encode', true);
        $separator = Array_Helper::remove($options, 'separator', "\n");
        $tag = Array_Helper::remove($options, 'tag', 'div');
        $strict = Array_Helper::remove($options, 'strict', false);
        $hidden = '';
        if (isset($options['unselect'])) {
            // add a hidden field so that if the list box has no option being selected, it still submits a value
            $hidden_options = [];
            // make sure disabled input is not sending any value
            if (!empty($options['disabled'])) {
                $hidden_options['disabled'] = $options['disabled'];
            }
            $hidden = static::hidden_input($name, $options['unselect'], $hidden_options);
            unset($options['unselect'], $options['disabled']);
        }
        $lines = [];
        $index = 0;
        foreach ($items as $value => $label) {
            $checked = $selection !== null && (!Array_Helper::is_traversable($selection) && !strcmp($value, $selection) || Array_Helper::is_traversable($selection) && Array_Helper::is_in((string) $value, $selection, $strict));
            if ($formatter !== null) {
                $lines[] = call_user_func($formatter, $index, $label, $name, $checked, $value);
            } else {
                $lines[] = static::radio($name, $checked, array_merge(['value' => $value, 'label' => $encode ? static::encode($label) : $label], $item_options));
            }
            $index++;
        }
        $visible_content = implode($separator, $lines);
        if ($tag === false) {
            return $hidden . $visible_content;
        }
        return $hidden . static::tag($tag, $visible_content, $options);
    }
    /**
     * Generates an unordered list.
     * @param array|\Traversable $items the items for generating the list. Each item generates a single list item.
     * Note that items will be automatically HTML encoded if `$options['encode']` is not set or true.
     * @param array $options options (name => config) for the radio button list. The following options are supported:
     *
     * - encode: boolean, whether to HTML-encode the items. Defaults to true.
     *   This option is ignored if the `item` option is specified.
     * - separator: string, the HTML code that separates items. Defaults to a simple newline (`"\n"`).
     *   This option is available since version 2.0.7.
     * - itemOptions: array, the HTML attributes for the `li` tags. This option is ignored if the `item` option is specified.
     * - item: callable, a callback that is used to generate each individual list item.
     *   The signature of this callback must be:
     *
     *   ```
     *   function ($item, $index)
     *   ```
     *
     *   where $index is the array key corresponding to `$item` in `$items`. The callback should return
     *   the whole list item tag.
     *
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     *
     * @return string the generated unordered list. An empty list tag will be returned if `$items` is empty.
     */
    public static function ul($items, $options = [])
    {
        $tag = Array_Helper::remove($options, 'tag', 'ul');
        $encode = Array_Helper::remove($options, 'encode', true);
        $formatter = Array_Helper::remove($options, 'item');
        $separator = Array_Helper::remove($options, 'separator', "\n");
        $item_options = Array_Helper::remove($options, 'itemOptions', []);
        if (empty($items)) {
            return static::tag($tag, '', $options);
        }
        $results = [];
        foreach ($items as $index => $item) {
            if ($formatter !== null) {
                $results[] = call_user_func($formatter, $item, $index);
            } else {
                $results[] = static::tag('li', $encode ? static::encode($item) : $item, $item_options);
            }
        }
        return static::tag($tag, $separator . implode($separator, $results) . $separator, $options);
    }
    /**
     * Generates an ordered list.
     * @param array|\Traversable $items the items for generating the list. Each item generates a single list item.
     * Note that items will be automatically HTML encoded if `$options['encode']` is not set or true.
     * @param array $options options (name => config) for the radio button list. The following options are supported:
     *
     * - encode: boolean, whether to HTML-encode the items. Defaults to true.
     *   This option is ignored if the `item` option is specified.
     * - itemOptions: array, the HTML attributes for the `li` tags. This option is ignored if the `item` option is specified.
     * - item: callable, a callback that is used to generate each individual list item.
     *   The signature of this callback must be:
     *
     *   ```
     *   function ($item, $index)
     *   ```
     *
     *   where $index is the array key corresponding to `$item` in `$items`. The callback should return
     *   the whole list item tag.
     *
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     *
     * @return string the generated ordered list. An empty string is returned if `$items` is empty.
     */
    public static function ol($items, array $options = [])
    {
        $options['tag'] = 'ol';
        return static::ul($items, $options);
    }
    /**
     * Generates a label tag for the given model attribute.
     * The label text is the label associated with the attribute, obtained via [[Model::getAttributeLabel()]].
     * @param Model $model the model object
     * @param string $attribute the attribute name or expression. See [[getAttributeName()]] for the format
     * about attribute expression.
     * @param array $options the tag options in terms of name-value pairs. These will be rendered as
     * the attributes of the resulting tag. The values will be HTML-encoded using [[encode()]].
     * If a value is null, the corresponding attribute will not be rendered.
     * The following options are specially handled:
     *
     * - label: this specifies the label to be displayed. Note that this will NOT be [[encode()|encoded]].
     *   If this is not set, [[Model::getAttributeLabel()]] will be called to get the label for display
     *   (after encoding).
     *
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     *
     * @return string the generated label tag
     */
    public static function active_label($model, $attribute, $options = [])
    {
        $for = Array_Helper::remove($options, 'for', static::get_input_id($model, $attribute));
        $attribute = static::get_attribute_name($attribute);
        $label = Array_Helper::remove($options, 'label', static::encode($model->get_attribute_label($attribute)));
        return static::label($label, $for, $options);
    }
    /**
     * Generates a hint tag for the given model attribute.
     * The hint text is the hint associated with the attribute, obtained via [[Model::getAttributeHint()]].
     * If no hint content can be obtained, method will return an empty string.
     * @param Model $model the model object
     * @param string $attribute the attribute name or expression. See [[getAttributeName()]] for the format
     * about attribute expression.
     * @param array $options the tag options in terms of name-value pairs. These will be rendered as
     * the attributes of the resulting tag. The values will be HTML-encoded using [[encode()]].
     * If a value is null, the corresponding attribute will not be rendered.
     * The following options are specially handled:
     *
     * - hint: this specifies the hint to be displayed. Note that this will NOT be [[encode()|encoded]].
     *   If this is not set, [[Model::getAttributeHint()]] will be called to get the hint for display
     *   (without encoding).
     *
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     *
     * @return string the generated hint tag
     * @since 2.0.4
     */
    public static function active_hint($model, $attribute, array $options = [])
    {
        $attribute = static::get_attribute_name($attribute);
        $hint = $options['hint'] ?? $model->get_attribute_hint($attribute);
        if (empty($hint)) {
            return '';
        }
        $tag = Array_Helper::remove($options, 'tag', 'div');
        unset($options['hint']);
        return static::tag($tag, $hint, $options);
    }
    /**
     * Generates a summary of the validation errors.
     * If there is no validation error, an empty error summary markup will still be generated, but it will be hidden.
     * @param Model|Model[] $models the model(s) whose validation errors are to be displayed.
     * @param array $options the tag options in terms of name-value pairs. The following options are specially handled:
     *
     * - header: string, the header HTML for the error summary. If not set, a default prompt string will be used.
     * - footer: string, the footer HTML for the error summary. Defaults to empty string.
     * - encode: boolean, if set to false then the error messages won't be encoded. Defaults to `true`.
     * - showAllErrors: boolean, if set to true every error message for each attribute will be shown otherwise
     *   only the first error message for each attribute will be shown. Defaults to `false`.
     *   Option is available since 2.0.10.
     * - emptyClass: string, the class name that is added to an empty summary.
     *
     * The rest of the options will be rendered as the attributes of the container tag.
     *
     * @return string the generated error summary
     */
    public static function error_summary($models, array $options = [])
    {
        $header = $options['header'] ?? '<p>' . Yii::t('yii', 'Please fix the following errors:') . '</p>';
        $footer = Array_Helper::remove($options, 'footer', '');
        $encode = Array_Helper::remove($options, 'encode', true);
        $show_all_errors = Array_Helper::remove($options, 'showAllErrors', false);
        $empty_class = Array_Helper::remove($options, 'emptyClass');
        unset($options['header']);
        $lines = self::collect_errors($models, $encode, $show_all_errors);
        if (empty($lines)) {
            // still render the placeholder for client-side validation use
            $content = '<ul></ul>';
            if ($empty_class !== null) {
                $options['class'] = $empty_class;
            } else {
                $options['style'] = isset($options['style']) ? rtrim($options['style'], ';') . '; display:none' : 'display:none';
            }
        } else {
            $content = '<ul><li>' . implode("</li>\n<li>", $lines) . '</li></ul>';
        }
        return Html::tag('div', $header . $content . $footer, $options);
    }
    /**
     * Return array of the validation errors
     * @param Model|Model[] $models the model(s) whose validation errors are to be displayed.
     * @param $encode boolean, if set to false then the error messages won't be encoded.
     * @param $showAllErrors boolean, if set to true every error message for each attribute will be shown otherwise
     * only the first error message for each attribute will be shown.
     * @return array of the validation errors
     * @since 2.0.14
     */
    private static function collect_errors($models, $encode, $show_all_errors): array
    {
        $lines = [];
        if (!is_array($models)) {
            $models = [$models];
        }
        foreach ($models as $model) {
            $lines = array_unique(array_merge($lines, $model->get_error_summary($show_all_errors)));
        }
        // If there are the same error messages for different attributes, array_unique will leave gaps
        // between sequential keys. Applying array_values to reorder array keys.
        $lines = array_values($lines);
        if ($encode) {
            foreach ($lines as &$line) {
                $line = Html::encode($line);
            }
        }
        return $lines;
    }
    /**
     * Generates a tag that contains the first validation error of the specified model attribute.
     * Note that even if there is no validation error, this method will still return an empty error tag.
     * @param Model $model the model object
     * @param string $attribute the attribute name or expression. See [[getAttributeName()]] for the format
     * about attribute expression.
     * @param array $options the tag options in terms of name-value pairs. The values will be HTML-encoded
     * using [[encode()]]. If a value is null, the corresponding attribute will not be rendered.
     *
     * The following options are specially handled:
     *
     * - tag: this specifies the tag name. If not set, "div" will be used.
     *   See also [[tag()]].
     * - encode: boolean, if set to false then the error message won't be encoded.
     * - errorSource (since 2.0.14): \Closure|callable, callback that will be called to obtain an error message.
     *   The signature of the callback must be: `function ($model, $attribute)` and return a string.
     *   When not set, the `$model->getFirstError()` method will be called.
     *
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     *
     * @return string the generated label tag
     */
    public static function error($model, $attribute, $options = [])
    {
        $attribute = static::get_attribute_name($attribute);
        $error_source = Array_Helper::remove($options, 'errorSource');
        if ($error_source !== null) {
            $error = call_user_func($error_source, $model, $attribute);
        } else {
            $error = $model->get_first_error($attribute);
        }
        $tag = Array_Helper::remove($options, 'tag', 'div');
        $encode = Array_Helper::remove($options, 'encode', true);
        return Html::tag($tag, $encode ? Html::encode($error) : $error, $options);
    }
    /**
     * Generates an input tag for the given model attribute.
     * This method will generate the "name" and "value" tag attributes automatically for the model attribute
     * unless they are explicitly specified in `$options`.
     * @param string $type the input type (e.g. 'text', 'password')
     * @param Model $model the model object
     * @param string $attribute the attribute name or expression. See [[getAttributeName()]] for the format
     * about attribute expression.
     * @param array $options the tag options in terms of name-value pairs. These will be rendered as
     * the attributes of the resulting tag. The values will be HTML-encoded using [[encode()]].
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     * @return string the generated input tag
     */
    public static function active_input($type, $model, $attribute, array $options = [])
    {
        $name = $options['name'] ?? static::get_input_name($model, $attribute);
        $value = $options['value'] ?? static::get_attribute_value($model, $attribute);
        if (!array_key_exists('id', $options)) {
            $options['id'] = static::get_input_id($model, $attribute);
        }
        static::set_active_placeholder($model, $attribute, $options);
        self::normalize_max_length($model, $attribute, $options);
        return static::input($type, $name, $value, $options);
    }
    /**
     * If `maxlength` option is set true and the model attribute is validated by a string validator,
     * the `maxlength` option will take the max value of [[\yii\validators\StringValidator::max]] and
     * [[\yii\validators\StringValidator::length]].
     * @param Model $model the model object
     * @param string $attribute the attribute name or expression.
     * @param array $options the tag options in terms of name-value pairs.
     */
    private static function normalize_max_length($model, $attribute, array &$options): void
    {
        if (isset($options['maxlength']) && $options['maxlength'] === true) {
            unset($options['maxlength']);
            $attr_name = static::get_attribute_name($attribute);
            foreach ($model->get_active_validators($attr_name) as $validator) {
                if ($validator instanceof String_Validator && ($validator->max !== null || $validator->length !== null)) {
                    $options['maxlength'] = max($validator->max, $validator->length);
                    break;
                }
            }
        }
    }
    /**
     * Generates a text input tag for the given model attribute.
     * This method will generate the "name" and "value" tag attributes automatically for the model attribute
     * unless they are explicitly specified in `$options`.
     * @param Model $model the model object
     * @param string $attribute the attribute name or expression. See [[getAttributeName()]] for the format
     * about attribute expression.
     * @param array $options the tag options in terms of name-value pairs. These will be rendered as
     * the attributes of the resulting tag. The values will be HTML-encoded using [[encode()]].
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     * The following special options are recognized:
     *
     * - maxlength: integer|boolean, when `maxlength` is set true and the model attribute is validated
     *   by a string validator, the `maxlength` option will take the max value of [[\yii\validators\StringValidator::max]]
     *   and [[\yii\validators\StringValidator::length].
     *   This is available since version 2.0.3 and improved taking `length` into account since version 2.0.42.
     * - placeholder: string|boolean, when `placeholder` equals `true`, the attribute label from the $model will be used
     *   as a placeholder (this behavior is available since version 2.0.14).
     *
     * @return string the generated input tag
     */
    public static function active_text_input($model, $attribute, $options = [])
    {
        return static::active_input('text', $model, $attribute, $options);
    }
    /**
     * Generate placeholder from model attribute label.
     *
     * @param Model $model the model object
     * @param string $attribute the attribute name or expression. See [[getAttributeName()]] for the format
     * about attribute expression.
     * @param array $options the tag options in terms of name-value pairs. These will be rendered as
     * the attributes of the resulting tag. The values will be HTML-encoded using [[encode()]].
     * @since 2.0.14
     */
    protected static function set_active_placeholder($model, $attribute, array &$options = [])
    {
        if (isset($options['placeholder']) && $options['placeholder'] === true) {
            $attribute = static::get_attribute_name($attribute);
            $options['placeholder'] = $model->get_attribute_label($attribute);
        }
    }
    /**
     * Generates a hidden input tag for the given model attribute.
     * This method will generate the "name" and "value" tag attributes automatically for the model attribute
     * unless they are explicitly specified in `$options`.
     * @param Model $model the model object
     * @param string $attribute the attribute name or expression. See [[getAttributeName()]] for the format
     * about attribute expression.
     * @param array $options the tag options in terms of name-value pairs. These will be rendered as
     * the attributes of the resulting tag. The values will be HTML-encoded using [[encode()]].
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     * @return string the generated input tag
     */
    public static function active_hidden_input($model, $attribute, $options = [])
    {
        return static::active_input('hidden', $model, $attribute, $options);
    }
    /**
     * Generates a password input tag for the given model attribute.
     * This method will generate the "name" and "value" tag attributes automatically for the model attribute
     * unless they are explicitly specified in `$options`.
     * @param Model $model the model object
     * @param string $attribute the attribute name or expression. See [[getAttributeName()]] for the format
     * about attribute expression.
     * @param array $options the tag options in terms of name-value pairs. These will be rendered as
     * the attributes of the resulting tag. The values will be HTML-encoded using [[encode()]].
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     * The following special options are recognized:
     *
     * - maxlength: integer|boolean, when `maxlength` is set true and the model attribute is validated
     *   by a string validator, the `maxlength` option will take the max value of [[\yii\validators\StringValidator::max]]
     *   and [[\yii\validators\StringValidator::length].
     *   This is available since version 2.0.6 and improved taking `length` into account since version 2.0.42.
     * - placeholder: string|boolean, when `placeholder` equals `true`, the attribute label from the $model will be used
     *   as a placeholder (this behavior is available since version 2.0.14).
     *
     * @return string the generated input tag
     */
    public static function active_password_input($model, $attribute, $options = [])
    {
        return static::active_input('password', $model, $attribute, $options);
    }
    /**
     * Generates a file input tag for the given model attribute.
     * This method will generate the "name" and "value" tag attributes automatically for the model attribute
     * unless they are explicitly specified in `$options`.
     * Additionally, if a separate set of HTML options array is defined inside `$options` with a key named `hiddenOptions`,
     * it will be passed to the `activeHiddenInput` field as its own `$options` parameter.
     * @param Model $model the model object
     * @param string $attribute the attribute name or expression. See [[getAttributeName()]] for the format
     * about attribute expression.
     * @param array $options the tag options in terms of name-value pairs. These will be rendered as
     * the attributes of the resulting tag. The values will be HTML-encoded using [[encode()]].
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     * If `hiddenOptions` parameter which is another set of HTML options array is defined, it will be extracted
     * from `$options` to be used for the hidden input.
     * @return string the generated input tag
     */
    public static function active_file_input($model, $attribute, array $options = []): string
    {
        $hidden_options = ['id' => null, 'value' => ''];
        if (isset($options['name'])) {
            $hidden_options['name'] = $options['name'];
        }
        // make sure disabled input is not sending any value
        if (!empty($options['disabled'])) {
            $hidden_options['disabled'] = $options['disabled'];
        }
        $hidden_options = Array_Helper::merge($hidden_options, Array_Helper::remove($options, 'hiddenOptions', []));
        // Add a hidden field so that if a model only has a file field, we can
        // still use isset($_POST[$modelClass]) to detect if the input is submitted.
        // The hidden input will be assigned its own set of html options via `$hiddenOptions`.
        // This provides the possibility to interact with the hidden field via client script.
        // Note: For file-field-only model with `disabled` option set to `true` input submitting detection won't work.
        return static::active_hidden_input($model, $attribute, $hidden_options) . static::active_input('file', $model, $attribute, $options);
    }
    /**
     * Generates a textarea tag for the given model attribute.
     * The model attribute value will be used as the content in the textarea.
     * @param Model $model the model object
     * @param string $attribute the attribute name or expression. See [[getAttributeName()]] for the format
     * about attribute expression.
     * @param array $options the tag options in terms of name-value pairs. These will be rendered as
     * the attributes of the resulting tag. The values will be HTML-encoded using [[encode()]].
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     * The following special options are recognized:
     *
     * - maxlength: integer|boolean, when `maxlength` is set true and the model attribute is validated
     *   by a string validator, the `maxlength` option will take the max value of [[\yii\validators\StringValidator::max]]
     *   and [[\yii\validators\StringValidator::length].
     *   This is available since version 2.0.6 and improved taking `length` into account since version 2.0.42.
     * - placeholder: string|boolean, when `placeholder` equals `true`, the attribute label from the $model will be used
     *   as a placeholder (this behavior is available since version 2.0.14).
     *
     * @return string the generated textarea tag
     */
    public static function active_textarea($model, $attribute, array $options = [])
    {
        $name = $options['name'] ?? static::get_input_name($model, $attribute);
        if (isset($options['value'])) {
            $value = $options['value'];
            unset($options['value']);
        } else {
            $value = static::get_attribute_value($model, $attribute);
        }
        if (!array_key_exists('id', $options)) {
            $options['id'] = static::get_input_id($model, $attribute);
        }
        self::normalize_max_length($model, $attribute, $options);
        static::set_active_placeholder($model, $attribute, $options);
        return static::textarea($name, $value, $options);
    }
    /**
     * Generates a radio button tag together with a label for the given model attribute.
     * This method will generate the "checked" tag attribute according to the model attribute value.
     * @param Model $model the model object
     * @param string $attribute the attribute name or expression. See [[getAttributeName()]] for the format
     * about attribute expression.
     * @param array $options the tag options in terms of name-value pairs.
     * See [[booleanInput()]] for details about accepted attributes.
     *
     * @return string the generated radio button tag
     */
    public static function active_radio($model, $attribute, $options = [])
    {
        return static::active_boolean_input('radio', $model, $attribute, $options);
    }
    /**
     * Generates a checkbox tag together with a label for the given model attribute.
     * This method will generate the "checked" tag attribute according to the model attribute value.
     * @param Model $model the model object
     * @param string $attribute the attribute name or expression. See [[getAttributeName()]] for the format
     * about attribute expression.
     * @param array $options the tag options in terms of name-value pairs.
     * See [[booleanInput()]] for details about accepted attributes.
     *
     * @return string the generated checkbox tag
     */
    public static function active_checkbox($model, $attribute, $options = [])
    {
        return static::active_boolean_input('checkbox', $model, $attribute, $options);
    }
    /**
     * Generates a boolean input
     * This method is mainly called by [[activeCheckbox()]] and [[activeRadio()]].
     * @param string $type the input type. This can be either `radio` or `checkbox`.
     * @param Model $model the model object
     * @param string $attribute the attribute name or expression. See [[getAttributeName()]] for the format
     * about attribute expression.
     * @param array $options the tag options in terms of name-value pairs.
     * See [[booleanInput()]] for details about accepted attributes.
     * @return string the generated input element
     * @since 2.0.9
     */
    protected static function active_boolean_input($type, $model, $attribute, array $options = [])
    {
        $name = $options['name'] ?? static::get_input_name($model, $attribute);
        $value = static::get_attribute_value($model, $attribute);
        if (!array_key_exists('value', $options)) {
            $options['value'] = '1';
        }
        if (!array_key_exists('uncheck', $options)) {
            $options['uncheck'] = '0';
        } elseif ($options['uncheck'] === false) {
            unset($options['uncheck']);
        }
        if (!array_key_exists('label', $options)) {
            $options['label'] = static::encode($model->get_attribute_label(static::get_attribute_name($attribute)));
        } elseif ($options['label'] === false) {
            unset($options['label']);
        }
        $checked = "{$value}" === "{$options['value']}";
        if (!array_key_exists('id', $options)) {
            $options['id'] = static::get_input_id($model, $attribute);
        }
        return static::$type($name, $checked, $options);
    }
    /**
     * Generates a drop-down list for the given model attribute.
     * The selection of the drop-down list is taken from the value of the model attribute.
     * @param Model $model the model object
     * @param string $attribute the attribute name or expression. See [[getAttributeName()]] for the format
     * about attribute expression.
     * @param array $items the option data items. The array keys are option values, and the array values
     * are the corresponding option labels. The array can also be nested (i.e. some array values are arrays too).
     * For each sub-array, an option group will be generated whose label is the key associated with the sub-array.
     * If you have a list of data models, you may convert them into the format described above using
     * [[\yii\helpers\ArrayHelper::map()]].
     *
     * Note, the values and labels will be automatically HTML-encoded by this method, and the blank spaces in
     * the labels will also be HTML-encoded.
     * @param array $options the tag options in terms of name-value pairs. The following options are specially handled:
     *
     * - prompt: string, a prompt text to be displayed as the first option. Since version 2.0.11 you can use an array
     *   to override the value and to set other tag attributes:
     *
     *   ```
     *   ['text' => 'Please select', 'options' => ['value' => 'none', 'class' => 'prompt', 'label' => 'Select']],
     *   ```
     *
     * - options: array, the attributes for the select option tags. The array keys must be valid option values,
     *   and the array values are the extra attributes for the corresponding option tags. For example,
     *
     *   ```
     *   [
     *       'value1' => ['disabled' => true],
     *       'value2' => ['label' => 'value 2'],
     *   ];
     *   ```
     *
     * - groups: array, the attributes for the optgroup tags. The structure of this is similar to that of 'options',
     *   except that the array keys represent the optgroup labels specified in $items.
     * - encodeSpaces: bool, whether to encode spaces in option prompt and option value with `&nbsp;` character.
     *   Defaults to false.
     * - encode: bool, whether to encode option prompt and option value characters.
     *   Defaults to `true`. This option is available since 2.0.3.
     *
     * The rest of the options will be rendered as the attributes of the resulting tag. The values will
     * be HTML-encoded using [[encode()]]. If a value is null, the corresponding attribute will not be rendered.
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     *
     * @return string the generated drop-down list tag
     */
    public static function active_drop_down_list($model, $attribute, $items, array $options = [])
    {
        if (empty($options['multiple'])) {
            return static::active_list_input('dropDownList', $model, $attribute, $items, $options);
        }
        return static::active_list_box($model, $attribute, $items, $options);
    }
    /**
     * Generates a list box.
     * The selection of the list box is taken from the value of the model attribute.
     * @param Model $model the model object
     * @param string $attribute the attribute name or expression. See [[getAttributeName()]] for the format
     * about attribute expression.
     * @param array $items the option data items. The array keys are option values, and the array values
     * are the corresponding option labels. The array can also be nested (i.e. some array values are arrays too).
     * For each sub-array, an option group will be generated whose label is the key associated with the sub-array.
     * If you have a list of data models, you may convert them into the format described above using
     * [[\yii\helpers\ArrayHelper::map()]].
     *
     * Note, the values and labels will be automatically HTML-encoded by this method, and the blank spaces in
     * the labels will also be HTML-encoded.
     * @param array $options the tag options in terms of name-value pairs. The following options are specially handled:
     *
     * - prompt: string, a prompt text to be displayed as the first option. Since version 2.0.11 you can use an array
     *   to override the value and to set other tag attributes:
     *
     *   ```
     *   ['text' => 'Please select', 'options' => ['value' => 'none', 'class' => 'prompt', 'label' => 'Select']],
     *   ```
     *
     * - options: array, the attributes for the select option tags. The array keys must be valid option values,
     *   and the array values are the extra attributes for the corresponding option tags. For example,
     *
     *   ```
     *   [
     *       'value1' => ['disabled' => true],
     *       'value2' => ['label' => 'value 2'],
     *   ];
     *   ```
     *
     * - groups: array, the attributes for the optgroup tags. The structure of this is similar to that of 'options',
     *   except that the array keys represent the optgroup labels specified in $items.
     * - unselect: string, the value that will be submitted when no option is selected.
     *   When this attribute is set, a hidden field will be generated so that if no option is selected in multiple
     *   mode, we can still obtain the posted unselect value.
     * - encodeSpaces: bool, whether to encode spaces in option prompt and option value with `&nbsp;` character.
     *   Defaults to false.
     * - encode: bool, whether to encode option prompt and option value characters.
     *   Defaults to `true`. This option is available since 2.0.3.
     *
     * The rest of the options will be rendered as the attributes of the resulting tag. The values will
     * be HTML-encoded using [[encode()]]. If a value is null, the corresponding attribute will not be rendered.
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     *
     * @return string the generated list box tag
     */
    public static function active_list_box($model, $attribute, $items, $options = [])
    {
        return static::active_list_input('listBox', $model, $attribute, $items, $options);
    }
    /**
     * Generates a list of checkboxes.
     * A checkbox list allows multiple selection, like [[listBox()]].
     * As a result, the corresponding submitted value is an array.
     * The selection of the checkbox list is taken from the value of the model attribute.
     * @param Model $model the model object
     * @param string $attribute the attribute name or expression. See [[getAttributeName()]] for the format
     * about attribute expression.
     * @param array $items the data item used to generate the checkboxes.
     * The array keys are the checkbox values, and the array values are the corresponding labels.
     * @param array $options options (name => config) for the checkbox list container tag.
     * The following options are specially handled:
     *
     * - tag: string|false, the tag name of the container element. False to render checkbox without container.
     *   See also [[tag()]].
     * - unselect: string, the value that should be submitted when none of the checkboxes is selected.
     *   You may set this option to be null to prevent default value submission.
     *   If this option is not set, an empty string will be submitted.
     * - encode: boolean, whether to HTML-encode the checkbox labels. Defaults to true.
     *   This option is ignored if `item` option is set.
     * - separator: string, the HTML code that separates items.
     * - itemOptions: array, the options for generating the checkbox tag using [[checkbox()]].
     * - item: callable, a callback that can be used to customize the generation of the HTML code
     *   corresponding to a single item in $items. The signature of this callback must be:
     *
     *   ```
     *   function ($index, $label, $name, $checked, $value)
     *   ```
     *
     *   where $index is the zero-based index of the checkbox in the whole list; $label
     *   is the label for the checkbox; and $name, $value and $checked represent the name,
     *   value and the checked status of the checkbox input.
     *
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     *
     * @return string the generated checkbox list
     */
    public static function active_checkbox_list($model, $attribute, $items, $options = [])
    {
        return static::active_list_input('checkboxList', $model, $attribute, $items, $options);
    }
    /**
     * Generates a list of radio buttons.
     * A radio button list is like a checkbox list, except that it only allows single selection.
     * The selection of the radio buttons is taken from the value of the model attribute.
     * @param Model $model the model object
     * @param string $attribute the attribute name or expression. See [[getAttributeName()]] for the format
     * about attribute expression.
     * @param array $items the data item used to generate the radio buttons.
     * The array keys are the radio values, and the array values are the corresponding labels.
     * @param array $options options (name => config) for the radio button list container tag.
     * The following options are specially handled:
     *
     * - tag: string|false, the tag name of the container element. False to render radio button without container.
     *   See also [[tag()]].
     * - unselect: string, the value that should be submitted when none of the radio buttons is selected.
     *   You may set this option to be null to prevent default value submission.
     *   If this option is not set, an empty string will be submitted.
     * - encode: boolean, whether to HTML-encode the checkbox labels. Defaults to true.
     *   This option is ignored if `item` option is set.
     * - separator: string, the HTML code that separates items.
     * - itemOptions: array, the options for generating the radio button tag using [[radio()]].
     * - item: callable, a callback that can be used to customize the generation of the HTML code
     *   corresponding to a single item in $items. The signature of this callback must be:
     *
     *   ```
     *   function ($index, $label, $name, $checked, $value)
     *   ```
     *
     *   where $index is the zero-based index of the radio button in the whole list; $label
     *   is the label for the radio button; and $name, $value and $checked represent the name,
     *   value and the checked status of the radio button input.
     *
     * See [[renderTagAttributes()]] for details on how attributes are being rendered.
     *
     * @return string the generated radio button list
     */
    public static function active_radio_list($model, $attribute, $items, $options = [])
    {
        return static::active_list_input('radioList', $model, $attribute, $items, $options);
    }
    /**
     * Generates a list of input fields.
     * This method is mainly called by [[activeListBox()]], [[activeRadioList()]] and [[activeCheckboxList()]].
     * @param string $type the input type. This can be 'listBox', 'radioList', or 'checkBoxList'.
     * @param Model $model the model object
     * @param string $attribute the attribute name or expression. See [[getAttributeName()]] for the format
     * about attribute expression.
     * @param array $items the data item used to generate the input fields.
     * The array keys are the input values, and the array values are the corresponding labels.
     * @param array $options options (name => config) for the input list. The supported special options
     * depend on the input type specified by `$type`.
     * @return string the generated input list
     */
    protected static function active_list_input($type, $model, $attribute, $items, array $options = [])
    {
        $name = Array_Helper::remove($options, 'name', static::get_input_name($model, $attribute));
        $selection = Array_Helper::remove($options, 'value', static::get_attribute_value($model, $attribute));
        if (!array_key_exists('unselect', $options)) {
            $options['unselect'] = '';
        }
        if (!array_key_exists('id', $options)) {
            $options['id'] = static::get_input_id($model, $attribute);
        }
        return static::$type($name, $selection, $items, $options);
    }
    /**
     * Renders the option tags that can be used by [[dropDownList()]] and [[listBox()]].
     * @param string|array|bool|null $selection the selected value(s). String/boolean for single or array for multiple
     * selection(s).
     * @param array $items the option data items. The array keys are option values, and the array values
     * are the corresponding option labels. The array can also be nested (i.e. some array values are arrays too).
     * For each sub-array, an option group will be generated whose label is the key associated with the sub-array.
     * If you have a list of data models, you may convert them into the format described above using
     * [[\yii\helpers\ArrayHelper::map()]].
     *
     * Note, the values and labels will be automatically HTML-encoded by this method, and the blank spaces in
     * the labels will also be HTML-encoded.
     * @param array $tagOptions the $options parameter that is passed to the [[dropDownList()]] or [[listBox()]] call.
     * This method will take out these elements, if any: "prompt", "options" and "groups". See more details
     * in [[dropDownList()]] for the explanation of these elements.
     *
     * @return string the generated list options
     */
    public static function render_select_options($selection, $items, array &$tag_options = []): string
    {
        if (Array_Helper::is_traversable($selection)) {
            $normalized_selection = [];
            foreach (Array_Helper::to_array($selection) as $selection_item) {
                if (is_bool($selection_item)) {
                    $normalized_selection[] = $selection_item ? '1' : '0';
                } else {
                    $normalized_selection[] = (string) $selection_item;
                }
            }
            $selection = $normalized_selection;
        } elseif (is_bool($selection)) {
            $selection = $selection ? '1' : '0';
        }
        $lines = [];
        $encode_spaces = Array_Helper::remove($tag_options, 'encodeSpaces', false);
        $encode = Array_Helper::remove($tag_options, 'encode', true);
        $strict = Array_Helper::remove($tag_options, 'strict', false);
        if (isset($tag_options['prompt'])) {
            $prompt_options = ['value' => ''];
            if (is_string($tag_options['prompt'])) {
                $prompt_text = $tag_options['prompt'];
            } else {
                $prompt_text = $tag_options['prompt']['text'];
                $prompt_options = array_merge($prompt_options, $tag_options['prompt']['options']);
            }
            $prompt_text = $encode ? static::encode($prompt_text) : $prompt_text;
            if ($encode_spaces) {
                $prompt_text = str_replace(' ', '&nbsp;', $prompt_text);
            }
            $lines[] = static::tag('option', $prompt_text, $prompt_options);
        }
        $options = $tag_options['options'] ?? [];
        $groups = $tag_options['groups'] ?? [];
        unset($tag_options['prompt'], $tag_options['options'], $tag_options['groups']);
        $options['encodeSpaces'] = Array_Helper::get_value($options, 'encodeSpaces', $encode_spaces);
        $options['encode'] = Array_Helper::get_value($options, 'encode', $encode);
        foreach ($items as $key => $value) {
            if (is_array($value)) {
                $group_attrs = $groups[$key] ?? [];
                if (!isset($group_attrs['label'])) {
                    $group_attrs['label'] = $key;
                }
                $attrs = ['options' => $options, 'groups' => $groups, 'encodeSpaces' => $encode_spaces, 'encode' => $encode, 'strict' => $strict];
                $content = static::render_select_options($selection, $value, $attrs);
                $lines[] = static::tag('optgroup', "\n" . $content . "\n", $group_attrs);
            } else {
                $attrs = $options[$key] ?? [];
                $attrs['value'] = (string) $key;
                if (!array_key_exists('selected', $attrs)) {
                    $selected = false;
                    if ($selection !== null) {
                        if (Array_Helper::is_traversable($selection)) {
                            $selected = Array_Helper::is_in((string) $key, $selection, $strict);
                        } elseif ($key === '' || $selection === '') {
                            $selected = $selection === $key;
                        } elseif ($strict) {
                            $selected = !strcmp((string) $key, (string) $selection);
                        } else {
                            $selected = $selection == $key;
                        }
                    }
                    $attrs['selected'] = $selected;
                }
                $text = $encode ? static::encode($value) : $value;
                if ($encode_spaces) {
                    $text = str_replace(' ', '&nbsp;', $text);
                }
                $lines[] = static::tag('option', $text, $attrs);
            }
        }
        return implode("\n", $lines);
    }
    /**
     * Renders the HTML tag attributes.
     *
     * Attributes whose values are of boolean type will be treated as
     * [boolean attributes](https://html.spec.whatwg.org/multipage/common-microsyntaxes.html#boolean-attributes).
     *
     * Attributes whose values are null will not be rendered.
     *
     * The values of attributes will be HTML-encoded using [[encode()]].
     *
     * `aria` and `data` attributes get special handling when they are set to an array value. In these cases,
     * the array will be "expanded" and a list of ARIA/data attributes will be rendered. For example,
     * `'aria' => ['role' => 'checkbox', 'value' => 'true']` would be rendered as
     * `aria-role="checkbox" aria-value="true"`.
     *
     * If a nested `data` value is set to an array, it will be JSON-encoded. For example,
     * `'data' => ['params' => ['id' => 1, 'name' => 'yii']]` would be rendered as
     * `data-params='{"id":1,"name":"yii"}'`.
     *
     * @param array $attributes attributes to be rendered. The attribute values will be HTML-encoded using [[encode()]].
     * @return string the rendering result. If the attributes are not empty, they will be rendered
     * into a string with a leading white space (so that it can be directly appended to the tag name
     * in a tag). If there is no attribute, an empty string will be returned.
     * @see addCssClass()
     */
    public static function render_tag_attributes($attributes): string
    {
        if (count($attributes) > 1) {
            $sorted = [];
            foreach (static::$attribute_order as $name) {
                if (isset($attributes[$name])) {
                    $sorted[$name] = $attributes[$name];
                }
            }
            $attributes = array_merge($sorted, $attributes);
        }
        $html = '';
        foreach ($attributes as $name => $value) {
            if (is_bool($value)) {
                if ($value) {
                    $html .= " {$name}";
                }
            } elseif (is_array($value)) {
                if (in_array($name, static::$data_attributes)) {
                    foreach ($value as $n => $v) {
                        if (is_array($v)) {
                            $html .= " {$name}-{$n}='" . Json::html_encode($v) . "'";
                        } elseif (is_bool($v)) {
                            if ($v) {
                                $html .= " {$name}-{$n}";
                            }
                        } elseif ($v !== null) {
                            $html .= " {$name}-{$n}=\"" . static::encode($v) . '"';
                        }
                    }
                } elseif ($name === 'class') {
                    if (empty($value)) {
                        continue;
                    }
                    if (static::$normalize_class_attribute === true && count($value) > 1) {
                        // removes duplicate classes
                        $value = explode(' ', implode(' ', $value));
                        $value = array_unique($value);
                    }
                    $html .= " {$name}=\"" . static::encode(implode(' ', array_filter($value))) . '"';
                } elseif ($name === 'style') {
                    if (empty($value)) {
                        continue;
                    }
                    $html .= " {$name}=\"" . static::encode(static::css_style_from_array($value)) . '"';
                } else {
                    $html .= " {$name}='" . Json::html_encode($value) . "'";
                }
            } elseif ($value !== null) {
                $html .= " {$name}=\"" . static::encode($value) . '"';
            }
        }
        return $html;
    }
    /**
     * Adds a CSS class (or several classes) to the specified options.
     *
     * If the CSS class is already in the options, it will not be added again.
     * If class specification at given options is an array, and some class placed there with the named (string) key,
     * overriding of such key will have no effect. For example:
     *
     * ```
     * $options = ['class' => ['persistent' => 'initial']];
     * Html::addCssClass($options, ['persistent' => 'override']);
     * var_dump($options['class']); // outputs: array('persistent' => 'initial');
     * ```
     *
     * @param array $options the options to be modified.
     * @param string|array $class the CSS class(es) to be added
     * @see removeCssClass()
     */
    public static function add_css_class(array &$options, $class): void
    {
        if (isset($options['class'])) {
            if (is_array($options['class'])) {
                $options['class'] = self::merge_css_classes($options['class'], (array) $class);
            } else {
                $classes = preg_split('/\s+/', $options['class'], -1, PREG_SPLIT_NO_EMPTY);
                $options['class'] = implode(' ', self::merge_css_classes($classes, (array) $class));
            }
        } else {
            $options['class'] = $class;
        }
    }
    /**
     * Merges already existing CSS classes with new one.
     * This method provides the priority for named existing classes over additional.
     * @param array $existingClasses already existing CSS classes.
     * @param array $additionalClasses CSS classes to be added.
     * @return array merge result.
     * @see addCssClass()
     */
    private static function merge_css_classes(array $existing_classes, array $additional_classes): array
    {
        foreach ($additional_classes as $key => $class) {
            if (is_int($key) && !in_array($class, $existing_classes)) {
                $existing_classes[] = $class;
            } elseif (!isset($existing_classes[$key])) {
                $existing_classes[$key] = $class;
            }
        }
        return static::$normalize_class_attribute ? array_unique($existing_classes) : $existing_classes;
    }
    /**
     * Removes a CSS class from the specified options.
     * @param array $options the options to be modified.
     * @param string|array $class the CSS class(es) to be removed
     * @see addCssClass()
     */
    public static function remove_css_class(array &$options, $class): void
    {
        if (isset($options['class'])) {
            if (is_array($options['class'])) {
                $classes = array_diff($options['class'], (array) $class);
                if (empty($classes)) {
                    unset($options['class']);
                } else {
                    $options['class'] = $classes;
                }
            } else {
                $classes = preg_split('/\s+/', $options['class'], -1, PREG_SPLIT_NO_EMPTY);
                $classes = array_diff($classes, (array) $class);
                if (empty($classes)) {
                    unset($options['class']);
                } else {
                    $options['class'] = implode(' ', $classes);
                }
            }
        }
    }
    /**
     * Adds the specified CSS style to the HTML options.
     *
     * If the options already contain a `style` element, the new style will be merged
     * with the existing one. If a CSS property exists in both the new and the old styles,
     * the old one may be overwritten if `$overwrite` is true.
     *
     * For example,
     *
     * ```
     * Html::addCssStyle($options, 'width: 100px; height: 200px');
     * ```
     *
     * @param array $options the HTML options to be modified.
     * @param string|array $style the new style string (e.g. `'width: 100px; height: 200px'`) or
     * array (e.g. `['width' => '100px', 'height' => '200px']`).
     * @param bool $overwrite whether to overwrite existing CSS properties if the new style
     * contain them too.
     * @see removeCssStyle()
     * @see cssStyleFromArray()
     * @see cssStyleToArray()
     */
    public static function add_css_style(array &$options, $style, $overwrite = true): void
    {
        if (!empty($options['style'])) {
            $old_style = is_array($options['style']) ? $options['style'] : static::css_style_to_array($options['style']);
            $new_style = is_array($style) ? $style : static::css_style_to_array($style);
            if (!$overwrite) {
                foreach ($new_style as $property => $value) {
                    if (isset($old_style[$property])) {
                        unset($new_style[$property]);
                    }
                }
            }
            $style = array_merge($old_style, $new_style);
        }
        $options['style'] = is_array($style) ? static::css_style_from_array($style) : $style;
    }
    /**
     * Removes the specified CSS style from the HTML options.
     *
     * For example,
     *
     * ```
     * Html::removeCssStyle($options, ['width', 'height']);
     * ```
     *
     * @param array $options the HTML options to be modified.
     * @param string|array $properties the CSS properties to be removed. You may use a string
     * if you are removing a single property.
     * @see addCssStyle()
     */
    public static function remove_css_style(array &$options, $properties): void
    {
        if (!empty($options['style'])) {
            $style = is_array($options['style']) ? $options['style'] : static::css_style_to_array($options['style']);
            foreach ((array) $properties as $property) {
                unset($style[$property]);
            }
            $options['style'] = static::css_style_from_array($style);
        }
    }
    /**
     * Converts a CSS style array into a string representation.
     *
     * For example,
     *
     * ```
     * print_r(Html::cssStyleFromArray(['width' => '100px', 'height' => '200px']));
     * // will display: 'width: 100px; height: 200px;'
     * ```
     *
     * @param array $style the CSS style array. The array keys are the CSS property names,
     * and the array values are the corresponding CSS property values.
     * @return string the CSS style string. If the CSS style is empty, a null will be returned.
     */
    public static function css_style_from_array(array $style): ?string
    {
        $result = '';
        foreach ($style as $name => $value) {
            $result .= "{$name}: {$value}; ";
        }
        // return null if empty to avoid rendering the "style" attribute
        return $result === '' ? null : rtrim($result);
    }
    /**
     * Converts a CSS style string into an array representation.
     *
     * The array keys are the CSS property names, and the array values
     * are the corresponding CSS property values.
     *
     * For example,
     *
     * ```
     * print_r(Html::cssStyleToArray('width: 100px; height: 200px;'));
     * // will display: ['width' => '100px', 'height' => '200px']
     * ```
     *
     * @param string $style the CSS style string
     * @return array the array representation of the CSS style
     */
    public static function css_style_to_array($style): array
    {
        $result = [];
        foreach (explode(';', $style) as $property) {
            $property = explode(':', $property);
            if (count($property) > 1) {
                $result[trim($property[0])] = trim($property[1]);
            }
        }
        return $result;
    }
    /**
     * Returns the real attribute name from the given attribute expression.
     *
     * An attribute expression is an attribute name prefixed and/or suffixed with array indexes.
     * It is mainly used in tabular data input and/or input of array type. Below are some examples:
     *
     * - `[0]content` is used in tabular data input to represent the "content" attribute
     *   for the first model in tabular input;
     * - `dates[0]` represents the first array element of the "dates" attribute;
     * - `[0]dates[0]` represents the first array element of the "dates" attribute
     *   for the first model in tabular input.
     *
     * If `$attribute` has neither prefix nor suffix, it will be returned back without change.
     * @param string $attribute the attribute name or expression
     * @return string the attribute name without prefix and suffix.
     * @throws InvalidArgumentException if the attribute name contains non-word characters.
     */
    public static function get_attribute_name($attribute): string
    {
        if (preg_match(static::$attribute_regex, $attribute, $matches)) {
            return $matches[2];
        }
        throw new InvalidArgumentException('Attribute name must contain word characters only.');
    }
    /**
     * Returns the value of the specified attribute name or expression.
     *
     * For an attribute expression like `[0]dates[0]`, this method will return the value of `$model->dates[0]`.
     * See [[getAttributeName()]] for more details about attribute expression.
     *
     * If an attribute value is an instance of [[ActiveRecordInterface]] or an array of such instances,
     * the primary value(s) of the AR instance(s) will be returned instead.
     *
     * @param Model $model the model object
     * @param string $attribute the attribute name or expression
     * @return string|array|null the corresponding attribute value
     * @throws InvalidArgumentException if the attribute name contains non-word characters.
     */
    public static function get_attribute_value($model, $attribute)
    {
        if (!preg_match(static::$attribute_regex, $attribute, $matches)) {
            throw new InvalidArgumentException('Attribute name must contain word characters only.');
        }
        $attribute = $matches[2];
        $value = $model->{$attribute};
        if ($matches[3] !== '') {
            foreach (explode('][', trim($matches[3], '[]')) as $id) {
                if ((is_array($value) || $value instanceof \ArrayAccess) && isset($value[$id])) {
                    $value = $value[$id];
                } else {
                    return null;
                }
            }
        }
        // https://github.com/yiisoft/yii2/issues/1457
        if (is_array($value)) {
            foreach ($value as $i => $v) {
                if ($v instanceof Active_Record_Interface) {
                    $v = $v->get_primary_key(false);
                    $value[$i] = is_array($v) ? json_encode($v) : $v;
                }
            }
        } elseif ($value instanceof Active_Record_Interface) {
            $value = $value->get_primary_key(false);
            return is_array($value) ? json_encode($value) : $value;
        }
        return $value;
    }
    /**
     * Generates an appropriate input name for the specified attribute name or expression.
     *
     * This method generates a name that can be used as the input name to collect user input
     * for the specified attribute. The name is generated according to the [[Model::formName|form name]]
     * of the model and the given attribute name. For example, if the form name of the `Post` model
     * is `Post`, then the input name generated for the `content` attribute would be `Post[content]`.
     *
     * See [[getAttributeName()]] for explanation of attribute expression.
     *
     * @param Model $model the model object
     * @param string $attribute the attribute name or expression
     * @return string the generated input name
     * @throws InvalidArgumentException if the attribute name contains non-word characters.
     */
    public static function get_input_name($model, $attribute): string
    {
        $form_name = $model->form_name();
        if (!preg_match(static::$attribute_regex, $attribute, $matches)) {
            throw new InvalidArgumentException('Attribute name must contain word characters only.');
        }
        $prefix = $matches[1];
        $attribute = $matches[2];
        $suffix = $matches[3];
        if ($form_name === '' && $prefix === '') {
            return $attribute . $suffix;
        }
        if ($form_name !== '') {
            return $form_name . $prefix . "[{$attribute}]" . $suffix;
        }
        throw new InvalidArgumentException(get_class($model) . '::formName() cannot be empty for tabular inputs.');
    }
    /**
     * Converts input name to ID.
     *
     * For example, if `$name` is `Post[content]`, this method will return `post-content`.
     *
     * @param string $name the input name
     * @return string the generated input ID
     * @since 2.0.43
     */
    public static function get_input_id_by_name($name): string
    {
        $charset = Yii::$app ? Yii::$app->charset : 'UTF-8';
        $name = mb_strtolower($name, $charset);
        return str_replace(['[]', '][', '[', ']', ' ', '.', '--'], ['', '-', '-', '', '-', '-', '-'], $name);
    }
    /**
     * Generates an appropriate input ID for the specified attribute name or expression.
     *
     * @param Model $model the model object
     * @param string $attribute the attribute name or expression. See [[getAttributeName()]] for explanation of attribute expression.
     * @return string the generated input ID.
     * @throws InvalidArgumentException if the attribute name contains non-word characters.
     */
    public static function get_input_id($model, $attribute)
    {
        $name = static::get_input_name($model, $attribute);
        return static::get_input_id_by_name($name);
    }
    /**
     * Escapes regular expression to use in JavaScript.
     * @param string $regexp the regular expression to be escaped.
     * @return string the escaped result.
     * @since 2.0.6
     */
    public static function escape_js_regular_expression($regexp)
    {
        $pattern = preg_replace('/\\\\x\{?([0-9a-fA-F]+)\}?/', '\u$1', $regexp);
        $deliminator = substr($pattern, 0, 1);
        $pos = strrpos($pattern, $deliminator, 1);
        $flag = substr($pattern, $pos + 1);
        if ($deliminator !== '/') {
            $pattern = '/' . str_replace('/', '\/', substr($pattern, 1, $pos - 1)) . '/';
        } else {
            $pattern = substr($pattern, 0, $pos + 1);
        }
        if (!empty($flag)) {
            $pattern .= preg_replace('/[^igmu]/', '', $flag);
        }
        return $pattern;
    }
}