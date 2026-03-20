<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\captcha;

use Yii;
use yii\base\Invalid_Config_Exception;
use yii\helpers\Json;
use yii\validators\Validation_Asset;
use yii\validators\Validator;
use yii\web\Controller;
/**
 * CaptchaValidator validates that the attribute value is the same as the verification code displayed in the CAPTCHA.
 *
 * CaptchaValidator should be used together with [[CaptchaAction]].
 *
 * Note that once CAPTCHA validation succeeds, a new CAPTCHA will be generated automatically. As a result,
 * CAPTCHA validation should not be used in AJAX validation mode because it may fail the validation
 * even if a user enters the same code as shown in the CAPTCHA image which is actually different from the latest CAPTCHA code.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Captcha_Validator extends Validator
{
    /**
     * @var bool whether to skip this validator if the input is empty.
     */
    public $skip_on_empty = false;
    /**
     * @var bool whether the comparison is case sensitive. Defaults to false.
     */
    public $case_sensitive = false;
    /**
     * @var string the route of the controller action that renders the CAPTCHA image.
     */
    public $captcha_action = 'site/captcha';
    /**
     * {@inheritdoc}
     */
    public function init(): void
    {
        parent::init();
        if ($this->message === null) {
            $this->message = Yii::t('yii', 'The verification code is incorrect.');
        }
    }
    /**
     * {@inheritdoc}
     */
    protected function validate_value($value): ?array
    {
        $captcha = $this->create_captcha_action();
        $valid = !is_array($value) && $captcha->validate($value, $this->case_sensitive);
        return $valid ? null : [$this->message, []];
    }
    /**
     * Creates the CAPTCHA action object from the route specified by [[captchaAction]].
     * @return CaptchaAction the action object
     * @throws InvalidConfigException
     */
    public function create_captcha_action()
    {
        $ca = Yii::$app->create_controller($this->captcha_action);
        if ($ca !== false) {
            /** @var Controller $controller */
            [$controller, $action_id] = $ca;
            /** @var CaptchaAction|null $action */
            $action = $controller->create_action($action_id);
            if ($action !== null) {
                return $action;
            }
        }
        throw new Invalid_Config_Exception('Invalid CAPTCHA action ID: ' . $this->captcha_action);
    }
    /**
     * {@inheritdoc}
     */
    public function client_validate_attribute($model, $attribute, $view): string
    {
        Validation_Asset::register($view);
        $options = $this->get_client_options($model, $attribute);
        return 'yii.validation.captcha(value, messages, ' . Json::html_encode($options) . ');';
    }
    /**
     * {@inheritdoc}
     */
    public function get_client_options($model, $attribute): array
    {
        $captcha = $this->create_captcha_action();
        $code = $captcha->get_verify_code(false);
        $hash = $captcha->generate_validation_hash($this->case_sensitive ? $code : strtolower($code));
        $options = ['hash' => $hash, 'hashKey' => 'yiiCaptcha/' . $captcha->get_unique_id(), 'caseSensitive' => $this->case_sensitive, 'message' => Yii::$app->get_i18n()->format($this->message, ['attribute' => $model->get_attribute_label($attribute)], Yii::$app->language)];
        if ($this->skip_on_empty) {
            $options['skipOnEmpty'] = 1;
        }
        return $options;
    }
}