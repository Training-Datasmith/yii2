<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\rbac;

use yii\base\Base_Object;
/**
 * Assignment represents an assignment of a role to a user.
 *
 * For more details and usage information on Assignment, see the [guide article on security authorization](guide:security-authorization).
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @author Alexander Kochetov <creocoder@gmail.com>
 * @since 2.0
 */
class Assignment extends Base_Object
{
    /**
     * @var string|int user ID (see [[\yii\web\User::id]])
     */
    public $user_id;
    /**
     * @var string the role name
     */
    public $role_name;
    /**
     * @var int UNIX timestamp representing the assignment creation time
     */
    public $created_at;
}