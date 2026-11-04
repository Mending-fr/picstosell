<?php

namespace App\Services\Pricing;

use App\Models\Garage;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Aide au prix : synthèse de la recherche web (Claude) et d'Auto-ways, en une fourchette et un prix conseillé.
 * Mise en cache 24 h par voiture décrite, pour ne pas repayer une recherche identique.
 */
class PriceAdvisor
{
    public function __construct(private WebPriceEstimator $web, private AutoWaysPrice $autoways) {}

    public function advise(array $car, Garage $garage): array
    {
        $key = 'price-advice:'.md5(json_encode([$car, $garage->isDemo()]));

        return Cache::remember($key, now()->addDay(), fn () => $this->compute($car, $garage));
    }

    private function compute(array $car, Garage $garage): array
    {
        $sources = [];
        $notes = [];
        $web = null;

        if (WebPriceEstimator::available()) {
            try {
                $web = $this->web->estimate(array_filter($car, fn ($v) => $v !== null && $v !== ''));
                $sources[] = ['provider' => 'web', 'label' => 'Annonces comparables (recherche web)', 'low' => $web['low'], 'high' => $web['high'], 'suggested' => $web['suggested'], 'confidence' => $web['confidence'], 'comment' => $web['comment'], 'comparables' => $web['comparables']];
            } catch (Throwable $e) {
                report($e);
                $notes[] = "La recherche d'annonces n'a pas abouti.";
            }
        } else {
            $notes[] = "Recherche d'annonces indisponible : clé ANTHROPIC_API_KEY manquante.";
        }

        $aw = null;
        if ($garage->isDemo()) {
            // Garage de démo : Auto-ways est simulé, l'IA reste réelle.
            if ($web) {
                $aw = ['price' => (int) round($web['suggested'] * (0.97 + (crc32((string) ($car['plate'] ?? '')) % 7) / 100) / 100) * 100, 'field' => 'démo'];
            }
        } elseif (AutoWaysPrice::available() && ! empty($car['plate'])) {
            try {
                $aw = $this->autoways->price((string) $car['plate']);
                if (! $aw) {
                    $notes[] = "Auto-ways n'a pas renvoyé de prix pour cette voiture.";
                }
            } catch (Throwable $e) {
                report($e);
                $notes[] = "Auto-ways n'a pas répondu.";
            }
        }
        if ($aw) {
            $sources[] = ['provider' => 'autoways', 'label' => $garage->isDemo() ? 'Auto-ways (simulé, garage en démo)' : 'Auto-ways', 'price' => $aw['price']];
        }

        $prices = array_values(array_filter([$web['low'] ?? null, $web['high'] ?? null, $aw['price'] ?? null]));
        if (! $prices) {
            return ['available' => false, 'low' => null, 'high' => null, 'suggested' => null, 'sources' => $sources, 'notes' => $notes];
        }
        $low = min($prices);
        $high = max($prices);
        $suggested = match (true) {
            $web && $aw => ($web['suggested'] + $aw['price']) / 2,
            (bool) $web => $web['suggested'],
            default => $aw['price'],
        };
        $round = fn ($n) => (int) (round($n / 100) * 100);

        return [
            'available' => true,
            'low' => $round($low),
            'high' => $round($high),
            'suggested' => $round(min(max($suggested, $low), $high)),
            'sources' => $sources,
            'notes' => $notes,
        ];
    }
}
