<?php

namespace App\Services\Plate;

use App\Models\Garage;
use App\Models\PlateLookup;
use App\Models\Vehicle;

/**
 * Données techniques à partir de la plaque, mises en cache : on ne paie jamais deux fois le même véhicule.
 * PLATE_PROVIDER=apiplaque (apiplaqueimmatriculation.com, jeton dans PLATE_API_KEY) ou fake (données d'exemple).
 * Un garage en mode démo reçoit toujours des données d'exemple, sans appel payant ni mise en cache.
 */
class PlateLookupService
{
    public function lookup(string $plate, ?Garage $garage = null): array
    {
        $plate = Vehicle::normalizePlate($plate);
        if ($garage?->isDemo()) {
            return ['found' => true, 'data' => $this->fake($plate, always: true), 'cached' => false, 'demo' => true];
        }
        $cached = PlateLookup::where('plate', $plate)->first();
        if ($cached) {
            return ['found' => $cached->found, 'data' => $cached->payload ?? [], 'cached' => true];
        }

        $provider = config('services.plate.provider', 'fake');
        $data = match ($provider) {
            'apiplaque' => (new ApiPlaqueProvider)->lookup($plate),
            default => $this->fake($plate),
        };

        PlateLookup::create(['plate' => $plate, 'provider' => $provider, 'found' => $data !== null, 'payload' => $data]);

        return ['found' => $data !== null, 'data' => $data ?? [], 'cached' => false];
    }

    private function fake(string $plate, bool $always = false): ?array
    {
        if (! $always && str_starts_with($plate, 'WW')) {
            return null; // plaque provisoire : rien à trouver
        }
        $catalog = [
            ['Renault', 'Clio V', '1.0 TCe 90 Evolution', 'essence', 'manuelle', 90, 5],
            ['Dacia', 'Duster', '1.5 Blue dCi 115 Prestige', 'diesel', 'manuelle', 115, 6],
            ['Renault', 'Captur', 'E-Tech 145 Techno', 'hybride', 'automatique', 145, 4],
            ['Dacia', 'Sandero Stepway', 'TCe 90 Expression', 'essence', 'manuelle', 90, 5],
            ['Renault', 'Austral', 'E-Tech 200 Techno', 'hybride', 'automatique', 200, 7],
            ['Dacia', 'Jogger', 'TCe 110 Extreme 7 places', 'essence', 'manuelle', 110, 6],
        ];
        $c = $catalog[crc32($plate) % count($catalog)];
        $year = 2019 + crc32(strrev($plate)) % 5;

        return [
            'make' => $c[0], 'model' => $c[1], 'version' => $c[2],
            'first_registration' => sprintf('%d-%02d-01', $year, 1 + crc32($plate.'m') % 12),
            'fuel' => $c[3], 'gearbox' => $c[4], 'power_hp' => $c[5], 'fiscal_hp' => $c[6],
            'color' => null, 'doors' => 5, 'seats' => str_contains($c[2], '7 places') ? 7 : 5, 'vin' => null,
        ];
    }
}
