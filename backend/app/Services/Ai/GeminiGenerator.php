<?php

namespace App\Services\Ai;

use App\Models\AiConfig;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Google Gemini (modèles image « Nano Banana »). */
class GeminiGenerator implements ImageGenerator
{
    public function generate(array $images, string $prompt, AiConfig $config): string
    {
        $key = config('services.gemini.key') ?: throw new RuntimeException('GEMINI_API_KEY manquante');
        $parts = [['text' => $prompt]];
        foreach ($images as $img) {
            $parts[] = ['inline_data' => ['mime_type' => $img['mime'], 'data' => base64_encode($img['bytes'])]];
        }
        $url = rtrim(config('services.gemini.base_url'), '/')."/models/{$config->model}:generateContent";
        $res = Http::withHeaders(['x-goog-api-key' => $key])->timeout(180)->post($url, [
            'contents' => [['parts' => $parts]],
            'generationConfig' => [
                'responseModalities' => ['IMAGE'],
                'imageConfig' => ['aspectRatio' => '16:9', 'imageSize' => $config->quality === '1K' ? '1K' : '2K'],
            ],
        ]);
        if (! $res->successful()) {
            throw new RuntimeException('Gemini '.$res->status().' : '.mb_substr($res->body(), 0, 400));
        }
        foreach ($res->json('candidates.0.content.parts', []) as $part) {
            $data = $part['inlineData']['data'] ?? $part['inline_data']['data'] ?? null;
            if ($data) {
                return base64_decode($data);
            }
        }
        throw new RuntimeException('Gemini : réponse sans image');
    }
}
