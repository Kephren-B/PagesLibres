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
use App\Enum\TypeMouvement;
use App\Geo\GeoRounding;
use App\State\MouvementProcessor;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * F3 (libération), F5 (trouvaille) : la transition de statut de
 * l'Exemplaire est décidée par MouvementProcessor, jamais ici ni côté
 * client. latitude/longitude sont en écriture seule (jamais dans un
 * groupe de lecture) : seule getPositionArrondie() sort de l'API — règle
 * non négociable, l'API n'expose jamais une position exacte.
 *
 * GetCollection + filtre "utilisateur" (F9, profil : historique des
 * mouvements). Ouvert à tout membre authentifié, pas restreint à
 * soi-même : le journal de voyage d'un Exemplaire (Get, public) expose
 * déjà les mêmes mouvements avec le pseudo du contributeur, ce endpoint
 * n'élève donc pas le niveau d'exposition existant.
 */
#[ORM\Entity]
#[ORM\Table(name: 'mouvement')]
#[ORM\Index(name: 'idx_mouvement_exemplaire', columns: ['id_exemplaire', 'date_mouvement'])]
#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(security: "is_granted('ROLE_USER')"),
        new Post(processor: MouvementProcessor::class, security: "is_granted('ROLE_USER')"),
    ],
    normalizationContext: ['groups' => ['mouvement:read']],
    denormalizationContext: ['groups' => ['mouvement:write']],
)]
#[ApiFilter(SearchFilter::class, properties: ['utilisateur' => 'exact', 'typeMouvement' => 'exact'])]
class Mouvement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_mouvement', type: Types::INTEGER)]
    #[Groups(['mouvement:read', 'exemplaire:read'])]
    private ?int $idMouvement = null;

    #[ORM\ManyToOne(targetEntity: Exemplaire::class, inversedBy: 'mouvements')]
    #[ORM\JoinColumn(name: 'id_exemplaire', referencedColumnName: 'id_exemplaire', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    #[Groups(['mouvement:read', 'mouvement:write'])]
    private Exemplaire $exemplaire;

    /**
     * Nullable + ON DELETE SET NULL : la suppression d'un compte anonymise
     * ses mouvements plutôt que de les effacer (règle RGPD, Jalon 1).
     * Ne jamais changer cette FK en CASCADE. Jamais dans le groupe
     * d'écriture : fixé par MouvementProcessor depuis l'utilisateur
     * authentifié, jamais depuis l'entrée client.
     */
    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(name: 'id_utilisateur', referencedColumnName: 'id_utilisateur', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['mouvement:read', 'exemplaire:read'])]
    private ?Utilisateur $utilisateur = null;

    #[ORM\Column(name: 'type_mouvement', type: Types::STRING, enumType: TypeMouvement::class)]
    #[Assert\NotNull]
    #[Groups(['mouvement:read', 'mouvement:write', 'exemplaire:read'])]
    private TypeMouvement $typeMouvement;

    /**
     * Précision exacte nécessaire pour alimenter le trigger
     * trg_maj_position_exemplaire — écriture seule, jamais renvoyée par
     * l'API (cf. getPositionArrondie()).
     */
    #[ORM\Column(name: 'latitude', type: Types::DECIMAL, precision: 9, scale: 6)]
    #[Assert\NotNull]
    #[Assert\Range(min: -90, max: 90)]
    #[Groups(['mouvement:write'])]
    private string $latitude;

    #[ORM\Column(name: 'longitude', type: Types::DECIMAL, precision: 9, scale: 6)]
    #[Assert\NotNull]
    #[Assert\Range(min: -180, max: 180)]
    #[Groups(['mouvement:write'])]
    private string $longitude;

    #[ORM\Column(name: 'message', type: Types::TEXT, nullable: true)]
    #[Groups(['mouvement:read', 'mouvement:write', 'exemplaire:read'])]
    private ?string $message = null;

    #[ORM\Column(name: 'date_mouvement', type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['mouvement:read', 'exemplaire:read'])]
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

    /** @return array{latitude: float, longitude: float} */
    #[ApiProperty]
    #[Groups(['mouvement:read', 'exemplaire:read'])]
    public function getPositionArrondie(): array
    {
        return [
            'latitude' => GeoRounding::round((float) $this->latitude),
            'longitude' => GeoRounding::round((float) $this->longitude),
        ];
    }
}
