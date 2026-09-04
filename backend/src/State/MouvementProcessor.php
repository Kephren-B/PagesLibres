<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Entity\Mouvement;
use App\Entity\Utilisateur;
use App\Enum\StatutExemplaire;
use App\Enum\TypeMouvement;
use App\Gamification\BadgeAttributionService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * Création d'un Mouvement (F3 libération / F5 trouvaille, F6 journal de
 * voyage en résulte par simple lecture chronologique).
 *
 * Ne recalcule jamais exemplaire.position : c'est le rôle exclusif du
 * trigger PostgreSQL trg_maj_position_exemplaire, déclenché par l'INSERT
 * sur mouvement que fait le processor Doctrine décoré ci-dessous. En
 * revanche, la transition de statut de l'Exemplaire (en_circulation /
 * trouve) n'est couverte par aucun trigger dans le MPD : c'est une
 * décision métier applicative, posée ici et déjà documentée dans le
 * diagramme de séquence F5 du Jalon 4.
 *
 * @implements ProcessorInterface<Mouvement, Mouvement>
 */
final class MouvementProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private readonly ProcessorInterface $persistProcessor,
        private readonly Security $security,
        private readonly BadgeAttributionService $badgeAttributionService,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof Mouvement) {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        $user = $this->security->getUser();
        if ($user instanceof Utilisateur) {
            $data->setUtilisateur($user);
        }

        $exemplaire = $data->getExemplaire();

        if ($data->getTypeMouvement() === TypeMouvement::Trouvaille) {
            if ($exemplaire->getStatut() !== StatutExemplaire::EnCirculation) {
                throw $this->conflict(
                    'typeMouvement',
                    'Impossible de déclarer une trouvaille : cet exemplaire n\'est pas en circulation (statut actuel : ' . $exemplaire->getStatut()->value . ').'
                );
            }
            $exemplaire->setStatut(StatutExemplaire::Trouve);
        } elseif ($data->getTypeMouvement() === TypeMouvement::Liberation) {
            if ($exemplaire->getStatut() === StatutExemplaire::Retire || $exemplaire->getStatut() === StatutExemplaire::Signale) {
                throw $this->conflict(
                    'typeMouvement',
                    'Impossible de (re)libérer cet exemplaire : statut actuel incompatible (' . $exemplaire->getStatut()->value . ').'
                );
            }
            $exemplaire->setStatut(StatutExemplaire::EnCirculation);
        }

        $result = $this->persistProcessor->process($data, $operation, $uriVariables, $context);

        // F9 : recalcul synchrone des badges après chaque mouvement, pas de
        // job planifié (volume attendu trop faible pour le justifier).
        if ($user instanceof Utilisateur) {
            $this->badgeAttributionService->evaluerEtAttribuer($user);
        }

        return $result;
    }

    private function conflict(string $property, string $message): ValidationException
    {
        return new ValidationException(new ConstraintViolationList([
            new ConstraintViolation($message, null, [], null, $property, null),
        ]));
    }
}
