<?php

namespace App\Services;

use App\Jobs\ProcessRender;
use App\Models\AiConfig;
use App\Models\Photo;
use App\Models\Render;

class RenderLauncher
{
    public static function configFor(Photo $photo): AiConfig
    {
        $garage = $photo->vehicle->garage;

        return $garage->aiConfig
            ?? AiConfig::where('active', true)->orderBy('id')->first()
            ?? new AiConfig(['provider' => 'fake', 'model' => 'fake-composite', 'method' => 'B', 'quality' => '2K', 'unit_cost_usd' => 0]);
    }

    public static function launch(Photo $photo, ?AiConfig $config = null, ?int $comparisonId = null, int $attempt = 1, ?string $testBackdrop = null): Render
    {
        $config ??= self::configFor($photo);
        $render = Render::create([
            'photo_id' => $photo->id,
            'vehicle_id' => $photo->vehicle_id,
            'ai_config_id' => $config->exists ? $config->id : null,
            'comparison_id' => $comparisonId,
            // Essai de décor : le rendu ne remplace pas les photos de la voiture.
            'is_test' => $testBackdrop !== null,
            'backdrop' => $testBackdrop,
            'provider' => $config->provider,
            'model' => $config->model,
            'method' => $config->method,
            'status' => 'pending',
            'attempt' => $attempt,
        ]);
        ProcessRender::dispatch($render->id);

        return $render;
    }
}
