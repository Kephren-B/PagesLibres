<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\StatutSignalement;
use App\Validator\ExactlyOneTarget;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * 4 cibles nullables (livre/exemplaire/avis/commentaire), exactement une
 * renseignée. Contrainte répliquée ici en amont de la contrainte CHECK
 * chk_signalement_une_cible de la base (règle non négociable).
 */
#[ORM\Entity]
#[ORM\Table(name: 'signalement')]
#[ExactlyOneTarget(fields: ['livre', 'exemplaire', 'avis', 'commentaire'], message: 'Un signalement doit cibler exactement une ressource parmi livre, exemplaire, avis ou commentaire.')]
class Signalement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_signalement', type: Types::INTEGER)]
    private ?int $idSignalement = null;

    /**
     * Nullable + ON DELETE SET NULL : la suppression d'un compte anonymise
     * les signalements qu'il a émis (règle RGPD, Jalon 1).
     */
    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(name: 'id_utilisateur_signaleur', referencedColumnName: 'id_utilisateur', nullable: true, onDelete: 'SET NULL')]
    private ?Utilisateur $utilisateurSignaleur = null;

    /**
     * Nullable + ON DELETE SET NULL : idem pour le modérateur qui a traité
     * le signalement.
     */
    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(name: 'id_utilisateur_traitant', referencedColumnName: 'id_utilisateur', nullable: true, onDelete: 'SET NULL')]
    private ?Utilisateur $utilisateurTraitant = null;

    #[ORM\ManyToOne(targetEntity: Livre::class)]
    #[ORM\JoinColumn(name: 'id_livre', referencedColumnName: 'id_livre', nullable: true, onDelete: 'CASCADE')]
    private ?Livre $livre = null;

    #[ORM\ManyToOne(targetEntity: Exemplaire::class)]
    #[ORM\JoinColumn(name: 'id_exemplaire', referencedColumnName: 'id_exemplaire', nullable: true, onDelete: 'CASCADE')]
    private ?Exemplaire $exemplaire = null;

    #[ORM\ManyToOne(targetEntity: Avis::class)]
    #[ORM\JoinColumn(name: 'id_avis', referencedColumnName: 'id_avis', nullable: true, onDelete: 'CASCADE')]
    private ?Avis $avis = null;

    #[ORM\ManyToOne(targetEntity: Commentaire::class)]
    #[ORM\JoinColumn(name: 'id_commentaire', referencedColumnName: 'id_commentaire', nullable: true, onDelete: 'CASCADE')]
    private ?Commentaire $commentaire = null;

    #[ORM\Column(name: 'motif', type: Types::STRING, length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private string $motif;

    #[ORM\Column(name: 'statut', type: Types::STRING, enumType: StatutSignalement::class)]
    private StatutSignalement $statut = StatutSignalement::EnAttente;

    #[ORM\Column(name: 'date_creation', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $dateCreation;

    #[ORM\Column(name: 'date_traitement', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dateTraitement = null;

    public function __construct()
    {
        $this->dateCreation = new \DateTimeImmutable();
    }

    public function getIdSignalement(): ?int
    {
        return $this->idSignalement;
    }

    public function getUtilisateurSignaleur(): ?Utilisateur
    {
        return $this->utilisateurSignaleur;
    }

    public function setUtilisateurSignaleur(?Utilisateur $utilisateurSignaleur): static
    {
        $this->utilisateurSignaleur = $utilisateurSignaleur;

        return $this;
    }

    public function getUtilisateurTraitant(): ?Utilisateur
    {
        return $this->utilisateurTraitant;
    }

    public function setUtilisateurTraitant(?Utilisateur $utilisateurTraitant): static
    {
        $this->utilisateurTraitant = $utilisateurTraitant;

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

    public function getExemplaire(): ?Exemplaire
    {
        return $this->exemplaire;
    }

    public function setExemplaire(?Exemplaire $exemplaire): static
    {
        $this->exemplaire = $exemplaire;

        return $this;
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

    public function getCommentaire(): ?Commentaire
    {
        return $this->commentaire;
    }

    public function setCommentaire(?Commentaire $commentaire): static
    {
        $this->commentaire = $commentaire;

        return $this;
    }

    public function getMotif(): string
    {
        return $this->motif;
    }

    public function setMotif(string $motif): static
    {
        $this->motif = $motif;

        return $this;
    }

    public function getStatut(): StatutSignalement
    {
        return $this->statut;
    }

    public function setStatut(StatutSignalement $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function getDateCreation(): \DateTimeImmutable
    {
        return $this->dateCreation;
    }

    public function getDateTraitement(): ?\DateTimeImmutable
    {
        return $this->dateTraitement;
    }

    public function setDateTraitement(?\DateTimeImmutable $dateTraitement): static
    {
        $this->dateTraitement = $dateTraitement;

        return $this;
    }
}
