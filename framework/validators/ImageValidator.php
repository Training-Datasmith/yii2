<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\validators;

use Yii;
use yii\helpers\Json;
use yii\web\Uploaded_File;
/**
 * ImageValidator verifies if an attribute is receiving a valid image.
 *
 * @author Taras Gudz <gudz.taras@gmail.com>
 * @since 2.0
 */
class Image_Validator extends File_Validator
{
    /**
     * @var string the error message used when the uploaded file is not an image.
     * You may use the following tokens in the message:
     *
     * - {attribute}: the attribute name
     * - {file}: the uploaded file name
     */
    public $not_image;
    /**
     * @var int|null the minimum width in pixels.
     * Defaults to null, meaning no limit.
     * @see underWidth for the customized message used when image width is too small.
     */
    public $min_width;
    /**
     * @var int|null the maximum width in pixels.
     * Defaults to null, meaning no limit.
     * @see overWidth for the customized message used when image width is too big.
     */
    public $max_width;
    /**
     * @var int|null the minimum height in pixels.
     * Defaults to null, meaning no limit.
     * @see underHeight for the customized message used when image height is too small.
     */
    public $min_height;
    /**
     * @var int|null the maximum width in pixels.
     * Defaults to null, meaning no limit.
     * @see overHeight for the customized message used when image height is too big.
     */
    public $max_height;
    /**
     * @var string the error message used when the image is under [[minWidth]].
     * You may use the following tokens in the message:
     *
     * - {attribute}: the attribute name
     * - {file}: the uploaded file name
     * - {limit}: the value of [[minWidth]]
     */
    public $under_width;
    /**
     * @var string the error message used when the image is over [[maxWidth]].
     * You may use the following tokens in the message:
     *
     * - {attribute}: the attribute name
     * - {file}: the uploaded file name
     * - {limit}: the value of [[maxWidth]]
     */
    public $over_width;
    /**
     * @var string the error message used when the image is under [[minHeight]].
     * You may use the following tokens in the message:
     *
     * - {attribute}: the attribute name
     * - {file}: the uploaded file name
     * - {limit}: the value of [[minHeight]]
     */
    public $under_height;
    /**
     * @var string the error message used when the image is over [[maxHeight]].
     * You may use the following tokens in the message:
     *
     * - {attribute}: the attribute name
     * - {file}: the uploaded file name
     * - {limit}: the value of [[maxHeight]]
     */
    public $over_height;
    /**
     * {@inheritdoc}
     */
    public function init(): void
    {
        parent::init();
        if ($this->not_image === null) {
            $this->not_image = Yii::t('yii', 'The file "{file}" is not an image.');
        }
        if ($this->under_width === null) {
            $this->under_width = Yii::t('yii', 'The image "{file}" is too small. The width cannot be smaller than {limit, number} {limit, plural, one{pixel} other{pixels}}.');
        }
        if ($this->under_height === null) {
            $this->under_height = Yii::t('yii', 'The image "{file}" is too small. The height cannot be smaller than {limit, number} {limit, plural, one{pixel} other{pixels}}.');
        }
        if ($this->over_width === null) {
            $this->over_width = Yii::t('yii', 'The image "{file}" is too large. The width cannot be larger than {limit, number} {limit, plural, one{pixel} other{pixels}}.');
        }
        if ($this->over_height === null) {
            $this->over_height = Yii::t('yii', 'The image "{file}" is too large. The height cannot be larger than {limit, number} {limit, plural, one{pixel} other{pixels}}.');
        }
    }
    /**
     * {@inheritdoc}
     */
    protected function validate_value($value)
    {
        $result = parent::validate_value($value);
        return empty($result) ? $this->validate_image($value) : $result;
    }
    /**
     * Validates an image file.
     * @param UploadedFile $image uploaded file passed to check against a set of rules
     * @return array|null the error message and the parameters to be inserted into the error message.
     * Null should be returned if the data is valid.
     */
    protected function validate_image($image): ?array
    {
        if (false === $image_info = getimagesize($image->temp_name)) {
            return [$this->not_image, ['file' => $image->name]];
        }
        [$width, $height] = $image_info;
        if ($width == 0 || $height == 0) {
            return [$this->not_image, ['file' => $image->name]];
        }
        if ($this->min_width !== null && $width < $this->min_width) {
            return [$this->under_width, ['file' => $image->name, 'limit' => $this->min_width]];
        }
        if ($this->min_height !== null && $height < $this->min_height) {
            return [$this->under_height, ['file' => $image->name, 'limit' => $this->min_height]];
        }
        if ($this->max_width !== null && $width > $this->max_width) {
            return [$this->over_width, ['file' => $image->name, 'limit' => $this->max_width]];
        }
        if ($this->max_height !== null && $height > $this->max_height) {
            return [$this->over_height, ['file' => $image->name, 'limit' => $this->max_height]];
        }
        return null;
    }
    /**
     * {@inheritdoc}
     */
    public function client_validate_attribute($model, $attribute, $view): string
    {
        Validation_Asset::register($view);
        $options = $this->get_client_options($model, $attribute);
        return 'yii.validation.image(attribute, messages, ' . Json::html_encode($options) . ', deferred);';
    }
    /**
     * {@inheritdoc}
     */
    public function get_client_options($model, $attribute)
    {
        $options = parent::get_client_options($model, $attribute);
        $label = $model->get_attribute_label($attribute);
        if ($this->not_image !== null) {
            $options['notImage'] = $this->format_message($this->not_image, ['attribute' => $label]);
        }
        if ($this->min_width !== null) {
            $options['minWidth'] = $this->min_width;
            $options['underWidth'] = $this->format_message($this->under_width, ['attribute' => $label, 'limit' => $this->min_width]);
        }
        if ($this->max_width !== null) {
            $options['maxWidth'] = $this->max_width;
            $options['overWidth'] = $this->format_message($this->over_width, ['attribute' => $label, 'limit' => $this->max_width]);
        }
        if ($this->min_height !== null) {
            $options['minHeight'] = $this->min_height;
            $options['underHeight'] = $this->format_message($this->under_height, ['attribute' => $label, 'limit' => $this->min_height]);
        }
        if ($this->max_height !== null) {
            $options['maxHeight'] = $this->max_height;
            $options['overHeight'] = $this->format_message($this->over_height, ['attribute' => $label, 'limit' => $this->max_height]);
        }
        return $options;
    }
}