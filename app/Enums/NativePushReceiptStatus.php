<?php

namespace App\Enums;

enum NativePushReceiptStatus: string
{
    case Pending = 'pending';

    case Ok = 'ok';

    case Error = 'error';

    case RetryPending = 'retry_pending';

    case Unavailable = 'unavailable';
}
