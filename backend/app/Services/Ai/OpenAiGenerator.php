<?php

namespace App\Services\Ai;

use App\Models\AiConfig;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** OpenAI Images (édition à partir d'images de référence). */
class OpenAiGenerator implements ImageGenerator
{
    public function generate(array $images, string $prompt, AiConfig $config): string
    {
        $key = config('services.openai.key') ?: throw new RuntimeException('OPENAI_API_KEY manquante');
        $req = Http::withToken($key)->timeout(180)->asMultipart();
        foreach ($images as $i => $img) {
            $req = $req->attach('image[]', $img['bytes'], "ref{$i}.".($img['mime'] === 'image/png' ? 'png' : 'jpg'), ['Content-Type' => $img['mime']]);
        }
        $res = $req->post(rtrim(config('services.openai.base_url'), '/').'/images/edits', [
            'model' => $config->model,
            'prompt' => $prompt,
            'size' => '1536x1024',
            'quality' => $config->quality === '1K' ? 'medium' : 'high',
            'n' => 1,
        ]);
        if (! $res->successful()) {
            throw new RuntimeException('OpenAI '.$res->status().' : '.mb_substr($res->body(), 0, 400));
        }
        $b64 = $res->json('data.0.b64_json');
        if ($b64) {
            return base64_decode($b64);
        }
        $url = $res->json('data.0.url') ?: throw new RuntimeException('OpenAI : réponse sans image');

        return Http::timeout(60)->get($url)->throw()->body();
    }
}
