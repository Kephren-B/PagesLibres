<?php

declare(strict_types=1);

namespace App\Gamification;

use App\Entity\Badge;
use App\Entity\ObtentionBadge;
use App\Entity\Utilisateur;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

/**
 * F9 : évalue les 5 badges du périmètre fermé (Jalon 1) après chaque
 * Mouvement créé, recalcul synchrone (pas de job planifié, volume trop
 * faible pour le justifier à ce stade).
 *
 * Les 5 badges sont des lignes de données (table badge, seedées par
 * migration — cf. VersionXXXX_seed_badges), pas du code : ce service lit
 * critere_type/critere_valeur en base plutôt que de coder les seuils en
 * dur, pour rester fidèle au MPD. Interprétations retenues pour les 2
 * critères non chiffrés précisément dans le CDC (décision technique
 * documentée, à valider) :
 * - "50 km cumulés" : somme des distances (haversine, via
 *   ST_DistanceSphere) entre chaque mouvement de cet utilisateur et le
 *   mouvement précédent du même exemplaire — la distance que ce
 *   mouvement a fait parcourir au livre.
 * - "chaîne de 5 relibérations" : 5 mouvements de type "liberation" de
 *   cet utilisateur qui ne sont PAS le tout premier mouvement de leur
 *   exemplaire (donc précédés d'une trouvaille, par n'importe qui).
 *
 * Périmètre fermé à ces 5 badges (Jalon 1) — ne pas en ajouter d'autres.
 */
final class BadgeAttributionService
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function evaluerEtAttribuer(Utilisateur $utilisateur): void
    {
        $userId = $utilisateur->getIdUtilisateur();
        if ($userId === null) {
            return;
        }

        $conn = $this->em->getConnection();

        $dejaObtenus = $conn->fetchFirstColumn(
            'SELECT id_badge FROM obtention_badge WHERE id_utilisateur = ?',
            [$userId]
        );

        /** @var Badge[] $badges */
        $badges = $this->em->getRepository(Badge::class)->findAll();

        foreach ($badges as $badge) {
            if (in_array($badge->getIdBadge(), $dejaObtenus, true)) {
                continue;
            }

            $valeur = $this->mesurer($badge->getCritereType(), $conn, $utilisateur);
            if ($valeur < $badge->getCritereValeur()) {
                continue;
            }

            $obtention = (new ObtentionBadge())->setUtilisateur($utilisateur)->setBadge($badge);
            $this->em->persist($obtention);

            try {
                $this->em->flush();
            } catch (UniqueConstraintViolationException) {
                // Attribution concurrente déjà passée entre-temps (UNIQUE
                // (id_utilisateur, id_badge) en base) : comportement
                // attendu, on ignore silencieusement, pas de double envoi.
                $this->em->detach($obtention);
            }
        }
    }

    private function mesurer(string $critereType, \Doctrine\DBAL\Connection $conn, Utilisateur $utilisateur): float
    {
        return match ($critereType) {
            'premiere_liberation', 'liberations' => (float) $this->compterLiberations($conn, $utilisateur->getIdUtilisateur()),
            'distance_km' => $this->distanceCumuleeKm($conn, $utilisateur->getIdUtilisateur()),
            'chaine_relibrations' => (float) $this->compterRelibrations($conn, $utilisateur->getIdUtilisateur()),
            'anciennete_jours' => (float) $this->ancienneteJours($utilisateur),
            default => 0.0,
        };
    }

    private function compterLiberations(\Doctrine\DBAL\Connection $conn, int $userId): int
    {
        return (int) $conn->fetchOne(
            "SELECT COUNT(*) FROM mouvement WHERE id_utilisateur = ? AND type_mouvement = 'liberation'",
            [$userId]
        );
    }

    private function distanceCumuleeKm(\Doctrine\DBAL\Connection $conn, int $userId): float
    {
        $km = $conn->fetchOne(
            <<<'SQL'
                SELECT COALESCE(SUM(
                    ST_DistanceSphere(
                        ST_MakePoint(prev_lon, prev_lat),
                        ST_MakePoint(longitude::float8, latitude::float8)
                    )
                ) / 1000.0, 0)
                FROM (
                    SELECT
                        id_utilisateur,
                        latitude,
                        longitude,
                        LAG(latitude::float8) OVER (PARTITION BY id_exemplaire ORDER BY date_mouvement) AS prev_lat,
                        LAG(longitude::float8) OVER (PARTITION BY id_exemplaire ORDER BY date_mouvement) AS prev_lon
                    FROM mouvement
                ) historique
                WHERE id_utilisateur = ? AND prev_lat IS NOT NULL
                SQL,
            [$userId]
        );

        return (float) $km;
    }

    private function compterRelibrations(\Doctrine\DBAL\Connection $conn, int $userId): int
    {
        return (int) $conn->fetchOne(
            <<<'SQL'
                SELECT COUNT(*)
                FROM (
                    SELECT
                        id_utilisateur,
                        type_mouvement,
                        LAG(id_mouvement) OVER (PARTITION BY id_exemplaire ORDER BY date_mouvement) AS precedent
                    FROM mouvement
                ) historique
                WHERE id_utilisateur = ?
                  AND type_mouvement = 'liberation'
                  AND precedent IS NOT NULL
                SQL,
            [$userId]
        );
    }

    private function ancienneteJours(Utilisateur $utilisateur): int
    {
        return $utilisateur->getDateInscription()->diff(new \DateTimeImmutable())->days;
    }
}
