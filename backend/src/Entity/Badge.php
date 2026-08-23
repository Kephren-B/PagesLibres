<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'badge')]
class Badge
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_badge', type: Types::INTEGER)]
    private ?int $idBadge = null;

    #[ORM\Column(name: 'nom', type: Types::STRING, length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private string $nom;

    #[ORM\Column(name: 'description', type: Types::STRING, length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $description;

    #[ORM\Column(name: 'icone', type: Types::STRING, length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $icone = null;

    #[ORM\Column(name: 'critere_type', type: Types::STRING, length: 50)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 50)]
    private string $critereType;

    #[ORM\Column(name: 'critere_valeur', type: Types::INTEGER)]
    #[Assert\NotNull]
    private int $critereValeur;

    public function getIdBadge(): ?int
    {
        return $this->idBadge;
    }

    public function getNom(): string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getIcone(): ?string
    {
        return $this->icone;
    }

    public function setIcone(?string $icone): static
    {
        $this->icone = $icone;

        return $this;
    }

    public function getCritereType(): string
    {
        return $this->critereType;
    }

    public function setCritereType(string $critereType): static
    {
        $this->critereType = $critereType;

        return $this;
    }

    public function getCritereValeur(): int
    {
        return $this->critereValeur;
    }

    public function setCritereValeur(int $critereValeur): static
    {
        $this->critereValeur = $critereValeur;

        return $this;
    }
}
