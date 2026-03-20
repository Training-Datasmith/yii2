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
/**
 * MessageSource is the base class for message translation repository classes.
 *
 * A message source stores message translations in some persistent storage.
 *
 * Child classes should override [[loadMessages()]] to provide translated messages.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Message_Source extends Component
{
    /**
     * @event MissingTranslationEvent an event that is triggered when a message translation is not found.
     */
    public const EVENT_MISSING_TRANSLATION = 'missingTranslation';
    /**
     * @var bool whether to force message translation when the source and target languages are the same.
     * Defaults to false, meaning translation is only performed when source and target languages are different.
     */
    public $force_translation = false;
    /**
     * @var string|null the language that the original messages are in. If not set, it will use the value of
     * [[\yii\base\Application::sourceLanguage]].
     */
    public $source_language;
    private array $_messages = [];
    /**
     * Initializes this component.
     */
    public function init(): void
    {
        parent::init();
        if ($this->source_language === null) {
            $this->source_language = Yii::$app->source_language;
        }
    }
    /**
     * Loads the message translation for the specified language and category.
     * If translation for specific locale code such as `en-US` isn't found it
     * tries more generic `en`.
     *
     * @param string $category the message category
     * @param string $language the target language
     * @return array the loaded messages. The keys are original messages, and the values
     * are translated messages.
     */
    protected function load_messages($category, $language): array
    {
        return [];
    }
    /**
     * Translates a message to the specified language.
     *
     * Note that unless [[forceTranslation]] is true, if the target language
     * is the same as the [[sourceLanguage|source language]], the message
     * will NOT be translated.
     *
     * If a translation is not found, a [[EVENT_MISSING_TRANSLATION|missingTranslation]] event will be triggered.
     *
     * @param string $category the message category
     * @param string $message the message to be translated
     * @param string $language the target language
     * @return string|bool the translated message or false if translation wasn't found or isn't required
     */
    public function translate($category, $message, $language)
    {
        if ($this->force_translation || $language !== $this->source_language) {
            return $this->translate_message($category, $message, $language);
        }
        return false;
    }
    /**
     * Translates the specified message.
     * If the message is not found, a [[EVENT_MISSING_TRANSLATION|missingTranslation]] event will be triggered.
     * If there is an event handler, it may provide a [[MissingTranslationEvent::$translatedMessage|fallback translation]].
     * If no fallback translation is provided this method will return `false`.
     * @param string $category the category that the message belongs to.
     * @param string $message the message to be translated.
     * @param string $language the target language.
     * @return string|bool the translated message or false if translation wasn't found.
     */
    protected function translate_message(string $category, $message, string $language)
    {
        $key = $language . '/' . $category;
        if (!isset($this->_messages[$key])) {
            $this->_messages[$key] = $this->load_messages($category, $language);
        }
        if (isset($this->_messages[$key][$message]) && $this->_messages[$key][$message] !== '') {
            return $this->_messages[$key][$message];
        }
        if ($this->has_event_handlers(self::EVENT_MISSING_TRANSLATION)) {
            $event = new Missing_Translation_Event(['category' => $category, 'message' => $message, 'language' => $language]);
            $this->trigger(self::EVENT_MISSING_TRANSLATION, $event);
            if ($event->translated_message !== null) {
                return $this->_messages[$key][$message] = $event->translated_message;
            }
        }
        return $this->_messages[$key][$message] = false;
    }
}