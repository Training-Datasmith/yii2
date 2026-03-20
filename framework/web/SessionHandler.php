<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\web;

use Session_Handler_Interface;
/**
 * SessionHandler implements an [[\SessionHandlerInterface]] for handling [[Session]] with custom session storage.
 *
 * @author Viktor Khokhryakov <viktor.khokhryakov@gmail.com>
 * @since 2.0.52
 */
class Session_Handler implements Session_Handler_Interface
{
    private \yii\web\Session $_session;
    public function __construct(Session $session)
    {
        $this->_session = $session;
    }
    /**
     * @inheritDoc
     */
    public function close(): bool
    {
        return $this->_session->close_session();
    }
    /**
     * @inheritDoc
     */
    public function destroy($id): bool
    {
        return $this->_session->destroy_session($id);
    }
    /**
     * @inheritDoc
     */
    #[\Return_Type_Will_Change]
    public function gc($max_lifetime)
    {
        return $this->_session->gc_session($max_lifetime);
    }
    /**
     * @inheritDoc
     */
    public function open($path, $name): bool
    {
        return $this->_session->open_session($path, $name);
    }
    /**
     * @inheritDoc
     */
    #[\Return_Type_Will_Change]
    public function read($id)
    {
        return $this->_session->read_session($id);
    }
    /**
     * @inheritDoc
     */
    public function write($id, $data): bool
    {
        return $this->_session->write_session($id, $data);
    }
}