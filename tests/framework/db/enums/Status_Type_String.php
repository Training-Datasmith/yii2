<?php

declare(strict_types=1);

namespace yiiunit\framework\db\enums;

enum StatusTypeString: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
