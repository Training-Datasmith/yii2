<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\mail;

use Yii;
use yii\base\Component;
use yii\base\Invalid_Config_Exception;
use yii\base\View_Context_Interface;
use yii\web\View;
/**
 * BaseMailer serves as a base class that implements the basic functions required by [[MailerInterface]].
 *
 * Concrete child classes should may focus on implementing the [[sendMessage()]] method.
 *
 * @see BaseMessage
 *
 * For more details and usage information on BaseMailer, see the [guide article on mailing](guide:tutorial-mailing).
 *
 * @property View $view View instance. Note that the type of this property differs in getter and setter. See
 * [[getView()]] and [[setView()]] for details.
 * @property string $viewPath The directory that contains the view files for composing mail messages Defaults
 * to '@app/mail'.
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 2.0
 */
abstract class Base_Mailer extends Component implements Mailer_Interface, View_Context_Interface
{
    /**
     * @event MailEvent an event raised right before send.
     * You may set [[MailEvent::isValid]] to be false to cancel the send.
     */
    public const EVENT_BEFORE_SEND = 'beforeSend';
    /**
     * @event MailEvent an event raised right after send.
     */
    public const EVENT_AFTER_SEND = 'afterSend';
    /**
     * @var string|bool HTML layout view name. This is the layout used to render HTML mail body.
     * The property can take the following values:
     *
     * - a relative view name: a view file relative to [[viewPath]], e.g., 'layouts/html'.
     * - a [path alias](guide:concept-aliases): an absolute view file path specified as a path alias, e.g., '@app/mail/html'.
     * - a boolean false: the layout is disabled.
     */
    public $html_layout = 'layouts/html';
    /**
     * @var string|bool text layout view name. This is the layout used to render TEXT mail body.
     * Please refer to [[htmlLayout]] for possible values that this property can take.
     */
    public $text_layout = 'layouts/text';
    /**
     * @var array the configuration that should be applied to any newly created
     * email message instance by [[createMessage()]] or [[compose()]]. Any valid property defined
     * by [[MessageInterface]] can be configured, such as `from`, `to`, `subject`, `textBody`, `htmlBody`, etc.
     *
     * For example:
     *
     * ```
     * [
     *     'charset' => 'UTF-8',
     *     'from' => 'noreply@mydomain.com',
     *     'bcc' => 'developer@mydomain.com',
     * ]
     * ```
     */
    public $message_config = [];
    /**
     * @var string the default class name of the new message instances created by [[createMessage()]]
     */
    public $message_class = 'yii\mail\BaseMessage';
    /**
     * @var bool whether to save email messages as files under [[fileTransportPath]] instead of sending them
     * to the actual recipients. This is usually used during development for debugging purpose.
     * @see fileTransportPath
     */
    public $use_file_transport = false;
    /**
     * @var string the directory where the email messages are saved when [[useFileTransport]] is true.
     */
    public $file_transport_path = '@runtime/mail';
    /**
     * @var callable|null a PHP callback that will be called by [[send()]] when [[useFileTransport]] is true.
     * The callback should return a file name which will be used to save the email message.
     * If not set, the file name will be generated based on the current timestamp.
     *
     * The signature of the callback is:
     *
     * ```
     * function ($mailer, $message)
     * ```
     */
    public $file_transport_callback;
    /**
     * @var View|array view instance or its array configuration.
     */
    private $_view = [];
    /**
     * @var string the directory containing view files for composing mail messages.
     */
    private $_view_path;
    /**
     * @param array|View $view view instance or its array configuration that will be used to
     * render message bodies.
     * @throws InvalidConfigException on invalid argument.
     */
    public function set_view($view): void
    {
        if (!is_array($view) && !is_object($view)) {
            throw new Invalid_Config_Exception('"' . get_class($this) . '::view" should be either object or configuration array, "' . gettype($view) . '" given.');
        }
        $this->_view = $view;
    }
    /**
     * @return View view instance.
     */
    public function get_view()
    {
        if (!is_object($this->_view)) {
            $this->_view = $this->create_view($this->_view);
        }
        return $this->_view;
    }
    /**
     * Creates view instance from given configuration.
     * @param array $config view configuration.
     * @return View view instance.
     */
    protected function create_view(array $config)
    {
        if (!array_key_exists('class', $config)) {
            $config['class'] = View::class_name();
        }
        return Yii::create_object($config);
    }
    private $_message;
    /**
     * Creates a new message instance and optionally composes its body content via view rendering.
     *
     * @param string|array|null $view the view to be used for rendering the message body. This can be:
     *
     * - a string, which represents the view name or [path alias](guide:concept-aliases) for rendering the HTML body of the email.
     *   In this case, the text body will be generated by applying `strip_tags()` to the HTML body.
     * - an array with 'html' and/or 'text' elements. The 'html' element refers to the view name or path alias
     *   for rendering the HTML body, while 'text' element is for rendering the text body. For example,
     *   `['html' => 'contact-html', 'text' => 'contact-text']`.
     * - null, meaning the message instance will be returned without body content.
     *
     * The view to be rendered can be specified in one of the following formats:
     *
     * - path alias (e.g. "@app/mail/contact");
     * - a relative view name (e.g. "contact") located under [[viewPath]].
     *
     * @param array $params the parameters (name-value pairs) that will be extracted and made available in the view file.
     * @return MessageInterface message instance.
     */
    public function compose($view = null, array $params = [])
    {
        $message = $this->create_message();
        if ($view === null) {
            return $message;
        }
        if (!array_key_exists('message', $params)) {
            $params['message'] = $message;
        }
        $this->_message = $message;
        if (is_array($view)) {
            if (isset($view['html'])) {
                $html = $this->render($view['html'], $params, $this->html_layout);
            }
            if (isset($view['text'])) {
                $text = $this->render($view['text'], $params, $this->text_layout);
            }
        } else {
            $html = $this->render($view, $params, $this->html_layout);
        }
        $this->_message = null;
        if (isset($html)) {
            $message->set_html_body($html);
        }
        if (isset($text)) {
            $message->set_text_body($text);
        } elseif (isset($html)) {
            if (preg_match('~<body[^>]*>(.*?)</body>~is', $html, $match)) {
                $html = $match[1];
            }
            // remove style and script
            $html = preg_replace('~<((style|script))[^>]*>(.*?)</\1>~is', '', $html);
            // strip all HTML tags and decoded HTML entities
            $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, Yii::$app ? Yii::$app->charset : 'UTF-8');
            // improve whitespace
            $text = preg_replace("~^[ \t]+~m", '', trim($text));
            $text = preg_replace('~\R\R+~mu', "\n\n", $text);
            $message->set_text_body($text);
        }
        return $message;
    }
    /**
     * Creates a new message instance.
     * The newly created instance will be initialized with the configuration specified by [[messageConfig]].
     * If the configuration does not specify a 'class', the [[messageClass]] will be used as the class
     * of the new message instance.
     * @return MessageInterface message instance.
     */
    protected function create_message()
    {
        $config = $this->message_config;
        if (!array_key_exists('class', $config)) {
            $config['class'] = $this->message_class;
        }
        $config['mailer'] = $this;
        return Yii::create_object($config);
    }
    /**
     * Sends the given email message.
     * This method will log a message about the email being sent.
     * If [[useFileTransport]] is true, it will save the email as a file under [[fileTransportPath]].
     * Otherwise, it will call [[sendMessage()]] to send the email to its recipient(s).
     * Child classes should implement [[sendMessage()]] with the actual email sending logic.
     * @param MessageInterface $message email message instance to be sent
     * @return bool whether the message has been sent successfully
     */
    public function send($message)
    {
        if (!$this->before_send($message)) {
            return false;
        }
        $address = $message->get_to();
        if (is_array($address)) {
            $address = implode(', ', array_keys($address));
        }
        Yii::info('Sending email "' . $message->get_subject() . '" to "' . $address . '"', __METHOD__);
        if ($this->use_file_transport) {
            $is_successful = $this->save_message($message);
        } else {
            $is_successful = $this->send_message($message);
        }
        $this->after_send($message, $is_successful);
        return $is_successful;
    }
    /**
     * Sends multiple messages at once.
     *
     * The default implementation simply calls [[send()]] multiple times.
     * Child classes may override this method to implement more efficient way of
     * sending multiple messages.
     *
     * @param array $messages list of email messages, which should be sent.
     * @return int number of messages that are successfully sent.
     */
    public function send_multiple(array $messages)
    {
        $success_count = 0;
        foreach ($messages as $message) {
            if ($this->send($message)) {
                $success_count++;
            }
        }
        return $success_count;
    }
    /**
     * Renders the specified view with optional parameters and layout.
     * The view will be rendered using the [[view]] component.
     * @param string $view the view name or the [path alias](guide:concept-aliases) of the view file.
     * @param array $params the parameters (name-value pairs) that will be extracted and made available in the view file.
     * @param string|bool $layout layout view name or [path alias](guide:concept-aliases). If false, no layout will be applied.
     * @return string the rendering result.
     */
    public function render($view, $params = [], $layout = false)
    {
        $output = $this->get_view()->render($view, $params, $this);
        if ($layout !== false) {
            return $this->get_view()->render($layout, ['content' => $output, 'message' => $this->_message], $this);
        }
        return $output;
    }
    /**
     * Sends the specified message.
     * This method should be implemented by child classes with the actual email sending logic.
     * @param MessageInterface $message the message to be sent
     * @return bool whether the message is sent successfully
     */
    abstract protected function send_message($message);
    /**
     * Saves the message as a file under [[fileTransportPath]].
     * @param MessageInterface $message
     * @return bool whether the message is saved successfully
     */
    protected function save_message($message)
    {
        $path = Yii::get_alias($this->file_transport_path);
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
        if ($this->file_transport_callback !== null) {
            $file = $path . '/' . call_user_func($this->file_transport_callback, $this, $message);
        } else {
            $file = $path . '/' . $this->generate_message_file_name();
        }
        file_put_contents($file, $message->to_string());
        return true;
    }
    /**
     * @return string the file name for saving the message when [[useFileTransport]] is true.
     */
    public function generate_message_file_name()
    {
        $time = microtime(true);
        $time_int = (int) $time;
        return date('Ymd-His-', $time_int) . sprintf('%04d', (int) (($time - $time_int) * 10000)) . '-' . sprintf('%04d', random_int(0, 10000)) . '.eml';
    }
    /**
     * @return string the directory that contains the view files for composing mail messages
     * Defaults to '@app/mail'.
     */
    public function get_view_path()
    {
        if ($this->_view_path === null) {
            $this->set_view_path('@app/mail');
        }
        return $this->_view_path;
    }
    /**
     * @param string $path the directory that contains the view files for composing mail messages
     * This can be specified as an absolute path or a [path alias](guide:concept-aliases).
     */
    public function set_view_path(string $path): void
    {
        $this->_view_path = Yii::get_alias($path);
    }
    /**
     * This method is invoked right before mail send.
     * You may override this method to do last-minute preparation for the message.
     * If you override this method, please make sure you call the parent implementation first.
     * @param MessageInterface $message
     * @return bool whether to continue sending an email.
     */
    public function before_send($message)
    {
        $event = new Mail_Event(['message' => $message]);
        $this->trigger(self::EVENT_BEFORE_SEND, $event);
        return $event->is_valid;
    }
    /**
     * This method is invoked right after mail was send.
     * You may override this method to do some postprocessing or logging based on mail send status.
     * If you override this method, please make sure you call the parent implementation first.
     * @param MessageInterface $message
     * @param bool $isSuccessful
     */
    public function after_send($message, $is_successful): void
    {
        $event = new Mail_Event(['message' => $message, 'isSuccessful' => $is_successful]);
        $this->trigger(self::EVENT_AFTER_SEND, $event);
    }
}