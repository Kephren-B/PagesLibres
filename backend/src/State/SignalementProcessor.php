<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Signalement;
use App\Entity\Utilisateur;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Création d'un signalement (F10) : le signaleur est fixé depuis
 * l'utilisateur authentifié, jamais depuis l'entrée client. L'exclusivité
 * de cible (une des 4) est portée par ExactlyOneTarget sur l'entité.
 *
 * @implements ProcessorInterface<Signalement, Signalement>
 */
final class SignalementProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private readonly ProcessorInterface $persistProcessor,
        private readonly Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof Signalement) {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        $user = $this->security->getUser();
        if ($user instanceof Utilisateur) {
            $data->setUtilisateurSignaleur($user);
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
