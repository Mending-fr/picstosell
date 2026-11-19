<?php

namespace App\Support;

use App\Models\Vehicle;

/**
 * Vignettes d'atouts : petits arguments mis en avant sur la photo principale, l'annonce, l'affiche et le site.
 * L'outil propose celles que les données justifient ; le gérant choisit celles à afficher, et peut en écrire d'autres.
 */
class Badges
{
    public const LABELS = [
        'low_km' => 'Faible kilométrage',
        'first_hand' => '1re main',
        'towbar' => 'Attelage',
        'seats_7' => '7 places',
        'seats_9' => '9 places',
        'park_assist' => 'Aide au stationnement',
        'automatic' => 'Boîte automatique',
        'warranty' => 'Garantie',
    ];

    /** Vignettes que les données de la voiture justifient. */
    public static function suggest(Vehicle $v): array
    {
        $items = collect($v->equipment ?? [])->flatMap(fn ($g) => $g['items'] ?? [])->map(fn ($i) => mb_strtolower((string) $i));
        $has = fn (string $re) => $items->contains(fn ($i) => preg_match($re, $i));
        $years = $v->first_registration ? max(1, $v->first_registration->diffInYears(now())) : null;

        $out = [];
        if ($v->mileage !== null && ($v->mileage < 20000 || ($years && $v->mileage <= 12000 * $years))) {
            $out[] = 'low_km';
        }
        if ($v->first_hand) {
            $out[] = 'first_hand';
        }
        if ($has('/attelage|crochet/u')) {
            $out[] = 'towbar';
        }
        if ($v->seats >= 9) {
            $out[] = 'seats_9';
        } elseif ($v->seats >= 7) {
            $out[] = 'seats_7';
        }
        if ($has('/radar|aide au stationnement|aide au parking|camera|caméra|park assist/u')) {
            $out[] = 'park_assist';
        }
        if ($v->gearbox === 'automatique') {
            $out[] = 'automatic';
        }
        if ($v->warranty) {
            $out[] = 'warranty';
        }

        return $out;
    }

    public static function label(string $key, Vehicle $v): string
    {
        if ($key === 'warranty') {
            return $v->warranty ? 'Garantie '.$v->warranty : 'Garantie';
        }

        // Une vignette inconnue est un texte libre écrit par le gérant.
        return self::LABELS[$key] ?? $key;
    }

    /** Libellés des vignettes choisies, dans l'ordre. */
    public static function labels(Vehicle $v): array
    {
        return array_values(array_map(fn ($k) => self::label($k, $v), $v->badges ?? []));
    }
}
