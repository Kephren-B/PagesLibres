<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Utilisateur;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * F9 : GET /api/moi — profil de l'utilisateur authentifié courant.
 * L'accès à l'email (ApiProperty::security sur Utilisateur::$email)
 * fonctionne ici normalement puisque "object === user".
 *
 * @implements ProviderInterface<Utilisateur>
 */
final class MoiProvider implements ProviderInterface
{
    public function __construct(private readonly Security $security)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Utilisateur
    {
        $user = $this->security->getUser();
        if (!$user instanceof Utilisateur) {
            throw new AccessDeniedHttpException();
        }

        return $user;
    }
}
