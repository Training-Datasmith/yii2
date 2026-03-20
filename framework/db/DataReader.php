<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\db;

use yii\base\Invalid_Call_Exception;
/**
 * DataReader represents a forward-only stream of rows from a query result set.
 *
 * To read the current row of data, call [[read()]]. The method [[readAll()]]
 * returns all the rows in a single array. Rows of data can also be read by
 * iterating through the reader. For example,
 *
 * ```
 * $command = $connection->createCommand('SELECT * FROM post');
 * $reader = $command->query();
 *
 * while ($row = $reader->read()) {
 *     $rows[] = $row;
 * }
 *
 * // equivalent to:
 * foreach ($reader as $row) {
 *     $rows[] = $row;
 * }
 *
 * // equivalent to:
 * $rows = $reader->readAll();
 * ```
 *
 * Note that since DataReader is a forward-only stream, you can only traverse it once.
 * Doing it the second time will throw an exception.
 *
 * It is possible to use a specific mode of data fetching by setting
 * [[fetchMode]]. See the [PHP manual](https://www.php.net/manual/en/function.PDOStatement-setFetchMode.php)
 * for more details about possible fetch mode.
 *
 * @property-read int $columnCount The number of columns in the result set.
 * @property-write int $fetchMode Fetch mode.
 * @property-read bool $isClosed Whether the reader is closed or not.
 * @property-read int $rowCount Number of rows contained in the result.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 *
 * @implements \Iterator<int, mixed>
 */
class Data_Reader extends \yii\base\Base_Object implements \Iterator, \Countable
{
    /**
     * @var \PDOStatement the PDOStatement associated with the command
     */
    private $_statement;
    private bool $_closed = false;
    private $_row;
    private int $_index = -1;
    /**
     * Constructor.
     * @param Command $command the command generating the query result
     * @param array $config name-value pairs that will be used to initialize the object properties
     */
    public function __construct(Command $command, $config = [])
    {
        $this->_statement = $command->pdo_statement;
        $this->_statement->set_fetch_mode(\PDO::FETCH_ASSOC);
        parent::__construct($config);
    }
    /**
     * Binds a column to a PHP variable.
     * When rows of data are being fetched, the corresponding column value
     * will be set in the variable. Note, the fetch mode must include PDO::FETCH_BOUND.
     * @param int|string $column Number of the column (1-indexed) or name of the column
     * in the result set. If using the column name, be aware that the name
     * should match the case of the column, as returned by the driver.
     * @param mixed $value Name of the PHP variable to which the column will be bound.
     * @param int|null $dataType Data type of the parameter
     * @see https://www.php.net/manual/en/function.PDOStatement-bindColumn.php
     */
    public function bind_column($column, &$value, $data_type = null): void
    {
        if ($data_type === null) {
            $this->_statement->bind_column($column, $value);
        } else {
            $this->_statement->bind_column($column, $value, $data_type);
        }
    }
    /**
     * Set the default fetch mode for this statement.
     *
     * @param int $mode fetch mode
     * @see https://www.php.net/manual/en/function.PDOStatement-setFetchMode.php
     */
    public function set_fetch_mode($mode): void
    {
        $params = func_get_args();
        call_user_func_array([$this->_statement, 'setFetchMode'], $params);
    }
    /**
     * Advances the reader to the next row in a result set.
     * @return array|false the current row, false if no more row available
     */
    public function read()
    {
        return $this->_statement->fetch();
    }
    /**
     * Returns a single column from the next row of a result set.
     * @param int $columnIndex zero-based column index
     * @return mixed the column of the current row, false if no more rows available
     */
    public function read_column($column_index)
    {
        return $this->_statement->fetch_column($column_index);
    }
    /**
     * Returns an object populated with the next row of data.
     * @param string $className class name of the object to be created and populated
     * @param array $fields Elements of this array are passed to the constructor
     * @return mixed the populated object, false if no more row of data available
     */
    public function read_object($class_name, $fields)
    {
        return $this->_statement->fetch_object($class_name, $fields);
    }
    /**
     * Reads the whole result set into an array.
     * @return array the result set (each array element represents a row of data).
     * An empty array will be returned if the result contains no row.
     */
    public function read_all(): array
    {
        return $this->_statement->fetch_all();
    }
    /**
     * Advances the reader to the next result when reading the results of a batch of statements.
     * This method is only useful when there are multiple result sets
     * returned by the query. Not all DBMS support this feature.
     * @return bool Returns true on success or false on failure.
     */
    public function next_result()
    {
        if (($result = $this->_statement->next_rowset()) !== false) {
            $this->_index = -1;
        }
        return $result;
    }
    /**
     * Closes the reader.
     * This frees up the resources allocated for executing this SQL statement.
     * Read attempts after this method call are unpredictable.
     */
    public function close(): void
    {
        $this->_statement->close_cursor();
        $this->_closed = true;
    }
    /**
     * whether the reader is closed or not.
     * @return bool whether the reader is closed or not.
     */
    public function get_is_closed()
    {
        return $this->_closed;
    }
    /**
     * Returns the number of rows in the result set.
     * Note, most DBMS may not give a meaningful count.
     * In this case, use "SELECT COUNT(*) FROM tableName" to obtain the number of rows.
     * @return int number of rows contained in the result.
     */
    public function get_row_count(): int
    {
        return $this->_statement->row_count();
    }
    /**
     * Returns the number of rows in the result set.
     * This method is required by the Countable interface.
     * Note, most DBMS may not give a meaningful count.
     * In this case, use "SELECT COUNT(*) FROM tableName" to obtain the number of rows.
     * @return int number of rows contained in the result.
     */
    #[\Return_Type_Will_Change]
    public function count()
    {
        return $this->get_row_count();
    }
    /**
     * Returns the number of columns in the result set.
     * Note, even there's no row in the reader, this still gives correct column number.
     * @return int the number of columns in the result set.
     */
    public function get_column_count(): int
    {
        return $this->_statement->column_count();
    }
    /**
     * Resets the iterator to the initial state.
     * This method is required by the interface [[\Iterator]].
     * @throws InvalidCallException if this method is invoked twice
     */
    #[\Return_Type_Will_Change]
    public function rewind(): void
    {
        if ($this->_index < 0) {
            $this->_row = $this->_statement->fetch();
            $this->_index = 0;
        } else {
            throw new Invalid_Call_Exception('DataReader cannot rewind. It is a forward-only reader.');
        }
    }
    /**
     * Returns the index of the current row.
     * This method is required by the interface [[\Iterator]].
     * @return int the index of the current row.
     */
    #[\Return_Type_Will_Change]
    public function key()
    {
        return $this->_index;
    }
    /**
     * Returns the current row.
     * This method is required by the interface [[\Iterator]].
     * @return mixed the current row.
     */
    #[\Return_Type_Will_Change]
    public function current()
    {
        return $this->_row;
    }
    /**
     * Moves the internal pointer to the next row.
     * This method is required by the interface [[\Iterator]].
     */
    #[\Return_Type_Will_Change]
    public function next(): void
    {
        $this->_row = $this->_statement->fetch();
        $this->_index++;
    }
    /**
     * Returns whether there is a row of data at current position.
     * This method is required by the interface [[\Iterator]].
     * @return bool whether there is a row of data at current position.
     */
    #[\Return_Type_Will_Change]
    public function valid()
    {
        return $this->_row !== false;
    }
}