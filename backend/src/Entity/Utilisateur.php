<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Enum\RoleUtilisateur;
use App\State\UtilisateurProcessor;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Inscription publique (POST), lecture publique du profil sans l'email
 * (visible seulement par soi-même ou un admin, via ApiProperty::security).
 * Aucune écriture directe de "role" ou "motDePasseHash" par le client :
 * seul UtilisateurProcessor peut les fixer (hash du mot de passe, rôle
 * "membre" forcé à l'inscription).
 */
#[ORM\Entity]
#[ORM\Table(name: 'utilisateur')]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
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
    #[Groups(['utilisateur:read', 'utilisateur:write'])]
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
     */
    #[Assert\NotBlank]
    #[Assert\Length(min: 8)]
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
