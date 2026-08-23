<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'obtention_badge')]
#[ORM\UniqueConstraint(name: 'obtention_badge_id_utilisateur_id_badge_key', columns: ['id_utilisateur', 'id_badge'])]
class ObtentionBadge
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_obtention', type: Types::INTEGER)]
    private ?int $idObtention = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(name: 'id_utilisateur', referencedColumnName: 'id_utilisateur', nullable: false, onDelete: 'CASCADE')]
    private Utilisateur $utilisateur;

    #[ORM\ManyToOne(targetEntity: Badge::class)]
    #[ORM\JoinColumn(name: 'id_badge', referencedColumnName: 'id_badge', nullable: false, onDelete: 'CASCADE')]
    private Badge $badge;

    #[ORM\Column(name: 'date_obtention', type: Types::DATETIME_IMMUTABLE)]
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
