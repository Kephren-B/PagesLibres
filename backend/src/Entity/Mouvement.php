<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\TypeMouvement;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'mouvement')]
#[ORM\Index(name: 'idx_mouvement_exemplaire', columns: ['id_exemplaire', 'date_mouvement'])]
class Mouvement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_mouvement', type: Types::INTEGER)]
    private ?int $idMouvement = null;

    #[ORM\ManyToOne(targetEntity: Exemplaire::class, inversedBy: 'mouvements')]
    #[ORM\JoinColumn(name: 'id_exemplaire', referencedColumnName: 'id_exemplaire', nullable: false, onDelete: 'CASCADE')]
    private Exemplaire $exemplaire;

    /**
     * Nullable + ON DELETE SET NULL : la suppression d'un compte anonymise
     * ses mouvements plutôt que de les effacer (règle RGPD, Jalon 1).
     * Ne jamais changer cette FK en CASCADE.
     */
    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(name: 'id_utilisateur', referencedColumnName: 'id_utilisateur', nullable: true, onDelete: 'SET NULL')]
    private ?Utilisateur $utilisateur = null;

    #[ORM\Column(name: 'type_mouvement', type: Types::STRING, enumType: TypeMouvement::class)]
    private TypeMouvement $typeMouvement;

    #[ORM\Column(name: 'latitude', type: Types::DECIMAL, precision: 9, scale: 6)]
    #[Assert\NotNull]
    #[Assert\Range(min: -90, max: 90)]
    private string $latitude;

    #[ORM\Column(name: 'longitude', type: Types::DECIMAL, precision: 9, scale: 6)]
    #[Assert\NotNull]
    #[Assert\Range(min: -180, max: 180)]
    private string $longitude;

    #[ORM\Column(name: 'message', type: Types::TEXT, nullable: true)]
    private ?string $message = null;

    #[ORM\Column(name: 'date_mouvement', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $dateMouvement;

    public function __construct()
    {
        $this->dateMouvement = new \DateTimeImmutable();
    }

    public function getIdMouvement(): ?int
    {
        return $this->idMouvement;
    }

    public function getExemplaire(): Exemplaire
    {
        return $this->exemplaire;
    }

    public function setExemplaire(Exemplaire $exemplaire): static
    {
        $this->exemplaire = $exemplaire;

        return $this;
    }

    public function getUtilisateur(): ?Utilisateur
    {
        return $this->utilisateur;
    }

    public function setUtilisateur(?Utilisateur $utilisateur): static
    {
        $this->utilisateur = $utilisateur;

        return $this;
    }

    public function getTypeMouvement(): TypeMouvement
    {
        return $this->typeMouvement;
    }

    public function setTypeMouvement(TypeMouvement $typeMouvement): static
    {
        $this->typeMouvement = $typeMouvement;

        return $this;
    }

    public function getLatitude(): string
    {
        return $this->latitude;
    }

    public function setLatitude(string $latitude): static
    {
        $this->latitude = $latitude;

        return $this;
    }

    public function getLongitude(): string
    {
        return $this->longitude;
    }

    public function setLongitude(string $longitude): static
    {
        $this->longitude = $longitude;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function getDateMouvement(): \DateTimeImmutable
    {
        return $this->dateMouvement;
    }
}
