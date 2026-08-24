<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Enum\StatutExemplaire;
use App\Geo\GeoRounding;
use App\State\ExemplaireProximiteProvider;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'exemplaire')]
#[ORM\Index(name: 'idx_exemplaire_livre', columns: ['id_livre'])]
#[ApiResource(
    operations: [
        new GetCollection(),
        // idExemplaire contraint à un entier : sinon "/exemplaires/proximite"
        // matche cette route en premier (même segment de chemin, "proximite"
        // pris comme idExemplaire littéral) et la route dédiée ci-dessous ne
        // serait jamais atteinte.
        new Get(requirements: ['idExemplaire' => '\d+']),
        new Post(security: "is_granted('ROLE_USER')"),
        // F4 : recherche par proximité (ST_DWithin), route dédiée hors CRUD standard.
        new GetCollection(
            uriTemplate: '/exemplaires/proximite',
            paginationEnabled: false,
            provider: ExemplaireProximiteProvider::class,
        ),
    ],
    normalizationContext: ['groups' => ['exemplaire:read']],
    denormalizationContext: ['groups' => ['exemplaire:write']],
)]
#[ApiFilter(SearchFilter::class, properties: ['codeBcid' => 'exact', 'livre' => 'exact', 'statut' => 'exact'])]
class Exemplaire
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_exemplaire', type: Types::INTEGER)]
    #[Groups(['exemplaire:read'])]
    private ?int $idExemplaire = null;

    #[ORM\ManyToOne(targetEntity: Livre::class, inversedBy: 'exemplaires')]
    #[ORM\JoinColumn(name: 'id_livre', referencedColumnName: 'id_livre', nullable: false, onDelete: 'CASCADE')]
    #[Groups(['exemplaire:read', 'exemplaire:write'])]
    private Livre $livre;

    #[ORM\Column(name: 'code_bcid', type: Types::STRING, length: 20, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 20)]
    #[Groups(['exemplaire:read', 'exemplaire:write'])]
    private string $codeBcid;

    #[ORM\Column(name: 'statut', type: Types::STRING, enumType: StatutExemplaire::class)]
    #[Groups(['exemplaire:read'])]
    private StatutExemplaire $statut = StatutExemplaire::EnCirculation;

    /**
     * GEOMETRY(Point,4326) — dénormalisée depuis le dernier MOUVEMENT par le
     * trigger PostgreSQL trg_maj_position_exemplaire. Jamais écrite depuis
     * l'application (insertable/updatable false) : seule l'insertion d'un
     * Mouvement fait évoluer cette colonne, via le trigger.
     *
     * Jamais taguée d'un groupe API Platform : la valeur précise ne doit
     * jamais sortir de l'API. Seule getPositionArrondie() (arrondie côté
     * PHP, cf. App\Geo\GeoRounding) est exposée en lecture.
     */
    #[ORM\Column(name: 'position', type: 'geometry', nullable: true, insertable: false, updatable: false, options: ['geometry_type' => 'POINT', 'srid' => 4326])]
    private ?string $position = null;

    #[ORM\Column(name: 'date_creation', type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['exemplaire:read'])]
    private \DateTimeImmutable $dateCreation;

    /**
     * Journal de voyage (F6) : tri chronologique appliqué au niveau du
     * mapping (OrderBy), pas recalculé côté PHP à chaque lecture.
     *
     * @var Collection<int, Mouvement>
     */
    #[ORM\OneToMany(targetEntity: Mouvement::class, mappedBy: 'exemplaire')]
    #[ORM\OrderBy(['dateMouvement' => 'ASC', 'idMouvement' => 'ASC'])]
    #[Groups(['exemplaire:read'])]
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

    #[ApiProperty]
    #[Groups(['exemplaire:read'])]
    public function getPositionArrondie(): ?array
    {
        return GeoRounding::fromPointWkt($this->position);
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
