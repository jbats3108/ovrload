<?php

namespace App\Routines\Exceptions;

use Exception;

class RoutineStaleException extends Exception
{
    public const string MESSAGE = 'This routine was changed elsewhere. Reload and try again.';

    public function __construct()
    {
        parent::__construct(self::MESSAGE);
    }
}
