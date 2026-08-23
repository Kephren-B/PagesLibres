<?php

declare(strict_types=1);

namespace App\Enum;

enum StatutExemplaire: string
{
    case EnCirculation = 'en_circulation';
    case Trouve = 'trouve';
    case Signale = 'signale';
    case Retire = 'retire';
}
