<?php

namespace App\Services\Ai;

use App\Models\AiConfig;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Modèles hébergés sur fal.ai (Seedream, etc.). Le champ « model » est l'identifiant fal, ex. fal-ai/bytedance/seedream/v4/edit. */
class FalGenerator implements ImageGenerator
{
    public function generate(array $images, string $prompt, AiConfig $config): string
    {
        $uris = array_map(fn ($i) => 'data:'.$i['mime'].';base64,'.base64_encode($i['bytes']), $images);
        $out = self::call($config->model, [
            'prompt' => $prompt,
            'image_urls' => $uris,
            'num_images' => 1,
        ]);
        $url = $out['images'][0]['url'] ?? $out['image']['url'] ?? throw new RuntimeException('fal : réponse sans image');

        return self::download($url);
    }

    public static function call(string $model, array $input): array
    {
        $key = config('services.fal.key') ?: throw new RuntimeException('FAL_KEY manquante');
        $res = Http::withHeaders(['Authorization' => 'Key '.$key])->timeout(240)->post('https://fal.run/'.ltrim($model, '/'), $input);
        if (! $res->successful()) {
            throw new RuntimeException('fal '.$res->status().' : '.mb_substr($res->body(), 0, 400));
        }

        return $res->json();
    }

    public static function download(string $url): string
    {
        if (str_starts_with($url, 'data:')) {
            return base64_decode(substr($url, strpos($url, ',') + 1));
        }

        return Http::timeout(60)->get($url)->throw()->body();
    }
}
