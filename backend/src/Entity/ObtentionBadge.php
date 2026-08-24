<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * F9 : lecture seule (attribution exclusivement via
 * App\Gamification\BadgeAttributionService, jamais par écriture API).
 * Filtre sur "utilisateur" pour permettre au front de charger "mes
 * badges" via ?utilisateur=/api/utilisateurs/{id}.
 */
#[ORM\Entity]
#[ORM\Table(name: 'obtention_badge')]
#[ORM\UniqueConstraint(name: 'obtention_badge_id_utilisateur_id_badge_key', columns: ['id_utilisateur', 'id_badge'])]
#[ApiResource(
    operations: [new GetCollection(security: "is_granted('ROLE_USER')")],
    normalizationContext: ['groups' => ['obtention_badge:read']],
)]
#[ApiFilter(SearchFilter::class, properties: ['utilisateur' => 'exact'])]
class ObtentionBadge
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_obtention', type: Types::INTEGER)]
    #[Groups(['obtention_badge:read'])]
    private ?int $idObtention = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(name: 'id_utilisateur', referencedColumnName: 'id_utilisateur', nullable: false, onDelete: 'CASCADE')]
    #[Groups(['obtention_badge:read'])]
    private Utilisateur $utilisateur;

    #[ORM\ManyToOne(targetEntity: Badge::class)]
    #[ORM\JoinColumn(name: 'id_badge', referencedColumnName: 'id_badge', nullable: false, onDelete: 'CASCADE')]
    #[Groups(['obtention_badge:read'])]
    private Badge $badge;

    #[ORM\Column(name: 'date_obtention', type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['obtention_badge:read'])]
    private \DateTimeImmutable $dateObtention;

    public function __construct()
    {
        $this->dateObtention = new \DateTimeImmutable();
    }

    public function getIdObtention(): ?int
    {
        return $this->idObtention;
    }

    public function getUtilisateur(): Utilisateur
    {
        return $this->utilisateur;
    }

    public function setUtilisateur(Utilisateur $utilisateur): static
    {
        $this->utilisateur = $utilisateur;

        return $this;
    }

    public function getBadge(): Badge
    {
        return $this->badge;
    }

    public function setBadge(Badge $badge): static
    {
        $this->badge = $badge;

        return $this;
    }

    public function getDateObtention(): \DateTimeImmutable
    {
        return $this->dateObtention;
    }
}
