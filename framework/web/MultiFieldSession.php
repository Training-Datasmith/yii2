<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\web;

/**
 * MultiFieldSession is the base class for session storage implementations with multi-field data storage support.
 *
 * With multi-field data storage, session data can be split between several fields in the storage record.
 * Using such a storage allows saving particular session data into separated field, which then can be used
 * to manipulate sessions in the way plain PHP does not allow.
 *
 * For example the ID of the authenticated user can be saved as separated column in the MySQL 'session' table,
 * which allows to query all active sessions for a particular user or terminate them at will.
 *
 * Customizing of the session writing is performed via [[writeCallback]], reading via [[readCallback]].
 *
 * While extending this class you should use [[composeFields()]] method - while writing the session data into the storage and
 * [[extractData()]] - while reading session data from the storage.
 *
 * @property-read bool $useCustomStorage Whether to use custom storage.
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 2.0.6
 */
abstract class Multi_Field_Session extends Session
{
    /**
     * @var callable a callback that will be called during session data reading.
     * The signature of the callback should be as follows:
     *
     * ```
     * function ($fields)
     * ```
     *
     * where `$fields` is the storage field set for read session and `$session` is this session instance.
     * If callback returns an array, it will be merged into the session data.
     *
     * For example:
     *
     * ```
     * function ($fields) {
     *     return [
     *         'expireDate' => Yii::$app->formatter->asDate($fields['expire']),
     *     ];
     * }
     * ```
     */
    public $read_callback;
    /**
     * @var callable|null a callback that will be called during session data writing.
     * The signature of the callback should be as follows:
     *
     * ```
     * function ($session)
     * ```
     *
     * where `$session` is this session instance, this variable can be used to retrieve session data.
     * Callback should return the actual fields set, which should be saved into the session storage.
     *
     * For example:
     *
     * ```
     * function ($session) {
     *     return [
     *         'user_id' => Yii::$app->user->id,
     *         'ip' => $_SERVER['REMOTE_ADDR'],
     *         'is_trusted' => $session->get('is_trusted', false),
     *     ];
     * }
     * ```
     */
    public $write_callback;
    /**
     * Returns a value indicating whether to use custom session storage.
     * This method overrides the parent implementation and always returns true.
     * @return bool whether to use custom storage.
     */
    public function get_use_custom_storage()
    {
        return true;
    }
    /**
     * Composes storage field set for session writing.
     * @param string|null $id Optional session id
     * @param string|null $data Optional session data
     * @return array storage fields
     */
    protected function compose_fields($id = null, $data = null)
    {
        $fields = $this->write_callback ? call_user_func($this->write_callback, $this) : [];
        if ($id !== null) {
            $fields['id'] = $id;
        }
        if ($data !== null) {
            $fields['data'] = $data;
        }
        return $fields;
    }
    /**
     * Extracts session data from storage field set.
     * @param array $fields storage fields.
     * @return string session data.
     */
    protected function extract_data(array $fields)
    {
        if ($this->read_callback !== null) {
            if (!isset($fields['data'])) {
                $fields['data'] = '';
            }
            $extra_data = call_user_func($this->read_callback, $fields);
            if (!empty($extra_data)) {
                session_decode($fields['data']);
                $_SESSION = array_merge($_SESSION, (array) $extra_data);
                return session_encode();
            }
            return $fields['data'];
        }
        return $fields['data'] ?? '';
    }
}