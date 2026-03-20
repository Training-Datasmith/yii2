<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\i18n;

use Yii;
use yii\base\Component;
use yii\base\Invalid_Config_Exception;
/**
 * I18N provides features related with internationalization (I18N) and localization (L10N).
 *
 * I18N is configured as an application component in [[\yii\base\Application]] by default.
 * You can access that instance via `Yii::$app->i18n`.
 *
 * @property MessageFormatter $messageFormatter The message formatter to be used to format message via ICU
 * message format. Note that the type of this property differs in getter and setter. See
 * [[getMessageFormatter()]] and [[setMessageFormatter()]] for details.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class I18N extends Component
{
    /**
     * @var array list of [[MessageSource]] configurations or objects. The array keys are message
     * category patterns, and the array values are the corresponding [[MessageSource]] objects or the configurations
     * for creating the [[MessageSource]] objects.
     *
     * The message category patterns can contain the wildcard `*` at the end to match multiple categories with the same prefix.
     * For example, `app/*` matches both `app/cat1` and `app/cat2`.
     *
     * The `*` category pattern will match all categories that do not match any other category patterns.
     *
     * This property may be modified on the fly by extensions who want to have their own message sources
     * registered under their own namespaces.
     *
     * The category `yii` and `app` are always defined. The former refers to the messages used in the Yii core
     * framework code, while the latter refers to the default message category for custom application code.
     * By default, both of these categories use [[PhpMessageSource]] and the corresponding message files are
     * stored under `@yii/messages` and `@app/messages`, respectively.
     *
     * You may override the configuration of both categories.
     */
    public $translations;
    /**
     * Initializes the component by configuring the default message categories.
     */
    public function init(): void
    {
        parent::init();
        if (!isset($this->translations['yii']) && !isset($this->translations['yii*'])) {
            $this->translations['yii'] = ['class' => 'yii\i18n\PhpMessageSource', 'sourceLanguage' => 'en-US', 'basePath' => '@yii/messages'];
        }
        if (!isset($this->translations['app']) && !isset($this->translations['app*'])) {
            $this->translations['app'] = ['class' => 'yii\i18n\PhpMessageSource', 'sourceLanguage' => Yii::$app->source_language, 'basePath' => '@app/messages'];
        }
    }
    /**
     * Translates a message to the specified language.
     *
     * After translation the message will be formatted using [[MessageFormatter]] if it contains
     * ICU message format and `$params` are not empty.
     *
     * @param string $category the message category.
     * @param string $message the message to be translated.
     * @param array $params the parameters that will be used to replace the corresponding placeholders in the message.
     * @param string $language the language code (e.g. `en-US`, `en`).
     * @return string the translated and formatted message.
     */
    public function translate($category, $message, $params, $language)
    {
        $message_source = $this->get_message_source($category);
        $translation = $message_source->translate($category, $message, $language);
        if ($translation === false) {
            return $this->format($message, $params, $message_source->source_language);
        }
        return $this->format($translation, $params, $language);
    }
    /**
     * Formats a message using [[MessageFormatter]].
     *
     * @param string $message the message to be formatted.
     * @param array $params the parameters that will be used to replace the corresponding placeholders in the message.
     * @param string $language the language code (e.g. `en-US`, `en`).
     * @return string the formatted message.
     */
    public function format($message, $params, $language)
    {
        $params = (array) $params;
        if ($params === []) {
            return $message;
        }
        if (preg_match('~{\s*[\w.]+\s*,~u', $message)) {
            $formatter = $this->get_message_formatter();
            $result = $formatter->format($message, $params, $language);
            if ($result === false) {
                $error_message = $formatter->get_error_message();
                Yii::warning("Formatting message for language '{$language}' failed with error: {$error_message}. The message being formatted was: {$message}.", __METHOD__);
                return $message;
            }
            return $result;
        }
        $p = [];
        foreach ($params as $name => $value) {
            $p['{' . $name . '}'] = $value;
        }
        return strtr($message, $p);
    }
    /**
     * @var string|array|MessageFormatter
     */
    private $_message_formatter;
    /**
     * Returns the message formatter instance.
     * @return MessageFormatter the message formatter to be used to format message via ICU message format.
     */
    public function get_message_formatter()
    {
        if ($this->_message_formatter === null) {
            $this->_message_formatter = new Message_Formatter();
        } elseif (is_array($this->_message_formatter) || is_string($this->_message_formatter)) {
            $this->_message_formatter = Yii::create_object($this->_message_formatter);
        }
        return $this->_message_formatter;
    }
    /**
     * @param string|array|MessageFormatter $value the message formatter to be used to format message via ICU message format.
     * Can be given as array or string configuration that will be given to [[Yii::createObject]] to create an instance
     * or a [[MessageFormatter]] instance.
     */
    public function set_message_formatter($value): void
    {
        $this->_message_formatter = $value;
    }
    /**
     * Returns the message source for the given category.
     * @param string $category the category name.
     * @return MessageSource the message source for the given category.
     * @throws InvalidConfigException if there is no message source available for the specified category.
     */
    public function get_message_source($category)
    {
        if (isset($this->translations[$category])) {
            $source = $this->translations[$category];
            if ($source instanceof Message_Source) {
                return $source;
            }
            return $this->translations[$category] = Yii::create_object($source);
        }
        // try wildcard matching
        foreach ($this->translations as $pattern => $source) {
            if (strpos($pattern, '*') > 0 && strpos($category, rtrim($pattern, '*')) === 0) {
                if ($source instanceof Message_Source) {
                    return $source;
                }
                return $this->translations[$category] = $this->translations[$pattern] = Yii::create_object($source);
            }
        }
        // match '*' in the last
        if (isset($this->translations['*'])) {
            $source = $this->translations['*'];
            if ($source instanceof Message_Source) {
                return $source;
            }
            return $this->translations[$category] = $this->translations['*'] = Yii::create_object($source);
        }
        throw new Invalid_Config_Exception("Unable to locate message source for category '{$category}'.");
    }
}