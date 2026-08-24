<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Signalement;
use App\Entity\Utilisateur;
use App\Enum\StatutSignalement;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Back-office modération (F10) : traiter ou rejeter un signalement.
 * Un seul processor pour les deux routes (/traiter et /rejeter),
 * distinguées via l'URI template de l'opération courante — évite de
 * dupliquer la logique commune (traitant + date_traitement).
 *
 * @implements ProcessorInterface<Signalement, Signalement>
 */
final class SignalementTraiterProcessor implements ProcessorInterface
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

        $estRejet = str_ends_with((string) $operation->getUriTemplate(), '/rejeter');
        $data->setStatut($estRejet ? StatutSignalement::Rejete : StatutSignalement::Traite);
        $data->setDateTraitement(new \DateTimeImmutable());

        $admin = $this->security->getUser();
        if ($admin instanceof Utilisateur) {
            $data->setUtilisateurTraitant($admin);
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
