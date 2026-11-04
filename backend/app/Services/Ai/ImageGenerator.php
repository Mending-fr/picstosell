<?php

namespace App\Services\Ai;

use App\Models\AiConfig;

interface ImageGenerator
{
    /**
     * @param  array<int, array{bytes: string, mime: string}>  $images  images de référence, dans l'ordre du prompt
     * @return string octets de l'image produite
     */
    public function generate(array $images, string $prompt, AiConfig $config): string;
}
