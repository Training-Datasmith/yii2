<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\i18n;

use Yii;
use yii\base\Component;
use yii\base\Invalid_Config_Exception;
/**
 * Locale provides various locale information via convenient methods.
 *
 * The class requires [PHP intl extension](https://www.php.net/manual/en/book.intl.php) to be installed.
 *
 * @property-read string $currencySymbol
 *
 * @since 2.0.14
 */
class Locale extends Component
{
    /**
     * @var string|null the locale ID.
     * If not set, [[\yii\base\Application::language]] will be used.
     */
    public $locale;
    /**
     * {@inheritdoc}
     */
    public function init(): void
    {
        if (!extension_loaded('intl')) {
            throw new Invalid_Config_Exception('Locale component requires PHP intl extension to be installed.');
        }
        if ($this->locale === null) {
            $this->locale = Yii::$app->language;
        }
    }
    /**
     * Returns a currency symbol
     *
     * @param string|null $currencyCode the 3-letter ISO 4217 currency code to get symbol for. If null,
     * method will attempt using currency code from [[locale]].
     */
    public function get_currency_symbol($currency_code = null): string
    {
        $locale = $this->locale;
        if ($currency_code !== null) {
            $locale .= '@currency=' . $currency_code;
        }
        $formatter = new \Number_Formatter($locale, \Number_Formatter::CURRENCY);
        return $formatter->get_symbol(\Number_Formatter::CURRENCY_SYMBOL);
    }
}