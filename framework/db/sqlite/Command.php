<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\db\sqlite;

use yii\db\Sql_Token;
use yii\helpers\String_Helper;
/**
 * Command represents an SQLite's SQL statement to be executed against a database.
 *
 * {@inheritdoc}
 *
 * @author Sergey Makinen <sergey@makinen.ru>
 * @since 2.0.14
 */
class Command extends \yii\db\Command
{
    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        $sql = $this->get_sql();
        $params = $this->params;
        $statements = $this->split_statements($sql, $params);
        if ($statements === false) {
            return parent::execute();
        }
        $result = null;
        foreach ($statements as $statement) {
            [$statement_sql, $statement_params] = $statement;
            $this->set_sql($statement_sql)->bind_values($statement_params);
            $result = parent::execute();
        }
        $this->set_sql($sql)->bind_values($params);
        return $result;
    }
    /**
     * {@inheritdoc}
     */
    protected function query_internal($method, $fetch_mode = null)
    {
        $sql = $this->get_sql();
        $params = $this->params;
        $statements = $this->split_statements($sql, $params);
        if ($statements === false) {
            return parent::query_internal($method, $fetch_mode);
        }
        [$last_statement_sql, $last_statement_params] = array_pop($statements);
        foreach ($statements as $statement) {
            [$statement_sql, $statement_params] = $statement;
            $this->set_sql($statement_sql)->bind_values($statement_params);
            parent::execute();
        }
        $this->set_sql($last_statement_sql)->bind_values($last_statement_params);
        $result = parent::query_internal($method, $fetch_mode);
        $this->set_sql($sql)->bind_values($params);
        return $result;
    }
    /**
     * Splits the specified SQL code into individual SQL statements and returns them
     * or `false` if there's a single statement.
     * @param string $sql
     * @param array $params
     * @return list<array{string, array}>|false
     */
    private function split_statements($sql, $params)
    {
        $semicolon_index = strpos($sql, ';');
        if ($semicolon_index === false || $semicolon_index === String_Helper::byte_length($sql) - 1) {
            return false;
        }
        $tokenizer = new Sql_Tokenizer($sql);
        $code_token = $tokenizer->tokenize();
        if (count($code_token->get_children()) === 1) {
            return false;
        }
        $statements = [];
        foreach ($code_token->get_children() as $statement) {
            $statements[] = [$statement->get_sql(), $this->extract_used_params($statement, $params)];
        }
        return $statements;
    }
    /**
     * Returns named bindings used in the specified statement token.
     */
    private function extract_used_params(Sql_Token $statement, array $params): array
    {
        preg_match_all('/(?P<placeholder>:\w+)/', $statement->get_sql(), $matches, PREG_SET_ORDER);
        $result = [];
        foreach ($matches as $match) {
            $ph_name = ltrim($match['placeholder'], ':');
            if (isset($params[$ph_name])) {
                $result[$ph_name] = $params[$ph_name];
            } elseif (isset($params[':' . $ph_name])) {
                $result[':' . $ph_name] = $params[':' . $ph_name];
            }
        }
        return $result;
    }
}