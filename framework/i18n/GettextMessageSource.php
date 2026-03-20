<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\i18n;

use Yii;
use yii\base\InvalidArgumentException;
/**
 * GettextMessageSource represents a message source that is based on GNU Gettext.
 *
 * Each GettextMessageSource instance represents the message translations
 * for a single domain. And each message category represents a message context
 * in Gettext. Translated messages are stored as either a MO or PO file,
 * depending on the [[useMoFile]] property value.
 *
 * All translations are saved under the [[basePath]] directory.
 *
 * Translations in one language are kept as MO or PO files under an individual
 * subdirectory whose name is the language ID. The file name is specified via
 * [[catalog]] property, which defaults to 'messages'.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Gettext_Message_Source extends Message_Source
{
    public const MO_FILE_EXT = '.mo';
    public const PO_FILE_EXT = '.po';
    /**
     * @var string base directory of messages files
     */
    public $base_path = '@app/messages';
    /**
     * @var string sub-directory of messages files
     */
    public $catalog = 'messages';
    /**
     * @var bool whether to use generated MO files
     */
    public $use_mo_file = true;
    /**
     * @var bool whether to use big-endian when reading and writing an integer
     */
    public $use_big_endian = false;
    /**
     * Loads the message translation for the specified $language and $category.
     * If translation for specific locale code such as `en-US` isn't found it
     * tries more generic `en`. When both are present, the `en-US` messages will be merged
     * over `en`. See [[loadFallbackMessages]] for details.
     * If the $language is less specific than [[sourceLanguage]], the method will try to
     * load the messages for [[sourceLanguage]]. For example: [[sourceLanguage]] is `en-GB`,
     * $language is `en`. The method will load the messages for `en` and merge them over `en-GB`.
     *
     * @param string $category the message category
     * @param string $language the target language
     * @return array the loaded messages. The keys are original messages, and the values are translated messages.
     * @see loadFallbackMessages
     * @see sourceLanguage
     */
    protected function load_messages($category, $language): array
    {
        $message_file = $this->get_message_file_path($language);
        $messages = $this->load_messages_from_file($message_file, $category);
        $fallback_language = substr($language, 0, 2);
        $fallback_source_language = substr($this->source_language, 0, 2);
        if ($fallback_language !== '' && $fallback_language !== $language) {
            $messages = $this->load_fallback_messages($category, $fallback_language, $messages, $message_file);
        } elseif ($fallback_source_language !== '' && $language === $fallback_source_language) {
            $messages = $this->load_fallback_messages($category, $this->source_language, $messages, $message_file);
        } elseif ($messages === null) {
            Yii::error("The message file for category '{$category}' does not exist: {$message_file}", __METHOD__);
        }
        return (array) $messages;
    }
    /**
     * The method is normally called by [[loadMessages]] to load the fallback messages for the language.
     * Method tries to load the $category messages for the $fallbackLanguage and adds them to the $messages array.
     *
     * @param string $category the message category
     * @param string $fallbackLanguage the target fallback language
     * @param array $messages the array of previously loaded translation messages.
     * The keys are original messages, and the values are the translated messages.
     * @param string $originalMessageFile the path to the file with messages. Used to log an error message
     * in case when no translations were found.
     * @return array the loaded messages. The keys are original messages, and the values are the translated messages.
     * @since 2.0.7
     */
    protected function load_fallback_messages($category, $fallback_language, $messages, $original_message_file)
    {
        $fallback_message_file = $this->get_message_file_path($fallback_language);
        $fallback_messages = $this->load_messages_from_file($fallback_message_file, $category);
        if ($messages === null && $fallback_messages === null && $fallback_language !== $this->source_language && strpos($this->source_language, $fallback_language) !== 0) {
            Yii::error("The message file for category '{$category}' does not exist: {$original_message_file} " . "Fallback file does not exist as well: {$fallback_message_file}", __METHOD__);
        } elseif (empty($messages)) {
            return $fallback_messages;
        } elseif (!empty($fallback_messages)) {
            foreach ($fallback_messages as $key => $value) {
                if (!empty($value) && empty($messages[$key])) {
                    $messages[$key] = $value;
                }
            }
        }
        return (array) $messages;
    }
    /**
     * Returns message file path for the specified language and category.
     *
     * @param string $language the target language
     * @return string path to message file
     */
    protected function get_message_file_path($language): string
    {
        $language = (string) $language;
        if ($language !== '' && !preg_match('/^[a-z0-9_-]+$/i', $language)) {
            throw new InvalidArgumentException(sprintf('Invalid language code: "%s".', $language));
        }
        $message_file = Yii::get_alias($this->base_path) . '/' . $language . '/' . $this->catalog;
        if ($this->use_mo_file) {
            $message_file .= self::MO_FILE_EXT;
        } else {
            $message_file .= self::PO_FILE_EXT;
        }
        return $message_file;
    }
    /**
     * Loads the message translation for the specified language and category or returns null if file doesn't exist.
     *
     * @param string $messageFile path to message file
     * @param string $category the message category
     * @return array|null array of messages or null if file not found
     */
    protected function load_messages_from_file($message_file, $category): ?array
    {
        if (is_file($message_file)) {
            if ($this->use_mo_file) {
                $gettext_file = new Gettext_Mo_File(['useBigEndian' => $this->use_big_endian]);
            } else {
                $gettext_file = new Gettext_Po_File();
            }
            $messages = $gettext_file->load($message_file, $category);
            if (!is_array($messages)) {
                return [];
            }
            return $messages;
        }
        return null;
    }
}