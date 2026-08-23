<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'avis')]
#[ORM\UniqueConstraint(name: 'avis_id_livre_id_utilisateur_key', columns: ['id_livre', 'id_utilisateur'])]
class Avis
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_avis', type: Types::INTEGER)]
    private ?int $idAvis = null;

    #[ORM\ManyToOne(targetEntity: Livre::class, inversedBy: 'avis')]
    #[ORM\JoinColumn(name: 'id_livre', referencedColumnName: 'id_livre', nullable: false, onDelete: 'CASCADE')]
    private Livre $livre;

    /**
     * Nullable + ON DELETE SET NULL : la suppression d'un compte anonymise
     * ses avis plutôt que de les effacer (règle RGPD, Jalon 1).
     */
    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(name: 'id_utilisateur', referencedColumnName: 'id_utilisateur', nullable: true, onDelete: 'SET NULL')]
    private ?Utilisateur $utilisateur = null;

    #[ORM\Column(name: 'note', type: Types::SMALLINT)]
    #[Assert\NotNull]
    #[Assert\Range(min: 1, max: 5)]
    private int $note;

    #[ORM\Column(name: 'commentaire', type: Types::TEXT, nullable: true)]
    private ?string $commentaire = null;

    #[ORM\Column(name: 'date_creation', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $dateCreation;

    /** @var Collection<int, Commentaire> */
    #[ORM\OneToMany(targetEntity: Commentaire::class, mappedBy: 'avis')]
    private Collection $commentaires;

    public function __construct()
    {
        $this->dateCreation = new \DateTimeImmutable();
        $this->commentaires = new ArrayCollection();
    }

    public function getIdAvis(): ?int
    {
        return $this->idAvis;
    }

    public function getLivre(): Livre
    {
        return $this->livre;
    }

    public function setLivre(Livre $livre): static
    {
        $this->livre = $livre;

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

    public function getNote(): int
    {
        return $this->note;
    }

    public function setNote(int $note): static
    {
        $this->note = $note;

        return $this;
    }

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): static
    {
        $this->commentaire = $commentaire;

        return $this;
    }

    public function getDateCreation(): \DateTimeImmutable
    {
        return $this->dateCreation;
    }

    /** @return Collection<int, Commentaire> */
    public function getCommentaires(): Collection
    {
        return $this->commentaires;
    }
}
