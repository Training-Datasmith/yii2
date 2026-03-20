<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\db;

use yii\base\Component;
use yii\di\Instance;
use yii\helpers\String_Helper;
/**
 * Migration is the base class for representing a database migration.
 *
 * Migration is designed to be used together with the "yii migrate" command.
 *
 * Each child class of Migration represents an individual database migration which
 * is identified by the child class name.
 *
 * Within each migration, the [[up()]] method should be overridden to contain the logic
 * for "upgrading" the database; while the [[down()]] method for the "downgrading"
 * logic. The "yii migrate" command manages all available migrations in an application.
 *
 * If the database supports transactions, you may also override [[safeUp()]] and
 * [[safeDown()]] so that if anything wrong happens during the upgrading or downgrading,
 * the whole migration can be reverted in a whole.
 *
 * Note that some DB queries in some DBMS cannot be put into a transaction. For some examples,
 * please refer to [implicit commit](https://dev.mysql.com/doc/refman/5.7/en/implicit-commit.html). If this is the case,
 * you should still implement `up()` and `down()`, instead.
 *
 * Migration provides a set of convenient methods for manipulating database data and schema.
 * For example, the [[insert()]] method can be used to easily insert a row of data into
 * a database table; the [[createTable()]] method can be used to create a database table.
 * Compared with the same methods in [[Command]], these methods will display extra
 * information showing the method parameters and execution time, which may be useful when
 * applying migrations.
 *
 * For more details and usage information on Migration, see the [guide article on Migration](guide:db-migrations).
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Migration extends Component implements Migration_Interface
{
    use Schema_Builder_Trait;
    /**
     * @var Connection|array|string the DB connection object or the application component ID of the DB connection
     * that this migration should work with. Starting from version 2.0.2, this can also be a configuration array
     * for creating the object.
     *
     * Note that when a Migration object is created by the `migrate` command, this property will be overwritten
     * by the command. If you do not want to use the DB connection provided by the command, you may override
     * the [[init()]] method like the following:
     *
     * ```
     * public function init()
     * {
     *     $this->db = 'db2';
     *     parent::init();
     * }
     * ```
     */
    public $db = 'db';
    /**
     * @var int max number of characters of the SQL outputted. Useful for reduction of long statements and making
     * console output more compact.
     * @since 2.0.13
     */
    public $max_sql_output_length;
    /**
     * @var bool indicates whether the console output should be compacted.
     * If this is set to true, the individual commands ran within the migration will not be output to the console.
     * Default is false, in other words the output is fully verbose by default.
     * @since 2.0.13
     */
    public $compact = false;
    /**
     * Initializes the migration.
     * This method will set [[db]] to be the 'db' application component, if it is `null`.
     */
    public function init(): void
    {
        parent::init();
        $this->db = Instance::ensure($this->db, Connection::class_name());
        $this->db->get_schema()->refresh();
        $this->db->enable_slaves = false;
    }
    /**
     * {@inheritdoc}
     * @since 2.0.6
     */
    protected function get_db()
    {
        return $this->db;
    }
    /**
     * This method contains the logic to be executed when applying this migration.
     * Child classes may override this method to provide actual migration logic.
     * @return false|void|mixed return a false value to indicate the migration fails
     * and should not proceed further. All other return values mean the migration succeeds.
     */
    public function up(): ?bool
    {
        $transaction = $this->db->begin_transaction();
        try {
            if ($this->safe_up() === false) {
                $transaction->roll_back();
                return false;
            }
            $transaction->commit();
        } catch (\Exception|\Throwable $e) {
            $this->print_exception($e);
            $transaction->roll_back();
            return false;
        }
        return null;
    }
    /**
     * This method contains the logic to be executed when removing this migration.
     * The default implementation throws an exception indicating the migration cannot be removed.
     * Child classes may override this method if the corresponding migrations can be removed.
     * @return false|void|mixed return a false value to indicate the migration fails
     * and should not proceed further. All other return values mean the migration succeeds.
     */
    public function down(): ?bool
    {
        $transaction = $this->db->begin_transaction();
        try {
            if ($this->safe_down() === false) {
                $transaction->roll_back();
                return false;
            }
            $transaction->commit();
        } catch (\Exception|\Throwable $e) {
            $this->print_exception($e);
            $transaction->roll_back();
            return false;
        }
        return null;
    }
    private function print_exception(\Throwable $e): void
    {
        echo 'Exception: ' . $e->get_message() . ' (' . $e->get_file() . ':' . $e->get_line() . ")\n";
        echo $e->get_trace_as_string() . "\n";
    }
    /**
     * This method contains the logic to be executed when applying this migration.
     * This method differs from [[up()]] in that the DB logic implemented here will
     * be enclosed within a DB transaction.
     * Child classes may implement this method instead of [[up()]] if the DB logic
     * needs to be within a transaction.
     *
     * Note: Not all DBMS support transactions. And some DB queries cannot be put into a transaction. For some examples,
     * please refer to [implicit commit](https://dev.mysql.com/doc/refman/5.7/en/implicit-commit.html).
     *
     * @return false|void|mixed return a false value to indicate the migration fails
     * and should not proceed further. All other return values mean the migration succeeds.
     */
    public function safe_up()
    {
    }
    /**
     * This method contains the logic to be executed when removing this migration.
     * This method differs from [[down()]] in that the DB logic implemented here will
     * be enclosed within a DB transaction.
     * Child classes may implement this method instead of [[down()]] if the DB logic
     * needs to be within a transaction.
     *
     * Note: Not all DBMS support transactions. And some DB queries cannot be put into a transaction. For some examples,
     * please refer to [implicit commit](https://dev.mysql.com/doc/refman/5.7/en/implicit-commit.html).
     *
     * @return false|void|mixed return a false value to indicate the migration fails
     * and should not proceed further. All other return values mean the migration succeeds.
     */
    public function safe_down()
    {
    }
    /**
     * Executes a SQL statement.
     * This method executes the specified SQL statement using [[db]].
     * @param string $sql the SQL statement to be executed
     * @param array $params input parameters (name => value) for the SQL execution.
     * See [[Command::execute()]] for more details.
     */
    public function execute($sql, $params = []): void
    {
        $sql_output = $sql;
        if ($this->max_sql_output_length !== null) {
            $sql_output = String_Helper::truncate($sql, $this->max_sql_output_length, '[... hidden]');
        }
        $time = $this->begin_command("execute SQL: {$sql_output}");
        $this->db->create_command($sql)->bind_values($params)->execute();
        $this->end_command($time);
    }
    /**
     * Creates and executes an INSERT SQL statement.
     * The method will properly escape the column names, and bind the values to be inserted.
     * @param string $table the table that new rows will be inserted into.
     * @param array $columns the column data (name => value) to be inserted into the table.
     */
    public function insert($table, $columns): void
    {
        $time = $this->begin_command("insert into {$table}");
        $this->db->create_command()->insert($table, $columns)->execute();
        $this->end_command($time);
    }
    /**
     * Creates and executes a batch INSERT SQL statement.
     * The method will properly escape the column names, and bind the values to be inserted.
     * @param string $table the table that new rows will be inserted into.
     * @param array $columns the column names.
     * @param array $rows the rows to be batch inserted into the table
     */
    public function batch_insert($table, $columns, $rows): void
    {
        $time = $this->begin_command("insert into {$table}");
        $this->db->create_command()->batch_insert($table, $columns, $rows)->execute();
        $this->end_command($time);
    }
    /**
     * Creates and executes a command to insert rows into a database table if
     * they do not already exist (matching unique constraints),
     * or update them if they do.
     *
     * The method will properly escape the column names, and bind the values to be inserted.
     *
     * @param string $table the table that new rows will be inserted into/updated in.
     * @param array|Query $insertColumns the column data (name => value) to be inserted into the table or instance
     * of [[Query]] to perform `INSERT INTO ... SELECT` SQL statement.
     * @param array|bool $updateColumns the column data (name => value) to be updated if they already exist.
     * If `true` is passed, the column data will be updated to match the insert column data.
     * If `false` is passed, no update will be performed if the column data already exists.
     * @param array $params the parameters to be bound to the command.
     * @since 2.0.14
     */
    public function upsert($table, $insert_columns, $update_columns = true, $params = []): void
    {
        $time = $this->begin_command("upsert into {$table}");
        $this->db->create_command()->upsert($table, $insert_columns, $update_columns, $params)->execute();
        $this->end_command($time);
    }
    /**
     * Creates and executes an UPDATE SQL statement.
     * The method will properly escape the column names and bind the values to be updated.
     * @param string $table the table to be updated.
     * @param array $columns the column data (name => value) to be updated.
     * @param array|string $condition the conditions that will be put in the WHERE part. Please
     * refer to [[Query::where()]] on how to specify conditions.
     * @param array $params the parameters to be bound to the query.
     */
    public function update($table, $columns, $condition = '', $params = []): void
    {
        $time = $this->begin_command("update {$table}");
        $this->db->create_command()->update($table, $columns, $condition, $params)->execute();
        $this->end_command($time);
    }
    /**
     * Creates and executes a DELETE SQL statement.
     * @param string $table the table where the data will be deleted from.
     * @param array|string $condition the conditions that will be put in the WHERE part. Please
     * refer to [[Query::where()]] on how to specify conditions.
     * @param array $params the parameters to be bound to the query.
     */
    public function delete($table, $condition = '', $params = []): void
    {
        $time = $this->begin_command("delete from {$table}");
        $this->db->create_command()->delete($table, $condition, $params)->execute();
        $this->end_command($time);
    }
    /**
     * Builds and executes a SQL statement for creating a new DB table.
     *
     * The columns in the new  table should be specified as name-definition pairs (e.g. 'name' => 'string'),
     * where name stands for a column name which will be properly quoted by the method, and definition
     * stands for the column type which must contain an abstract DB type.
     *
     * The [[QueryBuilder::getColumnType()]] method will be invoked to convert any abstract type into a physical one.
     *
     * If a column is specified with definition only (e.g. 'PRIMARY KEY (name, type)'), it will be directly
     * put into the generated SQL.
     *
     * Example usage:
     * ```
     * class m200000_000000_create_table_fruits extends \yii\db\Migration
     * {
     *     public function safeUp()
     *     {
     *          $this->createTable('{{%fruits}}', [
     *              // ...
     *              'column_name double precision null default null',
     * ```
     *
     *
     * @param string $table the name of the table to be created. The name will be properly quoted by the method.
     * @param array $columns the columns (name => definition) in the new table.
     * @param string|null $options additional SQL fragment that will be appended to the generated SQL.
     */
    public function create_table($table, $columns, $options = null): void
    {
        $time = $this->begin_command("create table {$table}");
        $this->db->create_command()->create_table($table, $columns, $options)->execute();
        foreach ($columns as $column => $type) {
            if ($type instanceof Column_Schema_Builder && $type->comment !== null) {
                $this->db->create_command()->add_comment_on_column($table, $column, $type->comment)->execute();
            }
        }
        $this->end_command($time);
    }
    /**
     * Builds and executes a SQL statement for renaming a DB table.
     * @param string $table the table to be renamed. The name will be properly quoted by the method.
     * @param string $newName the new table name. The name will be properly quoted by the method.
     */
    public function rename_table($table, $new_name): void
    {
        $time = $this->begin_command("rename table {$table} to {$new_name}");
        $this->db->create_command()->rename_table($table, $new_name)->execute();
        $this->end_command($time);
    }
    /**
     * Builds and executes a SQL statement for dropping a DB table.
     * @param string $table the table to be dropped. The name will be properly quoted by the method.
     */
    public function drop_table($table): void
    {
        $time = $this->begin_command("drop table {$table}");
        $this->db->create_command()->drop_table($table)->execute();
        $this->end_command($time);
    }
    /**
     * Builds and executes a SQL statement for truncating a DB table.
     * @param string $table the table to be truncated. The name will be properly quoted by the method.
     */
    public function truncate_table($table): void
    {
        $time = $this->begin_command("truncate table {$table}");
        $this->db->create_command()->truncate_table($table)->execute();
        $this->end_command($time);
    }
    /**
     * Builds and executes a SQL statement for adding a new DB column.
     * @param string $table the table that the new column will be added to. The table name will be properly quoted by the method.
     * @param string $column the name of the new column. The name will be properly quoted by the method.
     * @param string $type the column type. The [[QueryBuilder::getColumnType()]] method will be invoked to convert abstract column type (if any)
     * into the physical one. Anything that is not recognized as abstract type will be kept in the generated SQL.
     * For example, 'string' will be turned into 'varchar(255)', while 'string not null' will become 'varchar(255) not null'.
     */
    public function add_column($table, $column, $type): void
    {
        $time = $this->begin_command("add column {$column} {$type} to table {$table}");
        $this->db->create_command()->add_column($table, $column, $type)->execute();
        if ($type instanceof Column_Schema_Builder && $type->comment !== null) {
            $this->db->create_command()->add_comment_on_column($table, $column, $type->comment)->execute();
        }
        $this->end_command($time);
    }
    /**
     * Builds and executes a SQL statement for dropping a DB column.
     * @param string $table the table whose column is to be dropped. The name will be properly quoted by the method.
     * @param string $column the name of the column to be dropped. The name will be properly quoted by the method.
     */
    public function drop_column($table, $column): void
    {
        $time = $this->begin_command("drop column {$column} from table {$table}");
        $this->db->create_command()->drop_column($table, $column)->execute();
        $this->end_command($time);
    }
    /**
     * Builds and executes a SQL statement for renaming a column.
     * @param string $table the table whose column is to be renamed. The name will be properly quoted by the method.
     * @param string $name the old name of the column. The name will be properly quoted by the method.
     * @param string $newName the new name of the column. The name will be properly quoted by the method.
     */
    public function rename_column($table, $name, $new_name): void
    {
        $time = $this->begin_command("rename column {$name} in table {$table} to {$new_name}");
        $this->db->create_command()->rename_column($table, $name, $new_name)->execute();
        $this->end_command($time);
    }
    /**
     * Builds and executes a SQL statement for changing the definition of a column.
     * @param string $table the table whose column is to be changed. The table name will be properly quoted by the method.
     * @param string $column the name of the column to be changed. The name will be properly quoted by the method.
     * @param string $type the new column type. The [[QueryBuilder::getColumnType()]] method will be invoked to convert abstract column type (if any)
     * into the physical one. Anything that is not recognized as abstract type will be kept in the generated SQL.
     * For example, 'string' will be turned into 'varchar(255)', while 'string not null' will become 'varchar(255) not null'.
     */
    public function alter_column($table, $column, $type): void
    {
        $time = $this->begin_command("alter column {$column} in table {$table} to {$type}");
        $this->db->create_command()->alter_column($table, $column, $type)->execute();
        if ($type instanceof Column_Schema_Builder && $type->comment !== null) {
            $this->db->create_command()->add_comment_on_column($table, $column, $type->comment)->execute();
        }
        $this->end_command($time);
    }
    /**
     * Builds and executes a SQL statement for creating a primary key.
     * The method will properly quote the table and column names.
     * @param string $name the name of the primary key constraint.
     * @param string $table the table that the primary key constraint will be added to.
     * @param string|array $columns comma separated string or array of columns that the primary key will consist of.
     */
    public function add_primary_key($name, $table, $columns): void
    {
        $time = $this->begin_command("add primary key {$name} on {$table} (" . (is_array($columns) ? implode(',', $columns) : $columns) . ')');
        $this->db->create_command()->add_primary_key($name, $table, $columns)->execute();
        $this->end_command($time);
    }
    /**
     * Builds and executes a SQL statement for dropping a primary key.
     * @param string $name the name of the primary key constraint to be removed.
     * @param string $table the table that the primary key constraint will be removed from.
     */
    public function drop_primary_key($name, $table): void
    {
        $time = $this->begin_command("drop primary key {$name}");
        $this->db->create_command()->drop_primary_key($name, $table)->execute();
        $this->end_command($time);
    }
    /**
     * Builds a SQL statement for adding a foreign key constraint to an existing table.
     * The method will properly quote the table and column names.
     * @param string $name the name of the foreign key constraint.
     * @param string $table the table that the foreign key constraint will be added to.
     * @param string|array $columns the name of the column to that the constraint will be added on. If there are multiple columns, separate them with commas or use an array.
     * @param string $refTable the table that the foreign key references to.
     * @param string|array $refColumns the name of the column that the foreign key references to. If there are multiple columns, separate them with commas or use an array.
     * @param string|null $delete the ON DELETE option. Most DBMS support these options: RESTRICT, CASCADE, NO ACTION, SET DEFAULT, SET NULL
     * @param string|null $update the ON UPDATE option. Most DBMS support these options: RESTRICT, CASCADE, NO ACTION, SET DEFAULT, SET NULL
     */
    public function add_foreign_key($name, $table, $columns, $ref_table, $ref_columns, $delete = null, $update = null): void
    {
        $time = $this->begin_command("add foreign key {$name}: {$table} (" . implode(',', (array) $columns) . ") references {$ref_table} (" . implode(',', (array) $ref_columns) . ')');
        $this->db->create_command()->add_foreign_key($name, $table, $columns, $ref_table, $ref_columns, $delete, $update)->execute();
        $this->end_command($time);
    }
    /**
     * Builds a SQL statement for dropping a foreign key constraint.
     * @param string $name the name of the foreign key constraint to be dropped. The name will be properly quoted by the method.
     * @param string $table the table whose foreign is to be dropped. The name will be properly quoted by the method.
     */
    public function drop_foreign_key($name, $table): void
    {
        $time = $this->begin_command("drop foreign key {$name} from table {$table}");
        $this->db->create_command()->drop_foreign_key($name, $table)->execute();
        $this->end_command($time);
    }
    /**
     * Builds and executes a SQL statement for creating a new index.
     * @param string $name the name of the index. The name will be properly quoted by the method.
     * @param string $table the table that the new index will be created for. The table name will be properly quoted by the method.
     * @param string|array $columns the column(s) that should be included in the index. If there are multiple columns, please separate them
     * by commas or use an array. Each column name will be properly quoted by the method. Quoting will be skipped for column names that
     * include a left parenthesis "(".
     * @param bool $unique whether to add UNIQUE constraint on the created index.
     */
    public function create_index($name, $table, $columns, $unique = false): void
    {
        $time = $this->begin_command('create' . ($unique ? ' unique' : '') . " index {$name} on {$table} (" . implode(',', (array) $columns) . ')');
        $this->db->create_command()->create_index($name, $table, $columns, $unique)->execute();
        $this->end_command($time);
    }
    /**
     * Builds and executes a SQL statement for dropping an index.
     * @param string $name the name of the index to be dropped. The name will be properly quoted by the method.
     * @param string $table the table whose index is to be dropped. The name will be properly quoted by the method.
     */
    public function drop_index($name, $table): void
    {
        $time = $this->begin_command("drop index {$name} on {$table}");
        $this->db->create_command()->drop_index($name, $table)->execute();
        $this->end_command($time);
    }
    /**
     * Creates a SQL command for adding a check constraint to an existing table.
     * @param string $name the name of the check constraint.
     * The name will be properly quoted by the method.
     * @param string $table the table that the check constraint will be added to.
     * The name will be properly quoted by the method.
     * @param string $expression the SQL of the `CHECK` constraint.
     */
    public function add_check($name, $table, $expression): void
    {
        $time = $this->begin_command("add check {$name} in table {$table}");
        $this->db->create_command()->add_check($name, $table, $expression)->execute();
        $this->end_command($time);
    }
    /**
     * Creates a SQL command for dropping a check constraint.
     * @param string $name the name of the check constraint to be dropped.
     * The name will be properly quoted by the method.
     * @param string $table the table whose check constraint is to be dropped.
     * The name will be properly quoted by the method.
     */
    public function drop_check($name, $table): void
    {
        $time = $this->begin_command("drop check {$name} in table {$table}");
        $this->db->create_command()->drop_check($name, $table)->execute();
        $this->end_command($time);
    }
    /**
     * Builds and execute a SQL statement for adding comment to column.
     *
     * @param string $table the table whose column is to be commented. The table name will be properly quoted by the method.
     * @param string $column the name of the column to be commented. The column name will be properly quoted by the method.
     * @param string $comment the text of the comment to be added. The comment will be properly quoted by the method.
     * @since 2.0.8
     */
    public function add_comment_on_column($table, $column, $comment): void
    {
        $time = $this->begin_command("add comment on column {$column}");
        $this->db->create_command()->add_comment_on_column($table, $column, $comment)->execute();
        $this->end_command($time);
    }
    /**
     * Builds a SQL statement for adding comment to table.
     *
     * @param string $table the table to be commented. The table name will be properly quoted by the method.
     * @param string $comment the text of the comment to be added. The comment will be properly quoted by the method.
     * @since 2.0.8
     */
    public function add_comment_on_table($table, $comment): void
    {
        $time = $this->begin_command("add comment on table {$table}");
        $this->db->create_command()->add_comment_on_table($table, $comment)->execute();
        $this->end_command($time);
    }
    /**
     * Builds and execute a SQL statement for dropping comment from column.
     *
     * @param string $table the table whose column is to be commented. The table name will be properly quoted by the method.
     * @param string $column the name of the column to be commented. The column name will be properly quoted by the method.
     * @since 2.0.8
     */
    public function drop_comment_from_column($table, $column): void
    {
        $time = $this->begin_command("drop comment from column {$column}");
        $this->db->create_command()->drop_comment_from_column($table, $column)->execute();
        $this->end_command($time);
    }
    /**
     * Builds a SQL statement for dropping comment from table.
     *
     * @param string $table the table whose column is to be commented. The table name will be properly quoted by the method.
     * @since 2.0.8
     */
    public function drop_comment_from_table($table): void
    {
        $time = $this->begin_command("drop comment from table {$table}");
        $this->db->create_command()->drop_comment_from_table($table)->execute();
        $this->end_command($time);
    }
    /**
     * Prepares for a command to be executed, and outputs to the console.
     *
     * @param string $description the description for the command, to be output to the console.
     * @return float the time before the command is executed, for the time elapsed to be calculated.
     * @since 2.0.13
     */
    protected function begin_command($description): float
    {
        if (!$this->compact) {
            echo "    > {$description} ...";
        }
        return microtime(true);
    }
    /**
     * Finalizes after the command has been executed, and outputs to the console the time elapsed.
     *
     * @param float $time the time before the command was executed.
     * @since 2.0.13
     */
    protected function end_command($time)
    {
        if (!$this->compact) {
            echo ' done (time: ' . sprintf('%.3f', microtime(true) - $time) . "s)\n";
        }
    }
}