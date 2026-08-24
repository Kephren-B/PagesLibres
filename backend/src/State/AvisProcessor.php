<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Avis;
use App\Entity\Utilisateur;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Création d'un avis (F7) : l'auteur (utilisateur) est fixé depuis
 * l'utilisateur authentifié, jamais depuis l'entrée client — cohérent
 * avec MouvementProcessor. La note est validée par les Assert de l'entité
 * (NotNull + Range 1-5) en amont de l'écriture.
 *
 * @implements ProcessorInterface<Avis, Avis>
 */
final class AvisProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private readonly ProcessorInterface $persistProcessor,
        private readonly Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof Avis) {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        $user = $this->security->getUser();
        if ($user instanceof Utilisateur) {
            $data->setUtilisateur($user);
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
