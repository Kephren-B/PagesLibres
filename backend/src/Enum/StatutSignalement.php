<?php

declare(strict_types=1);

namespace App\Enum;

enum StatutSignalement: string
{
    case EnAttente = 'en_attente';
    case Traite = 'traite';
    case Rejete = 'rejete';
}
