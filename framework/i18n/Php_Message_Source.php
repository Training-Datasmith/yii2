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
 * PhpMessageSource represents a message source that stores translated messages in PHP scripts.
 *
 * PhpMessageSource uses PHP arrays to keep message translations.
 *
 * - Each PHP script contains one array which stores the message translations in one particular
 *   language and for a single message category;
 * - Each PHP script is saved as a file named as "[[basePath]]/LanguageID/CategoryName.php";
 * - Within each PHP script, the message translations are returned as an array like the following:
 *
 * ```
 * return [
 *     'original message 1' => 'translated message 1',
 *     'original message 2' => 'translated message 2',
 * ];
 * ```
 *
 * You may use [[fileMap]] to customize the association between category names and the file names.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Php_Message_Source extends Message_Source
{
    /**
     * @var string the base path for all translated messages. Defaults to '@app/messages'.
     */
    public $base_path = '@app/messages';
    /**
     * @var array mapping between message categories and the corresponding message file paths.
     * The file paths are relative to [[basePath]]. For example,
     *
     * ```
     * [
     *     'core' => 'core.php',
     *     'ext' => 'extensions.php',
     * ]
     * ```
     */
    public $file_map;
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
     * @return array the loaded messages. The keys are original messages, and the values are the translated messages.
     * @see loadFallbackMessages
     * @see sourceLanguage
     */
    protected function load_messages($category, $language): array
    {
        $message_file = $this->get_message_file_path($category, $language);
        $messages = $this->load_messages_from_file($message_file);
        $fallback_language = substr((string) $language, 0, 2);
        $fallback_source_language = substr($this->source_language, 0, 2);
        if ($fallback_language !== '' && $language !== $fallback_language) {
            $messages = $this->load_fallback_messages($category, $fallback_language, $messages, $message_file);
        } elseif ($fallback_source_language !== '' && $language === $fallback_source_language) {
            $messages = $this->load_fallback_messages($category, $this->source_language, $messages, $message_file);
        } elseif ($messages === null) {
            Yii::warning("The message file for category '{$category}' does not exist: {$message_file}", __METHOD__);
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
        $fallback_message_file = $this->get_message_file_path($category, $fallback_language);
        $fallback_messages = $this->load_messages_from_file($fallback_message_file);
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
     * @param string $category the message category
     * @param string $language the target language
     * @return string path to message file
     */
    protected function get_message_file_path($category, $language): string
    {
        $language = (string) $language;
        if ($language !== '' && !preg_match('/^[a-z0-9_-]+$/i', $language)) {
            throw new InvalidArgumentException(sprintf('Invalid language code: "%s".', $language));
        }
        $message_file = Yii::get_alias($this->base_path) . "/{$language}/";
        if (isset($this->file_map[$category])) {
            $message_file .= $this->file_map[$category];
        } else {
            $message_file .= str_replace('\\', '/', $category) . '.php';
        }
        return $message_file;
    }
    /**
     * Loads the message translation for the specified language and category or returns null if file doesn't exist.
     *
     * @param string $messageFile path to message file
     * @return array|null array of messages or null if file not found
     */
    protected function load_messages_from_file($message_file): ?array
    {
        if (is_file($message_file)) {
            $messages = include $message_file;
            if (!is_array($messages)) {
                return [];
            }
            return $messages;
        }
        return null;
    }
}