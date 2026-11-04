<?php

namespace App\Services\Ai;

/** Détourage (BiRefNet via fal). Sans clé, renvoie null : la photo est utilisée telle quelle. */
class Cutout
{
    public static function available(): bool
    {
        return (bool) config('services.fal.key');
    }

    /** @return array{bytes: string, cost: float}|null PNG transparent */
    public static function run(string $jpegBytes): ?array
    {
        if (! self::available()) {
            return null;
        }
        $out = FalGenerator::call(config('services.fal.cutout_model'), [
            'image_url' => 'data:image/jpeg;base64,'.base64_encode($jpegBytes),
        ]);
        $url = $out['image']['url'] ?? $out['images'][0]['url'] ?? null;
        if (! $url) {
            return null;
        }

        return ['bytes' => FalGenerator::download($url), 'cost' => (float) config('services.fal.cutout_cost_usd')];
    }
}
