<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'livre')]
#[ORM\Index(name: 'idx_livre_titre', columns: ['titre'])]
#[ORM\Index(name: 'idx_livre_auteur', columns: ['auteur'])]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(security: "is_granted('ROLE_USER')"),
    ],
    normalizationContext: ['groups' => ['livre:read']],
    denormalizationContext: ['groups' => ['livre:write']],
)]
#[ApiFilter(SearchFilter::class, properties: ['titre' => 'partial', 'auteur' => 'partial', 'categorie' => 'exact', 'isbn' => 'exact'])]
class Livre
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_livre', type: Types::INTEGER)]
    #[Groups(['livre:read'])]
    private ?int $idLivre = null;

    #[ORM\Column(name: 'isbn', type: Types::STRING, length: 20, unique: true, nullable: true)]
    #[Assert\Length(max: 20)]
    #[Groups(['livre:read', 'livre:write'])]
    private ?string $isbn = null;

    #[ORM\Column(name: 'titre', type: Types::STRING, length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Groups(['livre:read', 'livre:write'])]
    private string $titre;

    #[ORM\Column(name: 'auteur', type: Types::STRING, length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Groups(['livre:read', 'livre:write'])]
    private string $auteur;

    #[ORM\Column(name: 'annee_publication', type: Types::SMALLINT, nullable: true)]
    #[Groups(['livre:read', 'livre:write'])]
    private ?int $anneePublication = null;

    #[ORM\Column(name: 'categorie', type: Types::STRING, length: 50)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 50)]
    #[Groups(['livre:read', 'livre:write'])]
    private string $categorie;

    #[ORM\Column(name: 'resume', type: Types::TEXT, nullable: true)]
    #[Groups(['livre:read', 'livre:write'])]
    private ?string $resume = null;

    #[ORM\Column(name: 'couverture_url', type: Types::STRING, length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    #[Groups(['livre:read', 'livre:write'])]
    private ?string $couvertureUrl = null;

    /** @var Collection<int, Exemplaire> */
    #[ORM\OneToMany(targetEntity: Exemplaire::class, mappedBy: 'livre')]
    #[Groups(['livre:read'])]
    private Collection $exemplaires;

    /** @var Collection<int, Avis> */
    #[ORM\OneToMany(targetEntity: Avis::class, mappedBy: 'livre')]
    private Collection $avis;

    public function __construct()
    {
        $this->exemplaires = new ArrayCollection();
        $this->avis = new ArrayCollection();
    }

    public function getIdLivre(): ?int
    {
        return $this->idLivre;
    }

    public function getIsbn(): ?string
    {
        return $this->isbn;
    }

    public function setIsbn(?string $isbn): static
    {
        $this->isbn = $isbn;

        return $this;
    }

    public function getTitre(): string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): static
    {
        $this->titre = $titre;

        return $this;
    }

    public function getAuteur(): string
    {
        return $this->auteur;
    }

    public function setAuteur(string $auteur): static
    {
        $this->auteur = $auteur;

        return $this;
    }

    public function getAnneePublication(): ?int
    {
        return $this->anneePublication;
    }

    public function setAnneePublication(?int $anneePublication): static
    {
        $this->anneePublication = $anneePublication;

        return $this;
    }

    public function getCategorie(): string
    {
        return $this->categorie;
    }

    public function setCategorie(string $categorie): static
    {
        $this->categorie = $categorie;

        return $this;
    }

    public function getResume(): ?string
    {
        return $this->resume;
    }

    public function setResume(?string $resume): static
    {
        $this->resume = $resume;

        return $this;
    }

    public function getCouvertureUrl(): ?string
    {
        return $this->couvertureUrl;
    }

    public function setCouvertureUrl(?string $couvertureUrl): static
    {
        $this->couvertureUrl = $couvertureUrl;

        return $this;
    }

    /** @return Collection<int, Exemplaire> */
    public function getExemplaires(): Collection
    {
        return $this->exemplaires;
    }

    /** @return Collection<int, Avis> */
    public function getAvis(): Collection
    {
        return $this->avis;
    }
}
