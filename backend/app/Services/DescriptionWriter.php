<?php

namespace App\Services;

use App\Models\Vehicle;

/**
 * Propose un texte d'annonce à partir des seules données saisies : aucune affirmation inventée.
 * Le gérant relit toujours avant publication.
 */
class DescriptionWriter
{
    private const MONTHS = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

    public function write(Vehicle $v): string
    {
        $title = trim(implode(' ', array_filter([$v->make, $v->model, $v->version])));
        $parts = [];
        $first = $title ?: 'Véhicule';
        if ($v->first_registration) {
            $first .= ' de '.self::MONTHS[$v->first_registration->month - 1].' '.$v->first_registration->year;
        }
        if ($v->mileage) {
            $first .= ', '.number_format($v->mileage, 0, ',', ' ').' km';
        }
        $parts[] = $first.'.';

        $tech = array_filter([
            $v->fuel ? Vehicle::FUEL_LABELS[$v->fuel] ?? null : null,
            $v->gearbox ? 'boîte '.$v->gearbox : null,
            $v->power_hp ? $v->power_hp.' ch' : null,
            $v->fiscal_hp ? $v->fiscal_hp.' CV fiscaux' : null,
            $v->doors ? $v->doors.' portes' : null,
            $v->seats && $v->seats != 5 ? $v->seats.' places' : null,
            $v->color ? 'couleur '.mb_strtolower($v->color) : null,
        ]);
        if ($tech) {
            $parts[] = ucfirst(implode(', ', $tech)).'.';
        }

        $items = collect($v->equipment ?? [])->flatMap(fn ($g) => $g['items'] ?? [])->filter()->take(8)->values();
        if ($items->isNotEmpty()) {
            $parts[] = 'Équipements : '.mb_strtolower($items->implode(', ')).'.';
        }
        $parts[] = 'Visible sur place, contactez-nous pour un essai.';

        return implode(' ', $parts);
    }
}
