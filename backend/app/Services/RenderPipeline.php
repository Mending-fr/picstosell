<?php

namespace App\Services;

use App\Models\AiConfig;
use App\Models\Render;
use App\Models\Shot;
use App\Services\Ai\Cutout;
use App\Services\Ai\Generators;
use App\Support\Media;
use Throwable;

/**
 * Place une photo de parking dans le showroom du garage.
 * Méthode B (production) : détourage, pose dans le décor, harmonisation générative éventuelle, puis recollage des pixels d'origine.
 * Méthode A (comparaison) : le modèle génératif reçoit le décor et la voiture et produit l'image entière.
 */
class RenderPipeline
{
    public function run(Render $render): void
    {
        $started = microtime(true);
        $render->update(['status' => 'processing', 'error' => null]);
        $cost = 0.0;

        try {
            $photo = $render->photo()->firstOrFail();
            $vehicle = $render->vehicle()->firstOrFail();
            $garage = $vehicle->garage;
            $config = $render->aiConfig ?? new AiConfig(['provider' => $render->provider, 'model' => $render->model, 'method' => $render->method, 'quality' => '2K', 'unit_cost_usd' => 0]);
            // Un seul décor par garage : le même fond pour tous les angles de la voiture.
            $shot = Shot::query()
                ->whereHas('showroom', fn ($q) => $q->where('garage_id', $garage->id))
                ->orderByRaw("case when key = 'main' then 0 else 1 end")->orderBy('id')->first();

            $background = $shot?->image_path ? Imaging::load(Media::get($shot->image_path)) : Imaging::placeholderBackground();
            $background = Imaging::fit($background, 2048);
            $placement = array_merge(Shot::DEFAULT_PLACEMENT, $shot?->placement ?? []);
            $carBytes = Media::get($photo->path);
            $generator = Generators::for($config->provider);
            $isFake = $config->provider === 'fake';
            $prompt = $config->prompt ?: $this->defaultPrompt($config->method);

            if ($config->method === 'A' && ! $isFake) {
                $result = $generator->generate([
                    ['bytes' => Imaging::jpeg($background), 'mime' => 'image/jpeg'],
                    ['bytes' => $carBytes, 'mime' => 'image/jpeg'],
                ], $prompt, $config);
                $cost += (float) $config->unit_cost_usd;
                $final = Imaging::fit(Imaging::load($result), 2048);
            } else {
                $cut = $isFake ? null : Cutout::run($carBytes);
                if ($cut) {
                    $cost += $cut['cost'];
                }
                $car = Imaging::load($cut['bytes'] ?? $carBytes);
                [$composed, $rect] = Imaging::composite($background, $car, $placement, shadow: true);
                $final = $composed;
                if (! $isFake) {
                    $result = $generator->generate([['bytes' => Imaging::jpeg($composed, 92), 'mime' => 'image/jpeg']], $prompt, $config);
                    $cost += (float) $config->unit_cost_usd;
                    $harmonized = Imaging::load($result);
                    // Sans détourage, on ne recolle rien : la photo entière serait recollée.
                    $final = $cut ? Imaging::pasteBack($harmonized, $car, $rect) : Imaging::fit($harmonized, 2048);
                }
            }

            $path = "garages/{$garage->id}/vehicles/{$vehicle->id}/renders/{$render->id}.jpg";
            Media::put($path, Imaging::jpeg($final, 88));

            $render->update([
                'status' => $render->comparison_id || $garage->setting('review_before_publish') ? 'to_review' : 'approved',
                'output_path' => $path,
                'cost_usd' => $cost,
                'duration_ms' => (int) ((microtime(true) - $started) * 1000),
            ]);
        } catch (Throwable $e) {
            report($e);
            $render->update([
                'status' => 'failed',
                'error' => mb_substr($e->getMessage(), 0, 1000),
                'cost_usd' => $cost,
                'duration_ms' => (int) ((microtime(true) - $started) * 1000),
            ]);
        }

        if (! $render->comparison_id) {
            $render->vehicle?->refreshStatus();
        }
    }

    public function defaultPrompt(string $method): string
    {
        return $method === 'A'
            ? "Place exactement la voiture de la deuxième image dans le showroom de la première image, sous le même angle, posée au sol avec une ombre réaliste. Ne modifie pas la voiture : mêmes jantes, logos, couleur, plaque, rayures et défauts. Ne change pas le décor."
            : "Photo d'une voiture posée dans un showroom. Rends l'ombre au sol et la lumière autour de la voiture réalistes et cohérentes avec la pièce. Ne modifie pas la voiture elle-même ni le décor.";
    }
}
