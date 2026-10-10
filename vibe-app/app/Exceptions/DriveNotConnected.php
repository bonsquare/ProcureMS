<?php

namespace App\Exceptions;

use RuntimeException;

class DriveNotConnected extends RuntimeException
{
    public static function reconnect(): self
    {
        return new self('Your Google Drive connection has expired or was removed. Connect it again to continue.');
    }
}
