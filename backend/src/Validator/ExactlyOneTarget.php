<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * Réplique côté Symfony les contraintes CHECK d'exclusivité du MPD
 * (chk_commentaire_une_cible, chk_signalement_une_cible) : parmi les
 * propriétés listées, exactement une doit être renseignée (non null).
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class ExactlyOneTarget extends Constraint
{
    public string $message = 'Exactement une cible parmi "{{ fields }}" doit être renseignée (aucune ou plusieurs détectées).';

    /**
     * @param string[] $fields
     */
    public function __construct(
        public readonly array $fields,
        ?string $message = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct([], $groups, $payload);

        if ($message !== null) {
            $this->message = $message;
        }
    }

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
