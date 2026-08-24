<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * F9 : catalogue des 5 badges (données de référence, seedées par
 * migration — cf. Version20260824150304). Lecture publique uniquement,
 * aucune écriture via l'API (pas d'opération Post/Put/Patch/Delete) :
 * le catalogue est fixe, périmètre fermé au Jalon 1.
 */
#[ORM\Entity]
#[ORM\Table(name: 'badge')]
#[ApiResource(
    operations: [new GetCollection(), new Get()],
    normalizationContext: ['groups' => ['badge:read']],
)]
class Badge
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_badge', type: Types::INTEGER)]
    #[Groups(['badge:read', 'obtention_badge:read'])]
    private ?int $idBadge = null;

    #[ORM\Column(name: 'nom', type: Types::STRING, length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    #[Groups(['badge:read', 'obtention_badge:read'])]
    private string $nom;

    #[ORM\Column(name: 'description', type: Types::STRING, length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Groups(['badge:read', 'obtention_badge:read'])]
    private string $description;

    #[ORM\Column(name: 'icone', type: Types::STRING, length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    #[Groups(['badge:read', 'obtention_badge:read'])]
    private ?string $icone = null;

    #[ORM\Column(name: 'critere_type', type: Types::STRING, length: 50)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 50)]
    #[Groups(['badge:read'])]
    private string $critereType;

    #[ORM\Column(name: 'critere_valeur', type: Types::INTEGER)]
    #[Assert\NotNull]
    #[Groups(['badge:read'])]
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
