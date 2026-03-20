<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\db\oci;

/**
 * Command represents an Oracle SQL statement to be executed against a database.
 *
 * {@inheritdoc}
 *
 * @since 2.0.33
 */
class Command extends \yii\db\Command
{
    /**
     * {@inheritdoc}
     */
    protected function bind_pending_params()
    {
        $params_passed_by_reference = [];
        foreach ($this->pending_params as $name => $value) {
            if (\PDO::PARAM_STR === $value[1]) {
                $params_passed_by_reference[$name] = $value[0];
                $this->pdo_statement->bind_param($name, $params_passed_by_reference[$name], $value[1], strlen($value[0]));
            } else {
                $this->pdo_statement->bind_value($name, $value[0], $value[1]);
            }
        }
        $this->pending_params = [];
    }
}