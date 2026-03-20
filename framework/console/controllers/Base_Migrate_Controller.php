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
use yii\base\Base_Object;
use yii\base\Invalid_Config_Exception;
use yii\base\Not_Supported_Exception;
use yii\console\Application;
use yii\console\Controller;
use yii\console\Exception;
use yii\console\Exit_Code;
use yii\db\Migration_Interface;
use yii\helpers\Console;
use yii\helpers\File_Helper;
use yii\helpers\Inflector;
/**
 * BaseMigrateController is the base class for migrate controllers.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 *
 * @template T of Application = Application
 * @extends Controller<T>
 */
abstract class Base_Migrate_Controller extends Controller
{
    /**
     * The name of the dummy migration that marks the beginning of the whole migration history.
     */
    public const BASE_MIGRATION = 'm000000_000000_base';
    /**
     * @var string the default command action.
     */
    public $default_action = 'up';
    /**
     * @var string|array|null the directory containing the migration classes. This can be either
     * a [path alias](guide:concept-aliases) or a directory path.
     *
     * Migration classes located at this path should be declared without a namespace.
     * Use [[migrationNamespaces]] property in case you are using namespaced migrations.
     *
     * If you have set up [[migrationNamespaces]], you may set this field to `null` in order
     * to disable usage of migrations that are not namespaced.
     *
     * Since version 2.0.12 you may also specify an array of migration paths that should be searched for
     * migrations to load. This is mainly useful to support old extensions that provide migrations
     * without namespace and to adopt the new feature of namespaced migrations while keeping existing migrations.
     *
     * In general, to load migrations from different locations, [[migrationNamespaces]] is the preferable solution
     * as the migration name contains the origin of the migration in the history, which is not the case when
     * using multiple migration paths.
     *
     * @see migrationNamespaces
     */
    public $migration_path = ['@app/migrations'];
    /**
     * @var array list of namespaces containing the migration classes.
     *
     * Migration namespaces should be resolvable as a [path alias](guide:concept-aliases) if prefixed with `@`, e.g. if you specify
     * the namespace `app\migrations`, the code `Yii::getAlias('@app/migrations')` should be able to return
     * the file path to the directory this namespace refers to.
     * This corresponds with the [autoloading conventions](guide:concept-autoloading) of Yii.
     *
     * For example:
     *
     * ```
     * [
     *     'app\migrations',
     *     'some\extension\migrations',
     * ]
     * ```
     *
     * @since 2.0.10
     * @see migrationPath
     */
    public $migration_namespaces = [];
    /**
     * @var string the template file for generating new migrations.
     * This can be either a [path alias](guide:concept-aliases) (e.g. "@app/migrations/template.php")
     * or a file path.
     */
    public $template_file;
    /**
     * @var int|null the permission to be set for newly generated migration files.
     * This value will be used by PHP chmod() function. No umask will be applied.
     * If not set, the permission will be determined by the current environment.
     * @since 2.0.43
     */
    public $new_file_mode;
    /**
     * @var string|int|null the user and/or group ownership to be set for newly generated migration files.
     * If not set, the ownership will be determined by the current environment.
     * @since 2.0.43
     * @see FileHelper::changeOwnership()
     */
    public $new_file_ownership;
    /**
     * @var bool indicates whether the console output should be compacted.
     * If this is set to true, the individual commands ran within the migration will not be output to the console.
     * Default is false, in other words the output is fully verbose by default.
     * @since 2.0.13
     */
    public $compact = false;
    /**
     * {@inheritdoc}
     */
    public function options($action_id)
    {
        return array_merge(
            parent::options($action_id),
            ['migrationPath', 'migrationNamespaces', 'compact'],
            // global for all actions
            $action_id === 'create' ? ['templateFile'] : []
        );
    }
    /**
     * This method is invoked right before an action is to be executed (after all possible filters.)
     * It checks the existence of the [[migrationPath]].
     * @param Action<static> $action the action to be executed.
     * @throws InvalidConfigException if directory specified in migrationPath doesn't exist and action isn't "create".
     * @return bool whether the action should continue to be executed.
     *
     * @phpstan-param Action<static> $action
     * @psalm-param Action<self> $action
     */
    public function before_action($action)
    {
        if (parent::before_action($action)) {
            if (empty($this->migration_namespaces) && empty($this->migration_path)) {
                throw new Invalid_Config_Exception('At least one of `migrationPath` or `migrationNamespaces` should be specified.');
            }
            $this->migration_namespaces = (array) $this->migration_namespaces;
            foreach ($this->migration_namespaces as $key => $value) {
                $this->migration_namespaces[$key] = trim($value, '\\');
            }
            if (is_array($this->migration_path)) {
                foreach ($this->migration_path as $i => $path) {
                    $this->migration_path[$i] = Yii::get_alias($path);
                }
            } elseif ($this->migration_path !== null) {
                $path = Yii::get_alias($this->migration_path);
                if (!is_dir($path)) {
                    if ($action->id !== 'create') {
                        throw new Invalid_Config_Exception("Migration failed. Directory specified in migrationPath doesn't exist: {$this->migration_path}");
                    }
                    File_Helper::create_directory($path);
                }
                $this->migration_path = $path;
            }
            $version = Yii::get_version();
            $this->stdout("Yii Migration Tool (based on Yii v{$version})\n\n");
            return true;
        }
        return false;
    }
    /**
     * Upgrades the application by applying new migrations.
     *
     * For example,
     *
     * ```
     * yii migrate     # apply all new migrations
     * yii migrate 3   # apply the first 3 new migrations
     * ```
     *
     * @param int $limit the number of new migrations to be applied. If 0, it means
     * applying all available new migrations.
     *
     * @return int the status of the action execution. 0 means normal, other values mean abnormal.
     */
    public function action_up($limit = 0)
    {
        $migrations = $this->get_new_migrations();
        if (empty($migrations)) {
            $this->stdout("No new migrations found. Your system is up-to-date.\n", Console::FG_GREEN);
            return Exit_Code::OK;
        }
        $total = count($migrations);
        $limit = (int) $limit;
        if ($limit > 0) {
            $migrations = array_slice($migrations, 0, $limit);
        }
        $n = count($migrations);
        if ($n === $total) {
            $this->stdout("Total {$n} new " . ($n === 1 ? 'migration' : 'migrations') . " to be applied:\n", Console::FG_YELLOW);
        } else {
            $this->stdout("Total {$n} out of {$total} new " . ($total === 1 ? 'migration' : 'migrations') . " to be applied:\n", Console::FG_YELLOW);
        }
        foreach ($migrations as $migration) {
            $name_limit = $this->get_migration_name_limit();
            if ($name_limit !== null && strlen($migration) > $name_limit) {
                $this->stdout("\nThe migration name '{$migration}' is too long. Its not possible to apply this migration.\n", Console::FG_RED);
                return Exit_Code::UNSPECIFIED_ERROR;
            }
            $this->stdout("\t{$migration}\n");
        }
        $this->stdout("\n");
        $applied = 0;
        if ($this->confirm('Apply the above ' . ($n === 1 ? 'migration' : 'migrations') . '?')) {
            foreach ($migrations as $migration) {
                if (!$this->migrate_up($migration)) {
                    $this->stdout("\n{$applied} from {$n} " . ($applied === 1 ? 'migration was' : 'migrations were') . " applied.\n", Console::FG_RED);
                    $this->stdout("\nMigration failed. The rest of the migrations are canceled.\n", Console::FG_RED);
                    return Exit_Code::UNSPECIFIED_ERROR;
                }
                $applied++;
            }
            $this->stdout("\n{$n} " . ($n === 1 ? 'migration was' : 'migrations were') . " applied.\n", Console::FG_GREEN);
            $this->stdout("\nMigrated up successfully.\n", Console::FG_GREEN);
        }
        return Exit_Code::OK;
    }
    /**
     * Downgrades the application by reverting old migrations.
     *
     * For example,
     *
     * ```
     * yii migrate/down     # revert the last migration
     * yii migrate/down 3   # revert the last 3 migrations
     * yii migrate/down all # revert all migrations
     * ```
     *
     * @param int|string $limit the number of migrations to be reverted. Defaults to 1,
     * meaning the last applied migration will be reverted. When value is "all", all migrations will be reverted.
     * @throws Exception if the number of the steps specified is less than 1.
     *
     * @return int the status of the action execution. 0 means normal, other values mean abnormal.
     */
    public function action_down($limit = 1)
    {
        if ($limit === 'all') {
            $limit = null;
        } else {
            $limit = (int) $limit;
            if ($limit < 1) {
                throw new Exception('The step argument must be greater than 0.');
            }
        }
        $migrations = $this->get_migration_history($limit);
        if (empty($migrations)) {
            $this->stdout("No migration has been done before.\n", Console::FG_YELLOW);
            return Exit_Code::OK;
        }
        $migrations = array_keys($migrations);
        $n = count($migrations);
        $this->stdout("Total {$n} " . ($n === 1 ? 'migration' : 'migrations') . " to be reverted:\n", Console::FG_YELLOW);
        foreach ($migrations as $migration) {
            $this->stdout("\t{$migration}\n");
        }
        $this->stdout("\n");
        $reverted = 0;
        if ($this->confirm('Revert the above ' . ($n === 1 ? 'migration' : 'migrations') . '?')) {
            foreach ($migrations as $migration) {
                if (!$this->migrate_down($migration)) {
                    $this->stdout("\n{$reverted} from {$n} " . ($reverted === 1 ? 'migration was' : 'migrations were') . " reverted.\n", Console::FG_RED);
                    $this->stdout("\nMigration failed. The rest of the migrations are canceled.\n", Console::FG_RED);
                    return Exit_Code::UNSPECIFIED_ERROR;
                }
                $reverted++;
            }
            $this->stdout("\n{$n} " . ($n === 1 ? 'migration was' : 'migrations were') . " reverted.\n", Console::FG_GREEN);
            $this->stdout("\nMigrated down successfully.\n", Console::FG_GREEN);
        }
        return Exit_Code::OK;
    }
    /**
     * Redoes the last few migrations.
     *
     * This command will first revert the specified migrations, and then apply
     * them again. For example,
     *
     * ```
     * yii migrate/redo     # redo the last applied migration
     * yii migrate/redo 3   # redo the last 3 applied migrations
     * yii migrate/redo all # redo all migrations
     * ```
     *
     * @param int|string $limit the number of migrations to be redone. Defaults to 1,
     * meaning the last applied migration will be redone. When equals "all", all migrations will be redone.
     * @throws Exception if the number of the steps specified is less than 1.
     *
     * @return int the status of the action execution. 0 means normal, other values mean abnormal.
     */
    public function action_redo($limit = 1)
    {
        if ($limit === 'all') {
            $limit = null;
        } else {
            $limit = (int) $limit;
            if ($limit < 1) {
                throw new Exception('The step argument must be greater than 0.');
            }
        }
        $migrations = $this->get_migration_history($limit);
        if (empty($migrations)) {
            $this->stdout("No migration has been done before.\n", Console::FG_YELLOW);
            return Exit_Code::OK;
        }
        $migrations = array_keys($migrations);
        $n = count($migrations);
        $this->stdout("Total {$n} " . ($n === 1 ? 'migration' : 'migrations') . " to be redone:\n", Console::FG_YELLOW);
        foreach ($migrations as $migration) {
            $this->stdout("\t{$migration}\n");
        }
        $this->stdout("\n");
        if ($this->confirm('Redo the above ' . ($n === 1 ? 'migration' : 'migrations') . '?')) {
            foreach ($migrations as $migration) {
                if (!$this->migrate_down($migration)) {
                    $this->stdout("\nMigration failed. The rest of the migrations are canceled.\n", Console::FG_RED);
                    return Exit_Code::UNSPECIFIED_ERROR;
                }
            }
            foreach (array_reverse($migrations) as $migration) {
                if (!$this->migrate_up($migration)) {
                    $this->stdout("\nMigration failed. The rest of the migrations are canceled.\n", Console::FG_RED);
                    return Exit_Code::UNSPECIFIED_ERROR;
                }
            }
            $this->stdout("\n{$n} " . ($n === 1 ? 'migration was' : 'migrations were') . " redone.\n", Console::FG_GREEN);
            $this->stdout("\nMigration redone successfully.\n", Console::FG_GREEN);
        }
        return Exit_Code::OK;
    }
    /**
     * Upgrades or downgrades till the specified version.
     *
     * Can also downgrade versions to the certain apply time in the past by providing
     * a UNIX timestamp or a string parseable by the strtotime() function. This means
     * that all the versions applied after the specified certain time would be reverted.
     *
     * This command will first revert the specified migrations, and then apply
     * them again. For example,
     *
     * ```
     * yii migrate/to 101129_185401                          # using timestamp
     * yii migrate/to m101129_185401_create_user_table       # using full name
     * yii migrate/to 1392853618                             # using UNIX timestamp
     * yii migrate/to "2014-02-15 13:00:50"                  # using strtotime() parseable string
     * yii migrate/to app\migrations\M101129185401CreateUser # using full namespace name
     * ```
     *
     * @param string $version either the version name or the certain time value in the past
     * that the application should be migrated to. This can be either the timestamp,
     * the full name of the migration, the UNIX timestamp, or the parseable datetime
     * string.
     * @throws Exception if the version argument is invalid.
     */
    public function action_to($version)
    {
        if (($namespace_version = $this->extract_namespace_migration_version($version)) !== false) {
            return $this->migrate_to_version($namespace_version);
        }
        if (($migration_name = $this->extract_migration_version($version)) !== false) {
            return $this->migrate_to_version($migration_name);
        }
        if ((string) (int) $version == $version) {
            return $this->migrate_to_time($version);
        }
        if (($time = strtotime($version)) !== false) {
            return $this->migrate_to_time($time);
        }
        throw new Exception("The version argument must be either a timestamp (e.g. 101129_185401),\n the full name of a migration (e.g. m101129_185401_create_user_table),\n the full namespaced name of a migration (e.g. app\\migrations\\M101129185401CreateUserTable),\n a UNIX timestamp (e.g. 1392853000), or a datetime string parseable\nby the strtotime() function (e.g. 2014-02-15 13:00:50).");
    }
    /**
     * Modifies the migration history to the specified version.
     *
     * No actual migration will be performed.
     *
     * ```
     * yii migrate/mark 101129_185401                        # using timestamp
     * yii migrate/mark m101129_185401_create_user_table     # using full name
     * yii migrate/mark app\migrations\M101129185401CreateUser # using full namespace name
     * yii migrate/mark m000000_000000_base # reset the complete migration history
     * ```
     *
     * @param string $version the version at which the migration history should be marked.
     * This can be either the timestamp or the full name of the migration.
     * You may specify the name `m000000_000000_base` to set the migration history to a
     * state where no migration has been applied.
     * @return int CLI exit code
     * @throws Exception if the version argument is invalid or the version cannot be found.
     */
    public function action_mark($version)
    {
        $original_version = $version;
        if (($namespace_version = $this->extract_namespace_migration_version($version)) !== false) {
            $version = $namespace_version;
        } elseif (($migration_name = $this->extract_migration_version($version)) !== false) {
            $version = $migration_name;
        } elseif ($version !== static::BASE_MIGRATION) {
            throw new Exception("The version argument must be either a timestamp (e.g. 101129_185401)\nor the full name of a migration (e.g. m101129_185401_create_user_table)\nor the full name of a namespaced migration (e.g. app\\migrations\\M101129185401CreateUserTable).");
        }
        // try mark up
        $migrations = $this->get_new_migrations();
        foreach ($migrations as $i => $migration) {
            if (strpos($migration, $version) === 0) {
                if ($this->confirm("Set migration history at {$original_version}?")) {
                    for ($j = 0; $j <= $i; ++$j) {
                        $this->add_migration_history($migrations[$j]);
                    }
                    $this->stdout("The migration history is set at {$original_version}.\nNo actual migration was performed.\n", Console::FG_GREEN);
                }
                return Exit_Code::OK;
            }
        }
        // try mark down
        $migrations = array_keys($this->get_migration_history(null));
        $migrations[] = static::BASE_MIGRATION;
        foreach ($migrations as $i => $migration) {
            if (strpos($migration, $version) === 0) {
                if ($i === 0) {
                    $this->stdout("Already at '{$original_version}'. Nothing needs to be done.\n", Console::FG_YELLOW);
                } elseif ($this->confirm("Set migration history at {$original_version}?")) {
                    for ($j = 0; $j < $i; ++$j) {
                        $this->remove_migration_history($migrations[$j]);
                    }
                    $this->stdout("The migration history is set at {$original_version}.\nNo actual migration was performed.\n", Console::FG_GREEN);
                }
                return Exit_Code::OK;
            }
        }
        throw new Exception("Unable to find the version '{$original_version}'.");
    }
    /**
     * Drops all tables and related constraints. Starts the migration from the beginning.
     *
     * ```
     * yii migrate/fresh
     * ```
     *
     * @since 2.0.13
     */
    public function action_fresh()
    {
        if (YII_ENV_PROD) {
            $this->stdout("YII_ENV is set to 'prod'.\nRefreshing migrations is not possible on production systems.\n");
            return Exit_Code::OK;
        }
        if ($this->confirm("Are you sure you want to drop all tables and related constraints and start the migration from the beginning?\nAll data will be lost irreversibly!")) {
            $this->truncate_database();
            return $this->action_up();
        }
        $this->stdout('Action was cancelled by user. Nothing has been performed.');
        return Exit_Code::OK;
    }
    /**
     * Checks if given migration version specification matches namespaced migration name.
     * @param string $rawVersion raw version specification received from user input.
     * @return string|false actual migration version, `false` - if not match.
     * @since 2.0.10
     */
    private function extract_namespace_migration_version($raw_version)
    {
        if (preg_match('/^\\\\?([\w_]+\\\\)+m(\d{6}_?\d{6})(\D.*)?$/is', $raw_version, $matches)) {
            return trim($raw_version, '\\');
        }
        return false;
    }
    /**
     * Checks if given migration version specification matches migration base name.
     * @param string $rawVersion raw version specification received from user input.
     * @return string|false actual migration version, `false` - if not match.
     * @since 2.0.10
     */
    private function extract_migration_version($raw_version)
    {
        if (preg_match('/^m?(\d{6}_?\d{6})(\D.*)?$/is', $raw_version, $matches)) {
            return 'm' . $matches[1];
        }
        return false;
    }
    /**
     * Displays the migration history.
     *
     * This command will show the list of migrations that have been applied
     * so far. For example,
     *
     * ```
     * yii migrate/history     # showing the last 10 migrations
     * yii migrate/history 5   # showing the last 5 migrations
     * yii migrate/history all # showing the whole history
     * ```
     *
     * @param int|string $limit the maximum number of migrations to be displayed.
     * If it is "all", the whole migration history will be displayed.
     * @throws \yii\console\Exception if invalid limit value passed
     */
    public function action_history($limit = 10)
    {
        if ($limit === 'all') {
            $limit = null;
        } else {
            $limit = (int) $limit;
            if ($limit < 1) {
                throw new Exception('The limit must be greater than 0.');
            }
        }
        $migrations = $this->get_migration_history($limit);
        if (empty($migrations)) {
            $this->stdout("No migration has been done before.\n", Console::FG_YELLOW);
        } else {
            $n = count($migrations);
            if ($limit > 0) {
                $this->stdout("Showing the last {$n} applied " . ($n === 1 ? 'migration' : 'migrations') . ":\n", Console::FG_YELLOW);
            } else {
                $this->stdout("Total {$n} " . ($n === 1 ? 'migration has' : 'migrations have') . " been applied before:\n", Console::FG_YELLOW);
            }
            foreach ($migrations as $version => $time) {
                $this->stdout("\t(" . date('Y-m-d H:i:s', $time) . ') ' . $version . "\n");
            }
        }
        return Exit_Code::OK;
    }
    /**
     * Displays the un-applied new migrations.
     *
     * This command will show the new migrations that have not been applied.
     * For example,
     *
     * ```
     * yii migrate/new     # showing the first 10 new migrations
     * yii migrate/new 5   # showing the first 5 new migrations
     * yii migrate/new all # showing all new migrations
     * ```
     *
     * @param int|string $limit the maximum number of new migrations to be displayed.
     * If it is `all`, all available new migrations will be displayed.
     * @throws \yii\console\Exception if invalid limit value passed
     */
    public function action_new($limit = 10)
    {
        if ($limit !== 'all') {
            $limit = (int) $limit;
            if ($limit < 1) {
                throw new Exception('The limit must be greater than 0.');
            }
        }
        $migrations = $this->get_new_migrations();
        if (empty($migrations)) {
            $this->stdout("No new migrations found. Your system is up-to-date.\n", Console::FG_GREEN);
        } else {
            $n = count($migrations);
            if ($limit !== 'all' && $n > $limit) {
                $migrations = array_slice($migrations, 0, $limit);
                $this->stdout("Showing {$limit} out of {$n} new " . ($n === 1 ? 'migration' : 'migrations') . ":\n", Console::FG_YELLOW);
            } else {
                $this->stdout("Found {$n} new " . ($n === 1 ? 'migration' : 'migrations') . ":\n", Console::FG_YELLOW);
            }
            foreach ($migrations as $migration) {
                $this->stdout("\t" . $migration . "\n");
            }
        }
        return Exit_Code::OK;
    }
    /**
     * Creates a new migration.
     *
     * This command creates a new migration using the available migration template.
     * After using this command, developers should modify the created migration
     * skeleton by filling up the actual migration logic.
     *
     * ```
     * yii migrate/create create_user_table
     * ```
     *
     * In order to generate a namespaced migration, you should specify a namespace before the migration's name.
     * Note that backslash (`\`) is usually considered a special character in the shell, so you need to escape it
     * properly to avoid shell errors or incorrect behavior.
     * For example:
     *
     * ```
     * yii migrate/create app\\migrations\\createUserTable
     * ```
     *
     * In case [[migrationPath]] is not set and no namespace is provided, the first entry of [[migrationNamespaces]] will be used.
     *
     * @param string $name the name of the new migration. This should only contain
     * letters, digits, underscores and/or backslashes.
     *
     * Note: If the migration name is of a special form, for example create_xxx or
     * drop_xxx, then the generated migration file will contain extra code,
     * in this case for creating/dropping tables.
     *
     * @throws Exception if the name argument is invalid.
     */
    public function action_create($name)
    {
        if (!preg_match('/^[\w\\\\]+$/', $name)) {
            throw new Exception('The migration name should contain letters, digits, underscore and/or backslash characters only.');
        }
        [$namespace, $class_name] = $this->generate_class_name($name);
        // Abort if name is too long
        $name_limit = $this->get_migration_name_limit();
        if ($name_limit !== null && strlen($class_name) > $name_limit) {
            throw new Exception('The migration name is too long.');
        }
        $migration_path = $this->find_migration_path($namespace);
        $file = $migration_path . DIRECTORY_SEPARATOR . $class_name . '.php';
        if ($this->confirm("Create new migration '{$file}'?")) {
            $content = $this->generate_migration_source_code(['name' => $name, 'className' => $class_name, 'namespace' => $namespace]);
            File_Helper::create_directory($migration_path);
            if (file_put_contents($file, $content, LOCK_EX) === false) {
                $this->stdout("Failed to create new migration.\n", Console::FG_RED);
                return Exit_Code::IOERR;
            }
            File_Helper::change_ownership($file, $this->new_file_ownership, $this->new_file_mode);
            $this->stdout("New migration created successfully.\n", Console::FG_GREEN);
        }
        return Exit_Code::OK;
    }
    /**
     * Generates class base name and namespace from migration name from user input.
     * @param string $name migration name from user input.
     * @return array list of 2 elements: 'namespace' and 'class base name'
     * @since 2.0.10
     */
    private function generate_class_name($name): array
    {
        $namespace = null;
        $name = trim($name, '\\');
        if (strpos($name, '\\') !== false) {
            $namespace = substr($name, 0, strrpos($name, '\\'));
            $name = substr($name, strrpos($name, '\\') + 1);
        } elseif ($this->migration_path === null) {
            $migration_namespaces = $this->migration_namespaces;
            $namespace = array_shift($migration_namespaces);
        }
        if ($namespace === null) {
            $class = 'm' . gmdate('ymd_His') . '_' . $name;
        } else {
            $class = 'M' . gmdate('ymdHis') . Inflector::camelize($name);
        }
        return [$namespace, $class];
    }
    /**
     * Finds the file path for the specified migration namespace.
     * @param string|null $namespace migration namespace.
     * @return string migration file path.
     * @throws Exception on failure.
     * @since 2.0.10
     */
    private function find_migration_path($namespace)
    {
        if (empty($namespace)) {
            return is_array($this->migration_path) ? reset($this->migration_path) : $this->migration_path;
        }
        if (!in_array($namespace, $this->migration_namespaces, true)) {
            throw new Exception("Namespace '{$namespace}' not found in `migrationNamespaces`");
        }
        return $this->get_namespace_path($namespace);
    }
    /**
     * Returns the file path matching the give namespace.
     * @param string $namespace namespace.
     * @return string file path.
     * @since 2.0.10
     */
    private function get_namespace_path($namespace): string
    {
        return str_replace('/', DIRECTORY_SEPARATOR, Yii::get_alias('@' . str_replace('\\', '/', $namespace)));
    }
    /**
     * Upgrades with the specified migration class.
     * @param string $class the migration class name
     * @return bool whether the migration is successful
     */
    protected function migrate_up($class)
    {
        if ($class === self::BASE_MIGRATION) {
            return true;
        }
        $this->stdout("*** applying {$class}\n", Console::FG_YELLOW);
        $start = microtime(true);
        $migration = $this->create_migration($class);
        if ($migration->up() !== false) {
            $this->add_migration_history($class);
            $time = microtime(true) - $start;
            $this->stdout("*** applied {$class} (time: " . sprintf('%.3f', $time) . "s)\n\n", Console::FG_GREEN);
            return true;
        }
        $time = microtime(true) - $start;
        $this->stdout("*** failed to apply {$class} (time: " . sprintf('%.3f', $time) . "s)\n\n", Console::FG_RED);
        return false;
    }
    /**
     * Downgrades with the specified migration class.
     * @param string $class the migration class name
     * @return bool whether the migration is successful
     */
    protected function migrate_down($class)
    {
        if ($class === self::BASE_MIGRATION) {
            return true;
        }
        $this->stdout("*** reverting {$class}\n", Console::FG_YELLOW);
        $start = microtime(true);
        $migration = $this->create_migration($class);
        if ($migration->down() !== false) {
            $this->remove_migration_history($class);
            $time = microtime(true) - $start;
            $this->stdout("*** reverted {$class} (time: " . sprintf('%.3f', $time) . "s)\n\n", Console::FG_GREEN);
            return true;
        }
        $time = microtime(true) - $start;
        $this->stdout("*** failed to revert {$class} (time: " . sprintf('%.3f', $time) . "s)\n\n", Console::FG_RED);
        return false;
    }
    /**
     * Creates a new migration instance.
     * @param string $class the migration class name
     * @return \yii\db\MigrationInterface the migration instance
     */
    protected function create_migration($class)
    {
        $this->include_migration_file($class);
        /** @var MigrationInterface $migration */
        $migration = Yii::create_object($class);
        if ($migration instanceof Base_Object && $migration->can_set_property('compact')) {
            $migration->compact = $this->compact;
        }
        return $migration;
    }
    /**
     * Includes the migration file for a given migration class name.
     *
     * This function will do nothing on namespaced migrations, which are loaded by
     * autoloading automatically. It will include the migration file, by searching
     * [[migrationPath]] for classes without namespace.
     * @param string $class the migration class name.
     * @since 2.0.12
     */
    protected function include_migration_file($class)
    {
        $class = trim($class, '\\');
        if (strpos($class, '\\') === false) {
            if (is_array($this->migration_path)) {
                foreach ($this->migration_path as $path) {
                    $file = $path . DIRECTORY_SEPARATOR . $class . '.php';
                    if (is_file($file)) {
                        require_once $file;
                        break;
                    }
                }
            } else {
                $file = $this->migration_path . DIRECTORY_SEPARATOR . $class . '.php';
                require_once $file;
            }
        }
    }
    /**
     * Migrates to the specified apply time in the past.
     * @param int $time UNIX timestamp value.
     */
    protected function migrate_to_time($time)
    {
        $count = 0;
        $migrations = array_values($this->get_migration_history(null));
        while ($count < count($migrations) && $migrations[$count] > $time) {
            ++$count;
        }
        if ($count === 0) {
            $this->stdout("Nothing needs to be done.\n", Console::FG_GREEN);
        } else {
            return $this->action_down($count);
        }
        return Exit_Code::OK;
    }
    /**
     * Migrates to the certain version.
     * @param string $version name in the full format.
     * @return int CLI exit code
     * @throws Exception if the provided version cannot be found.
     */
    protected function migrate_to_version($version)
    {
        $original_version = $version;
        // try migrate up
        $migrations = $this->get_new_migrations();
        foreach ($migrations as $i => $migration) {
            if (strpos($migration, $version) === 0) {
                return $this->action_up($i + 1);
            }
        }
        // try migrate down
        $migrations = array_keys($this->get_migration_history(null));
        foreach ($migrations as $i => $migration) {
            if (strpos($migration, $version) === 0) {
                if ($i === 0) {
                    $this->stdout("Already at '{$original_version}'. Nothing needs to be done.\n", Console::FG_YELLOW);
                } else {
                    return $this->action_down($i);
                }
                return Exit_Code::OK;
            }
        }
        throw new Exception("Unable to find the version '{$original_version}'.");
    }
    /**
     * Returns the migrations that are not applied.
     * @return array list of new migrations
     */
    protected function get_new_migrations()
    {
        $applied = [];
        foreach ($this->get_migration_history(null) as $class => $time) {
            $applied[trim($class, '\\')] = true;
        }
        $migration_paths = [];
        if (is_array($this->migration_path)) {
            foreach ($this->migration_path as $path) {
                $migration_paths[] = [$path, ''];
            }
        } elseif (!empty($this->migration_path)) {
            $migration_paths[] = [$this->migration_path, ''];
        }
        foreach ($this->migration_namespaces as $namespace) {
            $migration_paths[] = [$this->get_namespace_path($namespace), $namespace];
        }
        $migrations = [];
        foreach ($migration_paths as $item) {
            [$migration_path, $namespace] = $item;
            if (!file_exists($migration_path)) {
                continue;
            }
            $handle = opendir($migration_path);
            while (($file = readdir($handle)) !== false) {
                if ($file === '.') {
                    continue;
                }
                if ($file === '..') {
                    continue;
                }
                $path = $migration_path . DIRECTORY_SEPARATOR . $file;
                if (preg_match('/^(m(\d{6}_?\d{6})\D.*?)\.php$/is', $file, $matches) && is_file($path)) {
                    $class = $matches[1];
                    if (!empty($namespace)) {
                        $class = $namespace . '\\' . $class;
                    }
                    $time = str_replace('_', '', $matches[2]);
                    if (!isset($applied[$class])) {
                        $migrations[$time . '\\' . $class] = $class;
                    }
                }
            }
            closedir($handle);
        }
        ksort($migrations);
        return array_values($migrations);
    }
    /**
     * Generates new migration source PHP code.
     * Child class may override this method, adding extra logic or variation to the process.
     * @param array $params generation parameters, usually following parameters are present:
     *
     *  - name: string migration base name
     *  - className: string migration class name
     *
     * @return string generated PHP code.
     * @since 2.0.8
     */
    protected function generate_migration_source_code($params)
    {
        return $this->render_file(Yii::get_alias($this->template_file), $params);
    }
    /**
     * Truncates the database.
     * This method should be overwritten in subclasses to implement the task of clearing the database.
     * @throws NotSupportedException if not overridden
     * @since 2.0.13
     */
    protected function truncate_database()
    {
        throw new Not_Supported_Exception('This command is not implemented in ' . get_class($this));
    }
    /**
     * Return the maximum name length for a migration.
     *
     * Subclasses may override this method to define a limit.
     * @return int|null the maximum name length for a migration or `null` if no limit applies.
     * @since 2.0.13
     */
    protected function get_migration_name_limit()
    {
        return null;
    }
    /**
     * Returns the migration history.
     * @param int|null $limit the maximum number of records in the history to be returned. `null` for "no limit".
     * @return array the migration history
     */
    abstract protected function get_migration_history($limit);
    /**
     * Adds new migration entry to the history.
     * @param string $version migration version name.
     */
    abstract protected function add_migration_history($version);
    /**
     * Removes existing migration from the history.
     * @param string $version migration version name.
     */
    abstract protected function remove_migration_history($version);
}