<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Enum\RoleUtilisateur;
use App\State\MoiProvider;
use App\State\UtilisateurProcessor;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\PasswordStrength;

/**
 * Inscription publique (POST), lecture publique du profil sans l'email
 * (visible seulement par soi-même ou un admin, via ApiProperty::security).
 * Aucune écriture directe de "role" ou "motDePasseHash" par le client :
 * seul UtilisateurProcessor peut les fixer (hash du mot de passe, rôle
 * "membre" forcé à l'inscription).
 *
 * GET /api/moi (F9) : profil de l'utilisateur authentifié courant, via
 * MoiProvider (lit le token, pas d'{id} dans l'URL).
 */
#[ORM\Entity]
#[ORM\Table(name: 'utilisateur')]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Get(uriTemplate: '/moi', provider: MoiProvider::class, security: "is_granted('ROLE_USER')"),
        new Post(processor: UtilisateurProcessor::class),
    ],
    normalizationContext: ['groups' => ['utilisateur:read']],
    denormalizationContext: ['groups' => ['utilisateur:write']],
)]
class Utilisateur implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_utilisateur', type: Types::INTEGER)]
    #[Groups(['utilisateur:read'])]
    private ?int $idUtilisateur = null;

    #[ORM\Column(name: 'pseudo', type: Types::STRING, length: 50, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 50)]
    #[Groups(['utilisateur:read', 'utilisateur:write', 'avis:read', 'commentaire:read', 'mouvement:read', 'exemplaire:read', 'signalement:read'])]
    private string $pseudo;

    #[ORM\Column(name: 'email', type: Types::STRING, length: 255, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 255)]
    #[ApiProperty(security: "is_granted('ROLE_ADMIN') or object === user")]
    #[Groups(['utilisateur:read', 'utilisateur:write'])]
    private string $email;

    #[ORM\Column(name: 'mot_de_passe_hash', type: Types::STRING, length: 255)]
    private string $motDePasseHash;

    /**
     * Transitoire, jamais persisté tel quel : hashé par UtilisateurProcessor
     * via UserPasswordHasherInterface avant écriture dans motDePasseHash.
     * Écriture seule (jamais dans le groupe de lecture).
     *
     * Politique de robustesse (Jalon 5, § IX) — trois contrôles :
     *
     * · `min: 8` est un plancher, pas une politique : « 12345678 » le passait ;
     * · `PasswordStrength(minScore: STRONG)` exige au moins 3 sur 4 selon
     *   l'estimation entropique de Symfony. Mesuré : « 12345678 », « motdepasse »,
     *   « azertyuiop » et « Admin2026! » sont refusés ; le mot de passe du jeu de
     *   démonstration « DemoPagesLibres2026! » atteint 4 (VERY_STRONG), la démo
     *   reste donc utilisable ;
     * · `max: 128` borne le travail de hachage : Argon2id lit la chaîne entière,
     *   un mot de passe démesuré coûterait de la mémoire et du temps à chaque
     *   tentative (faculté de déni de service).
     *
     * `NotCompromisedPassword` interroge l'API publique « Pwned Passwords » par
     * préfixe de hachage — le mot de passe n'est jamais transmis, seules les 5
     * premiers caractères de son empreinte SHA-1 le sont (k-anonymat).
     * `skipOnError: true` : si l'API est injoignable, on n'empêche pas une
     * inscription — la disponibilité d'un service tiers ne doit pas bloquer le
     * nôtre. Contrepartie assumée : un mot de passe fuité passe quand l'API est
     * hors ligne, et l'appel sort du réseau (à documenter comme la clé Google
     * Books).
     */
    #[Assert\NotBlank]
    #[Assert\Length(min: 8, max: 128)]
    #[Assert\PasswordStrength(minScore: PasswordStrength::STRENGTH_STRONG)]
    #[Assert\NotCompromisedPassword(skipOnError: true)]
    #[Groups(['utilisateur:write'])]
    private ?string $plainPassword = null;

    #[ORM\Column(name: 'avatar_url', type: Types::STRING, length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    #[Groups(['utilisateur:read', 'utilisateur:write'])]
    private ?string $avatarUrl = null;

    #[ORM\Column(name: 'bio', type: Types::TEXT, nullable: true)]
    #[Groups(['utilisateur:read', 'utilisateur:write'])]
    private ?string $bio = null;

    #[ORM\Column(name: 'role', type: Types::STRING, enumType: RoleUtilisateur::class)]
    #[Groups(['utilisateur:read'])]
    private RoleUtilisateur $role = RoleUtilisateur::Membre;

    #[ORM\Column(name: 'date_inscription', type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['utilisateur:read'])]
    private \DateTimeImmutable $dateInscription;

    public function __construct()
    {
        $this->dateInscription = new \DateTimeImmutable();
    }

    public function getIdUtilisateur(): ?int
    {
        return $this->idUtilisateur;
    }

    public function getPseudo(): string
    {
        return $this->pseudo;
    }

    public function setPseudo(string $pseudo): static
    {
        $this->pseudo = $pseudo;

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getMotDePasseHash(): string
    {
        return $this->motDePasseHash;
    }

    public function setMotDePasseHash(string $motDePasseHash): static
    {
        $this->motDePasseHash = $motDePasseHash;

        return $this;
    }

    public function getPlainPassword(): ?string
    {
        return $this->plainPassword;
    }

    public function setPlainPassword(?string $plainPassword): static
    {
        $this->plainPassword = $plainPassword;

        return $this;
    }

    public function getAvatarUrl(): ?string
    {
        return $this->avatarUrl;
    }

    public function setAvatarUrl(?string $avatarUrl): static
    {
        $this->avatarUrl = $avatarUrl;

        return $this;
    }

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function setBio(?string $bio): static
    {
        $this->bio = $bio;

        return $this;
    }

    public function getRole(): RoleUtilisateur
    {
        return $this->role;
    }

    public function setRole(RoleUtilisateur $role): static
    {
        $this->role = $role;

        return $this;
    }

    public function getDateInscription(): \DateTimeImmutable
    {
        return $this->dateInscription;
    }

    public function getRoles(): array
    {
        $roles = ['ROLE_USER'];
        if ($this->role === RoleUtilisateur::Admin) {
            $roles[] = 'ROLE_ADMIN';
        }

        return array_unique($roles);
    }

    public function getPassword(): string
    {
        return $this->motDePasseHash;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function eraseCredentials(): void
    {
        $this->plainPassword = null;
    }
}
