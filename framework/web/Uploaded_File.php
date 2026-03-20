<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\web;

use Yii;
use yii\base\Base_Object;
use yii\helpers\Array_Helper;
use yii\helpers\Html;
/**
 * UploadedFile represents the information for an uploaded file.
 *
 * You can call [[getInstance()]] to retrieve the instance of an uploaded file,
 * and then use [[saveAs()]] to save it on the server.
 * You may also query other information about the file, including [[name]],
 * [[tempName]], [[type]], [[size]], [[error]] and [[fullPath]].
 *
 * For more details and usage information on UploadedFile, see the [guide article on handling uploads](guide:input-file-upload).
 *
 * @property-read string $baseName Original file base name.
 * @property-read string $extension File extension.
 * @property-read bool $hasError Whether there is an error with the uploaded file. Check [[error]] for
 * detailed error code information.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Uploaded_File extends Base_Object
{
    /**
     * @var string the original name of the file being uploaded
     */
    public $name;
    /**
     * @var string the path of the uploaded file on the server.
     * Note, this is a temporary file which will be automatically deleted by PHP
     * after the current request is processed.
     */
    public $temp_name;
    /**
     * @var string the MIME-type of the uploaded file (such as "image/gif").
     * Since this MIME type is not checked on the server-side, do not take this value for granted.
     * Instead, use [[\yii\helpers\FileHelper::getMimeType()]] to determine the exact MIME type.
     */
    public $type;
    /**
     * @var int the actual size of the uploaded file in bytes
     */
    public $size;
    /**
     * @var int an error code describing the status of this file uploading.
     * @see https://www.php.net/manual/en/features.file-upload.errors.php
     */
    public $error;
    /**
     * @var string|null The full path as submitted by the browser. Note this value does not always
     * contain a real directory structure, and cannot be trusted. Available as of PHP 8.1.
     * @since 2.0.46
     */
    public $full_path;
    /**
     * @var resource|null a temporary uploaded stream resource used within PUT and PATCH request.
     */
    private $_temp_resource;
    /**
     * @var array[]|null
     */
    private static ?array $_files = null;
    /**
     * UploadedFile constructor.
     *
     * @param array $config name-value pairs that will be used to initialize the object properties
     */
    public function __construct($config = [])
    {
        $this->_temp_resource = Array_Helper::remove($config, 'tempResource');
        parent::__construct($config);
    }
    /**
     * String output.
     * This is PHP magic method that returns string representation of an object.
     * The implementation here returns the uploaded file's name.
     * @return string the string representation of the object
     */
    public function __toString(): string
    {
        return $this->name;
    }
    /**
     * Returns an uploaded file for the given model attribute.
     * The file should be uploaded using [[\yii\widgets\ActiveField::fileInput()]].
     * @param \yii\base\Model $model the data model
     * @param string $attribute the attribute name. The attribute name may contain array indexes.
     * For example, '[1]file' for tabular file uploading; and 'file[1]' for an element in a file array.
     * @return UploadedFile|null the instance of the uploaded file.
     * Null is returned if no file is uploaded for the specified model attribute.
     * @see getInstanceByName()
     */
    public static function get_instance($model, $attribute)
    {
        $name = Html::get_input_name($model, $attribute);
        return static::get_instance_by_name($name);
    }
    /**
     * Returns all uploaded files for the given model attribute.
     * @param \yii\base\Model $model the data model
     * @param string $attribute the attribute name. The attribute name may contain array indexes
     * for tabular file uploading, e.g. '[1]file'.
     * @return UploadedFile[] array of UploadedFile objects.
     * Empty array is returned if no available file was found for the given attribute.
     */
    public static function get_instances($model, $attribute)
    {
        $name = Html::get_input_name($model, $attribute);
        return static::get_instances_by_name($name);
    }
    /**
     * Returns an uploaded file according to the given file input name.
     * The name can be a plain string or a string like an array element (e.g. 'Post[imageFile]', or 'Post[0][imageFile]').
     * @param string $name the name of the file input field.
     * @return UploadedFile|null the instance of the uploaded file.
     * Null is returned if no file is uploaded for the specified name.
     */
    public static function get_instance_by_name($name): ?self
    {
        $files = self::load_files();
        return isset($files[$name]) ? new static($files[$name]) : null;
    }
    /**
     * Returns an array of uploaded files corresponding to the specified file input name.
     * This is mainly used when multiple files were uploaded and saved as 'files[0]', 'files[1]',
     * 'files[n]'..., and you can retrieve them all by passing 'files' as the name.
     * @param string $name the name of the array of files
     * @return UploadedFile[] the array of UploadedFile objects. Empty array is returned
     * if no adequate upload was found. Please note that this array will contain
     * all files from all sub-arrays regardless how deeply nested they are.
     */
    public static function get_instances_by_name($name): array
    {
        $files = self::load_files();
        if (isset($files[$name])) {
            return [new static($files[$name])];
        }
        $results = [];
        foreach ($files as $key => $file) {
            if (strpos($key, "{$name}[") === 0) {
                $results[] = new static($file);
            }
        }
        return $results;
    }
    /**
     * Cleans up the loaded UploadedFile instances.
     * This method is mainly used by test scripts to set up a fixture.
     */
    public static function reset(): void
    {
        self::$_files = null;
    }
    /**
     * Saves the uploaded file.
     * If the target file `$file` already exists, it will be overwritten.
     * @param string $file the file path or a path alias used to save the uploaded file.
     * @param bool $deleteTempFile whether to delete the temporary file after saving.
     * If true, you will not be able to save the uploaded file again in the current request.
     * @return bool true whether the file is saved successfully
     * @see error
     */
    public function save_as(string $file, $delete_temp_file = true)
    {
        if ($this->has_error) {
            return false;
        }
        $target_file = Yii::get_alias($file);
        if (is_resource($this->_temp_resource)) {
            $result = $this->copy_temp_file($target_file);
            return $delete_temp_file ? @fclose($this->_temp_resource) : (bool) $result;
        }
        return $delete_temp_file ? move_uploaded_file($this->temp_name, $target_file) : copy($this->temp_name, $target_file);
    }
    /**
     * Copy temporary file into file specified
     *
     * @param string $targetFile path of the file to copy to
     * @return int|false the total count of bytes copied, or false on failure
     * @since 2.0.32
     */
    protected function copy_temp_file($target_file)
    {
        $target = fopen($target_file, 'wb');
        if ($target === false) {
            return false;
        }
        $result = stream_copy_to_stream($this->_temp_resource, $target);
        @fclose($target);
        return $result;
    }
    /**
     * @return string original file base name
     */
    public function get_base_name(): string
    {
        // https://github.com/yiisoft/yii2/issues/11012
        $path_info = pathinfo('_' . $this->name, PATHINFO_FILENAME);
        return mb_substr($path_info, 1, mb_strlen($path_info, '8bit'), '8bit');
    }
    /**
     * @return string file extension
     */
    public function get_extension(): string
    {
        return strtolower(pathinfo($this->name, PATHINFO_EXTENSION));
    }
    /**
     * @return bool whether there is an error with the uploaded file.
     * Check [[error]] for detailed error code information.
     */
    public function get_has_error(): bool
    {
        return $this->error != UPLOAD_ERR_OK;
    }
    /**
     * Returns reformated data of uplodaded files.
     *
     * @return array[]
     */
    private static function load_files()
    {
        if (self::$_files === null) {
            self::$_files = [];
            if (is_array($_FILES)) {
                foreach ($_FILES as $key => $info) {
                    self::load_files_recursive($key, $info['name'], $info['tmp_name'], $info['type'], $info['size'], $info['error'], $info['full_path'] ?? [], $info['tmp_resource'] ?? []);
                }
            }
        }
        return self::$_files;
    }
    /**
     * Recursive reformats data of uplodaded file(s).
     *
     * @param string $key key for identifying uploaded file(sub-array index)
     * @param string[]|string $names file name(s) provided by PHP
     * @param string[]|string $tempNames temporary file name(s) provided by PHP
     * @param string[]|string $types file type(s) provided by PHP
     * @param int[]|int $sizes file size(s) provided by PHP
     * @param int[]|int $errors uploading issue(s) provided by PHP
     * @param array|string|null $fullPaths the full path(s) as submitted by the browser/PHP
     * @param array|resource|null $tempResources the resource(s)
     */
    private static function load_files_recursive(string $key, $names, $temp_names, $types, $sizes, $errors, $full_paths, $temp_resources): void
    {
        if (is_array($names)) {
            foreach ($names as $i => $name) {
                self::load_files_recursive($key . '[' . $i . ']', $name, $temp_names[$i], $types[$i], $sizes[$i], $errors[$i], $full_paths[$i] ?? null, $temp_resources[$i] ?? null);
            }
            return;
        }
        /** @var int $errors */
        if ($errors != UPLOAD_ERR_NO_FILE) {
            self::$_files[$key] = ['name' => $names, 'tempName' => $temp_names, 'tempResource' => is_resource($temp_resources) ? $temp_resources : null, 'type' => $types, 'size' => $sizes, 'error' => $errors, 'fullPath' => is_string($full_paths) ? $full_paths : null];
        }
    }
}