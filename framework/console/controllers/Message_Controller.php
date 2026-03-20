<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\console\controllers;

use Yii;
use yii\console\Application;
use yii\console\Controller;
use yii\console\Exception;
use yii\console\Exit_Code;
use yii\db\Connection;
use yii\db\Query;
use yii\di\Instance;
use yii\helpers\Console;
use yii\helpers\File_Helper;
use yii\helpers\Var_Dumper;
use yii\i18n\Gettext_Po_File;
/**
 * Extracts messages to be translated from source files.
 *
 * The extracted messages can be saved the following depending on `format`
 * setting in config file:
 *
 * - PHP message source files.
 * - ".po" files.
 * - Database.
 *
 * Usage:
 * 1. Create a configuration file using the 'message/config' command:
 *    yii message/config /path/to/myapp/messages/config.php
 * 2. Edit the created config file, adjusting it for your web application needs.
 * 3. Run the 'message/extract' command, using created config:
 *    yii message /path/to/myapp/messages/config.php
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 *
 * @template T of Application = Application
 * @extends Controller<T>
 */
class Message_Controller extends Controller
{
    /**
     * @var string controller default action ID.
     */
    public $default_action = 'extract';
    /**
     * @var string required, root directory of all source files.
     */
    public $source_path = '@yii';
    /**
     * @var string required, root directory containing message translations.
     */
    public $message_path = '@yii/messages';
    /**
     * @var array required, list of language codes that the extracted messages
     * should be translated to. For example, ['zh-CN', 'de'].
     */
    public $languages = [];
    /**
     * @var string|string[] the name of the function for translating messages.
     * This is used as a mark to find the messages to be translated.
     * You may use a string for single function name or an array for multiple function names.
     */
    public $translator = ['Yii::t', '\Yii::t'];
    /**
     * @var bool whether to sort messages by keys when merging new messages
     * with the existing ones. Defaults to false, which means the new (untranslated)
     * messages will be separated from the old (translated) ones.
     */
    public $sort = false;
    /**
     * @var bool whether the message file should be overwritten with the merged messages
     */
    public $overwrite = true;
    /**
     * @var bool whether to remove messages that no longer appear in the source code.
     * Defaults to false, which means these messages will NOT be removed.
     */
    public $remove_unused = false;
    /**
     * @var bool whether to mark messages that no longer appear in the source code.
     * Defaults to true, which means each of these messages will be enclosed with a pair of '@@' marks.
     */
    public $mark_unused = true;
    /**
     * @var array|null list of patterns that specify which files/directories should NOT be processed.
     * If empty or not set, all files/directories will be processed.
     * See helpers/FileHelper::findFiles() description for pattern matching rules.
     * If a file/directory matches both a pattern in "only" and "except", it will NOT be processed.
     */
    public $except = ['.*', '/.*', '/messages', '/tests', '/runtime', '/vendor', '/BaseYii.php'];
    /**
     * @var array|null list of patterns that specify which files (not directories) should be processed.
     * If empty or not set, all files will be processed.
     * See helpers/FileHelper::findFiles() description for pattern matching rules.
     * If a file/directory matches both a pattern in "only" and "except", it will NOT be processed.
     */
    public $only = ['*.php'];
    /**
     * @var string generated file format. Can be "php", "db", "po" or "pot".
     */
    public $format = 'php';
    /**
     * @var string connection component ID for "db" format.
     */
    public $db = 'db';
    /**
     * @var string custom name for source message table for "db" format.
     */
    public $source_message_table = '{{%source_message}}';
    /**
     * @var string custom name for translation message table for "db" format.
     */
    public $message_table = '{{%message}}';
    /**
     * @var string name of the file that will be used for translations for "po" format.
     */
    public $catalog = 'messages';
    /**
     * @var array message categories to ignore. For example, 'yii', 'app*', 'widgets/menu', etc.
     * @see isCategoryIgnored
     */
    public $ignore_categories = [];
    /**
     * @var string File header in generated PHP file with messages. This property is used only if [[$format]] is "php".
     * @since 2.0.13
     */
    public $php_file_header = '';
    /**
     * @var string|null DocBlock used for messages array in generated PHP file. If `null`, default DocBlock will be used.
     * This property is used only if [[$format]] is "php".
     * @since 2.0.13
     */
    public $php_doc_block;
    /**
     * @var array Config for messages extraction.
     * @see actionExtract()
     * @see initConfig()
     * @since 2.0.13
     */
    protected $config;
    /**
     * {@inheritdoc}
     */
    public function options($action_id): array
    {
        return array_merge(parent::options($action_id), ['sourcePath', 'messagePath', 'languages', 'translator', 'sort', 'overwrite', 'removeUnused', 'markUnused', 'except', 'only', 'format', 'db', 'sourceMessageTable', 'messageTable', 'catalog', 'ignoreCategories', 'phpFileHeader', 'phpDocBlock']);
    }
    /**
     * {@inheritdoc}
     * @since 2.0.8
     */
    public function option_aliases(): array
    {
        return array_merge(parent::option_aliases(), ['c' => 'catalog', 'e' => 'except', 'f' => 'format', 'i' => 'ignoreCategories', 'l' => 'languages', 'u' => 'markUnused', 'p' => 'messagePath', 'o' => 'only', 'w' => 'overwrite', 'S' => 'sort', 't' => 'translator', 'm' => 'sourceMessageTable', 's' => 'sourcePath', 'r' => 'removeUnused']);
    }
    /**
     * Creates a configuration file for the "extract" command using command line options specified.
     *
     * The generated configuration file contains parameters required
     * for source code messages extraction.
     * You may use this configuration file with the "extract" command.
     *
     * @param string $filePath output file name or alias.
     * @return int CLI exit code
     * @throws Exception on failure.
     */
    public function action_config($file_path): int
    {
        $file_path = Yii::get_alias($file_path);
        $dir = dirname($file_path);
        if (file_exists($file_path)) {
            if (!$this->confirm("File '{$file_path}' already exists. Do you wish to overwrite it?")) {
                return Exit_Code::OK;
            }
        }
        $array = Var_Dumper::export($this->get_option_values($this->action->id));
        $content = <<<EOD
        <?php
        /**
         * Configuration file for 'yii {$this->id}/{$this->default_action}' command.
         *
         * This file is automatically generated by 'yii {$this->id}/{$this->action->id}' command.
         * It contains parameters for source code messages extraction.
         * You may modify this file to suit your needs.
         *
         * You can use 'yii {$this->id}/{$this->action->id}-template' command to create
         * template configuration file with detailed description for each parameter.
         */
        return {$array};
        
        EOD;
        if (File_Helper::create_directory($dir) === false || file_put_contents($file_path, $content, LOCK_EX) === false) {
            $this->stdout("Configuration file was NOT created: '{$file_path}'.\n\n", Console::FG_RED);
            return Exit_Code::UNSPECIFIED_ERROR;
        }
        $this->stdout("Configuration file created: '{$file_path}'.\n\n", Console::FG_GREEN);
        return Exit_Code::OK;
    }
    /**
     * Creates a configuration file template for the "extract" command.
     *
     * The created configuration file contains detailed instructions on
     * how to customize it to fit for your needs. After customization,
     * you may use this configuration file with the "extract" command.
     *
     * @param string $filePath output file name or alias.
     * @return int CLI exit code
     * @throws Exception on failure.
     */
    public function action_config_template($file_path): int
    {
        $file_path = Yii::get_alias($file_path);
        if (file_exists($file_path)) {
            if (!$this->confirm("File '{$file_path}' already exists. Do you wish to overwrite it?")) {
                return Exit_Code::OK;
            }
        }
        if (!copy(Yii::get_alias('@yii/views/messageConfig.php'), $file_path)) {
            $this->stdout("Configuration file template was NOT created at '{$file_path}'.\n\n", Console::FG_RED);
            return Exit_Code::UNSPECIFIED_ERROR;
        }
        $this->stdout("Configuration file template created at '{$file_path}'.\n\n", Console::FG_GREEN);
        return Exit_Code::OK;
    }
    /**
     * Extracts messages to be translated from source code.
     *
     * This command will search through source code files and extract
     * messages that need to be translated in different languages.
     *
     * @param string|null $configFile the path or alias of the configuration file.
     * You may use the "yii message/config" command to generate
     * this file and then customize it for your needs.
     * @throws Exception on failure.
     */
    public function action_extract($config_file = null): void
    {
        $this->init_config($config_file);
        $files = File_Helper::find_files(realpath($this->config['sourcePath']), $this->config);
        $messages = [];
        foreach ($files as $file) {
            $messages = array_merge_recursive($messages, $this->extract_messages($file, $this->config['translator'], $this->config['ignoreCategories']));
        }
        $catalog = $this->config['catalog'] ?? 'messages';
        if (in_array($this->config['format'], ['php', 'po'])) {
            foreach ($this->config['languages'] as $language) {
                $dir = $this->config['messagePath'] . DIRECTORY_SEPARATOR . $language;
                if (!is_dir($dir) && !@mkdir($dir)) {
                    throw new Exception("Directory '{$dir}' can not be created.");
                }
                if ($this->config['format'] === 'po') {
                    $this->save_messages_to_po($messages, $dir, $this->config['overwrite'], $this->config['removeUnused'], $this->config['sort'], $catalog, $this->config['markUnused']);
                } else {
                    $this->save_messages_to_php($messages, $dir, $this->config['overwrite'], $this->config['removeUnused'], $this->config['sort'], $this->config['markUnused']);
                }
            }
        } elseif ($this->config['format'] === 'db') {
            /** @var Connection $db */
            $db = Instance::ensure($this->config['db'], Connection::class_name());
            $source_message_table = $this->config['sourceMessageTable'] ?? '{{%source_message}}';
            $message_table = $this->config['messageTable'] ?? '{{%message}}';
            $this->save_messages_to_db($messages, $db, $source_message_table, $message_table, $this->config['removeUnused'], $this->config['languages'], $this->config['markUnused']);
        } elseif ($this->config['format'] === 'pot') {
            $this->save_messages_to_pot($messages, $this->config['messagePath'], $catalog);
        }
    }
    /**
     * Saves messages to database.
     *
     * @param array $messages
     * @param Connection $db
     * @param string $sourceMessageTable
     * @param string $messageTable
     * @param bool $removeUnused
     * @param array $languages
     * @param bool $markUnused
     */
    protected function save_messages_to_db($messages, $db, $source_message_table, $message_table, $remove_unused, $languages, $mark_unused)
    {
        $current_messages = [];
        $rows = (new Query())->select(['id', 'category', 'message'])->from($source_message_table)->all($db);
        foreach ($rows as $row) {
            $current_messages[$row['category']][$row['id']] = $row['message'];
        }
        $new = [];
        $obsolete = [];
        foreach ($messages as $category => $msgs) {
            $msgs = array_unique($msgs);
            if (isset($current_messages[$category])) {
                $new[$category] = array_diff($msgs, $current_messages[$category]);
                // obsolete messages per category
                $obsolete += array_diff($current_messages[$category], $msgs);
            } else {
                $new[$category] = $msgs;
            }
        }
        // obsolete categories
        foreach (array_diff(array_keys($current_messages), array_keys($messages)) as $category) {
            $obsolete += $current_messages[$category];
        }
        if (!$remove_unused) {
            foreach ($obsolete as $pk => $msg) {
                // skip already marked unused
                if (strncmp($msg, '@@', 2) === 0 && substr($msg, -2) === '@@') {
                    unset($obsolete[$pk]);
                }
            }
        }
        $this->stdout('Inserting new messages...');
        $insert_count = 0;
        foreach ($new as $category => $msgs) {
            foreach ($msgs as $msg) {
                $insert_count++;
                $db->schema->insert($source_message_table, ['category' => $category, 'message' => $msg]);
            }
        }
        $this->stdout($insert_count ? "{$insert_count} saved.\n" : "Nothing to save.\n");
        $this->stdout($remove_unused ? 'Deleting obsoleted messages...' : 'Updating obsoleted messages...');
        if (empty($obsolete)) {
            $this->stdout("Nothing obsoleted...skipped.\n");
        }
        if ($obsolete) {
            if ($remove_unused) {
                $affected = $db->create_command()->delete($source_message_table, ['in', 'id', array_keys($obsolete)])->execute();
                $this->stdout("{$affected} deleted.\n");
            } elseif ($mark_unused) {
                $marked = 0;
                $rows = (new Query())->select(['id', 'message'])->from($source_message_table)->where(['in', 'id', array_keys($obsolete)])->all($db);
                foreach ($rows as $row) {
                    $marked++;
                    $db->create_command()->update($source_message_table, ['message' => '@@' . $row['message'] . '@@'], ['id' => $row['id']])->execute();
                }
                $this->stdout("{$marked} updated.\n");
            } else {
                $this->stdout("kept untouched.\n");
            }
        }
        // get fresh message id list
        $fresh_messages_ids = [];
        $rows = (new Query())->select(['id'])->from($source_message_table)->all($db);
        foreach ($rows as $row) {
            $fresh_messages_ids[] = $row['id'];
        }
        $this->stdout('Generating missing rows...');
        $generated_missing_rows = [];
        foreach ($languages as $language) {
            $count = 0;
            // get list of ids of translations for this language
            $msg_rows_ids = [];
            $msg_rows = (new Query())->select(['id'])->from($message_table)->where(['language' => $language])->all($db);
            foreach ($msg_rows as $row) {
                $msg_rows_ids[] = $row['id'];
            }
            // insert missing
            foreach ($fresh_messages_ids as $id) {
                if (!in_array($id, $msg_rows_ids)) {
                    $db->create_command()->insert($message_table, ['id' => $id, 'language' => $language])->execute();
                    $count++;
                }
            }
            if ($count) {
                $generated_missing_rows[] = "{$count} for {$language}";
            }
        }
        $this->stdout($generated_missing_rows ? implode(', ', $generated_missing_rows) . ".\n" : "Nothing to do.\n");
        $this->stdout('Dropping unused languages...');
        $dropped_languages = [];
        $current_languages = [];
        $rows = (new Query())->select(['language'])->from($message_table)->group_by('language')->all($db);
        foreach ($rows as $row) {
            $current_languages[] = $row['language'];
        }
        foreach ($current_languages as $current_language) {
            if (!in_array($current_language, $languages)) {
                $deleted = $db->create_command()->delete($message_table, 'language=:language', ['language' => $current_language])->execute();
                $dropped_languages[] = "removed {$deleted} rows for {$current_language}";
            }
        }
        $this->stdout($dropped_languages ? implode(', ', $dropped_languages) . ".\n" : "Nothing to do.\n");
    }
    /**
     * Extracts messages from a file.
     *
     * @param string $fileName name of the file to extract messages from
     * @param string $translator name of the function used to translate messages
     * @param array $ignoreCategories message categories to ignore.
     * This parameter is available since version 2.0.4.
     */
    protected function extract_messages($file_name, $translator, array $ignore_categories = []): array
    {
        $this->stdout('Extracting messages from ');
        $this->stdout($file_name, Console::FG_CYAN);
        $this->stdout("...\n");
        $subject = file_get_contents($file_name);
        $messages = [];
        $tokens = token_get_all($subject);
        foreach ((array) $translator as $current_translator) {
            $translator_tokens = token_get_all('<?php ' . $current_translator);
            array_shift($translator_tokens);
            $messages = array_merge_recursive($messages, $this->extract_messages_from_tokens($tokens, $translator_tokens, $ignore_categories));
        }
        $this->stdout("\n");
        return $messages;
    }
    /**
     * Extracts messages from a parsed PHP tokens list.
     * @param array $tokens tokens to be processed.
     * @param array $translatorTokens translator tokens.
     * @param array $ignoreCategories message categories to ignore.
     * @return array messages.
     */
    protected function extract_messages_from_tokens(array $tokens, array $translator_tokens, array $ignore_categories): array
    {
        $messages = [];
        $translator_tokens_count = count($translator_tokens);
        $matched_tokens_count = 0;
        $buffer = [];
        $pending_parenthesis_count = 0;
        foreach ($tokens as $token_index => $token) {
            // finding out translator call
            if ($matched_tokens_count < $translator_tokens_count) {
                if ($this->tokens_equal($token, $translator_tokens[$matched_tokens_count])) {
                    $matched_tokens_count++;
                } else {
                    $matched_tokens_count = 0;
                }
            } elseif ($matched_tokens_count === $translator_tokens_count) {
                // translator found
                // end of function call
                if ($this->tokens_equal(')', $token)) {
                    $pending_parenthesis_count--;
                    if ($pending_parenthesis_count === 0) {
                        // end of translator call or end of something that we can't extract
                        if (isset($buffer[0][0], $buffer[1], $buffer[2][0]) && $buffer[0][0] === T_CONSTANT_ENCAPSED_STRING && $buffer[1] === ',' && $buffer[2][0] === T_CONSTANT_ENCAPSED_STRING) {
                            // is valid call we can extract
                            $category = stripcslashes($buffer[0][1]);
                            $category = mb_substr($category, 1, -1);
                            if (!$this->is_category_ignored($category, $ignore_categories)) {
                                $full_message = mb_substr($buffer[2][1], 1, -1);
                                $i = 3;
                                while ($i < count($buffer) - 1 && !is_array($buffer[$i]) && $buffer[$i] === '.') {
                                    $full_message .= mb_substr($buffer[$i + 1][1], 1, -1);
                                    $i += 2;
                                }
                                $message = stripcslashes($full_message);
                                $messages[$category][] = $message;
                            }
                            $nested_tokens = array_slice($buffer, 3);
                            if (count($nested_tokens) > $translator_tokens_count) {
                                // search for possible nested translator calls
                                $messages = array_merge_recursive($messages, $this->extract_messages_from_tokens($nested_tokens, $translator_tokens, $ignore_categories));
                            }
                        } else {
                            // invalid call or dynamic call we can't extract
                            $line = Console::ansi_format($this->get_line($buffer), [Console::FG_CYAN]);
                            $skipping = Console::ansi_format('Skipping line', [Console::FG_YELLOW]);
                            $this->stdout("{$skipping} {$line}. Make sure both category and message are static strings.\n");
                        }
                        // prepare for the next match
                        $matched_tokens_count = 0;
                        $pending_parenthesis_count = 0;
                        $buffer = [];
                    } else {
                        $buffer[] = $token;
                    }
                } elseif ($this->tokens_equal('(', $token)) {
                    // count beginning of function call, skipping translator beginning
                    // If we are not yet inside the translator, make sure that it's beginning of the real translator.
                    // See https://github.com/yiisoft/yii2/issues/16828
                    if ($pending_parenthesis_count === 0) {
                        $previous_token_index = $token_index - $matched_tokens_count - 1;
                        if (is_array($tokens[$previous_token_index])) {
                            $previous_token = $tokens[$previous_token_index][0];
                            if (in_array($previous_token, [T_OBJECT_OPERATOR, T_PAAMAYIM_NEKUDOTAYIM], true)) {
                                $matched_tokens_count = 0;
                                continue;
                            }
                        }
                    }
                    if ($pending_parenthesis_count > 0) {
                        $buffer[] = $token;
                    }
                    $pending_parenthesis_count++;
                } elseif (isset($token[0]) && !in_array($token[0], [T_WHITESPACE, T_COMMENT])) {
                    // ignore comments and whitespaces
                    $buffer[] = $token;
                }
            }
        }
        return $messages;
    }
    /**
     * The method checks, whether the $category is ignored according to $ignoreCategories array.
     *
     * Examples:
     *
     * - `myapp` - will be ignored only `myapp` category;
     * - `myapp*` - will be ignored by all categories beginning with `myapp` (`myapp`, `myapplication`, `myapprove`, `myapp/widgets`, `myapp.widgets`, etc).
     *
     * @param string $category category that is checked
     * @param array $ignoreCategories message categories to ignore.
     * @since 2.0.7
     */
    protected function is_category_ignored($category, array $ignore_categories): bool
    {
        if (!empty($ignore_categories)) {
            if (in_array($category, $ignore_categories, true)) {
                return true;
            }
            foreach ($ignore_categories as $pattern) {
                if (strpos($pattern, '*') > 0 && strpos($category, rtrim($pattern, '*')) === 0) {
                    return true;
                }
            }
        }
        return false;
    }
    /**
     * Finds out if two PHP tokens are equal.
     *
     * @param array|string $a
     * @param array|string $b
     * @return bool
     * @since 2.0.1
     */
    protected function tokens_equal($a, $b)
    {
        if (is_string($a) && is_string($b)) {
            return $a === $b;
        }
        if (isset($a[0], $a[1], $b[0], $b[1])) {
            return $a[0] === $b[0] && $a[1] == $b[1];
        }
        return false;
    }
    /**
     * Finds out a line of the first non-char PHP token found.
     *
     * @param array $tokens
     * @return int|string
     * @since 2.0.1
     */
    protected function get_line($tokens)
    {
        foreach ($tokens as $token) {
            if (isset($token[2])) {
                return $token[2];
            }
        }
        return 'unknown';
    }
    /**
     * Writes messages into PHP files.
     *
     * @param array $messages
     * @param string $dirName name of the directory to write to
     * @param bool $overwrite if existing file should be overwritten without backup
     * @param bool $removeUnused if obsolete translations should be removed
     * @param bool $sort if translations should be sorted
     * @param bool $markUnused if obsolete translations should be marked
     */
    protected function save_messages_to_php($messages, $dir_name, $overwrite, $remove_unused, $sort, $mark_unused)
    {
        foreach ($messages as $category => $msgs) {
            $file = str_replace('\\', '/', "{$dir_name}/{$category}.php");
            $path = dirname($file);
            File_Helper::create_directory($path);
            $msgs = array_values(array_unique($msgs));
            $colored_file_name = Console::ansi_format($file, [Console::FG_CYAN]);
            $this->stdout("Saving messages to {$colored_file_name}...\n");
            $this->save_messages_category_to_php($msgs, $file, $overwrite, $remove_unused, $sort, $category, $mark_unused);
        }
        if ($remove_unused) {
            $this->delete_unused_php_message_files(array_keys($messages), $dir_name);
        }
    }
    /**
     * Writes category messages into PHP file.
     *
     * @param array $messages
     * @param string $fileName name of the file to write to
     * @param bool $overwrite if existing file should be overwritten without backup
     * @param bool $removeUnused if obsolete translations should be removed
     * @param bool $sort if translations should be sorted
     * @param string $category message category
     * @param bool $markUnused if obsolete translations should be marked
     * @return int exit code
     */
    protected function save_messages_category_to_php($messages, string $file_name, $overwrite, $remove_unused, $sort, $category, $mark_unused): int
    {
        if (is_file($file_name)) {
            $raw_existing_messages = require $file_name;
            $existing_messages = $raw_existing_messages;
            sort($messages);
            ksort($existing_messages);
            if (array_keys($existing_messages) === $messages && (!$sort || array_keys($raw_existing_messages) === $messages)) {
                $this->stdout("Nothing new in \"{$category}\" category... Nothing to save.\n\n", Console::FG_GREEN);
                return Exit_Code::OK;
            }
            unset($raw_existing_messages);
            $merged = [];
            $untranslated = [];
            foreach ($messages as $message) {
                if (array_key_exists($message, $existing_messages) && $existing_messages[$message] !== '') {
                    $merged[$message] = $existing_messages[$message];
                } else {
                    $untranslated[] = $message;
                }
            }
            ksort($merged);
            sort($untranslated);
            $todo = [];
            foreach ($untranslated as $message) {
                $todo[$message] = '';
            }
            ksort($existing_messages);
            foreach ($existing_messages as $message => $translation) {
                if (!$remove_unused && !isset($merged[$message]) && !isset($todo[$message])) {
                    if (!$mark_unused || !empty($translation) && (strncmp($translation, '@@', 2) === 0 && substr_compare($translation, '@@', -2, 2) === 0)) {
                        $todo[$message] = $translation;
                    } else {
                        $todo[$message] = '@@' . $translation . '@@';
                    }
                }
            }
            $merged = array_merge($merged, $todo);
            if ($sort) {
                ksort($merged);
            }
            if (false === $overwrite) {
                $file_name .= '.merged';
            }
            $this->stdout("Translation merged.\n");
        } else {
            $merged = [];
            foreach ($messages as $message) {
                $merged[$message] = '';
            }
            ksort($merged);
        }
        $array = Var_Dumper::export($merged);
        $content = <<<EOD
        <?php
        {$this->config['phpFileHeader']}{$this->config['phpDocBlock']}
        return {$array};
        
        EOD;
        if (file_put_contents($file_name, $content, LOCK_EX) === false) {
            $this->stdout("Translation was NOT saved.\n\n", Console::FG_RED);
            return Exit_Code::UNSPECIFIED_ERROR;
        }
        $this->stdout("Translation saved.\n\n", Console::FG_GREEN);
        return Exit_Code::OK;
    }
    /**
     * Writes messages into PO file.
     *
     * @param array $messages
     * @param string $dirName name of the directory to write to
     * @param bool $overwrite if existing file should be overwritten without backup
     * @param bool $removeUnused if obsolete translations should be removed
     * @param bool $sort if translations should be sorted
     * @param string $catalog message catalog
     * @param bool $markUnused if obsolete translations should be marked
     */
    protected function save_messages_to_po($messages, $dir_name, $overwrite, $remove_unused, $sort, $catalog, $mark_unused)
    {
        $file = str_replace('\\', '/', "{$dir_name}/{$catalog}.po");
        File_Helper::create_directory(dirname($file));
        $this->stdout("Saving messages to {$file}...\n");
        $po_file = new Gettext_Po_File();
        $merged = [];
        $todos = [];
        $has_something_to_write = false;
        foreach ($messages as $category => $msgs) {
            $not_translated_yet = [];
            $msgs = array_values(array_unique($msgs));
            if (is_file($file)) {
                $existing_messages = $po_file->load($file, $category);
                sort($msgs);
                ksort($existing_messages);
                if (array_keys($existing_messages) == $msgs) {
                    $this->stdout("Nothing new in \"{$category}\" category...\n");
                    sort($msgs);
                    foreach ($msgs as $message) {
                        $merged[$category . chr(4) . $message] = $existing_messages[$message];
                    }
                    ksort($merged);
                    continue;
                }
                // merge existing message translations with new message translations
                foreach ($msgs as $message) {
                    if (array_key_exists($message, $existing_messages) && $existing_messages[$message] !== '') {
                        $merged[$category . chr(4) . $message] = $existing_messages[$message];
                    } else {
                        $not_translated_yet[] = $message;
                    }
                }
                ksort($merged);
                sort($not_translated_yet);
                // collect not yet translated messages
                foreach ($not_translated_yet as $message) {
                    $todos[$category . chr(4) . $message] = '';
                }
                // add obsolete unused messages
                foreach ($existing_messages as $message => $translation) {
                    if (!$remove_unused && !isset($merged[$category . chr(4) . $message]) && !isset($todos[$category . chr(4) . $message])) {
                        if (!$mark_unused || !empty($translation) && (substr($translation, 0, 2) === '@@' && substr($translation, -2) === '@@')) {
                            $todos[$category . chr(4) . $message] = $translation;
                        } else {
                            $todos[$category . chr(4) . $message] = '@@' . $translation . '@@';
                        }
                    }
                }
                $merged = array_merge($merged, $todos);
                if ($sort) {
                    ksort($merged);
                }
                if ($overwrite === false) {
                    $file .= '.merged';
                }
            } else {
                sort($msgs);
                foreach ($msgs as $message) {
                    $merged[$category . chr(4) . $message] = '';
                }
                ksort($merged);
            }
            $this->stdout("Category \"{$category}\" merged.\n");
            $has_something_to_write = true;
        }
        if ($has_something_to_write) {
            $po_file->save($file, $merged);
            $this->stdout("Translation saved.\n", Console::FG_GREEN);
        } else {
            $this->stdout("Nothing to save.\n", Console::FG_GREEN);
        }
    }
    /**
     * Writes messages into POT file.
     *
     * @param array $messages
     * @param string $dirName name of the directory to write to
     * @param string $catalog message catalog
     * @since 2.0.6
     */
    protected function save_messages_to_pot($messages, $dir_name, $catalog)
    {
        $file = str_replace('\\', '/', "{$dir_name}/{$catalog}.pot");
        File_Helper::create_directory(dirname($file));
        $this->stdout("Saving messages to {$file}...\n");
        $po_file = new Gettext_Po_File();
        $merged = [];
        $has_something_to_write = false;
        foreach ($messages as $category => $msgs) {
            $msgs = array_values(array_unique($msgs));
            sort($msgs);
            foreach ($msgs as $message) {
                $merged[$category . chr(4) . $message] = '';
            }
            $this->stdout("Category \"{$category}\" merged.\n");
            $has_something_to_write = true;
        }
        if ($has_something_to_write) {
            ksort($merged);
            $po_file->save($file, $merged);
            $this->stdout("Translation saved.\n", Console::FG_GREEN);
        } else {
            $this->stdout("Nothing to save.\n", Console::FG_GREEN);
        }
    }
    private function delete_unused_php_message_files(array $existing_categories, $dir_name): void
    {
        $message_files = File_Helper::find_files($dir_name);
        foreach ($message_files as $message_file) {
            $category_file_name = str_replace($dir_name, '', $message_file);
            $category_file_name = ltrim($category_file_name, DIRECTORY_SEPARATOR);
            $category = preg_replace('#\.php$#', '', $category_file_name);
            $category = str_replace(DIRECTORY_SEPARATOR, '/', $category);
            if (!in_array($category, $existing_categories, true)) {
                unlink($message_file);
            }
        }
    }
    /**
     * @param string $configFile
     * @throws Exception If configuration file does not exists.
     * @since 2.0.13
     */
    protected function init_config($config_file)
    {
        $config_file_content = [];
        if ($config_file !== null) {
            $config_file = Yii::get_alias($config_file);
            if (!is_file($config_file)) {
                throw new Exception("The configuration file does not exist: {$config_file}");
            }
            $config_file_content = require $config_file;
        }
        $this->config = array_merge($this->get_option_values($this->action->id), $config_file_content, $this->get_passed_option_values());
        $this->config['sourcePath'] = Yii::get_alias($this->config['sourcePath']);
        $this->config['messagePath'] = Yii::get_alias($this->config['messagePath']);
        if (!isset($this->config['sourcePath'], $this->config['languages'])) {
            throw new Exception('The configuration file must specify "sourcePath" and "languages".');
        }
        if (!is_dir($this->config['sourcePath'])) {
            throw new Exception("The source path {$this->config['sourcePath']} is not a valid directory.");
        }
        if (empty($this->config['format']) || !in_array($this->config['format'], ['php', 'po', 'pot', 'db'])) {
            throw new Exception('Format should be either "php", "po", "pot" or "db".');
        }
        if (in_array($this->config['format'], ['php', 'po', 'pot'])) {
            if (!isset($this->config['messagePath'])) {
                throw new Exception('The configuration file must specify "messagePath".');
            }
            if (!is_dir($this->config['messagePath'])) {
                throw new Exception("The message path {$this->config['messagePath']} is not a valid directory.");
            }
        }
        if (empty($this->config['languages'])) {
            throw new Exception('Languages cannot be empty.');
        }
        if ($this->config['format'] === 'php' && $this->config['phpDocBlock'] === null) {
            $this->config['phpDocBlock'] = <<<DOCBLOCK
            /**
             * Message translations.
             *
             * This file is automatically generated by 'yii {$this->id}/{$this->action->id}' command.
             * It contains the localizable messages extracted from source code.
             * You may modify this file by translating the extracted messages.
             *
             * Each array element represents the translation (value) of a message (key).
             * If the value is empty, the message is considered as not translated.
             * Messages that no longer need translation will have their translations
             * enclosed between a pair of '@@' marks.
             *
             * Message string can be used with plural forms format. Check i18n section
             * of the guide for details.
             *
             * NOTE: this file must be saved in UTF-8 encoding.
             */
            DOCBLOCK;
        }
    }
}