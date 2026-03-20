<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\validators;

use Yii;
use yii\helpers\File_Helper;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\String_Helper;
use yii\web\Js_Expression;
use yii\web\Uploaded_File;
/**
 * FileValidator verifies if an attribute is receiving a valid uploaded file.
 *
 * Note that you should enable `fileinfo` PHP extension.
 *
 * @property-read int $sizeLimit The size limit for uploaded files.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class File_Validator extends Validator
{
    /**
     * @var array|string|null a list of file name extensions that are allowed to be uploaded.
     * This can be either an array or a string consisting of file extension names
     * separated by space or comma (e.g. "gif, jpg").
     * Extension names are case-insensitive. Defaults to null, meaning all file name
     * extensions are allowed.
     * @see wrongExtension for the customized message for wrong file type.
     */
    public $extensions;
    /**
     * @var bool whether to check file type (extension) with mime-type. If extension produced by
     * file mime-type check differs from uploaded file extension, the file will be considered as invalid.
     */
    public $check_extension_by_mime_type = true;
    /**
     * @var array|string|null a list of file MIME types that are allowed to be uploaded.
     * This can be either an array or a string consisting of file MIME types
     * separated by space or comma (e.g. "text/plain, image/png").
     * The mask with the special character `*` can be used to match groups of mime types.
     * For example `image/*` will pass all mime types, that begin with `image/` (e.g. `image/jpeg`, `image/png`).
     * Mime type names are case-insensitive. Defaults to null, meaning all MIME types are allowed.
     * @see wrongMimeType for the customized message for wrong MIME type.
     */
    public $mime_types;
    /**
     * @var int|null the minimum number of bytes required for the uploaded file.
     * Defaults to null, meaning no limit.
     * @see tooSmall for the customized message for a file that is too small.
     */
    public $min_size;
    /**
     * @var int|null the maximum number of bytes required for the uploaded file.
     * Defaults to null, meaning no limit.
     * Note, the size limit is also affected by `upload_max_filesize` and `post_max_size` INI setting
     * and the 'MAX_FILE_SIZE' hidden field value. See [[getSizeLimit()]] for details.
     * @see https://www.php.net/manual/en/ini.core.php#ini.upload-max-filesize
     * @see https://www.php.net/post-max-size
     * @see getSizeLimit
     * @see tooBig for the customized message for a file that is too big.
     */
    public $max_size;
    /**
     * @var int the maximum file count the given attribute can hold.
     * Defaults to 1, meaning single file upload. By defining a higher number,
     * multiple uploads become possible. Setting it to `0` means there is no limit on
     * the number of files that can be uploaded simultaneously.
     *
     * > Note: The maximum number of files allowed to be uploaded simultaneously is
     * also limited with PHP directive `max_file_uploads`, which defaults to 20.
     *
     * @see https://www.php.net/manual/en/ini.core.php#ini.max-file-uploads
     * @see tooMany for the customized message when too many files are uploaded.
     */
    public $max_files = 1;
    /**
     * @var int the minimum file count the given attribute can hold.
     * Defaults to 0. Higher value means at least that number of files should be uploaded.
     *
     * @see tooFew for the customized message when too few files are uploaded.
     * @since 2.0.14
     */
    public $min_files = 0;
    /**
     * @var string the error message used when a file is not uploaded correctly.
     */
    public $message;
    /**
     * @var string the error message used when no file is uploaded.
     * Note that this is the text of the validation error message. To make uploading files required,
     * you have to set [[skipOnEmpty]] to `false`.
     */
    public $upload_required;
    /**
     * @var string the error message used when the uploaded file is too large.
     * You may use the following tokens in the message:
     *
     * - {attribute}: the attribute name
     * - {file}: the uploaded file name
     * - {limit}: the maximum size allowed (see [[getSizeLimit()]])
     * - {formattedLimit}: the maximum size formatted
     *   with [[\yii\i18n\Formatter::asShortSize()|Formatter::asShortSize()]]
     */
    public $too_big;
    /**
     * @var string the error message used when the uploaded file is too small.
     * You may use the following tokens in the message:
     *
     * - {attribute}: the attribute name
     * - {file}: the uploaded file name
     * - {limit}: the value of [[minSize]]
     * - {formattedLimit}: the value of [[minSize]] formatted
     *   with [[\yii\i18n\Formatter::asShortSize()|Formatter::asShortSize()]
     */
    public $too_small;
    /**
     * @var string the error message used if the count of multiple uploads exceeds limit.
     * You may use the following tokens in the message:
     *
     * - {attribute}: the attribute name
     * - {limit}: the value of [[maxFiles]]
     */
    public $too_many;
    /**
     * @var string the error message used if the count of multiple uploads less that minFiles.
     * You may use the following tokens in the message:
     *
     * - {attribute}: the attribute name
     * - {limit}: the value of [[minFiles]]
     *
     * @since 2.0.14
     */
    public $too_few;
    /**
     * @var string the error message used when the uploaded file has an extension name
     * that is not listed in [[extensions]]. You may use the following tokens in the message:
     *
     * - {attribute}: the attribute name
     * - {file}: the uploaded file name
     * - {extensions}: the list of the allowed extensions.
     */
    public $wrong_extension;
    /**
     * @var string the error message used when the file has an mime type
     * that is not allowed by [[mimeTypes]] property.
     * You may use the following tokens in the message:
     *
     * - {attribute}: the attribute name
     * - {file}: the uploaded file name
     * - {mimeTypes}: the value of [[mimeTypes]]
     */
    public $wrong_mime_type;
    /**
     * {@inheritdoc}
     */
    public function init(): void
    {
        parent::init();
        if ($this->message === null) {
            $this->message = Yii::t('yii', 'File upload failed.');
        }
        if ($this->upload_required === null) {
            $this->upload_required = Yii::t('yii', 'Please upload a file.');
        }
        if ($this->too_many === null) {
            $this->too_many = Yii::t('yii', 'You can upload at most {limit, number} {limit, plural, one{file} other{files}}.');
        }
        if ($this->too_few === null) {
            $this->too_few = Yii::t('yii', 'You should upload at least {limit, number} {limit, plural, one{file} other{files}}.');
        }
        if ($this->wrong_extension === null) {
            $this->wrong_extension = Yii::t('yii', 'Only files with these extensions are allowed: {extensions}.');
        }
        if ($this->too_big === null) {
            $this->too_big = Yii::t('yii', 'The file "{file}" is too big. Its size cannot exceed {formattedLimit}.');
        }
        if ($this->too_small === null) {
            $this->too_small = Yii::t('yii', 'The file "{file}" is too small. Its size cannot be smaller than {formattedLimit}.');
        }
        if (!is_array($this->extensions)) {
            $this->extensions = preg_split('/[\s,]+/', strtolower((string) $this->extensions), -1, PREG_SPLIT_NO_EMPTY);
        } else {
            $this->extensions = array_map('strtolower', $this->extensions);
        }
        if ($this->wrong_mime_type === null) {
            $this->wrong_mime_type = Yii::t('yii', 'Only files with these MIME types are allowed: {mimeTypes}.');
        }
        if (!is_array($this->mime_types)) {
            $this->mime_types = preg_split('/[\s,]+/', strtolower((string) $this->mime_types), -1, PREG_SPLIT_NO_EMPTY);
        } else {
            $this->mime_types = array_map('strtolower', $this->mime_types);
        }
    }
    /**
     * {@inheritdoc}
     */
    public function validate_attribute($model, $attribute): void
    {
        $files = $this->filter_files(is_array($model->{$attribute}) ? $model->{$attribute} : [$model->{$attribute}]);
        $files_count = count($files);
        if ($files_count === 0) {
            $this->add_error($model, $attribute, $this->upload_required);
            return;
        }
        if ($this->max_files > 0 && $files_count > $this->max_files) {
            $this->add_error($model, $attribute, $this->too_many, ['limit' => $this->max_files]);
        }
        if ($this->min_files > 0 && $this->min_files > $files_count) {
            $this->add_error($model, $attribute, $this->too_few, ['limit' => $this->min_files]);
        }
        foreach ($files as $file) {
            $result = $this->validate_value($file);
            if (!empty($result)) {
                $this->add_error($model, $attribute, $result[0], $result[1]);
            }
        }
    }
    /**
     * Files filter.
     * @return UploadedFile[]
     */
    private function filter_files(array $files): array
    {
        $result = [];
        foreach ($files as $file_name => $file) {
            if ($file instanceof Uploaded_File && $file->error !== UPLOAD_ERR_NO_FILE) {
                $result[$file_name] = $file;
            }
        }
        return $result;
    }
    /**
     * {@inheritdoc}
     */
    protected function validate_value($value): ?array
    {
        if (!$value instanceof Uploaded_File || $value->error == UPLOAD_ERR_NO_FILE) {
            return [$this->upload_required, []];
        }
        switch ($value->error) {
            case UPLOAD_ERR_OK:
                if ($this->max_size !== null && $value->size > $this->get_size_limit()) {
                    return [$this->too_big, ['file' => $value->name, 'limit' => $this->get_size_limit(), 'formattedLimit' => Yii::$app->formatter->as_short_size($this->get_size_limit())]];
                }
                if ($this->min_size !== null && $value->size < $this->min_size) {
                    return [$this->too_small, ['file' => $value->name, 'limit' => $this->min_size, 'formattedLimit' => Yii::$app->formatter->as_short_size($this->min_size)]];
                }
                if (!empty($this->extensions) && !$this->validate_extension($value)) {
                    return [$this->wrong_extension, ['file' => $value->name, 'extensions' => implode(', ', $this->extensions)]];
                }
                if (!empty($this->mime_types) && !$this->validate_mime_type($value)) {
                    return [$this->wrong_mime_type, ['file' => $value->name, 'mimeTypes' => implode(', ', $this->mime_types)]];
                }
                return null;
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return [$this->too_big, ['file' => $value->name, 'limit' => $this->get_size_limit(), 'formattedLimit' => Yii::$app->formatter->as_short_size($this->get_size_limit())]];
            case UPLOAD_ERR_PARTIAL:
                Yii::warning('File was only partially uploaded: ' . $value->name, __METHOD__);
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                Yii::warning('Missing the temporary folder to store the uploaded file: ' . $value->name, __METHOD__);
                break;
            case UPLOAD_ERR_CANT_WRITE:
                Yii::warning('Failed to write the uploaded file to disk: ' . $value->name, __METHOD__);
                break;
            case UPLOAD_ERR_EXTENSION:
                Yii::warning('File upload was stopped by some PHP extension: ' . $value->name, __METHOD__);
                break;
            default:
                break;
        }
        return [$this->message, []];
    }
    /**
     * Returns the maximum size allowed for uploaded files.
     *
     * This is determined based on four factors:
     *
     * - 'upload_max_filesize' in php.ini
     * - 'post_max_size' in php.ini
     * - 'MAX_FILE_SIZE' hidden field
     * - [[maxSize]]
     *
     * @return int the size limit for uploaded files.
     */
    public function get_size_limit()
    {
        // Get the lowest between post_max_size and upload_max_filesize, log a warning if the first is < than the latter
        $limit = String_Helper::convert_ini_size_to_bytes(ini_get('upload_max_filesize'));
        $post_limit = String_Helper::convert_ini_size_to_bytes(ini_get('post_max_size'));
        if ($post_limit > 0 && $post_limit < $limit) {
            Yii::warning('PHP.ini\'s \'post_max_size\' is less than \'upload_max_filesize\'.', __METHOD__);
            $limit = $post_limit;
        }
        if ($this->max_size !== null && $limit > 0 && $this->max_size < $limit) {
            $limit = $this->max_size;
        }
        if (isset($_POST['MAX_FILE_SIZE']) && $_POST['MAX_FILE_SIZE'] > 0 && $_POST['MAX_FILE_SIZE'] < $limit) {
            return (int) $_POST['MAX_FILE_SIZE'];
        }
        return $limit;
    }
    /**
     * {@inheritdoc}
     * @param bool $trim
     */
    public function is_empty($value, $trim = false): bool
    {
        $value = is_array($value) ? reset($value) : $value;
        return !$value instanceof Uploaded_File || $value->error == UPLOAD_ERR_NO_FILE;
    }
    /**
     * Checks if given uploaded file have correct type (extension) according current validator settings.
     * @param UploadedFile $file
     */
    protected function validate_extension($file): bool
    {
        $extension = mb_strtolower($file->extension, 'UTF-8');
        if ($this->check_extension_by_mime_type) {
            $mime_type = File_Helper::get_mime_type($file->temp_name, null, false);
            if ($mime_type === null) {
                return false;
            }
            $extensions_by_mime_type = File_Helper::get_extensions_by_mime_type($mime_type);
            if (!in_array($extension, $extensions_by_mime_type, true)) {
                return false;
            }
        }
        if (!empty($this->extensions)) {
            foreach ((array) $this->extensions as $ext) {
                if ($extension === $ext || String_Helper::ends_with($file->name, ".{$ext}", false)) {
                    return true;
                }
            }
            return false;
        }
        return true;
    }
    /**
     * {@inheritdoc}
     */
    public function client_validate_attribute($model, $attribute, $view): string
    {
        Validation_Asset::register($view);
        $options = $this->get_client_options($model, $attribute);
        return 'yii.validation.file(attribute, messages, ' . Json::html_encode($options) . ');';
    }
    /**
     * {@inheritdoc}
     * @return mixed[]
     */
    public function get_client_options($model, $attribute): array
    {
        $label = $model->get_attribute_label($attribute);
        $options = [];
        if ($this->message !== null) {
            $options['message'] = $this->format_message($this->message, ['attribute' => $label]);
        }
        $options['skipOnEmpty'] = $this->skip_on_empty;
        if (!$this->skip_on_empty) {
            $options['uploadRequired'] = $this->format_message($this->upload_required, ['attribute' => $label]);
        }
        if ($this->mime_types !== null) {
            $mime_types = [];
            foreach ($this->mime_types as $mime_type) {
                $mime_types[] = new Js_Expression(Html::escape_js_regular_expression($this->build_mime_type_regexp($mime_type)));
            }
            $options['mimeTypes'] = $mime_types;
            $options['wrongMimeType'] = $this->format_message($this->wrong_mime_type, ['attribute' => $label, 'mimeTypes' => implode(', ', $this->mime_types)]);
        }
        if ($this->extensions !== null) {
            $options['extensions'] = $this->extensions;
            $options['wrongExtension'] = $this->format_message($this->wrong_extension, ['attribute' => $label, 'extensions' => implode(', ', $this->extensions)]);
        }
        if ($this->min_size !== null) {
            $options['minSize'] = $this->min_size;
            $options['tooSmall'] = $this->format_message($this->too_small, ['attribute' => $label, 'limit' => $this->min_size, 'formattedLimit' => Yii::$app->formatter->as_short_size($this->min_size)]);
        }
        if ($this->max_size !== null) {
            $options['maxSize'] = $this->max_size;
            $options['tooBig'] = $this->format_message($this->too_big, ['attribute' => $label, 'limit' => $this->get_size_limit(), 'formattedLimit' => Yii::$app->formatter->as_short_size($this->get_size_limit())]);
        }
        if ($this->max_files !== null) {
            $options['maxFiles'] = $this->max_files;
            $options['tooMany'] = $this->format_message($this->too_many, ['attribute' => $label, 'limit' => $this->max_files]);
        }
        return $options;
    }
    /**
     * Builds the RegExp from the $mask.
     *
     * @param string $mask
     * @return string the regular expression
     * @see mimeTypes
     */
    private function build_mime_type_regexp($mask): string
    {
        return '/^' . str_replace('\*', '.*', preg_quote($mask, '/')) . '$/i';
    }
    /**
     * Checks the mimeType of the $file against the list in the [[mimeTypes]] property.
     *
     * @param UploadedFile $file
     * @return bool whether the $file mimeType is allowed
     * @throws \yii\base\InvalidConfigException
     * @see mimeTypes
     * @since 2.0.8
     */
    protected function validate_mime_type($file): bool
    {
        $file_mime_type = $this->get_mime_type_by_file($file->temp_name);
        if ($file_mime_type === null) {
            return false;
        }
        foreach ($this->mime_types as $mime_type) {
            if (strcasecmp($mime_type, $file_mime_type) === 0) {
                return true;
            }
            if (strpos($mime_type, '*') !== false && preg_match($this->build_mime_type_regexp($mime_type), $file_mime_type)) {
                return true;
            }
        }
        return false;
    }
    /**
     * Get MIME type by file path
     *
     * @param string $filePath
     * @return string|null
     * @throws \yii\base\InvalidConfigException
     * @since 2.0.26
     */
    protected function get_mime_type_by_file($file_path)
    {
        return File_Helper::get_mime_type($file_path);
    }
}