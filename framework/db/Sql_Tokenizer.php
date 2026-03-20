<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\db;

use yii\base\Component;
use yii\base\InvalidArgumentException;
/**
 * SqlTokenizer splits an SQL query into individual SQL tokens.
 *
 * It can be used to obtain an addition information from an SQL code.
 *
 * Usage example:
 *
 * ```
 * $tokenizer = new SqlTokenizer("SELECT * FROM user WHERE id = 1");
 * $root = $tokeinzer->tokenize();
 * $sqlTokens = $root->getChildren();
 * ```
 *
 * Tokens are instances of [[SqlToken]].
 *
 * @author Sergey Makinen <sergey@makinen.ru>
 * @since 2.0.13
 */
abstract class Sql_Tokenizer extends Component
{
    /**
     * @var string SQL code.
     */
    public $sql;
    /**
     * @var int SQL code string length.
     */
    protected $length;
    /**
     * @var int SQL code string current offset.
     */
    protected $offset;
    /**
     * @var \SplStack<SqlToken> stack of active tokens.
     */
    private ?\SplStack $_token_stack = null;
    /**
     * @var SqlToken active token. It's usually a top of the token stack.
     */
    private $_current_token;
    /**
     * @var string[] cached substrings.
     */
    private ?array $_substrings = null;
    /**
     * @var string current buffer value.
     */
    private string $_buffer = '';
    /**
     * @var SqlToken resulting token of a last [[tokenize()]] call.
     */
    private $_token;
    /**
     * Constructor.
     * @param string $sql SQL code to be tokenized.
     * @param array $config name-value pairs that will be used to initialize the object properties
     */
    public function __construct($sql, $config = [])
    {
        $this->sql = $sql;
        parent::__construct($config);
    }
    /**
     * Tokenizes and returns a code type token.
     * @return SqlToken code type token.
     */
    public function tokenize()
    {
        $this->length = mb_strlen($this->sql, 'UTF-8');
        $this->offset = 0;
        $this->_substrings = [];
        $this->_buffer = '';
        $this->_token = new Sql_Token(['type' => Sql_Token::TYPE_CODE, 'content' => $this->sql]);
        $this->_token_stack = new \SplStack();
        $this->_token_stack->push($this->_token);
        $this->_token[] = new Sql_Token(['type' => Sql_Token::TYPE_STATEMENT]);
        $this->_token_stack->push($this->_token[0]);
        $this->_current_token = $this->_token_stack->top();
        while (!$this->is_eof()) {
            if ($this->is_whitespace($length) || $this->is_comment($length)) {
                $this->add_token_from_buffer();
                $this->advance($length);
                continue;
            }
            if ($this->tokenize_operator($length) || $this->tokenize_delimited_string($length)) {
                $this->advance($length);
                continue;
            }
            $this->_buffer .= $this->substring(1);
            $this->advance(1);
        }
        $this->add_token_from_buffer();
        if ($this->_token->get_has_children() && !$this->_token[-1]->get_has_children()) {
            unset($this->_token[-1]);
        }
        return $this->_token;
    }
    /**
     * Returns whether there's a whitespace at the current offset.
     * If this methos returns `true`, it has to set the `$length` parameter to the length of the matched string.
     * @param int $length length of the matched string.
     * @return bool whether there's a whitespace at the current offset.
     */
    abstract protected function is_whitespace(&$length);
    /**
     * Returns whether there's a commentary at the current offset.
     * If this methos returns `true`, it has to set the `$length` parameter to the length of the matched string.
     * @param int $length length of the matched string.
     * @return bool whether there's a commentary at the current offset.
     */
    abstract protected function is_comment(&$length);
    /**
     * Returns whether there's an operator at the current offset.
     * If this methos returns `true`, it has to set the `$length` parameter to the length of the matched string.
     * It may also set `$content` to a string that will be used as a token content.
     * @param int $length length of the matched string.
     * @param string $content optional content instead of the matched string.
     * @return bool whether there's an operator at the current offset.
     */
    abstract protected function is_operator(&$length, &$content);
    /**
     * Returns whether there's an identifier at the current offset.
     * If this methos returns `true`, it has to set the `$length` parameter to the length of the matched string.
     * It may also set `$content` to a string that will be used as a token content.
     * @param int $length length of the matched string.
     * @param string $content optional content instead of the matched string.
     * @return bool whether there's an identifier at the current offset.
     */
    abstract protected function is_identifier(&$length, &$content);
    /**
     * Returns whether there's a string literal at the current offset.
     * If this methos returns `true`, it has to set the `$length` parameter to the length of the matched string.
     * It may also set `$content` to a string that will be used as a token content.
     * @param int $length length of the matched string.
     * @param string $content optional content instead of the matched string.
     * @return bool whether there's a string literal at the current offset.
     */
    abstract protected function is_string_literal(&$length, &$content);
    /**
     * Returns whether the given string is a keyword.
     * The method may set `$content` to a string that will be used as a token content.
     * @param string $string string to be matched.
     * @param string $content optional content instead of the matched string.
     * @return bool whether the given string is a keyword.
     */
    abstract protected function is_keyword($string, &$content);
    /**
     * Returns whether the longest common prefix equals to the SQL code of the same length at the current offset.
     * @param array<int, string>|array<int, array<string, mixed>> $with strings to be tested.
     * The method **will** modify this parameter to speed up lookups.
     * @param bool $caseSensitive whether to perform a case sensitive comparison.
     * @param int|null $length length of the matched string.
     * @param string|null $content matched string.
     * @return bool whether a match is found.
     */
    protected function starts_with_any_longest(array &$with, $case_sensitive, &$length = null, &$content = null)
    {
        if (empty($with)) {
            return false;
        }
        if (!is_array(reset($with))) {
            usort($with, fn($string1, $string2) => mb_strlen($string2, 'UTF-8') - mb_strlen($string1, 'UTF-8'));
            $map = [];
            /** @var string $string */
            foreach ($with as $string) {
                $map[mb_strlen($string, 'UTF-8')][$case_sensitive ? $string : mb_strtoupper($string, 'UTF-8')] = true;
            }
            $with = $map;
        }
        foreach ($with as $test_length => $test_values) {
            $content = $this->substring($test_length, $case_sensitive);
            if (isset($test_values[$content])) {
                $length = $test_length;
                return true;
            }
        }
        return false;
    }
    /**
     * Returns a string of the given length starting with the specified offset.
     * @param int $length string length to be returned.
     * @param bool $caseSensitive if it's `false`, the string will be uppercased.
     * @param int|null $offset SQL code offset, defaults to current if `null` is passed.
     * @return string result string, it may be empty if there's nothing to return.
     */
    protected function substring($length, $case_sensitive = true, $offset = null)
    {
        if ($offset === null) {
            $offset = $this->offset;
        }
        if ($offset + $length > $this->length) {
            return '';
        }
        $cache_key = $offset . ',' . $length;
        if (!isset($this->_substrings[$cache_key . ',1'])) {
            $this->_substrings[$cache_key . ',1'] = mb_substr($this->sql, $offset, $length, 'UTF-8');
        }
        if (!$case_sensitive && !isset($this->_substrings[$cache_key . ',0'])) {
            $this->_substrings[$cache_key . ',0'] = mb_strtoupper($this->_substrings[$cache_key . ',1'], 'UTF-8');
        }
        return $this->_substrings[$cache_key . ',' . (int) $case_sensitive];
    }
    /**
     * Returns an index after the given string in the SQL code starting with the specified offset.
     * @param string $string string to be found.
     * @param int|null $offset SQL code offset, defaults to current if `null` is passed.
     * @return int index after the given string or end of string index.
     */
    protected function index_after($string, $offset = null)
    {
        if ($offset === null) {
            $offset = $this->offset;
        }
        if ($offset + mb_strlen($string, 'UTF-8') > $this->length) {
            return $this->length;
        }
        $after_index_of = mb_strpos($this->sql, $string, $offset, 'UTF-8');
        if ($after_index_of === false) {
            $after_index_of = $this->length;
        } else {
            $after_index_of += mb_strlen($string, 'UTF-8');
        }
        return $after_index_of;
    }
    /**
     * Determines whether there is a delimited string at the current offset and adds it to the token children.
     */
    private function tokenize_delimited_string(int &$length): bool
    {
        $is_identifier = $this->is_identifier($length, $content);
        $is_string_literal = !$is_identifier && $this->is_string_literal($length, $content);
        if (!$is_identifier && !$is_string_literal) {
            return false;
        }
        $this->add_token_from_buffer();
        $this->_current_token[] = new Sql_Token(['type' => $is_identifier ? Sql_Token::TYPE_IDENTIFIER : Sql_Token::TYPE_STRING_LITERAL, 'content' => is_string($content) ? $content : $this->substring($length), 'startOffset' => $this->offset, 'endOffset' => $this->offset + $length]);
        return true;
    }
    /**
     * Determines whether there is an operator at the current offset and adds it to the token children.
     */
    private function tokenize_operator(int &$length): bool
    {
        if (!$this->is_operator($length, $content)) {
            return false;
        }
        $this->add_token_from_buffer();
        switch ($this->substring($length)) {
            case '(':
                $this->_current_token[] = new Sql_Token(['type' => Sql_Token::TYPE_OPERATOR, 'content' => is_string($content) ? $content : $this->substring($length), 'startOffset' => $this->offset, 'endOffset' => $this->offset + $length]);
                $this->_current_token[] = new Sql_Token(['type' => Sql_Token::TYPE_PARENTHESIS]);
                $this->_token_stack->push($this->_current_token[-1]);
                $this->_current_token = $this->_token_stack->top();
                break;
            case ')':
                $this->_token_stack->pop();
                $this->_current_token = $this->_token_stack->top();
                $this->_current_token[] = new Sql_Token(['type' => Sql_Token::TYPE_OPERATOR, 'content' => ')', 'startOffset' => $this->offset, 'endOffset' => $this->offset + $length]);
                break;
            case ';':
                if (!$this->_current_token->get_has_children()) {
                    break;
                }
                $this->_current_token[] = new Sql_Token(['type' => Sql_Token::TYPE_OPERATOR, 'content' => is_string($content) ? $content : $this->substring($length), 'startOffset' => $this->offset, 'endOffset' => $this->offset + $length]);
                $this->_token_stack->pop();
                $this->_current_token = $this->_token_stack->top();
                $this->_current_token[] = new Sql_Token(['type' => Sql_Token::TYPE_STATEMENT]);
                $this->_token_stack->push($this->_current_token[-1]);
                $this->_current_token = $this->_token_stack->top();
                break;
            default:
                $this->_current_token[] = new Sql_Token(['type' => Sql_Token::TYPE_OPERATOR, 'content' => is_string($content) ? $content : $this->substring($length), 'startOffset' => $this->offset, 'endOffset' => $this->offset + $length]);
                break;
        }
        return true;
    }
    /**
     * Determines a type of text in the buffer, tokenizes it and adds it to the token children.
     */
    private function add_token_from_buffer(): void
    {
        if ($this->_buffer === '') {
            return;
        }
        $is_keyword = $this->is_keyword($this->_buffer, $content);
        $this->_current_token[] = new Sql_Token(['type' => $is_keyword ? Sql_Token::TYPE_KEYWORD : Sql_Token::TYPE_TOKEN, 'content' => is_string($content) ? $content : $this->_buffer, 'startOffset' => $this->offset - mb_strlen($this->_buffer, 'UTF-8'), 'endOffset' => $this->offset]);
        $this->_buffer = '';
    }
    /**
     * Adds the specified length to the current offset.
     * @throws InvalidArgumentException
     */
    private function advance(int $length): void
    {
        if ($length <= 0) {
            throw new InvalidArgumentException('Length must be greater than 0.');
        }
        $this->offset += $length;
        $this->_substrings = [];
    }
    /**
     * Returns whether the SQL code is completely traversed.
     */
    private function is_eof(): bool
    {
        return $this->offset >= $this->length;
    }
}