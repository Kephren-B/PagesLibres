<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\StatutExemplaire;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'exemplaire')]
#[ORM\Index(name: 'idx_exemplaire_livre', columns: ['id_livre'])]
class Exemplaire
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_exemplaire', type: Types::INTEGER)]
    private ?int $idExemplaire = null;

    #[ORM\ManyToOne(targetEntity: Livre::class, inversedBy: 'exemplaires')]
    #[ORM\JoinColumn(name: 'id_livre', referencedColumnName: 'id_livre', nullable: false, onDelete: 'CASCADE')]
    private Livre $livre;

    #[ORM\Column(name: 'code_bcid', type: Types::STRING, length: 20, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 20)]
    private string $codeBcid;

    #[ORM\Column(name: 'statut', type: Types::STRING, enumType: StatutExemplaire::class)]
    private StatutExemplaire $statut = StatutExemplaire::EnCirculation;

    /**
     * GEOMETRY(Point,4326) — dénormalisée depuis le dernier MOUVEMENT par le
     * trigger PostgreSQL trg_maj_position_exemplaire. Jamais écrite depuis
     * l'application (insertable/updatable false) : seule l'insertion d'un
     * Mouvement fait évoluer cette colonne, via le trigger.
     *
     * Nécessite le mapping DBAL du type "geometry" (ex. paquet Composer
     * jsor/doctrine-postgis) enregistré dans doctrine.yaml avant que
     * `doctrine:schema:validate` ne passe sur cette entité — à installer à
     * l'étape suivante (bootstrap Symfony), pas encore fait ici.
     *
     * Rappel de conception : cette colonne stocke la position précise en
     * base ; l'API ne doit exposer qu'une position arrondie à la centaine
     * de mètres, appliqué dans le serializer/DTO de sortie, jamais ici.
     */
    #[ORM\Column(name: 'position', type: 'geometry', nullable: true, insertable: false, updatable: false, options: ['geometry_type' => 'POINT', 'srid' => 4326])]
    private ?string $position = null;

    #[ORM\Column(name: 'date_creation', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $dateCreation;

    /** @var Collection<int, Mouvement> */
    #[ORM\OneToMany(targetEntity: Mouvement::class, mappedBy: 'exemplaire')]
    private Collection $mouvements;

    public function __construct()
    {
        $this->dateCreation = new \DateTimeImmutable();
        $this->mouvements = new ArrayCollection();
    }

    public function getIdExemplaire(): ?int
    {
        return $this->idExemplaire;
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

    public function getCodeBcid(): string
    {
        return $this->codeBcid;
    }

    public function setCodeBcid(string $codeBcid): static
    {
        $this->codeBcid = $codeBcid;

        return $this;
    }

    public function getStatut(): StatutExemplaire
    {
        return $this->statut;
    }

    public function setStatut(StatutExemplaire $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function getPosition(): ?string
    {
        return $this->position;
    }

    public function getDateCreation(): \DateTimeImmutable
    {
        return $this->dateCreation;
    }

    /** @return Collection<int, Mouvement> */
    public function getMouvements(): Collection
    {
        return $this->mouvements;
    }
}
