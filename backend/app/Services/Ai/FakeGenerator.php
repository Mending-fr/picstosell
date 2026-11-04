<?php

namespace App\Services\Ai;

use App\Models\AiConfig;

/** Fournisseur de test : ne transforme rien, renvoie la première image (la composition déjà faite). */
class FakeGenerator implements ImageGenerator
{
    public function generate(array $images, string $prompt, AiConfig $config): string
    {
        return $images[0]['bytes'];
    }
}
