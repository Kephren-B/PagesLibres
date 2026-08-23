<?php

declare(strict_types=1);

namespace App\Enum;

enum RoleUtilisateur: string
{
    case Membre = 'membre';
    case Admin = 'admin';
}
