<?php

namespace App\Services\Plate;

use App\Models\Vehicle;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * apiplaqueimmatriculation.com : données de la carte grise à partir de la plaque.
 * Abonnement en libre-service, jeton dans PLATE_API_KEY.
 */
class ApiPlaqueProvider
{
    private const ENERGY = [1 => 'essence', 2 => 'gpl', 3 => 'electrique', 4 => 'hybride', 5 => 'autre', 6 => 'autre'];

    public function lookup(string $plate): ?array
    {
        $token = config('services.plate.api_key') ?: throw new RuntimeException('PLATE_API_KEY manquante');
        $res = Http::acceptJson()->timeout(20)->post(config('services.plate.url').'?'.http_build_query([
            'host_name' => 'apiplaqueimmatriculation.com',
            'immatriculation' => $plate,
            'token' => $token,
            'format' => 'json',
        ]));
        $d = $res->json('data');
        if (! is_array($d) || $d === [] || ! empty($d['erreur'])) {
            $code = $res->json('code_erreur');
            if ($code && (int) $code !== 404) {
                throw new RuntimeException('Recherche par plaque : '.($res->json('message') ?? 'erreur '.$code));
            }

            return null; // plaque inconnue
        }

        return self::map($d);
    }

    public static function map(array $d): array
    {
        $fuelText = (string) ($d['energieNGC'] ?? '');
        $fuel = $fuelText !== '' ? Vehicle::normalizeFuel($fuelText) : (self::ENERGY[(int) ($d['energie'] ?? 0)] ?? null);
        $gear = strtoupper((string) ($d['boite_vitesse'] ?? ''));
        $date = $d['date1erCir_us'] ?? null;

        return [
            'make' => self::title($d['marque'] ?? null),
            'model' => self::title($d['modele'] ?? null),
            'version' => self::title($d['sra_commercial'] ?? null),
            'first_registration' => $date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : null,
            'fuel' => $fuel,
            'gearbox' => $gear === 'A' ? 'automatique' : ($gear === 'M' ? 'manuelle' : null),
            'power_hp' => isset($d['puisFiscReel']) && is_numeric($d['puisFiscReel']) ? (int) $d['puisFiscReel'] : null,
            'fiscal_hp' => isset($d['puisFisc']) && is_numeric($d['puisFisc']) ? (int) $d['puisFisc'] : null,
            'color' => self::title($d['couleur'] ?? null),
            'doors' => isset($d['nb_portes']) && is_numeric($d['nb_portes']) ? (int) $d['nb_portes'] : null,
            'seats' => isset($d['nr_passagers']) && is_numeric($d['nr_passagers']) ? (int) $d['nr_passagers'] : null,
            'vin' => $d['vin'] ?? null ?: null,
        ];
    }

    private static function title(?string $s): ?string
    {
        $s = trim((string) $s);

        return $s === '' ? null : mb_convert_case(mb_strtolower($s), MB_CASE_TITLE);
    }
}
