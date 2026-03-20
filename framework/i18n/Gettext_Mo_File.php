<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\i18n;

use yii\base\Exception;
/**
 * GettextMoFile represents an MO Gettext message file.
 *
 * This class is written by adapting Michael's Gettext_MO class in PEAR.
 * Please refer to the following license terms.
 *
 * Copyright (c) 2004-2005, Michael Wallner <mike@iworks.at>.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 *     * Redistributions of source code must retain the above copyright notice,
 *       this list of conditions and the following disclaimer.
 *     * Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE
 * FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
 * DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
 * SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY,
 * OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Gettext_Mo_File extends Gettext_File
{
    /**
     * @var bool whether to use big-endian when reading and writing an integer.
     */
    public $use_big_endian = false;
    /**
     * Loads messages from an MO file.
     * @param string $filePath file path
     * @param string $context message context
     * @return array message translations. Array keys are source messages and array values are translated messages:
     * source message => translated message.
     * @throws Exception if unable to read the MO file
     */
    public function load($file_path, $context): array
    {
        if (false === $file_handle = @fopen($file_path, 'rb')) {
            throw new Exception('Unable to read file "' . $file_path . '".');
        }
        if (false === @flock($file_handle, LOCK_SH)) {
            throw new Exception('Unable to lock file "' . $file_path . '" for reading.');
        }
        // magic
        $array = unpack('c', $this->read_bytes($file_handle, 4));
        $magic = current($array);
        if ($magic == -34) {
            $this->use_big_endian = false;
        } elseif ($magic == -107) {
            $this->use_big_endian = true;
        } else {
            throw new Exception('Invalid MO file: ' . $file_path . ' (magic: ' . $magic . ').');
        }
        // revision
        $revision = $this->read_integer($file_handle);
        if ($revision !== 0) {
            throw new Exception('Invalid MO file revision: ' . $revision . '.');
        }
        $count = $this->read_integer($file_handle);
        $source_offset = $this->read_integer($file_handle);
        $target_offset = $this->read_integer($file_handle);
        $source_lengths = [];
        $source_offsets = [];
        fseek($file_handle, $source_offset);
        for ($i = 0; $i < $count; ++$i) {
            $source_lengths[] = $this->read_integer($file_handle);
            $source_offsets[] = $this->read_integer($file_handle);
        }
        $target_lengths = [];
        $target_offsets = [];
        fseek($file_handle, $target_offset);
        for ($i = 0; $i < $count; ++$i) {
            $target_lengths[] = $this->read_integer($file_handle);
            $target_offsets[] = $this->read_integer($file_handle);
        }
        $messages = [];
        for ($i = 0; $i < $count; ++$i) {
            $id = $this->read_string($file_handle, $source_lengths[$i], $source_offsets[$i]);
            $separator_position = strpos((string) $id, chr(4));
            if (!$context && $separator_position === false || $context && $separator_position !== false && strncmp($id, $context, $separator_position) === 0) {
                if ($separator_position !== false) {
                    $id = substr($id, $separator_position + 1);
                }
                $message = $this->read_string($file_handle, $target_lengths[$i], $target_offsets[$i]);
                $messages[$id] = $message;
            }
        }
        @flock($file_handle, LOCK_UN);
        @fclose($file_handle);
        return $messages;
    }
    /**
     * Saves messages to an MO file.
     * @param string $filePath file path
     * @param array $messages message translations. Array keys are source messages and array values are
     * translated messages: source message => translated message. Note if the message has a context,
     * the message ID must be prefixed with the context with chr(4) as the separator.
     * @throws Exception if unable to save the MO file
     */
    public function save($file_path, $messages): void
    {
        if (false === $file_handle = @fopen($file_path, 'wb')) {
            throw new Exception('Unable to write file "' . $file_path . '".');
        }
        if (false === @flock($file_handle, LOCK_EX)) {
            throw new Exception('Unable to lock file "' . $file_path . '" for reading.');
        }
        // magic
        if ($this->use_big_endian) {
            $this->write_bytes($file_handle, pack('c*', 0x95, 0x4, 0x12, 0xde));
            // -107
        } else {
            $this->write_bytes($file_handle, pack('c*', 0xde, 0x12, 0x4, 0x95));
            // -34
        }
        // revision
        $this->write_integer($file_handle, 0);
        // message count
        $message_count = count($messages);
        $this->write_integer($file_handle, $message_count);
        // offset of source message table
        $offset = 28;
        $this->write_integer($file_handle, $offset);
        $offset += $message_count * 8;
        $this->write_integer($file_handle, $offset);
        // hashtable size, omitted
        $this->write_integer($file_handle, 0);
        $offset += $message_count * 8;
        $this->write_integer($file_handle, $offset);
        // length and offsets for source messages
        foreach (array_keys($messages) as $id) {
            $length = strlen($id);
            $this->write_integer($file_handle, $length);
            $this->write_integer($file_handle, $offset);
            $offset += $length + 1;
        }
        // length and offsets for target messages
        foreach ($messages as $message) {
            $length = strlen($message);
            $this->write_integer($file_handle, $length);
            $this->write_integer($file_handle, $offset);
            $offset += $length + 1;
        }
        // source messages
        foreach (array_keys($messages) as $id) {
            $this->write_string($file_handle, $id);
        }
        // target messages
        foreach ($messages as $message) {
            $this->write_string($file_handle, $message);
        }
        @flock($file_handle, LOCK_UN);
        @fclose($file_handle);
    }
    /**
     * Reads one or several bytes.
     * @param resource $fileHandle to read from
     * @param int $byteCount to be read
     * @return string|null bytes
     */
    protected function read_bytes($file_handle, $byte_count = 1)
    {
        if ($byte_count > 0) {
            return fread($file_handle, $byte_count);
        }
        return null;
    }
    /**
     * Write bytes.
     * @param resource $fileHandle to write to
     * @param string $bytes to be written
     * @return int how many bytes are written
     */
    protected function write_bytes($file_handle, $bytes)
    {
        return fwrite($file_handle, $bytes);
    }
    /**
     * Reads a 4-byte integer.
     * @param resource $fileHandle to read from
     * @return int the result
     */
    protected function read_integer($file_handle)
    {
        $array = unpack($this->use_big_endian ? 'N' : 'V', $this->read_bytes($file_handle, 4));
        return current($array);
    }
    /**
     * Writes a 4-byte integer.
     * @param resource $fileHandle to write to
     * @param int $integer to be written
     * @return int how many bytes are written
     */
    protected function write_integer($file_handle, $integer)
    {
        return $this->write_bytes($file_handle, pack($this->use_big_endian ? 'N' : 'V', (int) $integer));
    }
    /**
     * Reads a string.
     * @param resource $fileHandle file handle
     * @param int $length of the string
     * @param int|null $offset of the string in the file. If null, it reads from the current position.
     * @return string|null the result
     */
    protected function read_string($file_handle, $length, $offset = null)
    {
        if ($offset !== null) {
            fseek($file_handle, $offset);
        }
        return $this->read_bytes($file_handle, $length);
    }
    /**
     * Writes a string.
     * @param resource $fileHandle to write to
     * @param string $string to be written
     * @return int how many bytes are written
     */
    protected function write_string($file_handle, string $string)
    {
        return $this->write_bytes($file_handle, $string . "\x00");
    }
}