<?php

declare(strict_types=1);

namespace yiiunit\framework\db\enums;

enum StatusTypeInt: int
{
    case Active = 1;
    case Inactive = 0;
}
