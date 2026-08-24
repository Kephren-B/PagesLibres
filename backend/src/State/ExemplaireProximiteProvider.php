<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Exemplaire;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * F4 : recherche des exemplaires à moins de {rayon} mètres de {lat},{lon}.
 * ST_DWithin sur exemplaire.position, casté en geography pour comparer en
 * mètres (en SRID 4326 brut, les unités sont des degrés). N'expose que des
 * entités Exemplaire, dont le serializer applique déjà l'arrondi de
 * position (getPositionArrondie()) — aucune coordonnée exacte ne sort ici.
 *
 * @implements ProviderInterface<Exemplaire>
 */
final class ExemplaireProximiteProvider implements ProviderInterface
{
    private const RAYON_DEFAUT_METRES = 1000.0;
    private const RAYON_MAX_METRES = 50000.0;

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): iterable
    {
        $request = $context['request'] ?? null;
        $lat = $request?->query->get('lat');
        $lon = $request?->query->get('lon');
        $rayon = $request?->query->get('rayon');

        if ($lat === null || $lon === null || !is_numeric($lat) || !is_numeric($lon)) {
            throw new BadRequestHttpException('Les paramètres numériques "lat" et "lon" sont requis.');
        }

        $lat = (float) $lat;
        $lon = (float) $lon;
        if ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
            throw new BadRequestHttpException('"lat" doit être compris entre -90 et 90, "lon" entre -180 et 180.');
        }

        $rayon = $rayon !== null && is_numeric($rayon) ? (float) $rayon : self::RAYON_DEFAUT_METRES;
        $rayon = min(max($rayon, 1.0), self::RAYON_MAX_METRES);

        $ids = $this->em->getConnection()->fetchFirstColumn(
            <<<'SQL'
                SELECT id_exemplaire
                FROM exemplaire
                WHERE position IS NOT NULL
                  AND ST_DWithin(
                        position::geography,
                        ST_SetSRID(ST_MakePoint(:lon, :lat), 4326)::geography,
                        :rayon
                      )
                SQL,
            ['lon' => $lon, 'lat' => $lat, 'rayon' => $rayon]
        );

        if ($ids === []) {
            return [];
        }

        return $this->em->getRepository(Exemplaire::class)->findBy(['idExemplaire' => $ids]);
    }
}
