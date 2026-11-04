<?php

namespace App\Services\Pricing;

use Illuminate\Support\Facades\Http;

/**
 * Auto-ways (auto-ways.net) : on interroge la plaque et on cherche un champ de prix ou de cote dans la réponse.
 * Leur documentation publique ne détaille pas ce champ : sans prix dans la réponse, la source est simplement ignorée.
 */
class AutoWaysPrice
{
    public static function available(): bool
    {
        return (bool) config('services.autoways.token');
    }

    /** @return array{price:int,field:string}|null */
    public function price(string $plate): ?array
    {
        $res = Http::acceptJson()->timeout(15)->get(config('services.autoways.url'), [
            'plaque' => str_replace('-', '', $plate),
            'token' => config('services.autoways.token'),
            'country' => 'fr',
        ]);
        if (! $res->successful() || ! is_array($res->json())) {
            return null;
        }

        return $this->findPrice($res->json());
    }

    /** Premier champ numérique dont le nom évoque un prix, une cote ou une valeur. */
    private function findPrice(array $data, string $path = ''): ?array
    {
        foreach ($data as $k => $v) {
            $key = $path === '' ? (string) $k : $path.'.'.$k;
            if (is_array($v)) {
                if ($found = $this->findPrice($v, $key)) {
                    return $found;
                }
            } elseif (preg_match('/(prix|price|cote|valeur|value|argus)/i', (string) $k) && is_numeric(str_replace([' ', ','], ['', '.'], (string) $v))) {
                $n = (int) round((float) str_replace([' ', ','], ['', '.'], (string) $v));
                if ($n > 500 && $n < 500000) {
                    return ['price' => $n, 'field' => $key];
                }
            }
        }

        return null;
    }
}
