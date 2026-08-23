<?php

declare(strict_types=1);

namespace App\Enum;

enum TypeMouvement: string
{
    case Liberation = 'liberation';
    case Trouvaille = 'trouvaille';
}
