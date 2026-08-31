<?php

namespace App\Shared\Enums;

enum WarmUpWeightMode: string
{
    case Percent = 'percent';
    case Bar = 'bar';
    case Fixed = 'fixed';
}
