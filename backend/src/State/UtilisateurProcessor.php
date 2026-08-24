<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Utilisateur;
use App\Enum\RoleUtilisateur;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Inscription (F1) : hash du mot de passe via UserPasswordHasherInterface
 * (jamais de hash maison) et rôle "membre" forcé — le client ne peut pas
 * s'auto-promouvoir admin, "role" n'est de toute façon pas dans le groupe
 * de sérialisation en écriture, mais on le fixe explicitement ici par
 * défense en profondeur.
 *
 * @implements ProcessorInterface<Utilisateur, Utilisateur>
 */
final class UtilisateurProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private readonly ProcessorInterface $persistProcessor,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof Utilisateur && $data->getPlainPassword() !== null) {
            $data->setMotDePasseHash(
                $this->passwordHasher->hashPassword($data, $data->getPlainPassword())
            );
            $data->setRole(RoleUtilisateur::Membre);
            $data->setPlainPassword(null);
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
