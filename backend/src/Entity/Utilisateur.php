<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\RoleUtilisateur;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'utilisateur')]
class Utilisateur
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_utilisateur', type: Types::INTEGER)]
    private ?int $idUtilisateur = null;

    #[ORM\Column(name: 'pseudo', type: Types::STRING, length: 50, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 50)]
    private string $pseudo;

    #[ORM\Column(name: 'email', type: Types::STRING, length: 255, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 255)]
    private string $email;

    #[ORM\Column(name: 'mot_de_passe_hash', type: Types::STRING, length: 255)]
    private string $motDePasseHash;

    #[ORM\Column(name: 'avatar_url', type: Types::STRING, length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $avatarUrl = null;

    #[ORM\Column(name: 'bio', type: Types::TEXT, nullable: true)]
    private ?string $bio = null;

    #[ORM\Column(name: 'role', type: Types::STRING, enumType: RoleUtilisateur::class)]
    private RoleUtilisateur $role = RoleUtilisateur::Membre;

    #[ORM\Column(name: 'date_inscription', type: Types::DATETIME_IMMUTABLE)]
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
}
