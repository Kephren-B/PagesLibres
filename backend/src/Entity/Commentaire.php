<?php

declare(strict_types=1);

namespace App\Entity;

use App\Validator\ExactlyOneTarget;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Un commentaire répond soit à un AVIS, soit directement à une fiche LIVRE
 * (F7), jamais les deux à la fois. Contrainte répliquée ici en amont de la
 * contrainte CHECK chk_commentaire_une_cible de la base (règle non
 * négociable : la validation applicative ne remplace pas le CHECK, elle le
 * précède).
 */
#[ORM\Entity]
#[ORM\Table(name: 'commentaire')]
#[ExactlyOneTarget(fields: ['avis', 'livre'], message: 'Un commentaire doit cibler exactement un avis OU une fiche livre, jamais les deux ni aucun des deux.')]
class Commentaire
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_commentaire', type: Types::INTEGER)]
    private ?int $idCommentaire = null;

    #[ORM\ManyToOne(targetEntity: Avis::class, inversedBy: 'commentaires')]
    #[ORM\JoinColumn(name: 'id_avis', referencedColumnName: 'id_avis', nullable: true, onDelete: 'CASCADE')]
    private ?Avis $avis = null;

    #[ORM\ManyToOne(targetEntity: Livre::class)]
    #[ORM\JoinColumn(name: 'id_livre', referencedColumnName: 'id_livre', nullable: true, onDelete: 'CASCADE')]
    private ?Livre $livre = null;

    /**
     * Nullable + ON DELETE SET NULL : la suppression d'un compte anonymise
     * ses commentaires plutôt que de les effacer (règle RGPD, Jalon 1).
     */
    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(name: 'id_utilisateur', referencedColumnName: 'id_utilisateur', nullable: true, onDelete: 'SET NULL')]
    private ?Utilisateur $utilisateur = null;

    #[ORM\Column(name: 'contenu', type: Types::TEXT)]
    #[Assert\NotBlank]
    private string $contenu;

    #[ORM\Column(name: 'date_creation', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $dateCreation;

    public function __construct()
    {
        $this->dateCreation = new \DateTimeImmutable();
    }

    public function getIdCommentaire(): ?int
    {
        return $this->idCommentaire;
    }

    public function getAvis(): ?Avis
    {
        return $this->avis;
    }

    public function setAvis(?Avis $avis): static
    {
        $this->avis = $avis;

        return $this;
    }

    public function getLivre(): ?Livre
    {
        return $this->livre;
    }

    public function setLivre(?Livre $livre): static
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

    public function getContenu(): string
    {
        return $this->contenu;
    }

    public function setContenu(string $contenu): static
    {
        $this->contenu = $contenu;

        return $this;
    }

    public function getDateCreation(): \DateTimeImmutable
    {
        return $this->dateCreation;
    }
}
