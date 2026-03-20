<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\web;

use Yii;
use yii\base\Invalid_Config_Exception;
use yii\db\Connection;
use yii\db\Pdo_Value;
use yii\db\Query;
use yii\di\Instance;
/**
 * DbSession extends [[Session]] by using database as session data storage.
 *
 * By default, DbSession stores session data in a DB table named 'session'. This table
 * must be pre-created. The table name can be changed by setting [[sessionTable]].
 *
 * The following example shows how you can configure the application to use DbSession:
 * Add the following to your application config under `components`:
 *
 * ```
 * 'session' => [
 *     'class' => 'yii\web\DbSession',
 *     // 'db' => 'mydb',
 *     // 'sessionTable' => 'my_session',
 * ]
 * ```
 *
 * DbSession extends [[MultiFieldSession]], thus it allows saving extra fields into the [[sessionTable]].
 * Refer to [[MultiFieldSession]] for more details.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Db_Session extends Multi_Field_Session
{
    /**
     * @var Connection|array|string the DB connection object or the application component ID of the DB connection.
     * After the DbSession object is created, if you want to change this property, you should only assign it
     * with a DB connection object.
     * Starting from version 2.0.2, this can also be a configuration array for creating the object.
     */
    public $db = 'db';
    /**
     * @var string the name of the DB table that stores the session data.
     * The table should be pre-created as follows:
     *
     * ```
     * CREATE TABLE session
     * (
     *     id CHAR(40) NOT NULL PRIMARY KEY,
     *     expire INTEGER,
     *     data BLOB
     * )
     * ```
     *
     * where 'BLOB' refers to the BLOB-type of your preferred DBMS. Below are the BLOB type
     * that can be used for some popular DBMS:
     *
     * - MySQL: LONGBLOB
     * - PostgreSQL: BYTEA
     * - MSSQL: BLOB
     *
     * When using DbSession in a production server, we recommend you create a DB index for the 'expire'
     * column in the session table to improve the performance.
     *
     * Note that according to the php.ini setting of `session.hash_function`, you may need to adjust
     * the length of the `id` column. For example, if `session.hash_function=sha256`, you should use
     * length 64 instead of 40.
     */
    public $session_table = '{{%session}}';
    /**
     * @var array Session fields to be written into session table columns
     * @since 2.0.17
     */
    protected $fields = [];
    /**
     * Initializes the DbSession component.
     * This method will initialize the [[db]] property to make sure it refers to a valid DB connection.
     * @throws InvalidConfigException if [[db]] is invalid.
     */
    public function init(): void
    {
        parent::init();
        $this->db = Instance::ensure($this->db, Connection::class_name());
    }
    /**
     * Session open handler.
     * @internal Do not call this method directly.
     * @param string $savePath session save path
     * @param string $sessionName session name
     * @return bool whether session is opened successfully
     */
    public function open_session($save_path, $session_name)
    {
        if ($this->get_use_strict_mode()) {
            $id = $this->get_id();
            if (!$this->get_read_query($id)->exists($this->db)) {
                //This session id does not exist, mark it for forced regeneration
                $this->_force_regenerate_id = $id;
            }
        }
        return parent::open_session($save_path, $session_name);
    }
    /**
     * {@inheritdoc}
     */
    public function regenerate_id($delete_old_session = false): void
    {
        $old_id = session_id();
        // if no session is started, there is nothing to regenerate
        if (empty($old_id)) {
            return;
        }
        parent::regenerate_id();
        $new_id = session_id();
        // if session id regeneration failed, no need to create/update it.
        if (empty($new_id)) {
            Yii::warning('Failed to generate new session ID', __METHOD__);
            return;
        }
        $row = $this->db->use_master(fn() => (new Query())->from($this->session_table)->where(['id' => $old_id])->create_command($this->db)->query_one());
        if ($row !== false && $this->get_is_active()) {
            if ($delete_old_session) {
                $this->db->create_command()->update($this->session_table, ['id' => $new_id], ['id' => $old_id])->execute();
            } else {
                $row['id'] = $new_id;
                $this->db->create_command()->insert($this->session_table, $row)->execute();
            }
        }
    }
    /**
     * Ends the current session and store session data.
     * @since 2.0.17
     */
    public function close(): void
    {
        if ($this->get_is_active()) {
            // prepare writeCallback fields before session closes
            $this->fields = $this->compose_fields();
            YII_DEBUG ? session_write_close() : @session_write_close();
        }
    }
    /**
     * Session read handler.
     * @internal Do not call this method directly.
     * @param string $id session ID
     * @return string|false the session data, or false on failure
     */
    public function read_session($id)
    {
        $query = $this->get_read_query($id);
        if ($this->read_callback !== null) {
            $fields = $query->one($this->db);
            return $fields === false ? '' : $this->extract_data($fields);
        }
        $data = $query->select(['data'])->scalar($this->db);
        return $data === false ? '' : $data;
    }
    /**
     * Session write handler.
     * @internal Do not call this method directly.
     * @param string $id session ID
     * @param string $data session data
     * @return bool whether session write is successful
     */
    public function write_session($id, $data): bool
    {
        if ($this->get_use_strict_mode() && $id === $this->_force_regenerate_id) {
            //Ignore write when forceRegenerate is active for this id
            return true;
        }
        // exception must be caught in session write handler
        // https://www.php.net/manual/en/function.session-set-save-handler.php#refsect1-function.session-set-save-handler-notes
        try {
            // ensure backwards compatability (fixed #9438)
            if ($this->write_callback && !$this->fields) {
                $this->fields = $this->compose_fields();
            }
            // ensure data consistency
            if (!isset($this->fields['data'])) {
                $this->fields['data'] = $data;
            } else {
                $_SESSION = $this->fields['data'];
            }
            // ensure 'id' and 'expire' are never affected by [[writeCallback]]
            $this->fields = array_merge($this->fields, ['id' => $id, 'expire' => time() + $this->get_timeout()]);
            $this->fields = $this->typecast_fields($this->fields);
            $this->db->create_command()->upsert($this->session_table, $this->fields)->execute();
            $this->fields = [];
        } catch (\Exception $e) {
            Yii::$app->error_handler->handle_exception($e);
            return false;
        }
        return true;
    }
    /**
     * Session destroy handler.
     * @internal Do not call this method directly.
     * @param string $id session ID
     * @return bool whether session is destroyed successfully
     */
    public function destroy_session($id): bool
    {
        $this->db->create_command()->delete($this->session_table, ['id' => $id])->execute();
        return true;
    }
    /**
     * Session GC (garbage collection) handler.
     * @internal Do not call this method directly.
     * @param int $maxLifetime the number of seconds after which data will be seen as 'garbage' and cleaned up.
     * @return int|false the number of deleted sessions on success, or false on failure
     */
    public function gc_session($max_lifetime)
    {
        return $this->db->create_command()->delete($this->session_table, '[[expire]]<:expire', [':expire' => time()])->execute();
    }
    /**
     * Generates a query to get the session from db
     * @param string $id The id of the session
     * @return Query
     */
    protected function get_read_query($id)
    {
        return (new Query())->from($this->session_table)->where('[[expire]]>:expire AND [[id]]=:id', [':expire' => time(), ':id' => $id]);
    }
    /**
     * Method typecasts $fields before passing them to PDO.
     * Default implementation casts field `data` to `\PDO::PARAM_LOB`.
     * You can override this method in case you need special type casting.
     *
     * @param array $fields Fields, that will be passed to PDO. Key - name, Value - value
     * @since 2.0.13
     */
    protected function typecast_fields(array $fields): array
    {
        if (isset($fields['data']) && !is_array($fields['data']) && !is_object($fields['data'])) {
            $fields['data'] = new Pdo_Value($fields['data'], \PDO::PARAM_LOB);
        }
        return $fields;
    }
}