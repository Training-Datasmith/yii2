<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\console\controllers;

use Yii;
use yii\base\Action;
use yii\console\Application;
use yii\db\Connection;
use yii\db\Query;
use yii\di\Instance;
use yii\helpers\Array_Helper;
use yii\helpers\Console;
use yii\helpers\Inflector;
/**
 * Manages application migrations.
 *
 * A migration means a set of persistent changes to the application environment
 * that is shared among different developers. For example, in an application
 * backed by a database, a migration may refer to a set of changes to
 * the database, such as creating a new table, adding a new table column.
 *
 * This command provides support for tracking the migration history, upgrading
 * or downloading with migrations, and creating new migration skeletons.
 *
 * The migration history is stored in a database table named
 * as [[migrationTable]]. The table will be automatically created the first time
 * this command is executed, if it does not exist. You may also manually
 * create it as follows:
 *
 * ```
 * CREATE TABLE migration (
 *     version varchar(180) PRIMARY KEY,
 *     apply_time integer
 * )
 * ```
 *
 * Below are some common usages of this command:
 *
 * ```
 * # creates a new migration named 'create_user_table'
 * yii migrate/create create_user_table
 *
 * # applies ALL new migrations
 * yii migrate
 *
 * # reverts the last applied migration
 * yii migrate/down
 * ```
 *
 * Since 2.0.10 you can use namespaced migrations. In order to enable this feature you should configure [[migrationNamespaces]]
 * property for the controller at application configuration:
 *
 * ```
 * return [
 *     'controllerMap' => [
 *         'migrate' => [
 *             'class' => 'yii\console\controllers\MigrateController',
 *             'migrationNamespaces' => [
 *                 'app\migrations',
 *                 'some\extension\migrations',
 *             ],
 *             //'migrationPath' => null, // allows to disable not namespaced migration completely
 *         ],
 *     ],
 * ];
 * ```
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 *
 * @template T of Application = Application
 * @extends BaseMigrateController<T>
 */
class Migrate_Controller extends Base_Migrate_Controller
{
    /**
     * Maximum length of a migration name.
     * @since 2.0.13
     */
    public const MAX_NAME_LENGTH = 180;
    /**
     * @var string the name of the table for keeping applied migration information.
     */
    public $migration_table = '{{%migration}}';
    /**
     * {@inheritdoc}
     */
    public $template_file = '@yii/views/migration.php';
    /**
     * @var array a set of template paths for generating migration code automatically.
     *
     * The key is the template type, the value is a path or the alias. Supported types are:
     * - `create_table`: table creating template
     * - `drop_table`: table dropping template
     * - `add_column`: adding new column template
     * - `drop_column`: dropping column template
     * - `create_junction`: create junction template
     *
     * @since 2.0.7
     */
    public $generator_template_files = ['create_table' => '@yii/views/createTableMigration.php', 'drop_table' => '@yii/views/dropTableMigration.php', 'add_column' => '@yii/views/addColumnMigration.php', 'drop_column' => '@yii/views/dropColumnMigration.php', 'create_junction' => '@yii/views/createTableMigration.php'];
    /**
     * @var bool indicates whether the table names generated should consider
     * the `tablePrefix` setting of the DB connection. For example, if the table
     * name is `post` the generator wil return `{{%post}}`.
     * @since 2.0.8
     */
    public $use_table_prefix = true;
    /**
     * @var array column definition strings used for creating migration code.
     *
     * The format of each definition is `COLUMN_NAME:COLUMN_TYPE:COLUMN_DECORATOR`. Delimiter is `,`.
     * For example, `--fields="name:string(12):notNull:unique"`
     * produces a string column of size 12 which is not null and unique values.
     *
     * Note: primary key is added automatically and is named id by default.
     * If you want to use another name you may specify it explicitly like
     * `--fields="id_key:primaryKey,name:string(12):notNull:unique"`
     * @since 2.0.7
     */
    public $fields = [];
    /**
     * @var Connection|array|string the DB connection object or the application component ID of the DB connection to use
     * when applying migrations. Starting from version 2.0.3, this can also be a configuration array
     * for creating the object.
     */
    public $db = 'db';
    /**
     * @var string the comment for the table being created.
     * @since 2.0.14
     */
    public $comment = '';
    /**
     * {@inheritdoc}
     */
    public function options($action_id): array
    {
        return array_merge(
            parent::options($action_id),
            ['migrationTable', 'db'],
            // global for all actions
            $action_id === 'create' ? ['templateFile', 'fields', 'useTablePrefix', 'comment'] : []
        );
    }
    /**
     * {@inheritdoc}
     * @since 2.0.8
     */
    public function option_aliases(): array
    {
        return array_merge(parent::option_aliases(), ['C' => 'comment', 'f' => 'fields', 'p' => 'migrationPath', 't' => 'migrationTable', 'F' => 'templateFile', 'P' => 'useTablePrefix', 'c' => 'compact']);
    }
    /**
     * This method is invoked right before an action is to be executed (after all possible filters.)
     * It checks the existence of the [[migrationPath]].
     * @param Action<static> $action the action to be executed.
     * @return bool whether the action should continue to be executed.
     *
     * @phpstan-param Action<static> $action
     * @psalm-param Action<self> $action
     */
    public function before_action($action): bool
    {
        if (parent::before_action($action)) {
            $this->db = Instance::ensure($this->db, Connection::class_name());
            return true;
        }
        return false;
    }
    /**
     * Creates a new migration instance.
     * @param string $class the migration class name
     * @return \yii\db\Migration the migration instance
     */
    protected function create_migration($class)
    {
        $this->include_migration_file($class);
        return Yii::create_object(['class' => $class, 'db' => $this->db, 'compact' => $this->compact]);
    }
    /**
     * {@inheritdoc}
     */
    protected function get_migration_history($limit)
    {
        if ($this->db->schema->get_table_schema($this->migration_table, true) === null) {
            $this->create_migration_history_table();
        }
        $query = (new Query())->select(['version', 'apply_time'])->from($this->migration_table)->order_by(['apply_time' => SORT_DESC, 'version' => SORT_DESC]);
        if (empty($this->migration_namespaces)) {
            $query->limit($limit);
            $rows = $query->all($this->db);
            $history = Array_Helper::map($rows, 'version', 'apply_time');
            unset($history[self::BASE_MIGRATION]);
            return $history;
        }
        $rows = $query->all($this->db);
        $history = [];
        foreach ($rows as $row) {
            if ($row['version'] === self::BASE_MIGRATION) {
                continue;
            }
            if (preg_match('/m?(\d{6}_?\d{6})(\D.*)?$/is', $row['version'], $matches)) {
                $time = str_replace('_', '', $matches[1]);
                $row['canonicalVersion'] = $time;
            } else {
                $row['canonicalVersion'] = $row['version'];
            }
            $row['apply_time'] = (int) $row['apply_time'];
            $history[] = $row;
        }
        usort($history, function (array $a, array $b): int {
            if ($a['apply_time'] === $b['apply_time']) {
                if (($compare_result = strcasecmp($b['canonicalVersion'], $a['canonicalVersion'])) !== 0) {
                    return $compare_result;
                }
                return strcasecmp($b['version'], $a['version']);
            }
            return $a['apply_time'] > $b['apply_time'] ? -1 : +1;
        });
        $history = array_slice($history, 0, $limit);
        return Array_Helper::map($history, 'version', 'apply_time');
    }
    /**
     * Creates the migration history table.
     */
    protected function create_migration_history_table()
    {
        $table_name = $this->db->schema->get_raw_table_name($this->migration_table);
        $this->stdout("Creating migration history table \"{$table_name}\"...", Console::FG_YELLOW);
        $this->db->create_command()->create_table($this->migration_table, ['version' => 'varchar(' . static::MAX_NAME_LENGTH . ') NOT NULL PRIMARY KEY', 'apply_time' => 'integer'])->execute();
        $this->db->create_command()->insert($this->migration_table, ['version' => self::BASE_MIGRATION, 'apply_time' => time()])->execute();
        $this->stdout("Done.\n", Console::FG_GREEN);
    }
    /**
     * {@inheritdoc}
     */
    protected function add_migration_history($version)
    {
        $command = $this->db->create_command();
        $command->insert($this->migration_table, ['version' => $version, 'apply_time' => time()])->execute();
    }
    /**
     * {@inheritdoc}
     * @since 2.0.13
     */
    protected function truncate_database()
    {
        $db = $this->db;
        $schemas = $db->schema->get_table_schemas();
        // First drop all foreign keys,
        foreach ($schemas as $schema) {
            foreach ($schema->foreign_keys as $name => $foreign_key) {
                $db->create_command()->drop_foreign_key($name, $schema->name)->execute();
                $this->stdout("Foreign key {$name} dropped.\n");
            }
        }
        // Then drop the tables:
        foreach ($schemas as $schema) {
            try {
                $db->create_command()->drop_table($schema->name)->execute();
                $this->stdout("Table {$schema->name} dropped.\n");
            } catch (\Exception $e) {
                if ($this->is_view_related($e->get_message())) {
                    $db->create_command()->drop_view($schema->name)->execute();
                    $this->stdout("View {$schema->name} dropped.\n");
                } else {
                    $this->stdout("Cannot drop {$schema->name} Table .\n");
                }
            }
        }
    }
    /**
     * Determines whether the error message is related to deleting a view or not
     */
    private function is_view_related(string $error_message): bool
    {
        $drop_view_errors = [
            'DROP VIEW to delete view',
            // SQLite
            'SQLSTATE[42S02]',
            // MySQL
            'is a view. Use DROP VIEW',
        ];
        foreach ($drop_view_errors as $drop_view_error) {
            if (strpos($error_message, $drop_view_error) !== false) {
                return true;
            }
        }
        return false;
    }
    /**
     * {@inheritdoc}
     */
    protected function remove_migration_history($version)
    {
        $command = $this->db->create_command();
        $command->delete($this->migration_table, ['version' => $version])->execute();
    }
    private $_migration_name_limit;
    /**
     * {@inheritdoc}
     * @since 2.0.13
     */
    protected function get_migration_name_limit()
    {
        if ($this->_migration_name_limit !== null) {
            return $this->_migration_name_limit;
        }
        $table_schema = $this->db->schema ? $this->db->schema->get_table_schema($this->migration_table, true) : null;
        if ($table_schema !== null) {
            return $this->_migration_name_limit = $table_schema->columns['version']->size;
        }
        return static::MAX_NAME_LENGTH;
    }
    /**
     * Normalizes table name for generator.
     * When name is preceded with underscore name case is kept - otherwise it's converted from camelcase to underscored.
     * Last underscore is always trimmed so if there should be underscore at the end of name use two of them.
     * @return string
     */
    private function normalize_table_name(string $name)
    {
        if (substr($name, -1) === '_') {
            $name = substr($name, 0, -1);
        }
        if (strncmp($name, '_', 1) === 0) {
            return substr($name, 1);
        }
        return Inflector::underscore($name);
    }
    /**
     * {@inheritdoc}
     * @since 2.0.8
     */
    protected function generate_migration_source_code($params)
    {
        $parsed_fields = $this->parse_fields();
        $fields = $parsed_fields['fields'];
        $foreign_keys = $parsed_fields['foreignKeys'];
        $name = $params['name'];
        if ($params['namespace']) {
            $name = substr($name, (strrpos($name, '\\') ?: -1) + 1);
        }
        $template_file = $this->template_file;
        $table = null;
        if (preg_match('/^create_?junction_?(?:table)?_?(?:for)?(.+)_?and(.+)_?tables?$/i', $name, $matches)) {
            $template_file = $this->generator_template_files['create_junction'];
            $first_table = $this->normalize_table_name($matches[1]);
            $second_table = $this->normalize_table_name($matches[2]);
            $fields = array_merge([['property' => $first_table . '_id', 'decorators' => 'integer()'], ['property' => $second_table . '_id', 'decorators' => 'integer()']], $fields, [['property' => 'PRIMARY KEY(' . $first_table . '_id, ' . $second_table . '_id)']]);
            $foreign_keys[$first_table . '_id']['table'] = $first_table;
            $foreign_keys[$second_table . '_id']['table'] = $second_table;
            $foreign_keys[$first_table . '_id']['column'] = null;
            $foreign_keys[$second_table . '_id']['column'] = null;
            $table = $first_table . '_' . $second_table;
        } elseif (preg_match('/^add(.+)columns?_?to(.+)table$/i', $name, $matches)) {
            $template_file = $this->generator_template_files['add_column'];
            $table = $this->normalize_table_name($matches[2]);
        } elseif (preg_match('/^drop(.+)columns?_?from(.+)table$/i', $name, $matches)) {
            $template_file = $this->generator_template_files['drop_column'];
            $table = $this->normalize_table_name($matches[2]);
        } elseif (preg_match('/^create(.+)table$/i', $name, $matches)) {
            $this->add_default_primary_key($fields);
            $template_file = $this->generator_template_files['create_table'];
            $table = $this->normalize_table_name($matches[1]);
        } elseif (preg_match('/^drop(.+)table$/i', $name, $matches)) {
            $this->add_default_primary_key($fields);
            $template_file = $this->generator_template_files['drop_table'];
            $table = $this->normalize_table_name($matches[1]);
        }
        foreach ($foreign_keys as $column => $foreign_key) {
            $related_column = $foreign_key['column'];
            $related_table = $foreign_key['table'];
            // Since 2.0.11 if related column name is not specified,
            // we're trying to get it from table schema
            // @see https://github.com/yiisoft/yii2/issues/12748
            if ($related_column === null) {
                $related_column = 'id';
                try {
                    $this->db = Instance::ensure($this->db, Connection::class_name());
                    $related_table_schema = $this->db->get_table_schema($related_table);
                    if ($related_table_schema !== null) {
                        $primary_key_count = count($related_table_schema->primary_key);
                        if ($primary_key_count === 1) {
                            $related_column = $related_table_schema->primary_key[0];
                        } elseif ($primary_key_count > 1) {
                            $this->stdout("Related table for field \"{$column}\" exists, but primary key is composite. Default name \"id\" will be used for related field\n", Console::FG_YELLOW);
                        } elseif ($primary_key_count === 0) {
                            $this->stdout("Related table for field \"{$column}\" exists, but does not have a primary key. Default name \"id\" will be used for related field.\n", Console::FG_YELLOW);
                        }
                    }
                } catch (\Reflection_Exception $e) {
                    $this->stdout("Cannot initialize database component to try reading referenced table schema for field \"{$column}\". Default name \"id\" will be used for related field.\n", Console::FG_YELLOW);
                }
            }
            $foreign_keys[$column] = ['idx' => $this->generate_table_name("idx-{$table}-{$column}"), 'fk' => $this->generate_table_name("fk-{$table}-{$column}"), 'relatedTable' => $this->generate_table_name($related_table), 'relatedColumn' => $related_column];
        }
        return $this->render_file(Yii::get_alias($template_file), array_merge($params, ['table' => $this->generate_table_name($table), 'fields' => $fields, 'foreignKeys' => $foreign_keys, 'tableComment' => $this->comment]));
    }
    /**
     * If `useTablePrefix` equals true, then the table name will contain the
     * prefix format.
     *
     * @param string $tableName the table name to generate.
     * @since 2.0.8
     */
    protected function generate_table_name(string $table_name): string
    {
        if (!$this->use_table_prefix) {
            return $table_name;
        }
        return '{{%' . $table_name . '}}';
    }
    /**
     * Parse the command line migration fields.
     * @return array parse result with following fields:
     *
     * - fields: array, parsed fields
     * - foreignKeys: array, detected foreign keys
     *
     * @since 2.0.7
     */
    protected function parse_fields(): array
    {
        $fields = [];
        $foreign_keys = [];
        foreach ($this->fields as $field) {
            $chunks = $this->split_field_into_chunks($field);
            $property = array_shift($chunks);
            foreach ($chunks as $i => &$chunk) {
                if (strncmp($chunk, 'foreignKey', 10) === 0) {
                    preg_match('/foreignKey\((\w*)\s?(\w*)\)/', $chunk, $matches);
                    $foreign_keys[$property] = ['table' => $matches[1] ?? preg_replace('/_id$/', '', $property), 'column' => !empty($matches[2]) ? $matches[2] : null];
                    unset($chunks[$i]);
                    continue;
                }
                if (!preg_match('/^(.+?)\(([^(]+)\)$/', $chunk)) {
                    $chunk .= '()';
                }
            }
            $fields[] = ['property' => $property, 'decorators' => implode('->', $chunks)];
        }
        return ['fields' => $fields, 'foreignKeys' => $foreign_keys];
    }
    /**
     * Splits field into chunks
     *
     * @param string $field
     * @return string[]|false
     */
    protected function split_field_into_chunks($field)
    {
        $original_default_value = null;
        $default_value = null;
        preg_match_all('/defaultValue\(["\'].*?:?.*?["\']\)/', $field, $matches, PREG_SET_ORDER, 0);
        if (isset($matches[0][0])) {
            $original_default_value = $matches[0][0];
            $default_value = str_replace(':', '{{colon}}', $original_default_value);
            $field = str_replace($original_default_value, $default_value, $field);
        }
        $chunks = preg_split('/\s?:\s?/', $field);
        if (is_array($chunks) && $default_value !== null && $original_default_value !== null) {
            foreach ($chunks as $key => $chunk) {
                $chunks[$key] = str_replace($default_value, $original_default_value, $chunk);
            }
        }
        return $chunks;
    }
    /**
     * Adds default primary key to fields list if there's no primary key specified.
     * @param array $fields parsed fields
     * @since 2.0.7
     */
    protected function add_default_primary_key(&$fields)
    {
        foreach ($fields as $field) {
            if ($field['property'] === 'id' || false !== strripos($field['decorators'], 'primarykey()')) {
                return;
            }
        }
        array_unshift($fields, ['property' => 'id', 'decorators' => 'primaryKey()']);
    }
}