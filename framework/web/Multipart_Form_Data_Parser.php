<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\web;

use yii\base\Base_Object;
use yii\helpers\Array_Helper;
use yii\helpers\String_Helper;
/**
 * MultipartFormDataParser parses content encoded as 'multipart/form-data'.
 * This parser provides the fallback for the 'multipart/form-data' processing on non POST requests,
 * for example: the one with 'PUT' request method.
 *
 * In order to enable this parser you should configure [[Request::parsers]] in the following way:
 *
 * ```
 * return [
 *     'components' => [
 *         'request' => [
 *             'parsers' => [
 *                 'multipart/form-data' => 'yii\web\MultipartFormDataParser'
 *             ],
 *         ],
 *         // ...
 *     ],
 *     // ...
 * ];
 * ```
 *
 * Method [[parse()]] of this parser automatically populates `$_FILES` with the files parsed from raw body.
 *
 * > Note: since this is a request parser, it will initialize `$_FILES` values on [[Request::getBodyParams()]].
 * Until this method is invoked, `$_FILES` array will remain empty even if there are submitted files in the
 * request body. Make sure you have requested body params before any attempt to get uploaded file in case
 * you are using this parser.
 *
 * Usage example:
 *
 * ```
 * use yii\web\UploadedFile;
 *
 * $restRequestData = Yii::$app->request->getBodyParams();
 * $uploadedFile = UploadedFile::getInstancesByName('photo');
 *
 * $model = new Item();
 * $model->populate($restRequestData);
 * copy($uploadedFile->tempName, '/path/to/file/storage/photo.jpg');
 * ```
 *
 * > Note: although this parser fully emulates regular structure of the `$_FILES`, related temporary
 * files, which are available via `tmp_name` key, will not be recognized by PHP as uploaded ones.
 * Thus functions like `is_uploaded_file()` and `move_uploaded_file()` will fail on them.
 *
 * @property int $uploadFileMaxCount Maximum upload files count.
 * @property int $uploadFileMaxSize Upload file max size in bytes.
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 2.0.10
 */
class Multipart_Form_Data_Parser extends Base_Object implements Request_Parser_Interface
{
    /**
     * @var bool whether to parse raw body even for 'POST' request and `$_FILES` already populated.
     * By default this option is disabled saving performance for 'POST' requests, which are already
     * processed by PHP automatically.
     * > Note: if this option is enabled, value of `$_FILES` will be reset on each parse.
     * @since 2.0.13
     */
    public $force = false;
    /**
     * @var int upload file max size in bytes.
     */
    private $_upload_file_max_size;
    /**
     * @var int maximum upload files count.
     */
    private $_upload_file_max_count;
    /**
     * @return int upload file max size in bytes.
     */
    public function get_upload_file_max_size()
    {
        if ($this->_upload_file_max_size === null) {
            $this->_upload_file_max_size = $this->get_byte_size(ini_get('upload_max_filesize'));
        }
        return $this->_upload_file_max_size;
    }
    /**
     * @param int $uploadFileMaxSize upload file max size in bytes.
     */
    public function set_upload_file_max_size($upload_file_max_size): void
    {
        $this->_upload_file_max_size = $upload_file_max_size;
    }
    /**
     * @return int maximum upload files count.
     */
    public function get_upload_file_max_count()
    {
        if ($this->_upload_file_max_count === null) {
            $this->_upload_file_max_count = (int) ini_get('max_file_uploads');
        }
        return $this->_upload_file_max_count;
    }
    /**
     * @param int $uploadFileMaxCount maximum upload files count.
     */
    public function set_upload_file_max_count($upload_file_max_count): void
    {
        $this->_upload_file_max_count = $upload_file_max_count;
    }
    /**
     * {@inheritdoc}
     * @return mixed[]
     */
    public function parse($raw_body, $content_type): array
    {
        if (!$this->force) {
            if (!empty($_POST) || !empty($_FILES)) {
                // normal POST request is parsed by PHP automatically
                return $_POST;
            }
        } else {
            $_FILES = [];
        }
        if (empty($raw_body)) {
            return [];
        }
        if (!preg_match('/boundary="?(.*)"?$/is', $content_type, $matches)) {
            return [];
        }
        $boundary = trim($matches[1], '"');
        $body_parts = preg_split('/\R?-+' . preg_quote($boundary, '/') . '/s', $raw_body);
        array_pop($body_parts);
        // last block always has no data, contains boundary ending like `--`
        $body_params = [];
        $files_count = 0;
        foreach ($body_parts as $body_part) {
            if (empty($body_part)) {
                continue;
            }
            [$headers, $value] = preg_split('/\R\R/', $body_part, 2);
            $headers = $this->parse_headers($headers);
            if (!isset($headers['content-disposition']['name'])) {
                continue;
            }
            if (isset($headers['content-disposition']['filename'])) {
                // file upload:
                if ($files_count >= $this->get_upload_file_max_count()) {
                    continue;
                }
                $file_info = ['name' => $headers['content-disposition']['filename'], 'type' => Array_Helper::get_value($headers, 'content-type', 'application/octet-stream'), 'size' => String_Helper::byte_length($value), 'error' => UPLOAD_ERR_OK, 'tmp_name' => null];
                if ($file_info['size'] > $this->get_upload_file_max_size()) {
                    $file_info['error'] = UPLOAD_ERR_INI_SIZE;
                } else {
                    $tmp_resource = tmpfile();
                    if ($tmp_resource === false) {
                        $file_info['error'] = UPLOAD_ERR_CANT_WRITE;
                    } else {
                        $tmp_resource_meta_data = stream_get_meta_data($tmp_resource);
                        $tmp_file_name = $tmp_resource_meta_data['uri'];
                        if (empty($tmp_file_name)) {
                            $file_info['error'] = UPLOAD_ERR_CANT_WRITE;
                            @fclose($tmp_resource);
                        } else {
                            fwrite($tmp_resource, $value);
                            rewind($tmp_resource);
                            $file_info['tmp_name'] = $tmp_file_name;
                            $file_info['tmp_resource'] = $tmp_resource;
                            // save file resource, otherwise it will be deleted
                        }
                    }
                }
                $this->add_file($_FILES, $headers['content-disposition']['name'], $file_info);
                $files_count++;
            } else {
                // regular parameter:
                $this->add_value($body_params, $headers['content-disposition']['name'], $value);
            }
        }
        return $body_params;
    }
    /**
     * Parses content part headers.
     * @param string $headerContent headers source content
     * @return array parsed headers.
     */
    private function parse_headers($header_content): array
    {
        $headers = [];
        $header_parts = preg_split('/\R/su', $header_content, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($header_parts as $header_part) {
            if (strpos($header_part, ':') === false) {
                continue;
            }
            [$header_name, $header_value] = explode(':', $header_part, 2);
            $header_name = strtolower(trim($header_name));
            $header_value = trim($header_value);
            if (strpos($header_value, ';') === false) {
                $headers[$header_name] = $header_value;
            } else {
                $headers[$header_name] = [];
                foreach (explode(';', $header_value) as $part) {
                    $part = trim($part);
                    if (strpos($part, '=') === false) {
                        $headers[$header_name][] = $part;
                    } else {
                        [$name, $value] = explode('=', $part, 2);
                        $name = strtolower(trim($name));
                        $value = trim(trim($value), '"');
                        $headers[$header_name][$name] = $value;
                    }
                }
            }
        }
        return $headers;
    }
    /**
     * Adds value to the array by input name, e.g. `Item[name]`.
     * @param array $array array which should store value.
     * @param string $name input name specification.
     * @param mixed $value value to be added.
     */
    private function add_value(array &$array, $name, $value): void
    {
        $name_parts = preg_split('/\]\[|\[/s', $name);
        $current =& $array;
        foreach ($name_parts as $name_part) {
            $name_part = trim($name_part, ']');
            if ($name_part === '') {
                $current[] = [];
                $keys = array_keys($current);
                $last_key = array_pop($keys);
                $current =& $current[$last_key];
            } else {
                if (!isset($current[$name_part])) {
                    $current[$name_part] = [];
                }
                $current =& $current[$name_part];
            }
        }
        $current = $value;
    }
    /**
     * Adds file info to the uploaded files array by input name, e.g. `Item[file]`.
     * @param array $files array containing uploaded files
     * @param string $name input name specification.
     * @param array $info file info.
     */
    private function add_file(array &$files, $name, array $info): void
    {
        if (strpos($name, '[') === false) {
            $files[$name] = $info;
            return;
        }
        $file_info_attributes = ['name', 'type', 'size', 'error', 'tmp_name', 'tmp_resource'];
        $name_parts = preg_split('/\]\[|\[/s', $name);
        $base_name = array_shift($name_parts);
        if (!isset($files[$base_name])) {
            $files[$base_name] = [];
            foreach ($file_info_attributes as $attribute) {
                $files[$base_name][$attribute] = [];
            }
        } else {
            foreach ($file_info_attributes as $attribute) {
                $files[$base_name][$attribute] = (array) $files[$base_name][$attribute];
            }
        }
        foreach ($file_info_attributes as $attribute) {
            if (!isset($info[$attribute])) {
                continue;
            }
            $current =& $files[$base_name][$attribute];
            foreach ($name_parts as $name_part) {
                $name_part = trim($name_part, ']');
                if ($name_part === '') {
                    $current[] = [];
                    $keys = array_keys($current);
                    $last_key = array_pop($keys);
                    $current =& $current[$last_key];
                } else {
                    if (!isset($current[$name_part])) {
                        $current[$name_part] = [];
                    }
                    $current =& $current[$name_part];
                }
            }
            $current = $info[$attribute];
        }
    }
    /**
     * Gets the size in bytes from verbose size representation.
     *
     * For example: '5K' => 5*1024.
     * @param string $verboseSize verbose size representation.
     * @return int actual size in bytes.
     */
    private function get_byte_size($verbose_size)
    {
        if (empty($verbose_size)) {
            return 0;
        }
        if (is_numeric($verbose_size)) {
            return (int) $verbose_size;
        }
        $size_unit = trim($verbose_size, '0123456789');
        $size = trim(str_replace($size_unit, '', $verbose_size));
        if (!is_numeric($size)) {
            return 0;
        }
        switch (strtolower($size_unit)) {
            case 'kb':
            case 'k':
                return $size * 1024;
            case 'mb':
            case 'm':
                return $size * 1024 * 1024;
            case 'gb':
            case 'g':
                return $size * 1024 * 1024 * 1024;
            default:
                return 0;
        }
    }
}