<?php

declare(strict_types=1);

namespace App\Filter;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;

use function count;
use function is_string;
use function sprintf;
use function str_ends_with;

/**
 * Recherche textuelle insensible à la casse **et aux accents** (F8).
 *
 * `?titre=etranger`, `?titre=Étranger` et `?titre=ETRANGER` donnent le même
 * résultat : les deux côtés du LIKE passent par LOWER() puis unaccent().
 *
 * Pourquoi un filtre dédié plutôt que le SearchFilter d'API Platform : sa
 * stratégie `ipartial` ne sait neutraliser que la casse (elle enveloppe les deux
 * côtés dans LOWER()), jamais les accents. `categorie` et `isbn` restent donc sur
 * le SearchFilter — ce sont des égalités, insensibles aux accents par nature.
 *
 * Les propriétés déclarées doivent être les noms des champs DQL de l'entité : ce
 * filtre les écrit tels quels dans l'expression. La stratégie déclarée décide de
 * la comparaison — partial (défaut) pour un LIKE, exact pour une égalité :
 *
 *     ['titre' => 'partial', 'auteur' => 'partial', 'categorie' => 'exact']
 *
 * `categorie` gagne ainsi la même insensibilité aux accents que le texte, sans
 * devenir une recherche partielle : « theatre » trouve « Théâtre », mais « Ro »
 * ne trouve pas « Roman ».
 */
final class RechercheTexteFilter extends AbstractFilter
{
    public const STRATEGIE_PARTIELLE = 'partial';
    public const STRATEGIE_EXACTE = 'exact';

    protected function filterProperty(
        string $property,
        mixed $value,
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        if (!$this->isPropertyEnabled($property, $resourceClass)) {
            return;
        }

        // API Platform transmet tel quel un paramètre répété (`?titre[]=a&…`) :
        // on accepte la liste, en gardant un OR entre les valeurs.
        $valeurs = [];
        foreach ((array) $value as $valeur) {
            if (is_string($valeur) && $valeur !== '') {
                $valeurs[] = $valeur;
            }
        }

        if ($valeurs === []) {
            return;
        }

        $racine = $queryBuilder->getRootAliases()[0];
        $exacte = ($this->getProperties()[$property] ?? self::STRATEGIE_PARTIELLE) === self::STRATEGIE_EXACTE;
        $conditions = [];

        foreach ($valeurs as $valeur) {
            $parametre = $queryNameGenerator->generateParameterName($property);
            $conditions[] = sprintf(
                'unaccent(LOWER(%s.%s)) %s unaccent(LOWER(:%s))',
                $racine,
                $property,
                $exacte ? '=' : 'LIKE',
                $parametre,
            );
            $queryBuilder->setParameter($parametre, $exacte ? $valeur : '%' . $valeur . '%');
        }

        $queryBuilder->andWhere(count($conditions) === 1
            ? $conditions[0]
            : $queryBuilder->expr()->orX(...$conditions));
    }

    /** @return array<string, array<string, mixed>> */
    public function getDescription(string $resourceClass): array
    {
        $description = [];

        foreach ($this->getProperties() ?? [] as $propriete => $strategie) {
            $partielle = $strategie !== self::STRATEGIE_EXACTE;

            foreach ([$propriete, $propriete . '[]'] as $nomParametre) {
                $description[$nomParametre] = [
                    'property' => $propriete,
                    'type' => 'string',
                    'required' => false,
                    'strategy' => $partielle ? self::STRATEGIE_PARTIELLE : self::STRATEGIE_EXACTE,
                    'is_collection' => str_ends_with($nomParametre, '[]'),
                    'description' => $partielle
                        ? 'Recherche partielle, insensible à la casse et aux accents'
                        : 'Égalité insensible à la casse et aux accents',
                ];
            }
        }

        return $description;
    }
}
