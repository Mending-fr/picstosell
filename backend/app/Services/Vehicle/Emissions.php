<?php

namespace App\Services\Vehicle;

use Carbon\CarbonInterface;

/**
 * Vignette Crit'Air, norme Euro et classe CO2 d'une voiture particulière.
 * Crit'Air : barème du ministère pour les voitures particulières, d'après l'énergie et la norme Euro,
 * ou la date de 1re immatriculation quand la norme n'est pas connue.
 * Classe CO2 : barème de l'étiquette énergie (A à G).
 */
class Emissions
{
    public const CO2_CLASSES = [
        'A' => [0, 100], 'B' => [101, 120], 'C' => [121, 140], 'D' => [141, 160],
        'E' => [161, 200], 'F' => [201, 250], 'G' => [251, PHP_INT_MAX],
    ];

    public const CRITAIR_LABELS = [
        '0' => 'Crit\'Air 0 (électrique)', '1' => 'Crit\'Air 1', '2' => 'Crit\'Air 2', '3' => 'Crit\'Air 3',
        '4' => 'Crit\'Air 4', '5' => 'Crit\'Air 5', 'non_classe' => 'Non classé',
    ];

    /** Couleur officielle de la pastille. */
    public const CRITAIR_COLORS = [
        '0' => '#00a650', '1' => '#7f3f98', '2' => '#f2c400', '3' => '#f39200', '4' => '#8b4513', '5' => '#6d6e70', 'non_classe' => '#cccccc',
    ];

    public static function co2Class(?int $co2): ?string
    {
        if ($co2 === null) {
            return null;
        }
        foreach (self::CO2_CLASSES as $k => [$lo, $hi]) {
            if ($co2 >= $lo && $co2 <= $hi) {
                return $k;
            }
        }

        return null;
    }

    /** Norme Euro (1 à 6) : celle de la carte grise si on la comprend, sinon déduite de la date de 1re immatriculation. */
    public static function euroLevel(?string $norm, ?CarbonInterface $firstRegistration): ?int
    {
        if ($norm) {
            $n = strtoupper(preg_replace('/\s+/', '', $norm));
            if (preg_match('/EURO([1-6])/', $n, $m) || preg_match('/^([1-6])[A-Z-]*$/', $n, $m)) {
                return (int) $m[1];
            }
            // Directives citées par les anciennes cartes grises (champ V.9).
            if (str_contains($n, '98/69B')) {
                return 4;
            }
            if (str_contains($n, '98/69A') || str_contains($n, '98/69')) {
                return 3;
            }
            if (str_contains($n, '96/69') || str_contains($n, '94/12')) {
                return 2;
            }
            if (str_contains($n, '91/441') || str_contains($n, '93/59')) {
                return 1;
            }
        }
        if (! $firstRegistration) {
            return null;
        }
        $d = $firstRegistration->toDateString();

        return match (true) {
            $d >= '2015-09-01' => 6,
            $d >= '2011-01-01' => 5,
            $d >= '2006-01-01' => 4,
            $d >= '2001-01-01' => 3,
            $d >= '1997-01-01' => 2,
            $d >= '1993-01-01' => 1,
            default => 0,
        };
    }

    /** Vignette Crit'Air d'une voiture particulière. */
    public static function critair(?string $fuel, ?string $norm, ?CarbonInterface $firstRegistration): ?string
    {
        if (in_array($fuel, ['electrique'], true)) {
            return '0';
        }
        if (in_array($fuel, ['gpl', 'hybride_rechargeable'], true)) {
            return '1';
        }
        $euro = self::euroLevel($norm, $firstRegistration);
        if ($euro === null || ! $fuel || $fuel === 'autre') {
            return null;
        }
        // Une hybride simple suit le barème de son moteur thermique, essence dans la très grande majorité des cas.
        if ($fuel === 'essence' || $fuel === 'hybride') {
            return match (true) {
                $euro >= 5 => '1',
                $euro === 4 => '2',
                $euro >= 2 => '3',
                default => 'non_classe',
            };
        }
        if ($fuel === 'diesel') {
            return match (true) {
                $euro >= 5 => '2',
                $euro === 4 => '3',
                $euro === 3 => '4',
                $euro === 2 => '5',
                default => 'non_classe',
            };
        }

        return null;
    }
}
