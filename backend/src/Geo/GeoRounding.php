<?php

declare(strict_types=1);

namespace App\Geo;

/**
 * Règle de conception non négociable : l'API ne doit jamais exposer une
 * position géographique exacte. Arrondi à 3 décimales (~111 m par 0,001°
 * de latitude à l'équateur, donc proche de "la centaine de mètres"
 * imposée), appliqué uniquement en sortie API — jamais en base, jamais
 * sur les colonnes source (exemplaire.position, mouvement.latitude/
 * longitude), qui restent précises pour le calcul de proximité (index
 * GiST) et pour le trigger trg_maj_position_exemplaire.
 */
final class GeoRounding
{
    private const PRECISION = 3;

    public static function round(float $value): float
    {
        return round($value, self::PRECISION);
    }

    /**
     * Parse le WKT renvoyé par jsor/doctrine-postgis pour une colonne
     * "geometry" (ex: "POINT(2.294481 48.85837)") et retourne
     * [latitude, longitude] arrondies, ou null si la position est absente.
     *
     * @return array{latitude: float, longitude: float}|null
     */
    public static function fromPointWkt(?string $wkt): ?array
    {
        if ($wkt === null) {
            return null;
        }

        if (!preg_match('/POINT\s*\(\s*(-?\d+(?:\.\d+)?)\s+(-?\d+(?:\.\d+)?)\s*\)/i', $wkt, $matches)) {
            return null;
        }

        [, $lon, $lat] = $matches;

        return [
            'latitude' => self::round((float) $lat),
            'longitude' => self::round((float) $lon),
        ];
    }
}
