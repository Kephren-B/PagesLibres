<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Entity\Avis;
use App\Entity\Utilisateur;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * Création d'un avis (F7) : l'auteur (utilisateur) est fixé depuis
 * l'utilisateur authentifié, jamais depuis l'entrée client — cohérent
 * avec MouvementProcessor. La note est validée par les Assert de l'entité
 * (NotNull + Range 1-5) en amont de l'écriture.
 *
 * Un avis par utilisateur par livre (UNIQUE (id_livre, id_utilisateur) en
 * base). Un #[UniqueEntity] au niveau de l'entité ne suffirait pas ici :
 * la validation Assert tourne avant ce processor (ValidateProcessor
 * décore le processor, cf. pile d'appel), donc "utilisateur" est encore
 * null au moment de la validation — Doctrine ne verrait jamais le
 * doublon. On laisse la contrainte UNIQUE de la base faire le travail et
 * on convertit sa violation en 422 propre, plutôt qu'un 500 avec la trace
 * SQL exposée au client.
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

        try {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        } catch (UniqueConstraintViolationException) {
            throw new ValidationException(new ConstraintViolationList([
                new ConstraintViolation(
                    'Vous avez déjà publié un avis pour ce livre.',
                    null,
                    [],
                    null,
                    'livre',
                    null
                ),
            ]));
        }
    }
}
