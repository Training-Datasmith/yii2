<?php

declare(strict_types=1);

/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */

namespace yiiunit\framework\db\oci;

/**
 * @group db
 * @group oci
 * @group validators
 */
class ExistValidatorTest extends \yiiunit\framework\validators\ExistValidatorTest
{
    public $driverName = 'oci';
}
