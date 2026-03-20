<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\captcha;

use Yii;
use yii\base\Action;
use yii\base\Invalid_Config_Exception;
use yii\helpers\Url;
use yii\web\Controller;
use yii\web\Response;
/**
 * CaptchaAction renders a CAPTCHA image.
 *
 * CaptchaAction is used together with [[Captcha]] and [[\yii\captcha\CaptchaValidator]]
 * to provide the [CAPTCHA](https://en.wikipedia.org/wiki/CAPTCHA) feature.
 *
 * By configuring the properties of CaptchaAction, you may customize the appearance of
 * the generated CAPTCHA images, such as the font color, the background color, etc.
 *
 * Note that CaptchaAction requires either GD2 extension or ImageMagick PHP extension.
 *
 * Using CAPTCHA involves the following steps:
 *
 * 1. Override [[\yii\web\Controller::actions()]] and register an action of class CaptchaAction with ID 'captcha'
 * 2. In the form model, declare an attribute to store user-entered verification code, and declare the attribute
 *    to be validated by the 'captcha' validator.
 * 3. In the controller view, insert a [[Captcha]] widget in the form.
 *
 * @property-read string $verifyCode The verification code.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 *
 * @template T of Controller = Controller
 * @extends Action<T>
 */
class Captcha_Action extends Action
{
    /**
     * The name of the GET parameter indicating whether the CAPTCHA image should be regenerated.
     */
    public const REFRESH_GET_VAR = 'refresh';
    /**
     * @var int how many times should the same CAPTCHA be displayed. Defaults to 3.
     * A value less than or equal to 0 means the test is unlimited (available since version 1.1.2).
     */
    public $test_limit = 3;
    /**
     * @var int the width of the generated CAPTCHA image. Defaults to 120.
     */
    public $width = 120;
    /**
     * @var int the height of the generated CAPTCHA image. Defaults to 50.
     */
    public $height = 50;
    /**
     * @var int padding around the text. Defaults to 2.
     */
    public $padding = 2;
    /**
     * @var int the background color. For example, 0x55FF00.
     * Defaults to 0xFFFFFF, meaning white color.
     */
    public $back_color = 0xffffff;
    /**
     * @var int the font color. For example, 0x55FF00. Defaults to 0x2040A0 (blue color).
     */
    public $fore_color = 0x2040a0;
    /**
     * @var bool whether to use transparent background. Defaults to false.
     */
    public $transparent = false;
    /**
     * @var int the minimum length for randomly generated word. Defaults to 6.
     */
    public $min_length = 6;
    /**
     * @var int the maximum length for randomly generated word. Defaults to 7.
     */
    public $max_length = 7;
    /**
     * @var int the offset between characters. Defaults to -2. You can adjust this property
     * in order to decrease or increase the readability of the captcha.
     */
    public $offset = -2;
    /**
     * @var string the TrueType font file. This can be either a file path or [path alias](guide:concept-aliases).
     */
    public $font_file = '@yii/captcha/SpicyRice.ttf';
    /**
     * @var string|null the fixed verification code. When this property is set,
     * [[getVerifyCode()]] will always return the value of this property.
     * This is mainly used in automated tests where we want to be able to reproduce
     * the same verification code each time we run the tests.
     * If not set, it means the verification code will be randomly generated.
     */
    public $fixed_verify_code;
    /**
     * @var string|null the rendering library to use. Currently supported only 'gd' and 'imagick'.
     * If not set, library will be determined automatically.
     * @since 2.0.7
     */
    public $image_library;
    /**
     * Initializes the action.
     * @throws InvalidConfigException if the font file does not exist.
     */
    public function init(): void
    {
        $this->font_file = Yii::get_alias($this->font_file);
        if (!is_file($this->font_file)) {
            throw new Invalid_Config_Exception("The font file does not exist: {$this->font_file}");
        }
    }
    /**
     * Runs the action.
     */
    public function run()
    {
        if (Yii::$app->request->get_query_param(self::REFRESH_GET_VAR) !== null) {
            // AJAX request for regenerating code
            $code = $this->get_verify_code(true);
            Yii::$app->response->format = Response::FORMAT_JSON;
            return [
                'hash1' => $this->generate_validation_hash($code),
                'hash2' => $this->generate_validation_hash(strtolower($code)),
                // we add a random 'v' parameter so that FireFox can refresh the image
                // when src attribute of image tag is changed
                'url' => Url::to([$this->id, 'v' => uniqid('', true)]),
            ];
        }
        $this->set_http_headers();
        Yii::$app->response->format = Response::FORMAT_RAW;
        return $this->render_image($this->get_verify_code());
    }
    /**
     * Generates a hash code that can be used for client-side validation.
     * @param string $code the CAPTCHA code
     * @return string a hash code generated from the CAPTCHA code
     */
    public function generate_validation_hash($code): int
    {
        for ($h = 0, $i = strlen($code) - 1; $i >= 0; --$i) {
            $h += ord($code[$i]) << $i;
        }
        return $h;
    }
    /**
     * Gets the verification code.
     * @param bool $regenerate whether the verification code should be regenerated.
     * @return string the verification code.
     */
    public function get_verify_code($regenerate = false)
    {
        if ($this->fixed_verify_code !== null) {
            return $this->fixed_verify_code;
        }
        $session = Yii::$app->get_session();
        $session->open();
        $name = $this->get_session_key();
        if ($session[$name] === null || $regenerate) {
            $session[$name] = $this->generate_verify_code();
            $session[$name . 'count'] = 1;
        }
        return $session[$name];
    }
    /**
     * Validates the input to see if it matches the generated code.
     * @param string $input user input
     * @param bool $caseSensitive whether the comparison should be case-sensitive
     * @return bool whether the input is valid
     */
    public function validate($input, $case_sensitive)
    {
        $code = $this->get_verify_code();
        $valid = $case_sensitive ? $input === $code : strcasecmp($input, $code) === 0;
        $session = Yii::$app->get_session();
        $session->open();
        $name = $this->get_session_key() . 'count';
        $session[$name] += 1;
        if ($valid || $session[$name] > $this->test_limit && $this->test_limit > 0) {
            $this->get_verify_code(true);
        }
        return $valid;
    }
    /**
     * Generates a new verification code.
     * @return string the generated verification code
     */
    protected function generate_verify_code(): string
    {
        if ($this->min_length > $this->max_length) {
            $this->max_length = $this->min_length;
        }
        if ($this->min_length < 3) {
            $this->min_length = 3;
        }
        if ($this->max_length > 20) {
            $this->max_length = 20;
        }
        $length = random_int($this->min_length, $this->max_length);
        $letters = 'bcdfghjklmnpqrstvwxyz';
        $vowels = 'aeiou';
        $code = '';
        for ($i = 0; $i < $length; ++$i) {
            if ($i % 2 && random_int(0, 10) > 2 || !($i % 2) && random_int(0, 10) > 9) {
                $code .= $vowels[random_int(0, 4)];
            } else {
                $code .= $letters[random_int(0, 20)];
            }
        }
        return $code;
    }
    /**
     * Returns the session variable name used to store verification code.
     * @return string the session variable name
     */
    protected function get_session_key(): string
    {
        return '__captcha/' . $this->get_unique_id();
    }
    /**
     * Renders the CAPTCHA image.
     * @param string $code the verification code
     * @return string image contents
     * @throws InvalidConfigException if imageLibrary is not supported
     */
    protected function render_image($code)
    {
        if (isset($this->image_library)) {
            $image_library = $this->image_library;
        } else {
            $image_library = Captcha::check_requirements();
        }
        if ($image_library === 'gd') {
            return $this->render_image_by_gd($code);
        }
        if ($image_library === 'imagick') {
            return $this->render_image_by_imagick($code);
        }
        throw new Invalid_Config_Exception("Defined library '{$image_library}' is not supported");
    }
    /**
     * Renders the CAPTCHA image based on the code using GD library.
     * @param string $code the verification code
     * @return string image contents in PNG format.
     */
    protected function render_image_by_gd($code)
    {
        $image = imagecreatetruecolor($this->width, $this->height);
        $back_color = imagecolorallocate($image, (int) ($this->back_color % 0x1000000 / 0x10000), (int) ($this->back_color % 0x10000 / 0x100), $this->back_color % 0x100);
        imagefilledrectangle($image, 0, 0, $this->width - 1, $this->height - 1, $back_color);
        imagecolordeallocate($image, $back_color);
        if ($this->transparent) {
            imagecolortransparent($image, $back_color);
        }
        $fore_color = imagecolorallocate($image, (int) ($this->fore_color % 0x1000000 / 0x10000), (int) ($this->fore_color % 0x10000 / 0x100), $this->fore_color % 0x100);
        $length = strlen($code);
        $box = imagettfbbox(30, 0, $this->font_file, $code);
        $w = $box[4] - $box[0] + $this->offset * ($length - 1);
        $h = $box[1] - $box[5];
        $scale = min(($this->width - $this->padding * 2) / $w, ($this->height - $this->padding * 2) / $h);
        $x = 10;
        $y = round($this->height * 27 / 40);
        for ($i = 0; $i < $length; ++$i) {
            $font_size = (int) (random_int(26, 32) * $scale * 0.8);
            $angle = random_int(-10, 10);
            $letter = $code[$i];
            $box = imagettftext($image, $font_size, $angle, $x, $y, $fore_color, $this->font_file, $letter);
            $x = $box[2] + $this->offset;
        }
        imagecolordeallocate($image, $fore_color);
        ob_start();
        imagepng($image);
        // Function `imagedestroy` is deprecated since PHP `8.5`, as it has no effect since PHP `8.0`
        if (PHP_VERSION_ID < 80000) {
            imagedestroy($image);
        }
        return ob_get_clean();
    }
    /**
     * Renders the CAPTCHA image based on the code using ImageMagick library.
     * @param string $code the verification code
     * @return string image contents in PNG format.
     */
    protected function render_image_by_imagick($code): string
    {
        $back_color = $this->transparent ? new \Imagick_Pixel('transparent') : new \Imagick_Pixel('#' . str_pad(dechex($this->back_color), 6, 0, STR_PAD_LEFT));
        $fore_color = new \Imagick_Pixel('#' . str_pad(dechex($this->fore_color), 6, 0, STR_PAD_LEFT));
        $image = new \Imagick();
        $image->new_image($this->width, $this->height, $back_color);
        $draw = new \Imagick_Draw();
        $draw->set_font($this->font_file);
        $draw->set_font_size(30);
        $font_metrics = $image->query_font_metrics($draw, $code);
        $length = strlen($code);
        $w = (int) $font_metrics['textWidth'] - 8 + $this->offset * ($length - 1);
        $h = (int) $font_metrics['textHeight'] - 8;
        $scale = min(($this->width - $this->padding * 2) / $w, ($this->height - $this->padding * 2) / $h);
        $x = 10;
        $y = round($this->height * 27 / 40);
        for ($i = 0; $i < $length; ++$i) {
            $draw = new \Imagick_Draw();
            $draw->set_font($this->font_file);
            $draw->set_font_size((int) (random_int(26, 32) * $scale * 0.8));
            $draw->set_fill_color($fore_color);
            $image->annotate_image($draw, $x, $y, random_int(-10, 10), $code[$i]);
            $font_metrics = $image->query_font_metrics($draw, $code[$i]);
            $x += (int) $font_metrics['textWidth'] + $this->offset;
        }
        $image->set_image_format('png');
        return $image->get_image_blob();
    }
    /**
     * Sets the HTTP headers needed by image response.
     */
    protected function set_http_headers()
    {
        Yii::$app->get_response()->get_headers()->set('Pragma', 'public')->set('Expires', '0')->set('Cache-Control', 'must-revalidate, post-check=0, pre-check=0')->set('Content-Transfer-Encoding', 'binary')->set('Content-type', 'image/png');
    }
}