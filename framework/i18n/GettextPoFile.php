<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\i18n;

use Yii;
/**
 * GettextPoFile represents a PO Gettext message file.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Gettext_Po_File extends Gettext_File
{
    /**
     * Loads messages from a PO file.
     * @param string $filePath file path
     * @param string $context message context
     * @return array message translations. Array keys are source messages and array values are translated messages:
     * source message => translated message.
     */
    public function load($file_path, $context): array
    {
        $pattern = '/(msgctxt\s+"(.*?(?<!\\\\))")?\s+' . 'msgid\s+((?:".*(?<!\\\\)"\s*)+)\s+' . 'msgstr\s+((?:".*(?<!\\\\)"\s*)+)/';
        // translated string
        $content = file_get_contents($file_path);
        $matches = [];
        $match_count = preg_match_all($pattern, $content, $matches);
        $messages = [];
        for ($i = 0; $i < $match_count; ++$i) {
            if ($matches[2][$i] === $context) {
                $id = $this->decode($matches[3][$i]);
                $message = $this->decode($matches[4][$i]);
                $messages[$id] = $message;
            }
        }
        return $messages;
    }
    /**
     * Saves messages to a PO file.
     * @param string $filePath file path
     * @param array $messages message translations. Array keys are source messages and array values are
     * translated messages: source message => translated message. Note if the message has a context,
     * the message ID must be prefixed with the context with chr(4) as the separator.
     */
    public function save($file_path, $messages): void
    {
        $language = str_replace('-', '_', basename(dirname($file_path)));
        $headers = ['msgid ""', 'msgstr ""', '"Project-Id-Version: \n"', '"POT-Creation-Date: \n"', '"PO-Revision-Date: \n"', '"Last-Translator: \n"', '"Language-Team: \n"', '"Language: ' . $language . '\n"', '"MIME-Version: 1.0\n"', '"Content-Type: text/plain; charset=' . Yii::$app->charset . '\n"', '"Content-Transfer-Encoding: 8bit\n"'];
        $content = implode("\n", $headers) . "\n\n";
        foreach ($messages as $id => $message) {
            $separator_position = strpos($id, chr(4));
            if ($separator_position !== false) {
                $content .= 'msgctxt "' . substr($id, 0, $separator_position) . "\"\n";
                $id = substr($id, $separator_position + 1);
            }
            $content .= 'msgid "' . $this->encode($id) . "\"\n";
            $content .= 'msgstr "' . $this->encode($message) . "\"\n\n";
        }
        file_put_contents($file_path, $content);
    }
    /**
     * Encodes special characters in a message.
     * @param string $string message to be encoded
     * @return string the encoded message
     */
    protected function encode($string): string
    {
        return str_replace(['"', "\n", "\t", "\r"], ['\"', '\n', '\t', '\r'], $string);
    }
    /**
     * Decodes special characters in a message.
     * @param string $string message to be decoded
     * @return string the decoded message
     */
    protected function decode($string): string
    {
        $string = preg_replace(['/"\s+"/', '/\\\\n/', '/\\\\r/', '/\\\\t/', '/\\\\"/'], ['', "\n", "\r", "\t", '"'], $string);
        return substr(rtrim($string), 1, -1);
    }
}