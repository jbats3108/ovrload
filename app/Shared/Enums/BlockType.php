<?php

namespace App\Shared\Enums;

enum BlockType: string
{
    case Single = 'single';
    case Superset = 'superset';
    case Circuit = 'circuit';
}
