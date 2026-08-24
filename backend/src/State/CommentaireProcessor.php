<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Commentaire;
use App\Entity\Utilisateur;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Création d'un commentaire (F7) : l'auteur est fixé depuis l'utilisateur
 * authentifié. L'exclusivité de cible (avis OU livre, jamais les deux) est
 * portée par le validateur ExactlyOneTarget déclaré sur l'entité — la même
 * règle que la contrainte CHECK chk_commentaire_une_cible en base (règle
 * non négociable, cf. Jalon 4).
 *
 * @implements ProcessorInterface<Commentaire, Commentaire>
 */
final class CommentaireProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private readonly ProcessorInterface $persistProcessor,
        private readonly Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof Commentaire) {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        $user = $this->security->getUser();
        if ($user instanceof Utilisateur) {
            $data->setUtilisateur($user);
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
